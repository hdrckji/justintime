<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login('admin');

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_pdo();
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $absences = $pdo->query(
            'SELECT a.id, a.employee_id, a.type, a.start_date, a.end_date, a.reason,
                    CONCAT(e.first_name, " ", e.last_name) as employee_name
             FROM absences a
             JOIN employees e ON e.id = a.employee_id
             WHERE a.end_date >= CURDATE()
             ORDER BY a.start_date DESC'
        )->fetchAll();
        json_response(['absences' => $absences]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($action === 'delete') {
            $id = (int) $_GET['id'];
            $stmt = $pdo->prepare('DELETE FROM absences WHERE id = ?');
            $stmt->execute([$id]);
            json_response(['message' => 'Absence supprimee.']);
            exit;
        }

        // Insert absence
        $emp_id    = (int) $payload['employee_id'];
        $type      = trim($payload['type'] ?? '');
        $start     = trim($payload['start_date'] ?? '');
        $end       = trim($payload['end_date'] ?? '');
        $reason    = trim($payload['reason'] ?? '');

        if (!$emp_id || !$type || !$start || !$end) {
            json_response(['error' => 'Champs obligatoires manquants.'], 400);
            exit;
        }

        if (!in_array($type, ['sick', 'vacation', 'other'])) {
            json_response(['error' => 'Type invalide.'], 400);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO absences (employee_id, type, start_date, end_date, reason)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$emp_id, $type, $start, $end, $reason]);
        json_response(['message' => 'Absence enregistree.']);
    }
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
