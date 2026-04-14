<?php
/**
 * setup.php — Initialisation de la base MySQL
 *
 * A executer UNE SEULE FOIS depuis le navigateur:
 *   https://votredomaine.com/setup.php
 *
 * IMPORTANT: Supprimer ce fichier apres initialisation.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$setupAllowed = strtolower((string) env_or_default('JIT_ALLOW_SETUP', '0'));
if (!$isLocal && !in_array($setupAllowed, ['1', 'true', 'yes', 'on'], true)) {
    http_response_code(403);
    exit('setup.php est desactive en production.');
}

$output = [];

try {
    $pdo = get_pdo();

    $column_exists = function (string $table, string $column) use ($pdo): bool {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $cols = $pdo->query("SHOW COLUMNS FROM `{$safeTable}`")->fetchAll(PDO::FETCH_COLUMN);
        return in_array($column, $cols, true);
    };

    $index_exists = function (string $table, string $index) use ($pdo): bool {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', $index);
        $rows = $pdo->query("SHOW INDEX FROM `{$safeTable}`")->fetchAll();
        foreach ($rows as $row) {
            if (($row['Key_name'] ?? '') === $safeIndex) {
                return true;
            }
        }
        return false;
    };

    $foreign_key_exists = function (string $table, string $constraint) use ($pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.table_constraints
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND constraint_name = ?
               AND constraint_type = ?'
        );
        $stmt->execute([$table, $constraint, 'FOREIGN KEY']);
        return (int) $stmt->fetchColumn() > 0;
    };

    // --- TABLE employees ---
    // Creation avec le nouveau schema si elle n'existe pas
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS employees (
            id          INT          PRIMARY KEY AUTO_INCREMENT,
            first_name  VARCHAR(60)  NOT NULL DEFAULT '',
            last_name   VARCHAR(60)  NOT NULL DEFAULT '',
            badge_id    VARCHAR(40)  NOT NULL UNIQUE,
            active      TINYINT(1)   NOT NULL DEFAULT 1,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Migration si l'ancienne colonne 'name' existe encore
    $cols = $pdo->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('name', $cols)) {
        if (!in_array('last_name', $cols)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN last_name VARCHAR(60) NOT NULL DEFAULT ''");
        }
        if (!in_array('first_name', $cols)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN first_name VARCHAR(60) NOT NULL DEFAULT ''");
        }
        // Decouper "Prenom Nom" → first_name + last_name
        $pdo->exec("UPDATE employees SET
            first_name = SUBSTRING_INDEX(name, ' ', 1),
            last_name  = IF(LOCATE(' ', name) > 0, SUBSTRING(name, LOCATE(' ', name) + 1), name)
            WHERE first_name = '' AND last_name = ''");
        $output[] = '✅ Migration name → first_name + last_name effectuee.';
    } else {
        // Ajouter colonnes manquantes si schema partiel
        if (!$column_exists('employees', 'first_name')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN first_name VARCHAR(60) NOT NULL DEFAULT ''");
        }
        if (!$column_exists('employees', 'last_name')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN last_name VARCHAR(60) NOT NULL DEFAULT ''");
        }
        if (!$column_exists('employees', 'created_at')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
    }
    $output[] = '✅ Table employees OK.';

    // Colonnes geolocalisation + conges (ajout si absentes)
    if (!$column_exists('employees', 'address')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT ''");
    }
    if (!$column_exists('employees', 'latitude')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL");
    }
    if (!$column_exists('employees', 'longitude')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL");
    }
    if (!$column_exists('employees', 'geo_radius')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN geo_radius INT NOT NULL DEFAULT 300");
    }
    if (!$column_exists('employees', 'vacation_days')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN vacation_days INT NOT NULL DEFAULT 25");
    }
    if (!$column_exists('employees', 'vacation_adjustment_days')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN vacation_adjustment_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER vacation_days");
    }
    if (!$column_exists('employees', 'overtime_adjustment_hours')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN overtime_adjustment_hours DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER vacation_adjustment_days");
    }
    $output[] = '✅ Colonnes employes geo + conges OK.';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS departments (
            id          INT           PRIMARY KEY AUTO_INCREMENT,
            name        VARCHAR(100)  NOT NULL UNIQUE,
            created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    if (!$column_exists('employees', 'department_id')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN department_id INT NULL DEFAULT NULL AFTER active");
    }
    if (!$index_exists('employees', 'idx_employees_department')) {
        $pdo->exec("ALTER TABLE employees ADD INDEX idx_employees_department (department_id)");
    }
    if (!$foreign_key_exists('employees', 'fk_employees_department')) {
        try {
            $pdo->exec(
                "ALTER TABLE employees
                 ADD CONSTRAINT fk_employees_department
                 FOREIGN KEY (department_id) REFERENCES departments(id)
                 ON DELETE SET NULL"
            );
        } catch (Throwable $e) {
            // Ignore si la contrainte existe deja sous un autre nom.
        }
    }
    $output[] = '✅ Gestion des departements OK.';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS device_settings (
            config_key   VARCHAR(80) PRIMARY KEY,
            config_value TEXT NOT NULL,
            updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $output[] = '✅ Configuration boitier RFID OK.';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance_events (
            id          BIGINT   PRIMARY KEY AUTO_INCREMENT,
            employee_id INT      NOT NULL,
            event_type  ENUM('in','out')     NOT NULL,
            source      ENUM('rfid','manual') NOT NULL,
            timestamp   DATETIME NOT NULL,
            INDEX idx_attendance_employee (employee_id),
            INDEX idx_attendance_timestamp (timestamp),
            CONSTRAINT fk_attendance_employee
                FOREIGN KEY (employee_id) REFERENCES employees(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $output[] = '✅ Table attendance_events OK.';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS absences (
            id          INT      PRIMARY KEY AUTO_INCREMENT,
            employee_id INT      NOT NULL,
            type        ENUM('sick','vacation','other') NOT NULL,
            start_date  DATE     NOT NULL,
            end_date    DATE     NOT NULL,
            reason      TEXT,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_absences_employee (employee_id),
            INDEX idx_absences_dates (start_date, end_date),
            CONSTRAINT fk_absences_employee
                FOREIGN KEY (employee_id) REFERENCES employees(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $output[] = '✅ Table absences OK.';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS scheduled_hours (
            id          INT      PRIMARY KEY AUTO_INCREMENT,
            employee_id INT      NOT NULL,
            day_of_week INT      NOT NULL CHECK(day_of_week BETWEEN 0 AND 6),
            hours       DECIMAL(5,2) NOT NULL,
            UNIQUE KEY uq_employee_day (employee_id, day_of_week),
            CONSTRAINT fk_scheduled_employee
                FOREIGN KEY (employee_id) REFERENCES employees(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Extensions pour modes d'encodage: référence, heures/jour, heures/semaine
    if (!$column_exists('scheduled_hours', 'week_start')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN week_start DATE NULL AFTER employee_id");
    }
    if (!$column_exists('scheduled_hours', 'start_time')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN start_time TIME NULL AFTER hours");
    }
    if (!$column_exists('scheduled_hours', 'end_time')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN end_time TIME NULL AFTER start_time");
    }
    if (!$column_exists('scheduled_hours', 'break_minutes')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60 AFTER end_time");
    }
    if (!$column_exists('scheduled_hours', 'entry_mode')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN entry_mode ENUM('daily','reference','weekly') NOT NULL DEFAULT 'daily' AFTER break_minutes");
    }

    if (!$index_exists('scheduled_hours', 'idx_scheduled_employee')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD INDEX idx_scheduled_employee (employee_id)");
    }
    if ($index_exists('scheduled_hours', 'uq_employee_day')) {
        $pdo->exec("ALTER TABLE scheduled_hours DROP INDEX uq_employee_day");
    }
    if (!$index_exists('scheduled_hours', 'uq_employee_day_week')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD UNIQUE KEY uq_employee_day_week (employee_id, day_of_week, week_start)");
    }

    $output[] = '✅ Table scheduled_hours OK.';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS vacation_requests (
            id          INT      PRIMARY KEY AUTO_INCREMENT,
            employee_id INT      NOT NULL,
            start_date  DATE     NOT NULL,
            end_date    DATE     NOT NULL,
            reason      TEXT,
            status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_comment TEXT,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_requests_employee (employee_id),
            INDEX idx_requests_status (status),
            INDEX idx_requests_dates (start_date, end_date),
            CONSTRAINT fk_requests_employee
                FOREIGN KEY (employee_id) REFERENCES employees(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $output[] = '✅ Table vacation_requests OK.';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id        INT         PRIMARY KEY AUTO_INCREMENT,
            username  VARCHAR(40) NOT NULL UNIQUE,
            password  VARCHAR(255) NOT NULL,
            role      ENUM('admin','hr','viewer') NOT NULL DEFAULT 'viewer',
            active    TINYINT(1)  NOT NULL DEFAULT 1,
            created_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $output[] = '✅ Table users OK.';

    // Lier les comptes aux employes + role employe
    if (!$column_exists('users', 'employee_id')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN employee_id INT NULL");
    }
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','hr','viewer','employee') NOT NULL DEFAULT 'viewer'");
    $output[] = '✅ Table users: employee_id + role employe OK.';

    // Creer un utilisateur admin par defaut si aucun n'existe
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password, role) VALUES (?, ?, ?)'
        );
        $stmt->execute(['admin', $hash, 'admin']);
        $output[] = '✅ Utilisateur admin cree (login: admin / password: admin).';
        $output[] = '<strong style="color:red">⚠️  CHANGEZ CE MOT DE PASSE IMMEDIATEMENT !</strong>';
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
    $seed_demo_raw = strtolower((string) env_or_default('JIT_SEED_DEMO_EMPLOYEES', '0'));
    $should_seed_demo = (isset($_GET['seed_demo']) && $_GET['seed_demo'] === '1')
        || in_array($seed_demo_raw, ['1', 'true', 'yes', 'on'], true);

    if ($count === 0 && $should_seed_demo) {
        $employees = [
            ['Alice', 'Martin'],
            ['Benoit', 'Lefevre'],
            ['Camille', 'Rousseau'],
            ['David', 'Petit'],
            ['Emma', 'Moreau'],
            ['Florent', 'Girard'],
            ['Gaelle', 'Lambert'],
            ['Hugo', 'Mercier'],
            ['Ines', 'Caron'],
            ['Julien', 'Faure'],
            ['Karim', 'Dupuis'],
            ['Laura', 'Garnier'],
            ['Mathieu', 'Renard'],
            ['Nadia', 'Henry'],
            ['Olivier', 'Chevalier'],
            ['Pauline', 'Bonnet'],
            ['Quentin', 'Robin'],
            ['Rania', 'Marchand'],
            ['Sophie', 'Noel'],
            ['Thomas', 'Gauthier'],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO employees (first_name, last_name, badge_id) VALUES (?, ?, ?)'
        );
        $created_employee_ids = [];
        foreach ($employees as $i => $names) {
            $stmt->execute([$names[0], $names[1], 'RFID-' . (1001 + $i)]);
            $created_employee_ids[] = (int) $pdo->lastInsertId();
        }

        // Horaire par defaut : 8h du lundi au vendredi
        $stmt_hours = $pdo->prepare(
            'INSERT INTO scheduled_hours (employee_id, day_of_week, hours) VALUES (?, ?, ?)'
        );
        foreach ($created_employee_ids as $emp_id) {
            for ($day = 1; $day <= 5; $day++) { // 1=lundi, 5=vendredi
                $stmt_hours->execute([$emp_id, $day, 8.0]);
            }
        }

        $output[] = '✅ 20 employes de demo crees (badges RFID-1001 a RFID-1020).';
        $output[] = '✅ Horaires par defaut (8h/jour, lundi-vendredi).';
    } elseif ($count === 0) {
        $output[] = 'ℹ️  Aucun employe de demo cree automatiquement.';
        $output[] = 'ℹ️  Ajoutez vos collaborateurs depuis l\'interface admin.';
    } else {
        $output[] = "ℹ️  Employes deja presents ($count). Aucun ajout.";
    }

    // Mettre à jour le rayon géo pour les employés existants avec l'ancienne valeur
    $pdo->exec("UPDATE employees SET geo_radius = 300 WHERE geo_radius = 200");
    $output[] = '✅ Rayon de geolocalisatin mis à jour à 300m.';

    $output[] = '<strong style="color:green">✅ Base initialisee avec succes.</strong>';
    $output[] = '<strong style="color:red">⚠️  SUPPRIMEZ CE FICHIER (setup.php) maintenant !</strong>';
} catch (Throwable $e) {
    $output[] = '<strong style="color:red">❌ Erreur: ' . htmlspecialchars($e->getMessage()) . '</strong>';
    $output[] = 'Verifiez les parametres dans config.php (DB_NAME, DB_USER, DB_PASS, DB_HOST).';
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>JustInTime — Initialisation</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 600px; margin: 3rem auto; padding: 1rem; }
    p    { line-height: 1.8; }
  </style>
</head>
<body>
  <h1>Initialisation JustInTime</h1>
  <?php foreach ($output as $line): ?>
    <p><?= $line ?></p>
  <?php endforeach; ?>
</body>
</html>
