<?php
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Methode non autorisee.'], 405);
    exit;
}

$payload     = json_decode(file_get_contents('php://input'), true) ?? [];
$employee_id = filter_var($payload['employee_id'] ?? null, FILTER_VALIDATE_INT);
$event_type  = strtolower(trim((string) ($payload['event_type'] ?? '')));

if ($employee_id === false || $employee_id === null) {
    json_response(['error' => 'Employe invalide.'], 400);
    exit;
}

if (!in_array($event_type, ['in', 'out'], true)) {
    json_response(['error' => "Action invalide. Utilisez 'in' ou 'out'."], 400);
    exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT id, name FROM employees WHERE id = ? AND active = 1'
    );
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();

    if (!$employee) {
        json_response(['error' => 'Employe introuvable.'], 404);
        exit;
    }

    $last = get_last_event_type($pdo, (int) $employee_id);

    if ($event_type === 'in' && $last === 'in') {
        json_response(['error' => "{$employee['name']} est deja present."], 409);
        exit;
    }

    if ($event_type === 'out' && $last !== 'in') {
        json_response(['error' => "{$employee['name']} n'est pas en presence active."], 409);
        exit;
    }

    $event  = insert_event($pdo, (int) $employee_id, $event_type, 'manual');
    $action = $event_type === 'in' ? 'entree' : 'sortie';

    json_response([
        'message' => "{$employee['name']} enregistre: $action.",
        'event'   => $event,
    ]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
