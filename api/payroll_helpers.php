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

function jit_absence_profiles(): array
{
    static $profiles = null;
    if ($profiles !== null) {
        return $profiles;
    }

    $profiles = [
        'vacation_paid' => [
            'type' => 'vacation',
            'label' => 'Conge paye',
            'export_code' => 'CP',
            'paid_ratio' => 1.0,
            'credit_scheduled_hours' => true,
            'bucket' => 'paid_leave',
        ],
        'sick_paid' => [
            'type' => 'sick',
            'label' => 'Maladie payee',
            'export_code' => 'MAL',
            'paid_ratio' => 1.0,
            'credit_scheduled_hours' => true,
            'bucket' => 'sick_leave',
        ],
        'training_paid' => [
            'type' => 'other',
            'label' => 'Formation',
            'export_code' => 'FORM',
            'paid_ratio' => 1.0,
            'credit_scheduled_hours' => true,
            'bucket' => 'training',
        ],
        'authorized_paid' => [
            'type' => 'other',
            'label' => 'Absence autorisee payee',
            'export_code' => 'AUT',
            'paid_ratio' => 1.0,
            'credit_scheduled_hours' => true,
            'bucket' => 'authorized_paid',
        ],
        'unpaid_leave' => [
            'type' => 'other',
            'label' => 'Absence non payee',
            'export_code' => 'ABSNP',
            'paid_ratio' => 0.0,
            'credit_scheduled_hours' => false,
            'bucket' => 'unpaid_leave',
        ],
        'other_paid' => [
            'type' => 'other',
            'label' => 'Autre absence payee',
            'export_code' => 'AUTP',
            'paid_ratio' => 1.0,
            'credit_scheduled_hours' => true,
            'bucket' => 'authorized_paid',
        ],
    ];

    return $profiles;
}

function jit_default_payroll_code_for_type(string $type): string
{
    return match ($type) {
        'vacation' => 'vacation_paid',
        'sick' => 'sick_paid',
        'other' => 'other_paid',
        default => 'authorized_paid',
    };
}

function jit_absence_profile_from_code(string $payrollCode, string $fallbackType = 'other'): array
{
    $profiles = jit_absence_profiles();
    if (isset($profiles[$payrollCode])) {
        return $profiles[$payrollCode] + ['code' => $payrollCode];
    }

    $fallbackCode = jit_default_payroll_code_for_type($fallbackType);
    return $profiles[$fallbackCode] + ['code' => $fallbackCode];
}

function jit_resolve_absence_profile(array $row): array
{
    $type = (string) ($row['type'] ?? 'other');
    $payrollCode = trim((string) ($row['payroll_code'] ?? ''));
    $profile = jit_absence_profile_from_code($payrollCode !== '' ? $payrollCode : jit_default_payroll_code_for_type($type), $type);

    if (isset($row['paid_ratio']) && $row['paid_ratio'] !== null && $row['paid_ratio'] !== '') {
        $profile['paid_ratio'] = max(0.0, min(1.0, (float) $row['paid_ratio']));
    }
    if (array_key_exists('credit_scheduled_hours', $row)) {
        $profile['credit_scheduled_hours'] = (int) $row['credit_scheduled_hours'] === 1;
    }

    return $profile;
}

function jit_absence_on_day(PDO $pdo, int $employeeId, string $dateIso, float $scheduledHours = 0.0): ?array
{
    static $hasPayrollColumns = null;
    if ($hasPayrollColumns === null) {
        $cols = $pdo->query('SHOW COLUMNS FROM absences')->fetchAll(PDO::FETCH_COLUMN);
        $hasPayrollColumns = [
            'payroll_code' => in_array('payroll_code', $cols, true),
            'paid_ratio' => in_array('paid_ratio', $cols, true),
            'credit_scheduled_hours' => in_array('credit_scheduled_hours', $cols, true),
        ];
    }

    $selectFields = 'id, employee_id, type, start_date, end_date, reason';
    if ($hasPayrollColumns['payroll_code']) {
        $selectFields .= ', payroll_code';
    }
    if ($hasPayrollColumns['paid_ratio']) {
        $selectFields .= ', paid_ratio';
    }
    if ($hasPayrollColumns['credit_scheduled_hours']) {
        $selectFields .= ', credit_scheduled_hours';
    }

    $stmt = $pdo->prepare(
        "SELECT {$selectFields}
         FROM absences
         WHERE employee_id = ?
           AND start_date <= ?
           AND end_date >= ?
         ORDER BY start_date ASC, id ASC
         LIMIT 1"
    );
    $stmt->execute([$employeeId, $dateIso, $dateIso]);
    $row = $stmt->fetch();

    if (!$row) {
        $vacationStmt = $pdo->prepare(
            "SELECT id, employee_id, 'vacation' AS type, start_date, end_date, reason
             FROM vacation_requests
             WHERE employee_id = ?
               AND status = 'approved'
               AND start_date <= ?
               AND end_date >= ?
             ORDER BY start_date ASC, id ASC
             LIMIT 1"
        );
        $vacationStmt->execute([$employeeId, $dateIso, $dateIso]);
        $row = $vacationStmt->fetch();
        if (!$row) {
            return null;
        }
    }

    $profile = jit_resolve_absence_profile($row);
    $creditedHours = $profile['credit_scheduled_hours'] ? round($scheduledHours * (float) $profile['paid_ratio'], 2) : 0.0;

    return [
        'source' => isset($row['payroll_code']) ? 'absence' : 'vacation_request',
        'id' => (int) ($row['id'] ?? 0),
        'type' => (string) ($row['type'] ?? 'other'),
        'label' => (string) $profile['label'],
        'payroll_code' => (string) $profile['code'],
        'export_code' => (string) $profile['export_code'],
        'bucket' => (string) $profile['bucket'],
        'paid_ratio' => (float) $profile['paid_ratio'],
        'credited_hours' => $creditedHours,
        'scheduled_hours' => round($scheduledHours, 2),
        'reason' => (string) ($row['reason'] ?? ''),
    ];
}

function jit_payroll_breakdown_for_day(PDO $pdo, int $employeeId, string $dateIso): array
{
    $scheduledHours = jit_scheduled_hours_for_day($pdo, $employeeId, $dateIso);
    $worked = jit_worked_hours_for_day($pdo, $employeeId, $dateIso);
    $absence = jit_absence_on_day($pdo, $employeeId, $dateIso, $scheduledHours);

    $paidAbsenceHours = 0.0;
    $unpaidAbsenceHours = 0.0;
    $trainingHours = 0.0;
    $authorizedPaidHours = 0.0;
    $sickHours = 0.0;
    $vacationHours = 0.0;
    $dayType = 'worked';
    $exportCode = 'WORK';
    $dayLabel = 'Presence';

    if ($absence !== null) {
        $dayType = 'absence';
        $exportCode = (string) $absence['export_code'];
        $dayLabel = (string) $absence['label'];

        switch ($absence['bucket']) {
            case 'vacation_paid':
            case 'paid_leave':
                $paidAbsenceHours += (float) $absence['credited_hours'];
                $vacationHours += (float) $absence['credited_hours'];
                break;
            case 'sick_leave':
                $paidAbsenceHours += (float) $absence['credited_hours'];
                $sickHours += (float) $absence['credited_hours'];
                break;
            case 'training':
                $paidAbsenceHours += (float) $absence['credited_hours'];
                $trainingHours += (float) $absence['credited_hours'];
                break;
            case 'authorized_paid':
                $paidAbsenceHours += (float) $absence['credited_hours'];
                $authorizedPaidHours += (float) $absence['credited_hours'];
                break;
            case 'unpaid_leave':
                $unpaidAbsenceHours += round(max(0.0, $scheduledHours), 2);
                break;
        }

        if ((float) ($worked['hours'] ?? 0.0) > 0.0) {
            $dayType = 'mixed';
            $dayLabel = 'Presence + ' . $dayLabel;
        }
    } elseif ((float) ($worked['hours'] ?? 0.0) <= 0.0 && $scheduledHours <= 0.0) {
        $dayType = 'empty';
        $exportCode = 'OFF';
        $dayLabel = 'Sans activite';
    }

    $payableHours = round((float) ($worked['hours'] ?? 0.0) + $paidAbsenceHours, 2);
    $balance = round($payableHours - $scheduledHours, 2);

    return [
        'date' => $dateIso,
        'scheduled_hours' => round($scheduledHours, 2),
        'worked_hours' => round((float) ($worked['hours'] ?? 0.0), 2),
        'paid_absence_hours' => round($paidAbsenceHours, 2),
        'unpaid_absence_hours' => round($unpaidAbsenceHours, 2),
        'training_hours' => round($trainingHours, 2),
        'authorized_paid_hours' => round($authorizedPaidHours, 2),
        'sick_hours' => round($sickHours, 2),
        'vacation_hours' => round($vacationHours, 2),
        'payable_hours' => $payableHours,
        'period_balance' => $balance,
        'first_in' => $worked['first_in'] ?? null,
        'last_out' => $worked['last_out'] ?? null,
        'event_count' => (int) ($worked['event_count'] ?? 0),
        'day_type' => $dayType,
        'day_label' => $dayLabel,
        'export_code' => $exportCode,
        'absence' => $absence,
    ];
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
