<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login('admin');

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_pdo();
    $action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'create' : 'list');

    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $departments = $pdo->query(
            "SELECT d.id,
                    d.name,
                    COUNT(e.id) AS employee_count
             FROM departments d
             LEFT JOIN employees e ON e.department_id = d.id
             GROUP BY d.id, d.name
             ORDER BY d.name ASC"
        )->fetchAll();

        json_response(['departments' => $departments]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Methode non prise en charge.'], 405);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'create' || $action === 'save') {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            json_response(['error' => 'Nom du departement obligatoire.'], 400);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO departments (name) VALUES (?)');
        $stmt->execute([$name]);

        json_response([
            'message' => 'Departement ajoute.',
            'department' => [
                'id' => (int) $pdo->lastInsertId(),
                'name' => $name,
            ],
        ], 201);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_GET['id'] ?? ($payload['id'] ?? 0));
        if ($id <= 0) {
            json_response(['error' => 'Departement invalide.'], 400);
            exit;
        }

        $stmt = $pdo->prepare('SELECT id, name FROM departments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $department = $stmt->fetch();

        if (!$department) {
            json_response(['error' => 'Departement introuvable.'], 404);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE employees SET department_id = NULL WHERE department_id = ?');
        $stmt->execute([$id]);

        $stmt = $pdo->prepare('DELETE FROM departments WHERE id = ?');
        $stmt->execute([$id]);

        $pdo->commit();

        json_response(['message' => 'Departement supprime.']);
        exit;
    }

    json_response(['error' => 'Action invalide.'], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = $e->getMessage();
    $status = 500;

    if ($e instanceof PDOException) {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        if ($sqlState === '23000') {
            $status = 409;
            $message = 'Ce departement existe deja.';
        }
    }

    json_response(['error' => $message], $status);
}
