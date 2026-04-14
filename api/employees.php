<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login('admin');

header('Content-Type: application/json; charset=utf-8');

function table_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $cols = $pdo->query("SHOW COLUMNS FROM `{$safe}`")->fetchAll(PDO::FETCH_COLUMN);
    
    $cache[$table] = $cols;
    return $cols;
}

function table_exists(PDO $pdo, string $table): bool
{
    return jit_table_exists($pdo, $table);
}

function has_col(array $cols, string $name): bool
{
    return in_array($name, $cols, true);
}

try {
    $pdo = get_pdo();
    $action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'save' : 'list');
    $emp_cols = table_columns($pdo, 'employees');
    $usr_cols = table_columns($pdo, 'users');

    $has_first = has_col($emp_cols, 'first_name');
    $has_last  = has_col($emp_cols, 'last_name');
    $has_name  = has_col($emp_cols, 'name');
    $has_addr  = has_col($emp_cols, 'address');
    $has_lat   = has_col($emp_cols, 'latitude');
    $has_lng   = has_col($emp_cols, 'longitude');
    $has_geo   = has_col($emp_cols, 'geo_radius');
    $has_telework = has_col($emp_cols, 'telework_enabled');
    $has_vac   = has_col($emp_cols, 'vacation_days');
    $has_department = has_col($emp_cols, 'department_id') && table_exists($pdo, 'departments');
    $has_rayon = has_col($emp_cols, 'rayon');
    $has_allowed_locations = table_exists($pdo, 'employee_allowed_locations');
    $has_user_employee_id = has_col($usr_cols, 'employee_id');

    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $include_inactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === '1';
        $first_expr = $has_first ? "COALESCE(e.first_name, '') AS first_name" : "'' AS first_name";
        if ($has_last && $has_name) {
            $last_expr = "COALESCE(e.last_name, COALESCE(e.name, '')) AS last_name";
        } elseif ($has_last) {
            $last_expr = "COALESCE(e.last_name, '') AS last_name";
        } elseif ($has_name) {
            $last_expr = "COALESCE(e.name, '') AS last_name";
        } else {
            $last_expr = "'' AS last_name";
        }

        $address_expr = $has_addr ? "COALESCE(e.address, '') AS address" : "'' AS address";
        $lat_expr     = $has_lat ? "e.latitude" : "NULL AS latitude";
        $lng_expr     = $has_lng ? "e.longitude" : "NULL AS longitude";
        $geo_expr     = $has_geo ? "COALESCE(e.geo_radius, 200) AS geo_radius" : "200 AS geo_radius";
        $telework_expr = $has_telework ? 'COALESCE(e.telework_enabled, 0) AS telework_enabled' : '0 AS telework_enabled';
        $vac_expr     = $has_vac ? "COALESCE(e.vacation_days, 25) AS vacation_days" : "25 AS vacation_days";
        $department_id_expr = $has_department ? "e.department_id" : "NULL AS department_id";
        $department_name_expr = $has_department ? "COALESCE(d.name, '') AS department_name" : "'' AS department_name";
        $rayon_expr = $has_rayon ? "COALESCE(e.rayon, '') AS rayon" : "'' AS rayon";
        $login_expr   = $has_user_employee_id
            ? "(SELECT u.username FROM users u WHERE u.employee_id = e.id AND u.role = 'employee' LIMIT 1) AS login_username"
            : "NULL AS login_username";

        $order_by = $has_last && $has_first ? 'e.last_name, e.first_name' : 'e.id DESC';
        $where    = $include_inactive ? '' : 'WHERE e.active = 1';
        $join_departments = $has_department ? 'LEFT JOIN departments d ON d.id = e.department_id' : '';

        $sql = "SELECT e.id,
                {$first_expr},
                {$last_expr},
                e.badge_id, e.active,
                {$address_expr},
                {$lat_expr},
                {$lng_expr},
                {$geo_expr},
                {$telework_expr},
                {$vac_expr},
                {$department_id_expr},
                {$department_name_expr},
                     {$rayon_expr},
                {$login_expr}
             FROM employees e
             {$join_departments}
             {$where}
             ORDER BY {$order_by}";

        $employees = $pdo->query($sql)->fetchAll();

        if ($has_allowed_locations && $employees) {
            $ids = array_map(static fn ($e) => (int) ($e['id'] ?? 0), $employees);
            $ids = array_values(array_filter($ids, static fn ($id) => $id > 0));

            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare(
                    "SELECT employee_id, address, latitude, longitude
                     FROM employee_allowed_locations
                     WHERE employee_id IN ($placeholders)
                     ORDER BY id ASC"
                );
                $stmt->execute($ids);
                $rows = $stmt->fetchAll();

                $byEmployee = [];
                foreach ($rows as $row) {
                    $empId = (int) ($row['employee_id'] ?? 0);
                    if ($empId <= 0) {
                        continue;
                    }
                    $byEmployee[$empId][] = [
                        'address' => (string) ($row['address'] ?? ''),
                        'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
                        'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
                    ];
                }

                foreach ($employees as &$employee) {
                    $empId = (int) ($employee['id'] ?? 0);
                    $employee['allowed_locations'] = $byEmployee[$empId] ?? [];
                }
                unset($employee);
            }
        }

        foreach ($employees as &$employee) {
            if (!isset($employee['allowed_locations']) || !is_array($employee['allowed_locations'])) {
                $employee['allowed_locations'] = [];
            }
        }
        unset($employee);

        json_response(['employees' => $employees]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($action === 'delete') {
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                json_response(['error' => 'Identifiant employe invalide.'], 400);
                exit;
            }

            try {
                $stmt = $pdo->prepare('SELECT id FROM employees WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                if (!$stmt->fetch()) {
                    json_response(['error' => 'Employe introuvable.'], 404);
                    exit;
                }

                $pdo->beginTransaction();

                if ($has_user_employee_id) {
                    $stmt = $pdo->prepare('DELETE FROM users WHERE employee_id = ?');
                    $stmt->execute([$id]);
                }

                foreach (['attendance_events', 'absences', 'scheduled_hours', 'vacation_requests', 'employee_allowed_locations'] as $table) {
                    if (!table_exists($pdo, $table)) {
                        continue;
                    }
                    $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE employee_id = ?");
                    $stmt->execute([$id]);
                }

                $stmt = $pdo->prepare('DELETE FROM employees WHERE id = ?');
                $stmt->execute([$id]);
                $pdo->commit();

                json_response(['message' => 'Employe supprime definitivement.']);
                exit;
            } catch (Throwable $deleteError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $deleteError;
            }
        }

        if ($action === 'create_access') {
            if (!$has_user_employee_id) {
                json_response(['error' => 'Migration incomplète: colonne users.employee_id manquante. Relancez setup.php.'], 500);
                exit;
            }
            $emp_id   = (int) ($payload['employee_id'] ?? 0);
            $username = trim($payload['username'] ?? '');
            $password = trim($payload['password'] ?? '');
            if (!$emp_id || !$username || !$password) {
                json_response(['error' => 'Champs obligatoires manquants.'], 400); exit;
            }
            if (strlen($password) < 6) {
                json_response(['error' => 'Mot de passe trop court (6 caract. min).'], 400); exit;
            }
            $stmt = $pdo->prepare('SELECT id FROM users WHERE employee_id = ?');
            $stmt->execute([$emp_id]);
            if ($stmt->fetch()) {
                json_response(['error' => 'Cet employe a deja un compte.'], 409); exit;
            }
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                json_response(['error' => 'Ce nom d\'utilisateur est deja pris.'], 409); exit;
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password, role, employee_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $hash, 'employee', $emp_id]);
            json_response(['message' => 'Acces cree.', 'username' => $username]);
            exit;
        }

        if ($action === 'delete_access') {
            if (!$has_user_employee_id) {
                json_response(['error' => 'Migration incomplète: colonne users.employee_id manquante. Relancez setup.php.'], 500);
                exit;
            }
            $emp_id = (int) ($payload['employee_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM users WHERE employee_id = ? AND role = ?');
            $stmt->execute([$emp_id, 'employee']);
            json_response(['message' => 'Acces supprime.']);
            exit;
        }

        // Insert ou update employe
        $id        = $payload['id'] ?? null;
        $first     = trim($payload['first_name'] ?? '');
        $last      = trim($payload['last_name'] ?? '');
        $badge     = trim($payload['badge_id'] ?? '');
        $active    = (int) ($payload['active'] ?? 1);
        $address   = trim($payload['address'] ?? '');
        $latitude  = isset($payload['latitude'])  && $payload['latitude']  !== '' ? (float) $payload['latitude']  : null;
        $longitude = isset($payload['longitude']) && $payload['longitude'] !== '' ? (float) $payload['longitude'] : null;
        $geo_radius = (int) ($payload['geo_radius']    ?? 200);
        $telework_enabled = !empty($payload['telework_enabled']) ? 1 : 0;
        $allowed_locations = is_array($payload['allowed_locations'] ?? null)
            ? $payload['allowed_locations']
            : [];
        $vac_days   = (int) ($payload['vacation_days'] ?? 25);
        $department_id = isset($payload['department_id']) && (int) $payload['department_id'] > 0
            ? (int) $payload['department_id']
            : null;
        $rayon = trim((string) ($payload['rayon'] ?? ''));

        if (!$first || !$last || !$badge) {
            json_response(['error' => 'Tous les champs sont obligatoires.'], 400);
            exit;
        }

        if ($has_department && $department_id !== null) {
            $stmt = $pdo->prepare('SELECT id FROM departments WHERE id = ? LIMIT 1');
            $stmt->execute([$department_id]);
            if (!$stmt->fetch()) {
                json_response(['error' => 'Departement introuvable.'], 400);
                exit;
            }
        }

        if ($id) {
            $set = [];
            $vals = [];

            if ($has_first) {
                $set[] = 'first_name = ?';
                $vals[] = $first;
            }
            if ($has_last) {
                $set[] = 'last_name = ?';
                $vals[] = $last;
            }
            if (!$has_first && !$has_last && $has_name) {
                $set[] = 'name = ?';
                $vals[] = trim($first . ' ' . $last);
            }

            $set[] = 'badge_id = ?';
            $vals[] = $badge;
            $set[] = 'active = ?';
            $vals[] = $active;

            if ($has_addr) {
                $set[] = 'address = ?';
                $vals[] = $address;
            }
            if ($has_lat) {
                $set[] = 'latitude = ?';
                $vals[] = $latitude;
            }
            if ($has_lng) {
                $set[] = 'longitude = ?';
                $vals[] = $longitude;
            }
            if ($has_geo) {
                $set[] = 'geo_radius = ?';
                $vals[] = $geo_radius;
            }
            if ($has_telework) {
                $set[] = 'telework_enabled = ?';
                $vals[] = $telework_enabled;
            }
            if ($has_vac) {
                $set[] = 'vacation_days = ?';
                $vals[] = $vac_days;
            }
            if ($has_department) {
                $set[] = 'department_id = ?';
                $vals[] = $department_id;
            }
            if ($has_rayon) {
                $set[] = 'rayon = ?';
                $vals[] = $rayon;
            }

            $vals[] = $id;
            $stmt = $pdo->prepare('UPDATE employees SET ' . implode(', ', $set) . ' WHERE id = ?');
            $stmt->execute($vals);
            $employeeId = (int) $id;
        } else {
            $cols = [];
            $vals = [];
            $phs  = [];

            if ($has_first) {
                $cols[] = 'first_name';
                $vals[] = $first;
                $phs[] = '?';
            }
            if ($has_last) {
                $cols[] = 'last_name';
                $vals[] = $last;
                $phs[] = '?';
            }
            if (!$has_first && !$has_last && $has_name) {
                $cols[] = 'name';
                $vals[] = trim($first . ' ' . $last);
                $phs[] = '?';
            }

            $cols[] = 'badge_id';
            $vals[] = $badge;
            $phs[] = '?';

            $cols[] = 'active';
            $vals[] = $active;
            $phs[] = '?';

            if ($has_addr) {
                $cols[] = 'address';
                $vals[] = $address;
                $phs[] = '?';
            }
            if ($has_lat) {
                $cols[] = 'latitude';
                $vals[] = $latitude;
                $phs[] = '?';
            }
            if ($has_lng) {
                $cols[] = 'longitude';
                $vals[] = $longitude;
                $phs[] = '?';
            }
            if ($has_geo) {
                $cols[] = 'geo_radius';
                $vals[] = $geo_radius;
                $phs[] = '?';
            }
            if ($has_telework) {
                $cols[] = 'telework_enabled';
                $vals[] = $telework_enabled;
                $phs[] = '?';
            }
            if ($has_vac) {
                $cols[] = 'vacation_days';
                $vals[] = $vac_days;
                $phs[] = '?';
            }
            if ($has_department) {
                $cols[] = 'department_id';
                $vals[] = $department_id;
                $phs[] = '?';
            }
            if ($has_rayon) {
                $cols[] = 'rayon';
                $vals[] = $rayon;
                $phs[] = '?';
            }

            $stmt = $pdo->prepare(
                'INSERT INTO employees (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $phs) . ')'
            );
            $stmt->execute($vals);
            $employeeId = (int) $pdo->lastInsertId();
        }

        if ($has_allowed_locations && isset($employeeId) && $employeeId > 0) {
            $delete = $pdo->prepare('DELETE FROM employee_allowed_locations WHERE employee_id = ?');
            $delete->execute([$employeeId]);

            if ($telework_enabled === 1) {
                $insert = $pdo->prepare(
                    'INSERT INTO employee_allowed_locations (employee_id, address, latitude, longitude)
                     VALUES (?, ?, ?, ?)'
                );

                foreach ($allowed_locations as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $locationAddress = trim((string) ($row['address'] ?? ''));
                    if ($locationAddress === '') {
                        continue;
                    }

                    $locationLat = isset($row['latitude']) && $row['latitude'] !== ''
                        ? (float) $row['latitude']
                        : null;
                    $locationLng = isset($row['longitude']) && $row['longitude'] !== ''
                        ? (float) $row['longitude']
                        : null;

                    $insert->execute([$employeeId, $locationAddress, $locationLat, $locationLng]);
                }
            }
        }

        json_response(['message' => 'Employe enregistre.']);
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $status = 500;

    if ($e instanceof PDOException) {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        if ($sqlState === '23000') {
            $status = 409;
            $message = stripos($message, 'badge') !== false
                ? 'Ce badge RFID est deja attribue a un autre employe.'
                : 'Cette modification entre en conflit avec une valeur deja existante.';
        }
    }

    json_response(['error' => $message], $status);
}
