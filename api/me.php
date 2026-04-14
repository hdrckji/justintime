<?php
/**
 * api/me.php — Données personnelles de l'employé connecté
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login();
$auth = get_auth_user();

if (!$auth['employee_id']) {
    json_response(['error' => 'Compte non lie a un employe.'], 403);
    exit;
}

$emp_id = (int) $auth['employee_id'];
$today  = date('Y-m-d');
$year   = (int) date('Y');
$action = trim((string) ($_GET['action'] ?? ''));

try {
    $pdo = get_pdo();

    // ---- Action history : pointages filtrés (année / mois / semaine) ----
    if ($action === 'history') {
        $filterYear  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');
        $filterMonth = isset($_GET['month']) ? (int) $_GET['month'] : 0;
        $filterWeek  = isset($_GET['week'])  ? (int) $_GET['week']  : 0;

        $from = null;
        $to   = null;

        if ($filterWeek > 0 && $filterMonth > 0) {
            // Semaine N du mois : 1ère occurrence du lundi dans ce mois
            $firstDay = mktime(0, 0, 0, $filterMonth, 1, $filterYear);
            $dayOfWeek = (int) date('N', $firstDay); // 1=lun..7=dim
            $mondayOffset = ($dayOfWeek === 1) ? 0 : (8 - $dayOfWeek);
            $firstMonday = $firstDay + $mondayOffset * 86400;
            $weekStart = $firstMonday + ($filterWeek - 1) * 7 * 86400;
            $weekEnd   = $weekStart + 6 * 86400;
            $from = date('Y-m-d', $weekStart);
            $to   = date('Y-m-d', $weekEnd);
        } elseif ($filterMonth > 0) {
            $from = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
            $lastDay = (int) date('t', mktime(0, 0, 0, $filterMonth, 1, $filterYear));
            $to   = sprintf('%04d-%02d-%02d', $filterYear, $filterMonth, $lastDay);
        } else {
            $from = sprintf('%04d-01-01', $filterYear);
            $to   = sprintf('%04d-12-31', $filterYear);
        }

        $stmt = $pdo->prepare(
            "SELECT id, event_type, source, timestamp
             FROM attendance_events
             WHERE employee_id = ? AND DATE(timestamp) BETWEEN ? AND ?
             ORDER BY timestamp ASC"
        );
        $stmt->execute([$emp_id, $from, $to]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$e) {
            $e['timestamp'] = str_replace(' ', 'T', $e['timestamp']);
        }
        unset($e);

        json_response([
            'events' => $rows,
            'from'   => $from,
            'to'     => $to,
        ]);
        exit;
    }

    if ($action === 'manager_history') {
        $managedDepartmentIds = jit_get_managed_department_ids($pdo, $emp_id);
        if (!$managedDepartmentIds) {
            json_response(['error' => 'Acces manager requis.'], 403);
            exit;
        }

        $filterYear  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');
        $filterMonth = isset($_GET['month']) ? (int) $_GET['month'] : 0;
        $filterWeek  = isset($_GET['week'])  ? (int) $_GET['week']  : 0;
        $filterDepartmentId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
        $filterEmployeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
        $filterRayon = trim((string) ($_GET['rayon'] ?? ''));

        $from = null;
        $to   = null;

        if ($filterWeek > 0 && $filterMonth > 0) {
            $firstDay = mktime(0, 0, 0, $filterMonth, 1, $filterYear);
            $dayOfWeek = (int) date('N', $firstDay);
            $mondayOffset = ($dayOfWeek === 1) ? 0 : (8 - $dayOfWeek);
            $firstMonday = $firstDay + $mondayOffset * 86400;
            $weekStart = $firstMonday + ($filterWeek - 1) * 7 * 86400;
            $weekEnd   = $weekStart + 6 * 86400;
            $from = date('Y-m-d', $weekStart);
            $to   = date('Y-m-d', $weekEnd);
        } elseif ($filterMonth > 0) {
            $from = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
            $lastDay = (int) date('t', mktime(0, 0, 0, $filterMonth, 1, $filterYear));
            $to   = sprintf('%04d-%02d-%02d', $filterYear, $filterMonth, $lastDay);
        } else {
            $from = sprintf('%04d-01-01', $filterYear);
            $to   = sprintf('%04d-12-31', $filterYear);
        }

        $departmentIds = $managedDepartmentIds;
        if ($filterDepartmentId > 0) {
            if (!in_array($filterDepartmentId, $managedDepartmentIds, true)) {
                json_response(['error' => 'Departement hors perimetre manager.'], 403);
                exit;
            }
            $departmentIds = [$filterDepartmentId];
        }

        $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
        $sql =
            "SELECT ae.id,
                    ae.employee_id,
                    ae.event_type,
                    ae.source,
                    ae.timestamp,
                    COALESCE(e.first_name, '') AS first_name,
                    COALESCE(e.last_name, '') AS last_name,
                    e.department_id,
                    COALESCE(e.rayon, '') AS rayon
             FROM attendance_events ae
             JOIN employees e ON e.id = ae.employee_id
             WHERE e.department_id IN ($placeholders)
               AND DATE(ae.timestamp) BETWEEN ? AND ?";

        $params = $departmentIds;
        $params[] = $from;
        $params[] = $to;

        if ($filterRayon !== '') {
            $sql .= ' AND TRIM(COALESCE(e.rayon, "")) = ?';
            $params[] = $filterRayon;
        }

        if ($filterEmployeeId > 0) {
            $sql .= ' AND ae.employee_id = ?';
            $params[] = $filterEmployeeId;
        }

        $sql .= ' ORDER BY ae.timestamp ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$e) {
            $e['timestamp'] = str_replace(' ', 'T', (string) $e['timestamp']);
        }
        unset($e);

        json_response([
            'events' => $rows,
            'from' => $from,
            'to' => $to,
        ]);
        exit;
    }

    // ---- Action par défaut : données du tableau de bord employé ----

    // Infos de l'employe
    $stmt = $pdo->prepare(
        "SELECT id,
            COALESCE(first_name,'') AS first_name,
            COALESCE(last_name,'') AS last_name,
            badge_id,
            COALESCE(vacation_days, 25) AS vacation_days
         FROM employees WHERE id = ?"
    );
    $stmt->execute([$emp_id]);
    $employee = $stmt->fetch();

    if (!$employee) {
        json_response(['error' => 'Employe introuvable.'], 404);
        exit;
    }

    // Pointages du jour
    $stmt = $pdo->prepare(
        "SELECT id, event_type, source, timestamp
         FROM attendance_events
         WHERE employee_id = ? AND DATE(timestamp) = ?
         ORDER BY id ASC"
    );
    $stmt->execute([$emp_id, $today]);
    $today_events = $stmt->fetchAll();

    // Historique des 30 derniers jours
    $stmt = $pdo->prepare(
        "SELECT id, event_type, source, timestamp
         FROM attendance_events
         WHERE employee_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY id DESC"
    );
    $stmt->execute([$emp_id]);
    $history = $stmt->fetchAll();

    // Solde congés de l'année en cours
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(DATEDIFF(end_date, start_date) + 1), 0) AS used_days
         FROM absences
         WHERE employee_id = ? AND type = 'vacation' AND YEAR(start_date) = ?"
    );
    $stmt->execute([$emp_id, $year]);
    $used_days  = (int) $stmt->fetchColumn();
    $total_days = (int) $employee['vacation_days'];
    $balance    = $total_days - $used_days;

    // Normaliser les timestamps
    foreach ($today_events as &$e) {
        $e['timestamp'] = str_replace(' ', 'T', $e['timestamp']);
    }
    foreach ($history as &$e) {
        $e['timestamp'] = str_replace(' ', 'T', $e['timestamp']);
    }
    unset($e);

    $managedDepartmentIds = jit_get_managed_department_ids($pdo, $emp_id);
    $managedDepartments = [];
    $managedTeam = [];

    if ($managedDepartmentIds) {
        $placeholders = implode(',', array_fill(0, count($managedDepartmentIds), '?'));

        $stmt = $pdo->prepare(
            "SELECT d.id, d.name
             FROM departments d
             WHERE d.id IN ($placeholders)
             ORDER BY d.name ASC"
        );
        $stmt->execute($managedDepartmentIds);
        $managedDepartments = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            "SELECT e.id,
                    COALESCE(e.first_name, '') AS first_name,
                    COALESCE(e.last_name, '') AS last_name,
                    e.department_id,
                                        COALESCE(e.rayon, '') AS rayon,
                    COALESCE(d.name, '') AS department_name
             FROM employees e
             JOIN departments d ON d.id = e.department_id
             WHERE e.department_id IN ($placeholders)
               AND e.active = 1
               AND e.id <> ?
             ORDER BY d.name ASC, e.last_name ASC, e.first_name ASC"
        );
        $params = $managedDepartmentIds;
        $params[] = $emp_id;
        $stmt->execute($params);
        $managedTeam = $stmt->fetchAll();
    }

    json_response([
        'employee' => $employee,
        'today'    => $today_events,
        'history'  => $history,
        'manager_scope' => [
            'is_manager' => !empty($managedDepartments),
            'departments' => $managedDepartments,
            'team' => $managedTeam,
        ],
        'vacation' => [
            'total'   => $total_days,
            'used'    => $used_days,
            'balance' => $balance,
            'year'    => $year,
        ],
    ]);

} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
