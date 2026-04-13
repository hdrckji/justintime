<?php
/**
 * api/vacation_requests.php — Gestion des demandes de congés
 * Actions: list, create, review
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login();
$auth = get_auth_user();

$action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'create' : 'list');

try {
    $pdo = get_pdo();
    $authRole = (string) ($auth['role'] ?? '');
    $authEmployeeId = (int) ($auth['employee_id'] ?? 0);
    $isAdminLike = in_array($authRole, ['admin', 'hr'], true);
    $isEmployee = $authRole === 'employee';
    $managedDepartmentIds = $authEmployeeId > 0 ? jit_get_managed_department_ids($pdo, $authEmployeeId) : [];
    $isManager = !empty($managedDepartmentIds);

    // === LIST: Récupérer les demandes ===
    if ($action === 'list') {
        $filter = $_GET['status'] ?? null;
        $emp_id = $_GET['emp_id'] ?? null;

        $sql = "SELECT vr.id, vr.employee_id, vr.start_date, vr.end_date, vr.reason, vr.status, 
                       vr.admin_comment, vr.created_at, vr.updated_at,
                       COALESCE(e.first_name, '') AS emp_first,
                       COALESCE(e.last_name, '') AS emp_last
                FROM vacation_requests vr
                JOIN employees e ON vr.employee_id = e.id
                WHERE 1=1";

        $params = [];

        if ($filter && in_array($filter, ['pending', 'approved', 'rejected'])) {
            $sql .= " AND vr.status = ?";
            $params[] = $filter;
        }

        if ($isAdminLike) {
            if ($emp_id && is_numeric($emp_id)) {
                $sql .= " AND vr.employee_id = ?";
                $params[] = (int) $emp_id;
            }
        } elseif ($isManager) {
            $sql .= " AND e.department_id IN (" . implode(',', array_fill(0, count($managedDepartmentIds), '?')) . ")";
            foreach ($managedDepartmentIds as $deptId) {
                $params[] = (int) $deptId;
            }

            if ($emp_id && is_numeric($emp_id)) {
                $targetEmployeeId = (int) $emp_id;
                if (!jit_can_manage_employee($pdo, $authEmployeeId, $targetEmployeeId)) {
                    json_response(['error' => 'Acces refuse pour ce collaborateur.'], 403);
                    exit;
                }
                $sql .= " AND vr.employee_id = ?";
                $params[] = $targetEmployeeId;
            }
        } elseif ($isEmployee) {
            if (!$auth['employee_id']) {
                json_response(['error' => 'Compte non lie a un employe.'], 403);
                exit;
            }
            $sql .= " AND vr.employee_id = ?";
            $params[] = (int) $auth['employee_id'];
        }

        $sql .= " ORDER BY vr.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        json_response(['requests' => $requests]);
        exit;
    }

    // === CREATE: Créer une demande (emploi seulement) ===
    if ($action === 'create') {
        if (!$isEmployee) {
            json_response(['error' => 'Seuls les emploies peuvent creer des demandes.'], 403);
            exit;
        }

        if (!$auth['employee_id']) {
            json_response(['error' => 'Compte non lie a un employe.'], 403);
            exit;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $start_date = $payload['start_date'] ?? null;
        $end_date = $payload['end_date'] ?? null;
        $reason = trim($payload['reason'] ?? '');

        if (!$start_date || !$end_date) {
            json_response(['error' => 'Dates de debut et fin requises.'], 400);
            exit;
        }

        // Validation des dates
        $sd = DateTime::createFromFormat('Y-m-d', $start_date);
        $ed = DateTime::createFromFormat('Y-m-d', $end_date);
        if (!$sd || !$ed || $sd > $ed) {
            json_response(['error' => 'Dates invalides. La date de fin doit etre apres la date de debut.'], 400);
            exit;
        }

        // Vérifier qu'il n'existe pas déjà une demande en chevauchement
        $stmt = $pdo->prepare(
            "SELECT id FROM vacation_requests 
             WHERE employee_id = ? 
             AND status IN ('pending', 'approved')
             AND (start_date <= ? AND end_date >= ?)"
        );
        $stmt->execute([$auth['employee_id'], $end_date, $start_date]);
        if ($stmt->fetch()) {
            json_response(['error' => 'Une demande chevauchant cette periode existe deja.'], 409);
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO vacation_requests (employee_id, start_date, end_date, reason, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())"
        );
        $stmt->execute([$auth['employee_id'], $start_date, $end_date, $reason]);

        json_response([
            'message' => 'Demande de conge creee avec succes.',
            'request_id' => $pdo->lastInsertId(),
        ], 201);
        exit;
    }

    // === REVIEW: Valider ou rejeter une demande (admin seulement) ===
    if ($action === 'review') {
        $canReview = $isAdminLike || $isManager;
        if (!$canReview) {
            json_response(['error' => 'Seuls les admins, RH ou responsables peuvent valider les demandes.'], 403);
            exit;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = (int) ($payload['request_id'] ?? 0);
        $new_status = $payload['status'] ?? null; // 'approved' ou 'rejected'
        $admin_comment = trim($payload['comment'] ?? '');

        if (!$request_id) {
            json_response(['error' => 'ID de demande requis.'], 400);
            exit;
        }

        if (!in_array($new_status, ['approved', 'rejected'])) {
            json_response(['error' => 'Status invalide (approved ou rejected).'], 400);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, employee_id FROM vacation_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        if (!$request) {
            json_response(['error' => 'Demande introuvable.'], 404);
            exit;
        }

        if ($isManager && !jit_can_manage_employee($pdo, $authEmployeeId, (int) ($request['employee_id'] ?? 0))) {
            json_response(['error' => 'Acces refuse pour cette demande.'], 403);
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE vacation_requests SET status = ?, admin_comment = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$new_status, $admin_comment, $request_id]);

        json_response([
            'message' => 'Demande mise a jour.',
            'status' => $new_status,
        ]);
        exit;
    }

    json_response(['error' => 'Action inconnue.'], 400);

} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
