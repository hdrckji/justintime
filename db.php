<?php
require_once __DIR__ . '/config.php';

function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function format_iso_timestamp(?string $timestamp): string
{
    $raw = trim((string) $timestamp);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/([+-]\d{2}:\d{2}|Z)$/', $raw) === 1) {
        $dt = new DateTimeImmutable($raw);
        return $dt->format(DATE_ATOM);
    }

    $timezone = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : date_default_timezone_get());
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $timezone);
    if ($dt instanceof DateTimeImmutable) {
        return $dt->format(DATE_ATOM);
    }

    return str_replace(' ', 'T', $raw);
}

function get_last_event_type(PDO $pdo, int $employee_id): ?string
{
    $stmt = $pdo->prepare(
        'SELECT event_type
         FROM attendance_events
         WHERE employee_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch();
    return $row ? $row['event_type'] : null;
}

function infer_next_event(PDO $pdo, int $employee_id): string
{
    $last = get_last_event_type($pdo, $employee_id);
    return $last === 'in' ? 'out' : 'in';
}

function insert_event(PDO $pdo, int $employee_id, string $event_type, string $source): array
{
    $ts   = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO attendance_events (employee_id, event_type, source, timestamp)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$employee_id, $event_type, $source, $ts]);
    $id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "SELECT a.id, a.timestamp, a.event_type, a.source,
                TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))) AS name,
                e.badge_id
         FROM attendance_events a
         JOIN employees e ON e.id = a.employee_id
         WHERE a.id = ?"
    );
    $stmt->execute([$id]);
    $row              = $stmt->fetch();
    $row['timestamp'] = format_iso_timestamp((string) ($row['timestamp'] ?? ''));
    return $row;
}

function is_employee_absent(PDO $pdo, int $employee_id, string $date_iso): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) as cnt FROM absences 
         WHERE employee_id = ? AND start_date <= ? AND end_date >= ?'
    );
    $stmt->execute([$employee_id, $date_iso, $date_iso]);
    return (int) $stmt->fetch()['cnt'] > 0;
}

function haversine_distance(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $R    = 6371000; // rayon terrestre en metres
    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $dphi = deg2rad($lat2 - $lat1);
    $dlam = deg2rad($lon2 - $lon1);
    $a    = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlam / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function json_response(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
