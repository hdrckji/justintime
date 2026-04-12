<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login('admin');
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_pdo();
    $limit = max(10, min(200, (int) ($_GET['limit'] ?? 50)));
    $targetType = trim((string) ($_GET['target_type'] ?? ''));

    $sql = 'SELECT id, actor_username, actor_role, action_type, target_type, target_id, summary, details_json, created_at FROM audit_logs';
    $params = [];
    if ($targetType !== '') {
        $sql .= ' WHERE target_type = ?';
        $params[] = $targetType;
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['details'] = $row['details_json'] ? json_decode((string) $row['details_json'], true) : null;
        unset($row['details_json']);
    }
    unset($row);

    json_response(['logs' => $rows]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
