<?php
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = trim((string) ($_GET['action'] ?? 'config'));
    $device_id = trim((string) ($_GET['device_id'] ?? 'ESP32-RFID'));

    if (!in_array($action, ['config', 'status', 'ping'], true)) {
        json_response(['error' => 'Action inconnue.'], 400);
        exit;
    }

    json_response([
        'status' => 'ok',
        'site_name' => 'JustInTime',
        'device_id' => $device_id !== '' ? $device_id : 'ESP32-RFID',
        'server_time' => date(DATE_ATOM),
        'timezone' => date_default_timezone_get(),
        'rfid_endpoint' => '/api/attendance/rfid',
        'cooldown_ms' => 2000,
        'clock_refresh_ms' => 1000,
        'config_refresh_ms' => 300000,
        'display_message' => 'Passe un badge',
        'message' => 'Configuration RFID OK.',
    ]);
    exit;
}

if ($method !== 'POST') {
    json_response(['error' => 'Methode non autorisee.'], 405);
    exit;
}

$raw_payload = file_get_contents('php://input');
$payload = json_decode($raw_payload ?: '{}', true);
if (!is_array($payload)) {
    $payload = [];
}

$badge_id = trim((string) ($payload['badge_id'] ?? $payload['uid'] ?? $_POST['badge_id'] ?? ''));
$device_id = trim((string) ($payload['device_id'] ?? 'ESP32-RFID'));
$device_label = trim((string) ($payload['device_label'] ?? 'Lecteur RFID'));
$ip_address = trim((string) ($payload['ip'] ?? ''));
$rssi = isset($payload['rssi']) ? (int) $payload['rssi'] : null;
$firmware = trim((string) ($payload['firmware'] ?? ''));

if ($badge_id === '') {
    json_response(['error' => 'Badge RFID manquant.'], 400);
    exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        "SELECT id,
                TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) AS name,
                badge_id
         FROM employees
         WHERE badge_id = ? AND active = 1"
    );
    $stmt->execute([$badge_id]);
    $employee = $stmt->fetch();

    if (!$employee) {
        json_response([
            'error' => 'Badge inconnu.',
            'device' => [
                'id' => $device_id,
                'label' => $device_label,
                'ip' => $ip_address,
            ],
        ], 404);
        exit;
    }

    $next_type = infer_next_event($pdo, (int) $employee['id']);
    $event     = insert_event($pdo, (int) $employee['id'], $next_type, 'rfid');
    $action    = $next_type === 'in' ? 'entree' : 'sortie';

    json_response([
        'message' => "{$employee['name']} enregistre: $action.",
        'name' => $event['name'] ?? $employee['name'],
        'badge_id' => $event['badge_id'] ?? $badge_id,
        'event_type' => $event['event_type'] ?? $next_type,
        'timestamp' => $event['timestamp'] ?? date(DATE_ATOM),
        'event'   => $event,
        'server_time' => date(DATE_ATOM),
        'device' => [
            'id' => $device_id,
            'label' => $device_label,
            'ip' => $ip_address,
            'rssi' => $rssi,
            'firmware' => $firmware,
        ],
    ]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
