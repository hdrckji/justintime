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
                    d.manager_employee_id,
                    COALESCE(m.first_name, '') AS manager_first_name,
                    COALESCE(m.last_name, '') AS manager_last_name,
                    COUNT(e.id) AS employee_count
             FROM departments d
             LEFT JOIN employees e ON e.department_id = d.id
             LEFT JOIN employees m ON m.id = d.manager_employee_id
               GROUP BY d.id, d.name, d.manager_employee_id, m.first_name, m.last_name
             ORDER BY d.name ASC"
        )->fetchAll();

        if (jit_table_exists($pdo, 'rayons')) {
            $rayons = $pdo->query(
                "SELECT id, department_id, name
                 FROM rayons
                 ORDER BY department_id ASC, name ASC"
            )->fetchAll();

            $rayonsByDepartment = [];
            foreach ($rayons as $rayon) {
                $departmentId = (int) ($rayon['department_id'] ?? 0);
                if ($departmentId <= 0) {
                    continue;
                }
                if (!isset($rayonsByDepartment[$departmentId])) {
                    $rayonsByDepartment[$departmentId] = [];
                }
                $rayonsByDepartment[$departmentId][] = [
                    'id' => (int) ($rayon['id'] ?? 0),
                    'name' => (string) ($rayon['name'] ?? ''),
                ];
            }

            foreach ($departments as &$department) {
                $depId = (int) ($department['id'] ?? 0);
                $department['rayons'] = $rayonsByDepartment[$depId] ?? [];
            }
            unset($department);
        } else {
            foreach ($departments as &$department) {
                $department['rayons'] = [];
            }
            unset($department);
        }

        json_response(['departments' => $departments]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Methode non prise en charge.'], 405);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'create_rayon') {
        $departmentId = isset($payload['department_id']) ? (int) $payload['department_id'] : 0;
        $name = trim((string) ($payload['name'] ?? ''));

        if ($departmentId <= 0 || $name === '') {
            json_response(['error' => 'Departement et nom du rayon obligatoires.'], 400);
            exit;
        }

        $stmt = $pdo->prepare('SELECT id FROM departments WHERE id = ? LIMIT 1');
        $stmt->execute([$departmentId]);
        if (!$stmt->fetch()) {
            json_response(['error' => 'Departement introuvable.'], 404);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO rayons (department_id, name) VALUES (?, ?)');
        $stmt->execute([$departmentId, $name]);

        json_response([
            'message' => 'Rayon ajoute.',
            'rayon' => [
                'id' => (int) $pdo->lastInsertId(),
                'department_id' => $departmentId,
                'name' => $name,
            ],
        ], 201);
        exit;
    }

    if ($action === 'delete_rayon') {
        $id = (int) ($_GET['id'] ?? ($payload['id'] ?? 0));
        if ($id <= 0) {
            json_response(['error' => 'Rayon invalide.'], 400);
            exit;
        }

        $stmt = $pdo->prepare('SELECT id FROM rayons WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            json_response(['error' => 'Rayon introuvable.'], 404);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM rayons WHERE id = ?');
        $stmt->execute([$id]);

        json_response(['message' => 'Rayon supprime.']);
        exit;
    }

    if ($action === 'create' || $action === 'save') {
        $name = trim((string) ($payload['name'] ?? ''));
        $managerEmployeeId = isset($payload['manager_employee_id']) && (int) $payload['manager_employee_id'] > 0
            ? (int) $payload['manager_employee_id']
            : null;
        if ($name === '') {
            json_response(['error' => 'Nom du departement obligatoire.'], 400);
            exit;
        }

        if ($managerEmployeeId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM employees WHERE id = ? LIMIT 1');
            $stmt->execute([$managerEmployeeId]);
            if (!$stmt->fetch()) {
                json_response(['error' => 'Responsable introuvable.'], 400);
                exit;
            }
        }

        $stmt = $pdo->prepare('INSERT INTO departments (name, manager_employee_id) VALUES (?, ?)');
        $stmt->execute([$name, $managerEmployeeId]);

        json_response([
            'message' => 'Departement ajoute.',
            'department' => [
                'id' => (int) $pdo->lastInsertId(),
                'name' => $name,
                'manager_employee_id' => $managerEmployeeId,
            ],
        ], 201);
        exit;
    }

    if ($action === 'set_manager') {
        $id = (int) ($_GET['id'] ?? ($payload['id'] ?? 0));
        if ($id <= 0) {
            json_response(['error' => 'Departement invalide.'], 400);
            exit;
        }

        $managerEmployeeId = isset($payload['manager_employee_id']) && (int) $payload['manager_employee_id'] > 0
            ? (int) $payload['manager_employee_id']
            : null;

        $stmt = $pdo->prepare('SELECT id FROM departments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            json_response(['error' => 'Departement introuvable.'], 404);
            exit;
        }

        if ($managerEmployeeId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM employees WHERE id = ? LIMIT 1');
            $stmt->execute([$managerEmployeeId]);
            if (!$stmt->fetch()) {
                json_response(['error' => 'Responsable introuvable.'], 400);
                exit;
            }
        }

        $stmt = $pdo->prepare('UPDATE departments SET manager_employee_id = ? WHERE id = ?');
        $stmt->execute([$managerEmployeeId, $id]);

        json_response([
            'message' => 'Responsable mis a jour.',
            'department_id' => $id,
            'manager_employee_id' => $managerEmployeeId,
        ]);
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
            $message = 'Cette valeur existe deja (departement ou rayon).';
        }
    }

    json_response(['error' => $message], $status);
}
