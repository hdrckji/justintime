<?php
/**
 * api/me_pointage.php — Pointage manuel de l'employé connecté (avec vérification géo)
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login();
$auth = get_auth_user();

if (!$auth['employee_id']) {
    json_response(['error' => 'Compte non lie a un employe.'], 403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Methode non autorisee.'], 405);
    exit;
}

$emp_id  = (int) $auth['employee_id'];
$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$lat     = isset($payload['latitude'])  && $payload['latitude']  !== null ? (float) $payload['latitude']  : null;
$lng     = isset($payload['longitude']) && $payload['longitude'] !== null ? (float) $payload['longitude'] : null;

try {
    $pdo = get_pdo();

    if ($lat === null || $lng === null) {
        json_response([
            'error'        => 'Localisation requise: pointage impossible sans autorisation GPS.',
            'geo_required' => true,
        ], 403);
        exit;
    }

    // Récupérer les coordonnées enregistrées pour cet employé
    $stmt = $pdo->prepare('SELECT latitude, longitude, geo_radius FROM employees WHERE id = ?');
    $stmt->execute([$emp_id]);
    $emp = $stmt->fetch();

    if (!$emp) {
        json_response(['error' => 'Employe introuvable.'], 404);
        exit;
    }

    if ($emp['latitude'] === null || $emp['longitude'] === null) {
        json_response(['error' => 'Aucune adresse geolocalisee n\'est configuree pour votre compte.'], 403);
        exit;
    }

    $distance = haversine_distance(
        (float) $emp['latitude'],
        (float) $emp['longitude'],
        $lat,
        $lng
    );
    $radius = (int) ($emp['geo_radius'] ?? 300);

    if ($distance > $radius) {
        json_response([
            'error'    => sprintf(
                'Vous etes trop loin de votre lieu de travail (%.0f m, rayon autorise : %d m).',
                $distance,
                $radius
            ),
            'distance' => (int) round($distance),
            'radius'   => $radius,
        ], 403);
        exit;
    }

    $event_type = infer_next_event($pdo, $emp_id);
    $event      = insert_event($pdo, $emp_id, $event_type, 'manual');

    json_response([
        'event'      => $event,
        'event_type' => $event_type,
    ]);

} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
