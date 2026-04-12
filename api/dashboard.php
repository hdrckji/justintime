<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

require_login();
$auth = get_auth_user();
if (($auth['role'] ?? '') === 'employee') {
    json_response(['error' => 'Acces reserve au tableau de bord encadrant.'], 403);
    exit;
}

try {
    $pdo = get_pdo();

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
    ]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
