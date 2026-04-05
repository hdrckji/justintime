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

try {
    $pdo = get_pdo();

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

    json_response([
        'employee' => $employee,
        'today'    => $today_events,
        'history'  => $history,
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
