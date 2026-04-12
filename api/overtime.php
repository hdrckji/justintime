<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/payroll_helpers.php';

require_login('admin');
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_pdo();
    $period = trim((string) ($_GET['period'] ?? date('Y-m')));
    $departmentId = (int) ($_GET['department_id'] ?? 0);
    $employeeId = (int) ($_GET['employee_id'] ?? 0);

    if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
        json_response(['error' => 'Periode invalide.'], 400);
        exit;
    }

    $from = $period . '-01';
    $to = date('Y-m-t', strtotime($from));
    $dates = jit_each_date($from, $to);

    $sql = "SELECT e.id,
                   TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))) AS name,
                   e.department_id,
                   COALESCE(d.name, '') AS department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.active = 1";
    $params = [];
    if ($departmentId > 0) {
        $sql .= ' AND e.department_id = ?';
        $params[] = $departmentId;
    }
    if ($employeeId > 0) {
        $sql .= ' AND e.id = ?';
        $params[] = $employeeId;
    }
    $sql .= ' ORDER BY e.last_name, e.first_name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();

    $rows = [];
    foreach ($employees as $employee) {
        $empId = (int) $employee['id'];
        $scheduled = 0.0;
        $worked = 0.0;
        $cumulative = 0.0;

        foreach ($dates as $dateIso) {
            $scheduled += jit_scheduled_hours_for_day($pdo, $empId, $dateIso);
            $dayWork = jit_worked_hours_for_day($pdo, $empId, $dateIso);
            $worked += (float) ($dayWork['hours'] ?? 0);
        }

        $allDatesStmt = $pdo->prepare('SELECT DISTINCT DATE(timestamp) AS day FROM attendance_events WHERE employee_id = ? ORDER BY day ASC');
        $allDatesStmt->execute([$empId]);
        $allDates = $allDatesStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($allDates as $dateIso) {
            $cumulative += jit_worked_hours_for_day($pdo, $empId, (string) $dateIso)['hours'] - jit_scheduled_hours_for_day($pdo, $empId, (string) $dateIso);
        }

        $rows[] = [
            'employee_id' => $empId,
            'employee_name' => (string) ($employee['name'] ?? ''),
            'department_name' => (string) ($employee['department_name'] ?? ''),
            'scheduled_hours' => round($scheduled, 2),
            'worked_hours' => round($worked, 2),
            'period_balance' => round($worked - $scheduled, 2),
            'cumulative_balance' => round($cumulative, 2),
        ];
    }

    json_response(['period' => $period, 'rows' => $rows]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
