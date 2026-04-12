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

    $recent = $pdo->query(
        "SELECT a.id, a.timestamp, a.event_type, a.source,
                TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))) AS name,
                e.badge_id
         FROM attendance_events a
         JOIN employees e ON e.id = a.employee_id
         ORDER BY a.id DESC
         LIMIT 30"
    )->fetchAll();

    foreach ($recent as &$r) {
        $r['timestamp'] = format_iso_timestamp((string) ($r['timestamp'] ?? ''));
    }
    unset($r);

    json_response([
        'summary' => [
            'employees_total' => count($employee_statuses),
            'present'         => $present_count,
            'absent'          => $absent_expected,
            'events_today'    => $events_today,
            'expected_today'  => $expected_today,
            'unavailable'     => $unavailable_count,
        ],
        'employees' => $employee_statuses,
        'events'    => $recent,
    ]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
