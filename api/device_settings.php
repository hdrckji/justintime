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

    $cooldownMs = isset($payload['cooldown_ms']) ? (int) $payload['cooldown_ms'] : (int) $current['cooldown_ms'];
    $clockRefreshMs = isset($payload['clock_refresh_ms']) ? (int) $payload['clock_refresh_ms'] : (int) $current['clock_refresh_ms'];
    $configRefreshMs = isset($payload['config_refresh_ms']) ? (int) $payload['config_refresh_ms'] : (int) $current['config_refresh_ms'];

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

    if ($cooldownMs < 500 || $cooldownMs > 15000) {
        json_response(['error' => 'Cooldown invalide (500 a 15000 ms).'], 400);
        exit;
    }

    if ($clockRefreshMs < 250 || $clockRefreshMs > 10000) {
        json_response(['error' => 'Rafraichissement horloge invalide (250 a 10000 ms).'], 400);
        exit;
    }

    if ($configRefreshMs < 10000 || $configRefreshMs > 3600000) {
        json_response(['error' => 'Rafraichissement config invalide (10000 a 3600000 ms).'], 400);
        exit;
    }

    save_device_settings($pdo, [
        'site_name' => $siteName,
        'display_message' => $displayMessage,
        'success_message' => $successMessage,
        'cooldown_ms' => $cooldownMs,
        'clock_refresh_ms' => $clockRefreshMs,
        'config_refresh_ms' => $configRefreshMs,
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
