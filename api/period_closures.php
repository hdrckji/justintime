<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login('admin');
header('Content-Type: application/json; charset=utf-8');

$auth = get_auth_user();
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function jit_month_bounds(string $periodKey): array
{
    $from = $periodKey . '-01';
    $to = date('Y-m-t', strtotime($from));
    return [$from, $to];
}

function jit_month_unpaired_count(PDO $pdo, string $from, string $to): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM (
             SELECT ae.employee_id, DATE(ae.timestamp) AS day
             FROM attendance_events ae
             WHERE DATE(ae.timestamp) BETWEEN ? AND ?
             GROUP BY ae.employee_id, DATE(ae.timestamp)
             HAVING SUM(ae.event_type = 'in') != SUM(ae.event_type = 'out')
         ) anomalies"
    );
    $stmt->execute([$from, $to]);
    return (int) $stmt->fetchColumn();
}

function jit_month_unscheduled_count(PDO $pdo, string $from, string $to): int
{
    $scheduledCols = $pdo->query('SHOW COLUMNS FROM scheduled_hours')->fetchAll(PDO::FETCH_COLUMN);
    $hasWeekStart = in_array('week_start', $scheduledCols, true);

    $sql = $hasWeekStart
        ? 'SELECT DISTINCT employee_id, day_of_week FROM scheduled_hours WHERE hours > 0 AND week_start IS NULL'
        : 'SELECT DISTINCT employee_id, day_of_week FROM scheduled_hours WHERE hours > 0';
    $schedRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $scheduledDays = [];
    foreach ($schedRows as $row) {
        $scheduledDays[(int) $row['employee_id']][(int) $row['day_of_week']] = true;
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT employee_id, DATE(timestamp) AS day
         FROM attendance_events
         WHERE DATE(timestamp) BETWEEN ? AND ?'
    );
    $stmt->execute([$from, $to]);
    $days = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($days as $row) {
        $empId = (int) ($row['employee_id'] ?? 0);
        if ($empId <= 0 || !isset($scheduledDays[$empId])) {
            continue;
        }
        $dow = (int) date('w', strtotime((string) $row['day']));
        if (!isset($scheduledDays[$empId][$dow])) {
            $count++;
        }
    }

    return $count;
}

try {
    if ($method === 'GET') {
        $months = max(3, min(36, (int) ($_GET['months'] ?? 12)));
        $stmt = $pdo->query('SELECT period_key, closed_by, closed_at FROM payroll_closures ORDER BY period_key DESC');
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['period_key']] = $row;
        }

        $periods = [];
        $base = strtotime(date('Y-m-01'));
        for ($i = 0; $i < $months; $i++) {
            $periodKey = date('Y-m', strtotime("-{$i} month", $base));
            $periods[] = [
                'period_key' => $periodKey,
                'closed' => isset($map[$periodKey]),
                'closed_by' => $map[$periodKey]['closed_by'] ?? null,
                'closed_at' => $map[$periodKey]['closed_at'] ?? null,
            ];
        }

        json_response(['periods' => $periods]);
        exit;
    }

    if ($method !== 'POST') {
        json_response(['error' => 'Methode non autorisee.'], 405);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = trim((string) ($payload['action'] ?? ''));
    $periodKey = trim((string) ($payload['period_key'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}$/', $periodKey)) {
        json_response(['error' => 'Periode invalide.'], 400);
        exit;
    }

    if ($action === 'close') {
        [$from, $to] = jit_month_bounds($periodKey);
        $unpairedCount = jit_month_unpaired_count($pdo, $from, $to);
        $unscheduledCount = jit_month_unscheduled_count($pdo, $from, $to);

        if ($unpairedCount > 0 || $unscheduledCount > 0) {
            json_response([
                'error' => 'Cloture impossible: ' . $unpairedCount . ' jour(s) avec pointages impairs et ' . $unscheduledCount . ' jour(s) hors horaire restent a corriger sur cette periode.',
                'details' => [
                    'unpaired_count' => $unpairedCount,
                    'unscheduled_count' => $unscheduledCount,
                ],
            ], 409);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO payroll_closures (period_key, closed_by) VALUES (?, ?) ON DUPLICATE KEY UPDATE closed_by = VALUES(closed_by), closed_at = CURRENT_TIMESTAMP');
        $stmt->execute([$periodKey, (string) ($auth['username'] ?? 'admin')]);
        log_audit_event($pdo, $auth, 'close_period', 'payroll_period', $periodKey, 'Cloture de periode', ['period_key' => $periodKey]);
        json_response(['message' => 'Periode cloturee.', 'period_key' => $periodKey]);
        exit;
    }

    if ($action === 'reopen') {
        $stmt = $pdo->prepare('DELETE FROM payroll_closures WHERE period_key = ?');
        $stmt->execute([$periodKey]);
        log_audit_event($pdo, $auth, 'reopen_period', 'payroll_period', $periodKey, 'Reouverture de periode', ['period_key' => $periodKey]);
        json_response(['message' => 'Periode rouverte.', 'period_key' => $periodKey]);
        exit;
    }

    json_response(['error' => 'Action inconnue.'], 400);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
