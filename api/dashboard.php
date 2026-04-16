<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/payroll_helpers.php';

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function employee_exists(PDO $pdo, int $employeeId): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE id = ?');
    $stmt->execute([$employeeId]);
    return (int) $stmt->fetchColumn() > 0;
}

function can_manage_target_employee(PDO $pdo, array $auth, int $employeeId): bool
{
    $role = (string) ($auth['role'] ?? '');
    if (in_array($role, ['admin', 'hr'], true)) {
        return true;
    }

    if ($role !== 'manager') {
        return false;
    }

    $managerEmployeeId = (int) ($auth['employee_id'] ?? 0);
    return jit_can_manage_employee($pdo, $managerEmployeeId, $employeeId);
}

function resolve_calendar_period(array $query): array
{
    $period = (string) ($query['period'] ?? 'week');
    if (!in_array($period, ['week', 'month', 'custom'], true)) {
        $period = 'week';
    }

    $anchorDate = trim((string) ($query['anchor_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchorDate)) {
        $anchorDate = date('Y-m-d');
    }

    if ($period === 'month') {
        $from = date('Y-m-01', strtotime($anchorDate) ?: time());
        $to = date('Y-m-t', strtotime($anchorDate) ?: time());
        return [$period, $from, $to, $anchorDate];
    }

    if ($period === 'custom') {
        $from = trim((string) ($query['from_date'] ?? ''));
        $to = trim((string) ($query['to_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            throw new InvalidArgumentException('Periode personnalisee invalide.');
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $days = jit_each_date($from, $to);
        if (count($days) > 93) {
            throw new InvalidArgumentException('La periode personnalisee est limitee a 93 jours.');
        }

        return [$period, $from, $to, $anchorDate];
    }

    $anchorTs = strtotime($anchorDate) ?: time();
    $from = date('Y-m-d', strtotime('monday this week', $anchorTs));
    $to = date('Y-m-d', strtotime('sunday this week', $anchorTs));
    return [$period, $from, $to, $anchorDate];
}

function summarize_day_events(array $rows): array
{
    $openIn = null;
    $minutes = 0;
    $firstIn = null;
    $lastOut = null;
    $countIn = 0;
    $countOut = 0;
    $events = [];

    foreach ($rows as $row) {
        $eventType = (string) ($row['event_type'] ?? '');
        $timestamp = (string) ($row['timestamp'] ?? '');
        $source = (string) ($row['source'] ?? '');
        $ts = strtotime($timestamp);
        if ($ts === false) {
            continue;
        }

        if ($eventType === 'in') {
            $countIn++;
            if ($openIn === null) {
                $openIn = $ts;
                if ($firstIn === null) {
                    $firstIn = date('H:i', $ts);
                }
            }
        } elseif ($eventType === 'out') {
            $countOut++;
            if ($openIn !== null && $ts > $openIn) {
                $minutes += (int) round(($ts - $openIn) / 60);
                $lastOut = date('H:i', $ts);
                $openIn = null;
            }
        }

        $events[] = [
            'time' => date('H:i', $ts),
            'event_type' => $eventType,
            'source' => $source,
        ];
    }

    $status = 'empty';
    if (!empty($events)) {
        $status = $countIn === $countOut ? 'complete' : 'incomplete';
    }

    return [
        'worked_hours' => round($minutes / 60, 2),
        'first_in' => $firstIn,
        'last_out' => $lastOut,
        'event_count' => count($events),
        'status' => $status,
        'events' => $events,
    ];
}

require_login();
$auth = get_auth_user();
if (($auth['role'] ?? '') === 'employee') {
    json_response(['error' => 'Acces reserve au tableau de bord encadrant.'], 403);
    exit;
}

try {
    $pdo = get_pdo();

    $action = trim((string) ($_GET['action'] ?? 'summary'));

    if ($action === 'employee_calendar') {
        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        if ($employeeId <= 0 || !employee_exists($pdo, $employeeId)) {
            json_response(['error' => 'Collaborateur invalide.'], 400);
            exit;
        }

        if (!can_manage_target_employee($pdo, $auth, $employeeId)) {
            json_response(['error' => 'Acces refuse pour ce collaborateur.'], 403);
            exit;
        }

        [$period, $fromDate, $toDate, $anchorDate] = resolve_calendar_period($_GET);

        $employeeStmt = $pdo->prepare(
            "SELECT id,
                    CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) AS name,
                    badge_id
             FROM employees
             WHERE id = ?"
        );
        $employeeStmt->execute([$employeeId]);
        $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

        $eventsStmt = $pdo->prepare(
            'SELECT event_type, source, timestamp
             FROM attendance_events
             WHERE employee_id = ?
               AND DATE(timestamp) BETWEEN ? AND ?
             ORDER BY timestamp ASC, id ASC'
        );
        $eventsStmt->execute([$employeeId, $fromDate, $toDate]);
        $eventRows = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

        $eventsByDay = [];
        foreach ($eventRows as $row) {
            $day = date('Y-m-d', strtotime((string) ($row['timestamp'] ?? 'now')) ?: time());
            $eventsByDay[$day][] = $row;
        }

        $days = [];
        foreach (jit_each_date($fromDate, $toDate) as $dateIso) {
            $summary = summarize_day_events($eventsByDay[$dateIso] ?? []);
            $days[] = [
                'date' => $dateIso,
                'weekday' => (int) date('w', strtotime($dateIso) ?: time()),
                'day_number' => (int) date('j', strtotime($dateIso) ?: time()),
                'label' => strftime('%a %d/%m', strtotime($dateIso) ?: time()),
                'worked_hours' => $summary['worked_hours'],
                'first_in' => $summary['first_in'],
                'last_out' => $summary['last_out'],
                'event_count' => $summary['event_count'],
                'status' => $summary['status'],
                'events' => $summary['events'],
            ];
        }

        json_response([
            'employee' => [
                'id' => (int) ($employee['id'] ?? $employeeId),
                'name' => trim((string) ($employee['name'] ?? '')),
                'badge_id' => (string) ($employee['badge_id'] ?? ''),
            ],
            'period' => [
                'type' => $period,
                'anchor_date' => $anchorDate,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'days' => $days,
        ]);
        exit;
    }

    $employees = $pdo
        ->query("SELECT id,
            COALESCE(first_name, '') AS first_name,
            COALESCE(last_name, '') AS last_name,
            badge_id
         FROM employees WHERE active = 1 ORDER BY last_name, first_name")
        ->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count FROM attendance_events WHERE DATE(timestamp) = ?'
    );
    $today = date('Y-m-d');
    $stmt->execute([$today]);
    $events_today = (int) $stmt->fetch()['count'];

    $hasAbsences = table_exists($pdo, 'absences');
    $hasVacationRequests = table_exists($pdo, 'vacation_requests');
    $unavailableSqlParts = [];
    $unavailableParams = [];

    if ($hasAbsences) {
        $unavailableSqlParts[] =
            'SELECT employee_id
             FROM absences
             WHERE start_date <= ? AND end_date >= ?';
        $unavailableParams[] = $today;
        $unavailableParams[] = $today;
    }

    if ($hasVacationRequests) {
        $unavailableSqlParts[] =
            "SELECT employee_id
             FROM vacation_requests
             WHERE status = 'approved'
               AND start_date <= ?
               AND end_date >= ?";
        $unavailableParams[] = $today;
        $unavailableParams[] = $today;
    }

    $unavailableToday = [];

    if ($unavailableSqlParts) {
        $unavailableSql = 'SELECT DISTINCT employee_id FROM (' . implode(' UNION ', $unavailableSqlParts) . ') x';
        $unavailableRows = $pdo->prepare($unavailableSql);
        $unavailableRows->execute($unavailableParams);

        foreach ($unavailableRows->fetchAll(PDO::FETCH_COLUMN) as $employeeId) {
            $unavailableToday[(int) $employeeId] = true;
        }
    }

    $latest = $pdo->query(
        'SELECT e.employee_id, e.event_type
         FROM attendance_events e
         INNER JOIN (
             SELECT employee_id, MAX(id) AS max_id
             FROM attendance_events
             GROUP BY employee_id
         ) last_event ON e.id = last_event.max_id'
    )->fetchAll();

    $status_map = [];
    foreach ($latest as $row) {
        $status_map[(int) $row['employee_id']] = $row['event_type'];
    }

    $employee_statuses = [];
    $present_count     = 0;
    $present_expected_count = 0;
    $unavailable_count = 0;
    foreach ($employees as $emp) {
        $employeeId = (int) $emp['id'];
        $is_present = ($status_map[(int) $emp['id']] ?? 'out') === 'in';
        $is_unavailable = !empty($unavailableToday[$employeeId]);

        if ($is_present) {
            $present_count++;
        }

        if ($is_unavailable) {
            $unavailable_count++;
        } elseif ($is_present) {
            $present_expected_count++;
        }

        $status = 'absent';
        if ($is_present) {
            $status = 'present';
        } elseif ($is_unavailable) {
            $status = 'indisponible';
        }

        $employee_statuses[] = [
            'id'       => $employeeId,
            'name'     => $emp['first_name'] . ' ' . $emp['last_name'],
            'badge_id' => $emp['badge_id'],
            'status'   => $status,
        ];
    }

    $expected_today = max(count($employee_statuses) - $unavailable_count, 0);
    $absent_expected = max($expected_today - $present_expected_count, 0);

    $correctionsPending = 0;
    $fromDate = date('Y-m-d', strtotime('-30 day'));

    if (table_exists($pdo, 'attendance_events')) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM (
                 SELECT employee_id, DATE(timestamp) AS day
                 FROM attendance_events
                 WHERE DATE(timestamp) >= ? AND DATE(timestamp) < CURDATE()
                 GROUP BY employee_id, DATE(timestamp)
                 HAVING SUM(event_type = 'in') != SUM(event_type = 'out')
             ) unpaired_days"
        );
        $stmt->execute([$fromDate]);
        $correctionsPending += (int) $stmt->fetchColumn();
    }

    if (table_exists($pdo, 'attendance_events') && table_exists($pdo, 'scheduled_hours')) {
        $scheduledCols = $pdo->query('SHOW COLUMNS FROM scheduled_hours')->fetchAll(PDO::FETCH_COLUMN);
        $hasWeekStart = in_array('week_start', $scheduledCols, true);

        $sql = $hasWeekStart
            ? 'SELECT DISTINCT employee_id, day_of_week FROM scheduled_hours WHERE hours > 0 AND week_start IS NULL'
            : 'SELECT DISTINCT employee_id, day_of_week FROM scheduled_hours WHERE hours > 0';
        $schedRows = $pdo->query($sql)->fetchAll();

        $scheduledDays = [];
        foreach ($schedRows as $row) {
            $scheduledDays[(int) ($row['employee_id'] ?? 0)][(int) ($row['day_of_week'] ?? -1)] = true;
        }

        $stmt = $pdo->prepare(
            'SELECT DISTINCT employee_id, DATE(timestamp) AS day
             FROM attendance_events
             WHERE DATE(timestamp) >= ? AND DATE(timestamp) < CURDATE()'
        );
        $stmt->execute([$fromDate]);
        $activeDays = $stmt->fetchAll();

        $unscheduledCount = 0;
        foreach ($activeDays as $row) {
            $employeeId = (int) ($row['employee_id'] ?? 0);
            if ($employeeId <= 0 || !isset($scheduledDays[$employeeId])) {
                continue;
            }

            $dow = (int) date('w', strtotime((string) ($row['day'] ?? '')));
            if (!isset($scheduledDays[$employeeId][$dow])) {
                $unscheduledCount++;
            }
        }

        $correctionsPending += $unscheduledCount;
    }

    $recentEvents = $pdo->query(
        "SELECT a.id,
                a.timestamp,
                a.event_type,
                a.source,
                TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS name,
                e.badge_id
         FROM attendance_events a
         JOIN employees e ON e.id = a.employee_id
         ORDER BY a.id DESC
         LIMIT 30"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recentEvents as &$event) {
        $event['timestamp'] = str_replace(' ', 'T', (string) ($event['timestamp'] ?? ''));
    }
    unset($event);

    json_response([
        'summary' => [
            'employees_total' => count($employee_statuses),
            'present'         => $present_count,
            'absent'          => $absent_expected,
            'events_today'    => $events_today,
            'expected_today'  => $expected_today,
            'unavailable'     => $unavailable_count,
            'corrections_pending' => $correctionsPending,
        ],
        'employees' => $employee_statuses,
        'events' => $recentEvents,
    ]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
