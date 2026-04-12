<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/payroll_helpers.php';

require_login();
$auth = get_auth_user();

if (!in_array(($auth['role'] ?? ''), ['admin', 'hr'], true)) {
    json_response(['error' => 'Acces reserve a l\'administration RH.'], 403);
    exit;
}

function jit_employee_balance_summary(PDO $pdo, int $employeeId, int $year): array
{
    $stmt = $pdo->prepare(
        "SELECT id,
                TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS employee_name,
                badge_id,
                COALESCE(vacation_days, 25) AS vacation_days,
                COALESCE(vacation_adjustment_days, 0) AS vacation_adjustment_days,
                COALESCE(overtime_adjustment_hours, 0) AS overtime_adjustment_hours
         FROM employees
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        throw new RuntimeException('Collaborateur introuvable.');
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(days_count), 0)
         FROM (
             SELECT DATEDIFF(end_date, start_date) + 1 AS days_count
             FROM vacation_requests
             WHERE employee_id = ? AND status = 'approved'
               AND YEAR(start_date) = ?
             UNION ALL
             SELECT DATEDIFF(end_date, start_date) + 1 AS days_count
             FROM absences
             WHERE employee_id = ?
               AND type = 'vacation'
               AND YEAR(start_date) = ?
         ) vacation_days"
    );
    $stmt->execute([$employeeId, $year, $employeeId, $year]);
    $approvedDays = round((float) $stmt->fetchColumn(), 2);

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(DATEDIFF(end_date, start_date) + 1), 0)
         FROM vacation_requests
         WHERE employee_id = ? AND status = 'pending'
           AND YEAR(start_date) = ?"
    );
    $stmt->execute([$employeeId, $year]);
    $pendingDays = round((float) $stmt->fetchColumn(), 2);

    $totalDays = (float) ($employee['vacation_days'] ?? 25);
    $vacationAdjustment = (float) ($employee['vacation_adjustment_days'] ?? 0);
    $vacationBalance = round($totalDays - $approvedDays + $vacationAdjustment, 2);

    $to = date('Y-m-d');
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
    $rangeStmt->execute([$employeeId, $employeeId, $employeeId]);
    $from = (string) ($rangeStmt->fetchColumn() ?: date('Y-01-01'));

    $computedOvertime = 0.0;
    foreach (jit_each_date($from, $to) as $dateIso) {
        $computedOvertime += (float) jit_payroll_breakdown_for_day($pdo, $employeeId, (string) $dateIso)['period_balance'];
    }
    $computedOvertime = round($computedOvertime, 2);
    $overtimeAdjustment = (float) ($employee['overtime_adjustment_hours'] ?? 0);
    $displayedOvertime = round($computedOvertime + $overtimeAdjustment, 2);

    return [
        'employee_id' => (int) $employee['id'],
        'employee_name' => (string) ($employee['employee_name'] ?? ''),
        'badge_id' => (string) ($employee['badge_id'] ?? ''),
        'year' => $year,
        'vacation' => [
            'entitled_days' => round($totalDays, 2),
            'approved_days' => $approvedDays,
            'pending_days' => $pendingDays,
            'adjustment_days' => round($vacationAdjustment, 2),
            'balance_days' => $vacationBalance,
        ],
        'overtime' => [
            'computed_hours' => $computedOvertime,
            'adjustment_hours' => round($overtimeAdjustment, 2),
            'balance_hours' => $displayedOvertime,
        ],
    ];
}

try {
    $pdo = get_pdo();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $year = (int) ($_GET['year'] ?? date('Y'));
        if ($employeeId <= 0) {
            json_response(['error' => 'Collaborateur invalide.'], 400);
            exit;
        }

        json_response(['summary' => jit_employee_balance_summary($pdo, $employeeId, $year)]);
        exit;
    }

    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $vacationAdjustment = isset($payload['vacation_adjustment_days']) ? (float) $payload['vacation_adjustment_days'] : null;
        $overtimeAdjustment = isset($payload['overtime_adjustment_hours']) ? (float) $payload['overtime_adjustment_hours'] : null;

        if ($employeeId <= 0) {
            json_response(['error' => 'Collaborateur invalide.'], 400);
            exit;
        }

        $stmt = $pdo->prepare(
            'UPDATE employees
             SET vacation_adjustment_days = ?, overtime_adjustment_hours = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $vacationAdjustment ?? 0,
            $overtimeAdjustment ?? 0,
            $employeeId,
        ]);

        log_audit_event($pdo, $auth, 'update_employee_balances', 'employee', (string) $employeeId, 'Mise a jour des soldes RH', [
            'vacation_adjustment_days' => $vacationAdjustment ?? 0,
            'overtime_adjustment_hours' => $overtimeAdjustment ?? 0,
        ]);

        json_response([
            'message' => 'Soldes RH mis a jour.',
            'summary' => jit_employee_balance_summary($pdo, $employeeId, (int) date('Y')),
        ]);
        exit;
    }

    json_response(['error' => 'Methode non autorisee.'], 405);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}