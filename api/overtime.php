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
        $paidAbsence = 0.0;
        $unpaidAbsence = 0.0;
        $training = 0.0;
        $authorizedPaid = 0.0;
        $sick = 0.0;
        $vacation = 0.0;
        $payable = 0.0;
        $cumulative = 0.0;

        foreach ($dates as $dateIso) {
            $day = jit_payroll_breakdown_for_day($pdo, $empId, $dateIso);
            $scheduled += (float) $day['scheduled_hours'];
            $worked += (float) $day['worked_hours'];
            $paidAbsence += (float) $day['paid_absence_hours'];
            $unpaidAbsence += (float) $day['unpaid_absence_hours'];
            $training += (float) $day['training_hours'];
            $authorizedPaid += (float) $day['authorized_paid_hours'];
            $sick += (float) $day['sick_hours'];
            $vacation += (float) $day['vacation_hours'];
            $payable += (float) $day['payable_hours'];
        }

        $rangeStmt = $pdo->prepare(
            "SELECT MIN(first_day) AS first_day
             FROM (
               SELECT MIN(DATE(timestamp)) AS first_day FROM attendance_events WHERE employee_id = ?
               UNION ALL
               SELECT MIN(start_date) AS first_day FROM absences WHERE employee_id = ?
               UNION ALL
               SELECT MIN(start_date) AS first_day FROM vacation_requests WHERE employee_id = ? AND status = 'approved'
             ) dates"
        );
        $rangeStmt->execute([$empId, $empId, $empId]);
        $firstTrackedDay = (string) ($rangeStmt->fetchColumn() ?: $from);
        foreach (jit_each_date($firstTrackedDay, $to) as $dateIso) {
            $cumulative += (float) jit_payroll_breakdown_for_day($pdo, $empId, (string) $dateIso)['period_balance'];
        }

        $rows[] = [
            'employee_id' => $empId,
            'employee_name' => (string) ($employee['name'] ?? ''),
            'department_name' => (string) ($employee['department_name'] ?? ''),
            'scheduled_hours' => round($scheduled, 2),
            'worked_hours' => round($worked, 2),
            'paid_absence_hours' => round($paidAbsence, 2),
            'unpaid_absence_hours' => round($unpaidAbsence, 2),
            'training_hours' => round($training, 2),
            'authorized_paid_hours' => round($authorizedPaid, 2),
            'sick_hours' => round($sick, 2),
            'vacation_hours' => round($vacation, 2),
            'payable_hours' => round($payable, 2),
            'period_balance' => round($payable - $scheduled, 2),
            'cumulative_balance' => round($cumulative, 2),
        ];
    }

    json_response(['period' => $period, 'rows' => $rows]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
