<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/payroll_helpers.php';

require_login('admin');

$auth = get_auth_user();

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_pdo();
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $absences = $pdo->query(
            'SELECT a.id, a.employee_id, a.type, a.payroll_code, a.paid_ratio, a.credit_scheduled_hours, a.start_date, a.end_date, a.reason,
                    CONCAT(e.first_name, " ", e.last_name) as employee_name
             FROM absences a
             JOIN employees e ON e.id = a.employee_id
             WHERE a.end_date >= CURDATE()
             ORDER BY a.start_date DESC'
        )->fetchAll();

        foreach ($absences as &$absence) {
            $profile = jit_resolve_absence_profile($absence);
            $absence['type_label'] = $profile['label'];
            $absence['export_code'] = $profile['export_code'];
            $absence['is_paid'] = (float) $profile['paid_ratio'] > 0;
        }
        unset($absence);

        json_response(['absences' => $absences]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($action === 'delete') {
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                json_response(['error' => 'Identifiant d\'absence invalide.'], 400);
                exit;
            }

            $existingStmt = $pdo->prepare('SELECT id, employee_id, type, payroll_code, start_date, end_date, reason FROM absences WHERE id = ?');
            $existingStmt->execute([$id]);
            $existing = $existingStmt->fetch();
            if (!$existing) {
                json_response(['error' => 'Absence introuvable.'], 404);
                exit;
            }

            assert_period_range_open($pdo, (string) $existing['start_date'], (string) $existing['end_date'], 'Cette periode est cloturee: suppression d\'absence impossible.');

            $stmt = $pdo->prepare('DELETE FROM absences WHERE id = ?');
            $stmt->execute([$id]);

            log_audit_event($pdo, $auth, 'delete_absence', 'absence', (string) $id, 'Suppression d\'absence', [
                'before' => $existing,
            ]);

            json_response(['message' => 'Absence supprimee.']);
            exit;
        }

        // Insert absence
        $emp_id    = (int) ($payload['employee_id'] ?? 0);
        $payrollCode = trim((string) ($payload['payroll_code'] ?? ''));
        $type      = trim((string) ($payload['type'] ?? ''));
        $start     = trim($payload['start_date'] ?? '');
        $end       = trim($payload['end_date'] ?? '');
        $reason    = trim($payload['reason'] ?? '');

        if (!$emp_id || !$start || !$end) {
            json_response(['error' => 'Champs obligatoires manquants.'], 400);
            exit;
        }

        $profile = jit_absence_profile_from_code($payrollCode !== '' ? $payrollCode : jit_default_payroll_code_for_type($type), $type ?: 'other');
        $type = (string) $profile['type'];

        $startDate = DateTime::createFromFormat('Y-m-d', $start);
        $endDate = DateTime::createFromFormat('Y-m-d', $end);
        if (!$startDate || !$endDate || $startDate->format('Y-m-d') !== $start || $endDate->format('Y-m-d') !== $end || $startDate > $endDate) {
            json_response(['error' => 'Periode d\'absence invalide.'], 400);
            exit;
        }

        assert_period_range_open($pdo, $start, $end, 'Cette periode est cloturee: ajout d\'absence impossible.');

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE id = ? AND active = 1');
        $stmt->execute([$emp_id]);
        if ((int) $stmt->fetchColumn() === 0) {
            json_response(['error' => 'Collaborateur introuvable ou inactif.'], 404);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO absences (employee_id, type, payroll_code, paid_ratio, credit_scheduled_hours, start_date, end_date, reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $emp_id,
            $type,
            $profile['code'],
            $profile['paid_ratio'],
            $profile['credit_scheduled_hours'] ? 1 : 0,
            $start,
            $end,
            $reason,
        ]);
        $newId = (int) $pdo->lastInsertId();

        log_audit_event($pdo, $auth, 'add_absence', 'absence', (string) $newId, 'Ajout d\'absence', [
            'after' => [
                'id' => $newId,
                'employee_id' => $emp_id,
                'type' => $type,
                'payroll_code' => $profile['code'],
                'start_date' => $start,
                'end_date' => $end,
                'reason' => $reason,
            ],
        ]);

        json_response(['message' => 'Absence enregistree.', 'id' => $newId]);
    }
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
