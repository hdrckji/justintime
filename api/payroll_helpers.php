<?php
require_once __DIR__ . '/../db.php';

function jit_scheduled_schema_flags(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cols = $pdo->query('SHOW COLUMNS FROM scheduled_hours')->fetchAll(PDO::FETCH_COLUMN);
    $cache = [
        'hasWeekStart' => in_array('week_start', $cols, true),
        'hasRecurrence' => in_array('recurrence_interval', $cols, true) && in_array('recurrence_slot', $cols, true),
    ];
    return $cache;
}

function jit_cycle_slot_for_week(string $weekStart, int $interval): int
{
    if ($interval <= 1) {
        return 1;
    }

    $anchor = '2024-01-01';
    $weekTs = strtotime($weekStart);
    $anchorTs = strtotime($anchor);
    if ($weekTs === false || $anchorTs === false) {
        return 1;
    }

    $diffDays = (int) floor(($weekTs - $anchorTs) / 86400);
    $diffWeeks = (int) floor($diffDays / 7);
    $mod = (($diffWeeks % $interval) + $interval) % $interval;
    return $mod + 1;
}

function jit_week_start_for_date(string $dateIso): string
{
    $ts = strtotime($dateIso);
    if ($ts === false) {
        return date('Y-m-d', strtotime('monday this week'));
    }
    return date('Y-m-d', strtotime('monday this week', $ts));
}

function jit_scheduled_hours_for_day(PDO $pdo, int $employeeId, string $dateIso): float
{
    $flags = jit_scheduled_schema_flags($pdo);
    $dayOfWeek = (int) date('w', strtotime($dateIso));

    if ($flags['hasWeekStart']) {
        $weekStart = jit_week_start_for_date($dateIso);
        $stmt = $pdo->prepare(
            'SELECT hours
             FROM scheduled_hours
             WHERE employee_id = ? AND day_of_week = ? AND week_start = ?
             LIMIT 1'
        );
        $stmt->execute([$employeeId, $dayOfWeek, $weekStart]);
        $exact = $stmt->fetchColumn();
        if ($exact !== false) {
            return round((float) $exact, 2);
        }

        if ($flags['hasRecurrence']) {
            for ($interval = 3; $interval >= 1; $interval--) {
                $slot = jit_cycle_slot_for_week($weekStart, $interval);
                $stmt = $pdo->prepare(
                    'SELECT hours
                     FROM scheduled_hours
                     WHERE employee_id = ?
                       AND day_of_week = ?
                       AND week_start IS NULL
                       AND recurrence_interval = ?
                       AND recurrence_slot = ?
                     LIMIT 1'
                );
                $stmt->execute([$employeeId, $dayOfWeek, $interval, $slot]);
                $recurring = $stmt->fetchColumn();
                if ($recurring !== false) {
                    return round((float) $recurring, 2);
                }
            }
        }

        $stmt = $pdo->prepare(
            'SELECT hours
             FROM scheduled_hours
             WHERE employee_id = ?
               AND day_of_week = ?
               AND week_start IS NULL
             ORDER BY recurrence_interval ASC, recurrence_slot ASC
             LIMIT 1'
        );
        $stmt->execute([$employeeId, $dayOfWeek]);
        $fallback = $stmt->fetchColumn();
        return $fallback !== false ? round((float) $fallback, 2) : 0.0;
    }

    $stmt = $pdo->prepare(
        'SELECT hours
         FROM scheduled_hours
         WHERE employee_id = ? AND day_of_week = ?
         LIMIT 1'
    );
    $stmt->execute([$employeeId, $dayOfWeek]);
    $value = $stmt->fetchColumn();
    return $value !== false ? round((float) $value, 2) : 0.0;
}

function jit_worked_hours_for_day(PDO $pdo, int $employeeId, string $dateIso): array
{
    $stmt = $pdo->prepare(
        'SELECT event_type, timestamp
         FROM attendance_events
         WHERE employee_id = ? AND DATE(timestamp) = ?
         ORDER BY timestamp ASC, id ASC'
    );
    $stmt->execute([$employeeId, $dateIso]);
    $rows = $stmt->fetchAll();

    $openIn = null;
    $minutes = 0;
    $firstIn = null;
    $lastOut = null;

    foreach ($rows as $row) {
        $eventType = (string) ($row['event_type'] ?? '');
        $timestamp = (string) ($row['timestamp'] ?? '');
        $ts = strtotime($timestamp);
        if ($ts === false) {
            continue;
        }

        if ($eventType === 'in') {
            if ($openIn === null) {
                $openIn = $ts;
                if ($firstIn === null) {
                    $firstIn = date('H:i', $ts);
                }
            }
            continue;
        }

        if ($eventType === 'out' && $openIn !== null && $ts > $openIn) {
            $minutes += (int) round(($ts - $openIn) / 60);
            $lastOut = date('H:i', $ts);
            $openIn = null;
        }
    }

    return [
        'hours' => round($minutes / 60, 2),
        'first_in' => $firstIn,
        'last_out' => $lastOut,
        'event_count' => count($rows),
    ];
}

function jit_each_date(string $from, string $to): array
{
    $dates = [];
    $cursor = strtotime($from);
    $end = strtotime($to);
    if ($cursor === false || $end === false) {
        return $dates;
    }

    while ($cursor <= $end) {
        $dates[] = date('Y-m-d', $cursor);
        $cursor = strtotime('+1 day', $cursor);
        if ($cursor === false) {
            break;
        }
    }

    return $dates;
}
