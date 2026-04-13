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

    ensure_department_schema($pdo);
    ensure_device_settings_schema($pdo);
    ensure_telework_schema($pdo);
    ensure_payroll_schema($pdo);

    return $pdo;
}

function jit_table_exists(PDO $pdo, string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$safe]);
    return (int) $stmt->fetchColumn() > 0;
}

function jit_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!jit_table_exists($pdo, $table)) {
        return false;
    }

    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$safe}`");
    foreach ($stmt->fetchAll() as $row) {
        if (($row['Field'] ?? '') === $column) {
            return true;
        }
    }

    return false;
}

function jit_index_exists(PDO $pdo, string $table, string $index): bool
{
    if (!jit_table_exists($pdo, $table)) {
        return false;
    }

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', $index);
    $rows = $pdo->query("SHOW INDEX FROM `{$safeTable}`")->fetchAll();
    foreach ($rows as $row) {
        if (($row['Key_name'] ?? '') === $safeIndex) {
            return true;
        }
    }

    return false;
}

function jit_foreign_key_exists(PDO $pdo, string $table, string $constraint): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeConstraint = preg_replace('/[^a-zA-Z0-9_]/', '', $constraint);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.table_constraints
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND constraint_name = ?
           AND constraint_type = ?'
    );
    $stmt->execute([$safeTable, $safeConstraint, 'FOREIGN KEY']);
    return (int) $stmt->fetchColumn() > 0;
}

function ensure_department_schema(PDO $pdo): void
{
    if (!jit_table_exists($pdo, 'employees')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            manager_employee_id INT NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!jit_column_exists($pdo, 'departments', 'manager_employee_id')) {
        $pdo->exec("ALTER TABLE departments ADD COLUMN manager_employee_id INT NULL DEFAULT NULL AFTER name");
    }

    if (!jit_index_exists($pdo, 'departments', 'idx_departments_manager')) {
        $pdo->exec("ALTER TABLE departments ADD INDEX idx_departments_manager (manager_employee_id)");
    }

    if (!jit_foreign_key_exists($pdo, 'departments', 'fk_departments_manager_employee')) {
        try {
            $pdo->exec(
                "ALTER TABLE departments
                 ADD CONSTRAINT fk_departments_manager_employee
                 FOREIGN KEY (manager_employee_id) REFERENCES employees(id)
                 ON DELETE SET NULL"
            );
        } catch (Throwable $e) {
            // Ignorer si la contrainte existe deja sous un autre nom.
        }
    }

    if (!jit_column_exists($pdo, 'employees', 'department_id')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN department_id INT NULL DEFAULT NULL AFTER active");
    }

    if (!jit_index_exists($pdo, 'employees', 'idx_employees_department')) {
        $pdo->exec("ALTER TABLE employees ADD INDEX idx_employees_department (department_id)");
    }

    if (!jit_foreign_key_exists($pdo, 'employees', 'fk_employees_department')) {
        try {
            $pdo->exec(
                "ALTER TABLE employees
                 ADD CONSTRAINT fk_employees_department
                 FOREIGN KEY (department_id) REFERENCES departments(id)
                 ON DELETE SET NULL"
            );
        } catch (Throwable $e) {
            // Ignorer si la contrainte existe deja sous un autre nom.
        }
    }
}

function jit_get_managed_department_ids(PDO $pdo, int $managerEmployeeId): array
{
    if ($managerEmployeeId <= 0 || !jit_table_exists($pdo, 'departments') || !jit_column_exists($pdo, 'departments', 'manager_employee_id')) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT id FROM departments WHERE manager_employee_id = ? ORDER BY name ASC');
    $stmt->execute([$managerEmployeeId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function jit_can_manage_employee(PDO $pdo, int $managerEmployeeId, int $targetEmployeeId): bool
{
    if ($managerEmployeeId <= 0 || $targetEmployeeId <= 0 || $managerEmployeeId === $targetEmployeeId) {
        return false;
    }

    if (!jit_column_exists($pdo, 'employees', 'department_id')) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM employees e
         JOIN departments d ON d.id = e.department_id
         WHERE e.id = ? AND d.manager_employee_id = ?'
    );
    $stmt->execute([$targetEmployeeId, $managerEmployeeId]);
    return (int) $stmt->fetchColumn() > 0;
}

function ensure_device_settings_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS device_settings (
            config_key   VARCHAR(80) PRIMARY KEY,
            config_value TEXT NOT NULL,
            updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ensure_telework_schema(PDO $pdo): void
{
    if (!jit_table_exists($pdo, 'employees')) {
        return;
    }

    if (!jit_column_exists($pdo, 'employees', 'telework_enabled')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN telework_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER geo_radius");
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS employee_allowed_locations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            address VARCHAR(255) NOT NULL,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_allowed_locations_employee (employee_id),
            CONSTRAINT fk_allowed_locations_employee
                FOREIGN KEY (employee_id) REFERENCES employees(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ensure_payroll_schema(PDO $pdo): void
{
    if (jit_table_exists($pdo, 'employees')) {
        if (!jit_column_exists($pdo, 'employees', 'vacation_adjustment_days')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN vacation_adjustment_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER vacation_days");
        }

        if (!jit_column_exists($pdo, 'employees', 'overtime_adjustment_hours')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN overtime_adjustment_hours DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER vacation_adjustment_days");
        }
    }

    if (jit_table_exists($pdo, 'absences')) {
        if (!jit_column_exists($pdo, 'absences', 'payroll_code')) {
            $pdo->exec("ALTER TABLE absences ADD COLUMN payroll_code VARCHAR(40) NOT NULL DEFAULT '' AFTER type");
        }

        if (!jit_column_exists($pdo, 'absences', 'paid_ratio')) {
            $pdo->exec("ALTER TABLE absences ADD COLUMN paid_ratio DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER payroll_code");
        }

        if (!jit_column_exists($pdo, 'absences', 'credit_scheduled_hours')) {
            $pdo->exec("ALTER TABLE absences ADD COLUMN credit_scheduled_hours TINYINT(1) NOT NULL DEFAULT 1 AFTER paid_ratio");
        }

        if (!jit_index_exists($pdo, 'absences', 'idx_absences_payroll_code')) {
            $pdo->exec("ALTER TABLE absences ADD INDEX idx_absences_payroll_code (payroll_code)");
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS payroll_closures (
            id INT PRIMARY KEY AUTO_INCREMENT,
            period_key CHAR(7) NOT NULL UNIQUE,
            closed_by VARCHAR(100) NOT NULL DEFAULT 'system',
            closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            actor_username VARCHAR(100) NOT NULL DEFAULT 'system',
            actor_role VARCHAR(40) NOT NULL DEFAULT 'system',
            action_type VARCHAR(80) NOT NULL,
            target_type VARCHAR(80) NOT NULL,
            target_id VARCHAR(80) NOT NULL DEFAULT '',
            summary VARCHAR(255) NOT NULL DEFAULT '',
            details_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_created_at (created_at),
            INDEX idx_audit_target (target_type, target_id),
            INDEX idx_audit_action (action_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function get_device_settings(PDO $pdo): array
{
    $defaults = [
        'site_name' => 'JustInTime',
        'display_message' => 'Passe un badge',
        'success_message' => 'Pointage enregistre',
        'cooldown_ms' => 2000,
        'clock_refresh_ms' => 1000,
        'config_refresh_ms' => 300000,
        'led_enabled' => true,
        'buzzer_enabled' => true,
    ];

    $rows = $pdo->query('SELECT config_key, config_value FROM device_settings')->fetchAll();
    if (!$rows) {
        return $defaults;
    }

    $settings = $defaults;

    foreach ($rows as $row) {
        $key = (string) ($row['config_key'] ?? '');
        $value = (string) ($row['config_value'] ?? '');

        if (!array_key_exists($key, $settings)) {
            continue;
        }

        switch ($key) {
            case 'cooldown_ms':
            case 'clock_refresh_ms':
            case 'config_refresh_ms':
                $settings[$key] = (int) $value;
                break;
            case 'led_enabled':
            case 'buzzer_enabled':
                $settings[$key] = in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
                break;
            default:
                $settings[$key] = $value;
                break;
        }
    }

    // Garde-fous de plage pour eviter une config invalide cote firmware.
    $settings['cooldown_ms'] = max(500, min(15000, (int) $settings['cooldown_ms']));
    $settings['clock_refresh_ms'] = max(250, min(10000, (int) $settings['clock_refresh_ms']));
    $settings['config_refresh_ms'] = max(10000, (int) $settings['config_refresh_ms']);

    return $settings;
}

function save_device_settings(PDO $pdo, array $settings): void
{
    if (!$settings) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO device_settings (config_key, config_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP'
    );

    foreach ($settings as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_int($value) || is_float($value)) {
            $value = (string) $value;
        } else {
            $value = trim((string) $value);
        }

        $stmt->execute([(string) $key, $value]);
    }
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

const ATTENDANCE_DUPLICATE_WINDOW_SECONDS = 60;

function get_last_event(PDO $pdo, int $employee_id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT a.id, a.timestamp, a.event_type, a.source,
                TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))) AS name,
                e.badge_id
         FROM attendance_events a
         JOIN employees e ON e.id = a.employee_id
         WHERE a.employee_id = ?
         ORDER BY a.id DESC
         LIMIT 1"
    );
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function get_last_event_type(PDO $pdo, int $employee_id): ?string
{
    $last = get_last_event($pdo, $employee_id);
    return $last ? $last['event_type'] : null;
}

function get_seconds_since_timestamp(?string $timestamp): ?int
{
    $raw = trim((string) $timestamp);
    if ($raw === '') {
        return null;
    }

    try {
        $timezone = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : date_default_timezone_get());
        $eventTime = preg_match('/([+-]\d{2}:\d{2}|Z)$/', $raw) === 1
            ? new DateTimeImmutable($raw)
            : new DateTimeImmutable($raw, $timezone);
        $now = new DateTimeImmutable('now', $timezone);

        return $now->getTimestamp() - $eventTime->getTimestamp();
    } catch (Throwable $e) {
        return null;
    }
}

function infer_next_event(PDO $pdo, int $employee_id): string
{
    $last = get_last_event_type($pdo, $employee_id);
    return $last === 'in' ? 'out' : 'in';
}

function insert_event(PDO $pdo, int $employee_id, string $event_type, string $source): array
{
    assert_period_open($pdo, date('Y-m-d'), 'Cette periode est cloturee: aucun nouveau pointage ne peut etre ajoute.');

    $lastEvent = get_last_event($pdo, $employee_id);
    $secondsSinceLast = $lastEvent ? get_seconds_since_timestamp((string) ($lastEvent['timestamp'] ?? '')) : null;

    if ($lastEvent && $secondsSinceLast !== null && $secondsSinceLast >= 0 && $secondsSinceLast < ATTENDANCE_DUPLICATE_WINDOW_SECONDS) {
        $lastEvent['timestamp'] = format_iso_timestamp((string) ($lastEvent['timestamp'] ?? ''));
        $lastEvent['duplicate'] = true;
        $lastEvent['duplicate_window_seconds'] = ATTENDANCE_DUPLICATE_WINDOW_SECONDS;
        $lastEvent['seconds_since_last'] = $secondsSinceLast;
        return $lastEvent;
    }

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
    $row['duplicate'] = false;
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

function period_key_for_date(string $dateIso): string
{
    $raw = trim($dateIso);
    if ($raw === '') {
        return date('Y-m');
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return date('Y-m');
    }

    return date('Y-m', $ts);
}

function is_period_closed(PDO $pdo, string $dateIso): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM payroll_closures WHERE period_key = ?');
    $stmt->execute([period_key_for_date($dateIso)]);
    return (int) $stmt->fetchColumn() > 0;
}

function assert_period_open(PDO $pdo, string $dateIso, string $message = 'Cette periode est cloturee.'): void
{
    if (is_period_closed($pdo, $dateIso)) {
        throw new RuntimeException($message);
    }
}

function assert_period_range_open(PDO $pdo, string $startDateIso, string $endDateIso, string $message = 'Cette periode est cloturee.'): void
{
    $startTs = strtotime($startDateIso);
    $endTs = strtotime($endDateIso);
    if ($startTs === false || $endTs === false) {
        throw new RuntimeException('Periode invalide.');
    }

    if ($endTs < $startTs) {
        [$startTs, $endTs] = [$endTs, $startTs];
    }

    $cursor = strtotime(date('Y-m-01', $startTs));
    $limit = strtotime(date('Y-m-01', $endTs));
    while ($cursor !== false && $cursor <= $limit) {
        assert_period_open($pdo, date('Y-m-d', $cursor), $message);
        $cursor = strtotime('+1 month', $cursor);
    }
}

function log_audit_event(PDO $pdo, array $actor, string $actionType, string $targetType, string $targetId, string $summary, array $details = []): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (actor_username, actor_role, action_type, target_type, target_id, summary, details_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        trim((string) ($actor['username'] ?? 'system')) ?: 'system',
        trim((string) ($actor['role'] ?? 'system')) ?: 'system',
        $actionType,
        $targetType,
        $targetId,
        $summary,
        $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function json_response(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
