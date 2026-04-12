<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login('admin');
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_pdo();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        json_response([
            'settings' => get_device_settings($pdo),
        ]);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_response(['error' => 'Methode non autorisee.'], 405);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $current = get_device_settings($pdo);

    $siteName = trim((string) ($payload['site_name'] ?? $current['site_name']));
    $displayMessage = trim((string) ($payload['display_message'] ?? $current['display_message']));
    $successMessage = trim((string) ($payload['success_message'] ?? $current['success_message']));

    $ledEnabled = array_key_exists('led_enabled', $payload)
        ? filter_var($payload['led_enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        : (bool) $current['led_enabled'];
    $buzzerEnabled = array_key_exists('buzzer_enabled', $payload)
        ? filter_var($payload['buzzer_enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        : (bool) $current['buzzer_enabled'];

    if ($ledEnabled === null || $buzzerEnabled === null) {
        json_response(['error' => 'Les options LED/Buzzer doivent etre booleennes.'], 400);
        exit;
    }

    if ($siteName === '' || strlen($siteName) > 40) {
        json_response(['error' => 'Nom du site obligatoire (max 40 caracteres).'], 400);
        exit;
    }

    if ($displayMessage === '' || strlen($displayMessage) > 60) {
        json_response(['error' => 'Message d\'accueil obligatoire (max 60 caracteres).'], 400);
        exit;
    }

    if ($successMessage === '' || strlen($successMessage) > 60) {
        json_response(['error' => 'Message succes obligatoire (max 60 caracteres).'], 400);
        exit;
    }

    save_device_settings($pdo, [
        'site_name' => $siteName,
        'display_message' => $displayMessage,
        'success_message' => $successMessage,
        'led_enabled' => $ledEnabled,
        'buzzer_enabled' => $buzzerEnabled,
    ]);

    json_response([
        'message' => 'Configuration boitier enregistree.',
        'settings' => get_device_settings($pdo),
    ]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
