<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

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
    $stmt->execute([date('Y-m-d')]);
    $events_today = (int) $stmt->fetch()['count'];

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
    foreach ($employees as $emp) {
        $is_present = ($status_map[(int) $emp['id']] ?? 'out') === 'in';
        if ($is_present) {
            $present_count++;
        }
        $employee_statuses[] = [
            'id'       => (int) $emp['id'],
            'name'     => $emp['first_name'] . ' ' . $emp['last_name'],
            'badge_id' => $emp['badge_id'],
            'status'   => $is_present ? 'present' : 'absent',
        ];
    }

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
        $r['timestamp'] = str_replace(' ', 'T', $r['timestamp']);
    }
    unset($r);

    json_response([
        'summary' => [
            'employees_total' => count($employee_statuses),
            'present'         => $present_count,
            'absent'          => max(count($employee_statuses) - $present_count, 0),
            'events_today'    => $events_today,
        ],
        'employees' => $employee_statuses,
        'events'    => $recent,
    ]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
