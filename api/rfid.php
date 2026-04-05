<?php
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Methode non autorisee.'], 405);
    exit;
}

$payload  = json_decode(file_get_contents('php://input'), true) ?? [];
$badge_id = trim((string) ($payload['badge_id'] ?? ''));

if ($badge_id === '') {
    json_response(['error' => 'Badge RFID manquant.'], 400);
    exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        "SELECT id,
                TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, COALESCE(name, '')))) AS name,
                badge_id
         FROM employees
         WHERE badge_id = ? AND active = 1"
    );
    $stmt->execute([$badge_id]);
    $employee = $stmt->fetch();

    if (!$employee) {
        json_response(['error' => 'Badge inconnu.'], 404);
        exit;
    }

    $next_type = infer_next_event($pdo, (int) $employee['id']);
    $event     = insert_event($pdo, (int) $employee['id'], $next_type, 'rfid');
    $action    = $next_type === 'in' ? 'entree' : 'sortie';

    json_response([
        'message' => "{$employee['name']} enregistre: $action.",
        'event'   => $event,
    ]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
