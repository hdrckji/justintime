<?php
/**
 * debug.php — Diagnostic rapide (SUPPRIMER APRES UTILISATION)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$results = [];

try {
    $pdo = get_pdo();
    $results[] = ['ok', 'Connexion MySQL OK (' . DB_HOST . ' / ' . DB_NAME . ')'];

    // Tables existantes
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $results[] = ['ok', 'Tables trouvees : ' . (empty($tables) ? '(aucune)' : implode(', ', $tables))];

    // Colonnes de employees
    if (in_array('employees', $tables)) {
        $cols = $pdo->query('SHOW COLUMNS FROM employees')->fetchAll(PDO::FETCH_COLUMN);
        $results[] = ['ok', 'Colonnes employees : ' . implode(', ', $cols)];
        $count = $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
        $results[] = ['ok', "Employes en base : $count"];

        // Test de la requete dashboard (COALESCE)
        try {
            $test = $pdo->query(
                "SELECT id,
                    COALESCE(first_name, '') AS first_name,
                    COALESCE(last_name, COALESCE(name, '')) AS last_name,
                    badge_id
                 FROM employees LIMIT 3"
            )->fetchAll();
            $names = array_map(fn($r) => $r['first_name'] . ' ' . $r['last_name'], $test);
            $results[] = ['ok', 'Requete dashboard OK → ex: ' . implode(', ', $names)];
        } catch (Throwable $qe) {
            $results[] = ['err', 'Requete dashboard ECHEC : ' . $qe->getMessage()];
        }
    } else {
        $results[] = ['warn', 'Table employees ABSENTE'];
    }

    // Users
    if (in_array('users', $tables)) {
        $users = $pdo->query('SELECT username, role FROM users')->fetchAll();
        $list  = implode(', ', array_map(fn($u) => $u['username'] . ' (' . $u['role'] . ')', $users));
        $results[] = ['ok', "Utilisateurs : $list"];
    } else {
        $results[] = ['warn', 'Table users ABSENTE — setup.php doit etre relance'];
    }

    // absences / scheduled_hours
    foreach (['absences', 'scheduled_hours', 'attendance_events'] as $t) {
        $results[] = [
            in_array($t, $tables) ? 'ok' : 'warn',
            "Table $t : " . (in_array($t, $tables) ? 'OK' : 'ABSENTE')
        ];
    }

} catch (Throwable $e) {
    $results[] = ['err', 'Erreur : ' . $e->getMessage()];
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>JustInTime — Diagnostic</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 700px; margin: 3rem auto; padding: 1rem; }
    .ok   { color: #208455; }
    .warn { color: #b25428; }
    .err  { color: #7f2323; font-weight: bold; }
    li { padding: 0.3rem 0; }
  </style>
</head>
<body>
  <h1>🔍 Diagnostic JustInTime</h1>
  <ul>
    <?php foreach ($results as [$type, $msg]): ?>
      <li class="<?= $type ?>"><?= htmlspecialchars($msg) ?></li>
    <?php endforeach; ?>
  </ul>
  <p style="color:#888; margin-top:2rem;">⚠️ Supprimer ce fichier après utilisation.</p>
</body>
</html>
