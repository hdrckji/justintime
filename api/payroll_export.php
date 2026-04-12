<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/payroll_helpers.php';

require_login('admin');

try {
    $pdo = get_pdo();
    $period = trim((string) ($_GET['period'] ?? date('Y-m')));
    $departmentId = (int) ($_GET['department_id'] ?? 0);
    $employeeId = (int) ($_GET['employee_id'] ?? 0);

    if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
        http_response_code(400);
        exit('Periode invalide.');
    }

    $from = $period . '-01';
    $to = date('Y-m-t', strtotime($from));
    $dates = jit_each_date($from, $to);

    $sql = "SELECT e.id,
                   TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))) AS employee_name,
                   e.badge_id,
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

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payroll-export-' . $period . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Periode', 'Date', 'Employe', 'Badge', 'Departement', 'Type jour', 'Code paie', 'Libelle', 'Heures prevues', 'Heures travaillees', 'Absences payees', 'Absences non payees', 'Formation', 'Heures payables', 'Solde jour', 'Premiere entree', 'Derniere sortie', 'Motif'], ';');

    foreach ($employees as $employee) {
        $empId = (int) $employee['id'];
        foreach ($dates as $dateIso) {
            $day = jit_payroll_breakdown_for_day($pdo, $empId, $dateIso);
            $absence = $day['absence'] ?? null;

            fputcsv($out, [
                $period,
                $dateIso,
                $employee['employee_name'],
                $employee['badge_id'],
                $employee['department_name'],
                $day['day_type'],
                $day['export_code'],
                $day['day_label'],
                number_format((float) $day['scheduled_hours'], 2, '.', ''),
                number_format((float) $day['worked_hours'], 2, '.', ''),
                number_format((float) $day['paid_absence_hours'], 2, '.', ''),
                number_format((float) $day['unpaid_absence_hours'], 2, '.', ''),
                number_format((float) $day['training_hours'], 2, '.', ''),
                number_format((float) $day['payable_hours'], 2, '.', ''),
                number_format((float) $day['period_balance'], 2, '.', ''),
                $day['first_in'] ?? '',
                $day['last_out'] ?? '',
                $absence['reason'] ?? '',
            ], ';');
        }
    }

    fclose($out);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erreur export: ' . $e->getMessage();
}
