<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login('admin');
header('Content-Type: application/json; charset=utf-8');

$auth = get_auth_user();
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

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
