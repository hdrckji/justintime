<?php
require_once __DIR__ . '/auth.php';
require_login();
$user = get_auth_user();
if ($user['role'] === 'employee') {
    header('Location: employee.php');
    exit;
}
?>
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>JustInTime | Tableau de bord</title>
    <link rel="stylesheet" href="static/css/styles.css" />
  </head>
  <body>
    <div class="page-bg" aria-hidden="true"></div>

    <nav class="app-nav">
      <div class="app-nav-inner">
        <a href="index.php" class="app-nav-logo">Just In Time</a>
        <div class="app-nav-links">
          <a href="dashboard.php">📊 Tableau de bord</a>
          <a href="reporting.php">📈 Reporting</a>
          <?php if ($user['role'] === 'admin'): ?><a href="admin.php">🔧 Admin</a><?php endif; ?>
            <a href="corrections.php">✏️ Corrections</a>
            <span class="app-nav-user">👤 <strong><?= htmlspecialchars($user['username']) ?></strong></span>
          <a href="logout.php">🚪 Logout</a>
        </div>
      </div>
    </nav>

    <main class="layout">
      <header class="hero">
        <p class="kicker">PME - 20 collaborateurs</p>
        <h1>Tableau de bord</h1>
        <p class="subtitle">Pointage RFID et manuel en temps réel, avec vue d'ensemble des présences.</p>
      </header>

      <section class="panel panel-scan" aria-labelledby="scan-title">
        <h2 id="scan-title">Badge RFID</h2>
        <p>Scannez le badge puis validez avec Entree.</p>
        <form id="rfid-form" class="form-inline">
          <label for="badge-input" class="sr-only">Badge RFID</label>
          <input id="badge-input" name="badge" type="text" autocomplete="off" placeholder="Ex: RFID-1001" required />
          <button type="submit">Pointer</button>
        </form>
      </section>

      <section class="panel panel-manual" aria-labelledby="manual-title">
        <h2 id="manual-title">Pointage manuel</h2>
        <form id="manual-form" class="manual-grid">
          <label for="employee-select">Employe</label>
          <select id="employee-select" required></select>
          <div class="actions">
            <button type="button" id="btn-in" class="btn-in">Entree</button>
            <button type="button" id="btn-out" class="btn-out">Sortie</button>
          </div>
        </form>
      </section>

      <section class="stats" aria-label="Indicateurs">
        <article class="stat-card">
          <p>Total equipe</p>
          <strong id="stat-total">0</strong>
        </article>
        <article class="stat-card present">
          <p>Presents</p>
          <strong id="stat-present">0</strong>
        </article>
        <article class="stat-card absent">
          <p>Absents</p>
          <strong id="stat-absent">0</strong>
        </article>
        <article class="stat-card">
          <p>Evenements aujourd'hui</p>
          <strong id="stat-events">0</strong>
        </article>
      </section>

      <section class="panel" aria-labelledby="team-title">
        <div class="panel-head">
          <h2 id="team-title">Statut equipe</h2>
          <button id="refresh-btn" class="ghost">Actualiser</button>
        </div>
        <div id="employee-list" class="employee-list"></div>
      </section>

      <section class="panel" aria-labelledby="events-title">
        <h2 id="events-title">Derniers pointages</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Heure</th>
                <th>Employe</th>
                <th>Badge</th>
                <th>Action</th>
                <th>Source</th>
              </tr>
            </thead>
            <tbody id="events-body"></tbody>
          </table>
        </div>
      </section>

      <section id="toast" class="toast" role="status" aria-live="polite"></section>
    </main>

    <script src="static/js/app.js" defer></script>
  </body>
</html>
