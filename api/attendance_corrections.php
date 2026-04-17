<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login();
$user = get_auth_user();
if (($user['role'] ?? '') === 'employee') {
    json_response(['error' => 'Acces reserve a l administration.'], 403);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function fetch_employee_names(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS name
         FROM employees
         WHERE active = 1
         ORDER BY last_name, first_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $employees = [];
    foreach ($rows as $row) {
        $employees[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => trim((string) ($row['name'] ?? '')),
        ];
    }

    return $employees;
}

function fetch_events_for_day(PDO $pdo, int $employeeId, string $day): array
{
    $stmt = $pdo->prepare(
        'SELECT id, event_type, source, timestamp
         FROM attendance_events
         WHERE employee_id = ? AND DATE(timestamp) = ?
         ORDER BY timestamp ASC'
    );
    $stmt->execute([$employeeId, $day]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $pdo = get_pdo();

    // ------------------------------------------------------------------ GET --
    if ($method === 'GET') {
        $action = trim((string) ($_GET['action'] ?? 'anomalies'));
        $days   = max(1, min(365, (int) ($_GET['days'] ?? 30)));

        if ($action === 'employees') {
            json_response(['employees' => fetch_employee_names($pdo)]);
            exit;
        }

        if ($action === 'search_day') {
            $empId = (int) ($_GET['employee_id'] ?? 0);
            $day = trim((string) ($_GET['day'] ?? ''));

            if ($empId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                json_response(['error' => 'Parametres invalides.'], 400);
                exit;
            }

            $employeeStmt = $pdo->prepare(
                "SELECT id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS name
                 FROM employees
                 WHERE id = ? AND active = 1"
            );
            $employeeStmt->execute([$empId]);
            $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$employee) {
                json_response(['error' => 'Employe introuvable.'], 404);
                exit;
            }

            $events = fetch_events_for_day($pdo, $empId, $day);
            $cntIn = 0;
            $cntOut = 0;
            foreach ($events as $event) {
                if (($event['event_type'] ?? '') === 'in') {
                    $cntIn++;
                }
                if (($event['event_type'] ?? '') === 'out') {
                    $cntOut++;
                }
            }

            json_response([
                'result' => [
                    'employee_id' => $empId,
                    'employee_name' => trim((string) ($employee['name'] ?? '')),
                    'day' => $day,
                    'day_of_week' => (int) date('w', strtotime($day) ?: time()),
                    'cnt_in' => $cntIn,
                    'cnt_out' => $cntOut,
                    'events' => $events,
                ],
            ]);
            exit;
        }

        // -- day_events: detail of a single employee+date ---------------------
        if ($action === 'day_events') {
            $empId = (int) ($_GET['employee_id'] ?? 0);
            $day   = $_GET['day'] ?? '';
            if ($empId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                json_response(['error' => 'Parametres invalides.'], 400);
                exit;
            }
            json_response(['events' => fetch_events_for_day($pdo, $empId, $day)]);
            exit;
        }

        // -- anomalies: main detection ----------------------------------------
        if ($action === 'anomalies') {

            // --- Type 1 : pointages impairs (cnt_in != cnt_out) ----------------
            $stmt = $pdo->prepare(
                "SELECT
                     ae.employee_id,
                     DATE(ae.timestamp)          AS day,
                     SUM(ae.event_type = 'in')   AS cnt_in,
                     SUM(ae.event_type = 'out')  AS cnt_out
                 FROM attendance_events ae
                 WHERE ae.timestamp >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                   AND DATE(ae.timestamp) < CURDATE()
                 GROUP BY ae.employee_id, DATE(ae.timestamp)
                 HAVING cnt_in != cnt_out
                 ORDER BY day DESC, ae.employee_id"
            );
            $stmt->execute([$days]);
            $unpairedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch individual events for each unpaired day
            $unpairedAnomalies = [];
            foreach ($unpairedRows as $row) {
                $stmt2 = $pdo->prepare(
                    'SELECT id, event_type, source, timestamp
                     FROM attendance_events
                     WHERE employee_id = ? AND DATE(timestamp) = ?
                     ORDER BY timestamp ASC'
                );
                $stmt2->execute([$row['employee_id'], $row['day']]);
                $unpairedAnomalies[] = [
                    'employee_id' => (int) $row['employee_id'],
                    'day'         => $row['day'],
                    'cnt_in'      => (int) $row['cnt_in'],
                    'cnt_out'     => (int) $row['cnt_out'],
                    'events'      => $stmt2->fetchAll(PDO::FETCH_ASSOC),
                ];
            }

            // --- Type 2 : pointages sur jours non planifies --------------------
            $scheduledCols = $pdo->query("SHOW COLUMNS FROM scheduled_hours")->fetchAll(PDO::FETCH_COLUMN);
            $hasWeekStart  = in_array('week_start', $scheduledCols, true);

            $sql = $hasWeekStart
                ? 'SELECT DISTINCT employee_id, day_of_week FROM scheduled_hours WHERE hours > 0 AND week_start IS NULL'
                : 'SELECT DISTINCT employee_id, day_of_week FROM scheduled_hours WHERE hours > 0';
            $schedRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            // employee_id -> set of scheduled day_of_week (0=Sun … 6=Sat, PHP convention)
            $scheduledDays = [];
            foreach ($schedRows as $row) {
                $scheduledDays[(int) $row['employee_id']][(int) $row['day_of_week']] = true;
            }

            // Distinct (employee_id, date) with events in period (excluding today)
            $stmt3 = $pdo->prepare(
                'SELECT DISTINCT employee_id, DATE(timestamp) AS day
                 FROM attendance_events
                 WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                   AND DATE(timestamp) < CURDATE()'
            );
            $stmt3->execute([$days]);
            $activeDays = $stmt3->fetchAll(PDO::FETCH_ASSOC);

            $unscheduledAnomalies = [];
            foreach ($activeDays as $row) {
                $empId = (int) $row['employee_id'];
                if (!isset($scheduledDays[$empId])) {
                    // No schedule defined – cannot determine anomaly
                    continue;
                }
                $dow = (int) date('w', strtotime($row['day'])); // 0=Sun, 6=Sat
                if (!isset($scheduledDays[$empId][$dow])) {
                    $stmt4 = $pdo->prepare(
                        'SELECT id, event_type, source, timestamp
                         FROM attendance_events
                         WHERE employee_id = ? AND DATE(timestamp) = ?
                         ORDER BY timestamp ASC'
                    );
                    $stmt4->execute([$empId, $row['day']]);
                    $unscheduledAnomalies[] = [
                        'employee_id'  => $empId,
                        'day'          => $row['day'],
                        'day_of_week'  => $dow,
                        'events'       => $stmt4->fetchAll(PDO::FETCH_ASSOC),
                    ];
                }
            }

            // Sort unscheduled by date desc
            usort($unscheduledAnomalies, static fn ($a, $b) => strcmp($b['day'], $a['day']));

            // Enrich with employee names
            $empRows = fetch_employee_names($pdo);
            $empMap = [];
            foreach ($empRows as $r) {
                $empMap[(int) $r['id']] = trim((string) $r['name']);
            }

            foreach ($unpairedAnomalies as &$a) {
                $a['employee_name'] = $empMap[$a['employee_id']] ?? 'Employe inconnu';
            }
            unset($a);
            foreach ($unscheduledAnomalies as &$a) {
                $a['employee_name'] = $empMap[$a['employee_id']] ?? 'Employe inconnu';
            }
            unset($a);

            json_response([
                'unpaired'     => $unpairedAnomalies,
                'unscheduled'  => $unscheduledAnomalies,
                'days'         => $days,
            ]);
            exit;
        }

        json_response(['error' => 'Action inconnue.'], 400);
        exit;
    }

    // ----------------------------------------------------------------- POST --
    if ($method === 'POST') {
        $payload    = json_decode(file_get_contents('php://input'), true) ?? [];
        $action     = trim((string) ($payload['action'] ?? ''));

        // -- delete ------------------------------------------------------------
        if ($action === 'delete') {
            $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                json_response(['error' => 'ID invalide.'], 400);
                exit;
            }

            $existingStmt = $pdo->prepare('SELECT id, employee_id, event_type, source, timestamp FROM attendance_events WHERE id = ?');
            $existingStmt->execute([$id]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                json_response(['error' => 'Pointage introuvable.'], 404);
                exit;
            }

            assert_period_open($pdo, (string) $existing['timestamp'], 'Cette periode est cloturee: suppression impossible.');

            $stmt = $pdo->prepare('DELETE FROM attendance_events WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                json_response(['error' => 'Pointage introuvable.'], 404);
                exit;
            }

            log_audit_event($pdo, $user, 'delete_attendance_event', 'attendance_event', (string) $id, 'Suppression de pointage', [
                'before' => $existing,
            ]);

            json_response(['success' => true]);
            exit;
        }

        // -- edit --------------------------------------------------------------
        if ($action === 'edit') {
            $id         = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
            $eventType  = strtolower(trim((string) ($payload['event_type'] ?? '')));
            $timestamp  = trim((string) ($payload['timestamp'] ?? ''));

            if (!$id || !in_array($eventType, ['in', 'out'], true)) {
                json_response(['error' => 'Parametres invalides.'], 400);
                exit;
            }

            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp)
               ?: DateTime::createFromFormat('Y-m-d\TH:i', $timestamp)
               ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $timestamp);
            if (!$dt) {
                json_response(['error' => 'Format de timestamp invalide.'], 400);
                exit;
            }
            $cleanTs = $dt->format('Y-m-d H:i:s');

            $beforeStmt = $pdo->prepare('SELECT id, employee_id, event_type, source, timestamp FROM attendance_events WHERE id = ?');
            $beforeStmt->execute([$id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) {
                json_response(['error' => 'Pointage introuvable.'], 404);
                exit;
            }

            assert_period_open($pdo, (string) $before['timestamp'], 'Cette periode est cloturee: modification impossible.');
            assert_period_open($pdo, $cleanTs, 'Cette periode est cloturee: modification impossible.');

            $stmt = $pdo->prepare('UPDATE attendance_events SET event_type = ?, timestamp = ? WHERE id = ?');
            $stmt->execute([$eventType, $cleanTs, $id]);

            // rowCount can be 0 if values are identical – check existence
            $chk = $pdo->prepare('SELECT id FROM attendance_events WHERE id = ?');
            $chk->execute([$id]);
            if (!$chk->fetch()) {
                json_response(['error' => 'Pointage introuvable.'], 404);
                exit;
            }

            log_audit_event($pdo, $user, 'edit_attendance_event', 'attendance_event', (string) $id, 'Modification de pointage', [
                'before' => $before,
                'after' => [
                    'event_type' => $eventType,
                    'timestamp' => $cleanTs,
                ],
            ]);

            json_response(['success' => true, 'timestamp' => $cleanTs]);
            exit;
        }

        // -- add ---------------------------------------------------------------
        if ($action === 'add') {
            $empId     = filter_var($payload['employee_id'] ?? null, FILTER_VALIDATE_INT);
            $eventType = strtolower(trim((string) ($payload['event_type'] ?? '')));
            $timestamp = trim((string) ($payload['timestamp'] ?? ''));

            if (!$empId || !in_array($eventType, ['in', 'out'], true)) {
                json_response(['error' => 'Parametres invalides.'], 400);
                exit;
            }

            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp)
               ?: DateTime::createFromFormat('Y-m-d\TH:i', $timestamp)
               ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $timestamp);
            if (!$dt) {
                json_response(['error' => 'Format de timestamp invalide.'], 400);
                exit;
            }
            $cleanTs = $dt->format('Y-m-d H:i:s');
            assert_period_open($pdo, $cleanTs, 'Cette periode est cloturee: ajout impossible.');

            $chk = $pdo->prepare('SELECT id FROM employees WHERE id = ? AND active = 1');
            $chk->execute([$empId]);
            if (!$chk->fetch()) {
                json_response(['error' => 'Employe introuvable.'], 404);
                exit;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO attendance_events (employee_id, event_type, source, timestamp)
                 VALUES (?, ?, 'manual', ?)"
            );
            $stmt->execute([$empId, $eventType, $cleanTs]);
            $newId = (int) $pdo->lastInsertId();

            log_audit_event($pdo, $user, 'add_attendance_event', 'attendance_event', (string) $newId, 'Ajout manuel de pointage', [
                'after' => [
                    'id' => $newId,
                    'employee_id' => $empId,
                    'event_type' => $eventType,
                    'source' => 'manual',
                    'timestamp' => $cleanTs,
                ],
            ]);

            json_response(['success' => true, 'id' => $newId, 'timestamp' => $cleanTs]);
            exit;
        }

        json_response(['error' => 'Action inconnue.'], 400);
        exit;
    }

    json_response(['error' => 'Methode non autorisee.'], 405);

} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
