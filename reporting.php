<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

require_login();
$user = get_auth_user();
if (($user['role'] ?? '') === 'employee') {
    header('Location: employee.php');
    exit;
}

$pdo = get_pdo();

$scheduledCols = $pdo->query("SHOW COLUMNS FROM scheduled_hours")->fetchAll(PDO::FETCH_COLUMN);
$hasWeekStart = in_array('week_start', $scheduledCols, true);

// Récupérer les employés
$employees = $pdo->query(
    "SELECT id,
        COALESCE(first_name, '') AS first_name,
        COALESCE(last_name, '') AS last_name
     FROM employees WHERE active = 1 ORDER BY last_name, first_name"
)->fetchAll();

// Semaine courante
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

$selected_emp_id = $_GET['emp_id'] ?? ($employees[0]['id'] ?? 1);
$selected_week = $_GET['week'] ?? $week_start;
$week_start_selected = date('Y-m-d', strtotime('monday', strtotime($selected_week)));
$week_end_selected = date('Y-m-d', strtotime('sunday', strtotime($selected_week)));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>JustInTime | Reporting Heures</title>
  <link rel="stylesheet" href="static/css/styles.css" />
  <style>
    .filter { display: grid; gap: 1rem; grid-template-columns: 1fr 1fr; margin-bottom: 2rem; }
    .filter select, .filter input {
      padding: 0.6rem; background: var(--surface-2); color: var(--ink);
      border: 1px solid var(--line); border-radius: 8px; font: inherit;
    }
    .week-table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.95rem; }
    .week-table th, .week-table td { text-align: left; padding: 0.8rem; border-bottom: 1px solid var(--line); }
    .week-table th { background: var(--surface-2); font-weight: 600; color: var(--ink-soft); }
    .week-table tr:hover { background: rgba(255,255,255,0.03); }
    .status-ok { color: var(--ok); font-weight: 600; }
    .status-diff { color: var(--warn); font-weight: 600; }
    .summary { display: grid; gap: 1rem; grid-template-columns: repeat(4, 1fr); margin-top: 2rem; }
    .summary-card {
      padding: 1rem; background: var(--surface);
      border: 1px solid var(--line); border-radius: 10px; text-align: center;
    }
    .summary-card strong { font-size: 1.5rem; display: block; margin-top: 0.5rem; }
    .summary-card p { margin: 0.3rem 0 0; font-size: 0.8rem; color: var(--ink-soft); }
    @media (max-width: 920px) {
      .filter { grid-template-columns: 1fr; }
      .summary { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 560px) {
      .filter { gap: 0.8rem; }
      .summary { grid-template-columns: 1fr; gap: 0.8rem; }
      .week-table { font-size: 0.8rem; }
      .week-table th, .week-table td { padding: 0.5rem 0.3rem; }
    }
  </style>
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
        <span class="app-nav-user">👤 <strong><?= htmlspecialchars($user['username']) ?></strong></span>
        <a href="logout.php">🚪 Logout</a>
      </div>
    </div>
  </nav>

  <main class="layout">
    <header class="hero">
      <h1>📊 Heures Travaillées</h1>
      <p class="subtitle">Suivi hebdomadaire avec détail par jour</p>
    </header>

    <div class="panel">
      <form method="GET" class="filter">
        <div>
          <label for="emp-select" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Collaborateur</label>
          <select name="emp_id" id="emp-select" onchange="this.form.submit()">
            <?php foreach ($employees as $emp): ?>
              <option value="<?= $emp['id'] ?>" <?= $emp['id'] == $selected_emp_id ? 'selected' : '' ?>>
                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="week-select" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Semaine du</label>
          <input type="date" name="week" id="week-select" value="<?= $selected_week ?>" onchange="this.form.submit()" />
        </div>
      </form>

      <?php
      // Récupérer l'employe selectionne
      $stmt = $pdo->prepare('SELECT first_name, last_name FROM employees WHERE id = ?');
      $stmt->execute([$selected_emp_id]);
      $emp_name = $stmt->fetch();
      $emp_display = $emp_name ? $emp_name['first_name'] . ' ' . $emp_name['last_name'] : 'Employe';

        // Récupérer les horaires prévus: priorité à la semaine spécifique, sinon référence globale
        $scheduled = [];
          if ($hasWeekStart) {
            $stmt = $pdo->prepare(
              'SELECT day_of_week, hours
               FROM scheduled_hours
               WHERE employee_id = ? AND week_start = ?'
            );
            $stmt->execute([$selected_emp_id, $week_start_selected]);
            $rows = $stmt->fetchAll();

            if (!$rows) {
              $stmt = $pdo->prepare(
                'SELECT day_of_week, hours
                 FROM scheduled_hours
                 WHERE employee_id = ? AND week_start IS NULL'
              );
              $stmt->execute([$selected_emp_id]);
              $rows = $stmt->fetchAll();
            }
          } else {
            $stmt = $pdo->prepare(
              'SELECT day_of_week, hours
               FROM scheduled_hours
               WHERE employee_id = ?'
            );
            $stmt->execute([$selected_emp_id]);
            $rows = $stmt->fetchAll();
          }

        foreach ($rows as $row) {
          $scheduled[(int) $row['day_of_week']] = (float) $row['hours'];
        }

      // Jours de la semaine
      $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
      $dates = [];
      for ($i = 0; $i < 7; $i++) {
          $dates[$i] = date('Y-m-d', strtotime("+$i day", strtotime($week_start_selected)));
      }

      // Calculer les heures travaillees par jour
      $daily_worked = [];
      $total_worked = 0;
      $total_scheduled = 0;

      for ($day = 0; $day < 7; $day++) {
          $date_str = $dates[$day];
          $stmt = $pdo->prepare(
              'SELECT COALESCE(TIMESTAMPDIFF(SECOND, MIN(timestamp), MAX(timestamp)), 0)
               FROM attendance_events
               WHERE employee_id = ? AND DATE(timestamp) = ?'
          );
          $stmt->execute([$selected_emp_id, $date_str]);
          $worked_seconds = (int) $stmt->fetchColumn();
          $hours_worked = $worked_seconds > 0 ? ($worked_seconds / 3600) : 0;
          $daily_worked[$day] = round($hours_worked, 2);
          $total_worked += $hours_worked;

          $scheduled_hours = $scheduled[$day] ?? 0;
          $total_scheduled += $scheduled_hours;
      }

      $diff = round($total_worked - $total_scheduled, 2);
      $diff_class = $diff >= 0 ? 'status-ok' : 'status-diff';
      ?>

      <h2 style="margin-top: 2rem;">Semaine du <?= date('d/m/Y', strtotime($week_start_selected)) ?> au <?= date('d/m/Y', strtotime($week_end_selected)) ?> — <?= htmlspecialchars($emp_display) ?></h2>

      <table class="week-table">
        <thead>
          <tr>
            <th>Jour</th>
            <th>Date</th>
            <th>Horaire Prevu (h)</th>
            <th>Travaille (h)</th>
            <th>Difference (h)</th>
          </tr>
        </thead>
        <tbody>
          <?php for ($day = 0; $day < 7; $day++): ?>
            <?php 
            $scheduled_h = $scheduled[$day] ?? 0;
            $worked_h = $daily_worked[$day];
            $day_diff = round($worked_h - $scheduled_h, 2);
            $day_class = ($day_diff >= 0) ? 'status-ok' : 'status-diff';
            ?>
            <tr>
              <td><?= $days[$day] ?></td>
              <td><?= date('d/m/Y', strtotime($dates[$day])) ?></td>
              <td><?= number_format($scheduled_h, 2, ',', '') ?></td>
              <td><?= number_format($worked_h, 2, ',', '') ?></td>
              <td class="<?= $day_class ?>">
                <?= ($day_diff >= 0 ? '+' : '') . number_format($day_diff, 2, ',', '') ?>
              </td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>

      <div class="summary">
        <div class="summary-card">
          <p>Total Prevu (h)</p>
          <strong><?= number_format($total_scheduled, 2, ',', '') ?></strong>
        </div>
        <div class="summary-card">
          <p>Total Travaille (h)</p>
          <strong><?= number_format($total_worked, 2, ',', '') ?></strong>
        </div>
        <div class="summary-card">
          <p>Solde Heures</p>
          <strong class="<?= $diff_class ?>">
            <?= ($diff >= 0 ? '+' : '') . number_format($diff, 2, ',', '') ?>
          </strong>
        </div>
        <div class="summary-card">
          <p>Statut</p>
          <strong class="<?= $diff_class ?>">
            <?= $diff >= 0 ? '✅ +' . number_format($diff, 1, ',', '') . 'h' : '⚠️ ' . number_format($diff, 1, ',', '') . 'h' ?>
          </strong>
        </div>
      </div>
    </div>

    <section id="toast" class="toast" role="status" aria-live="polite"></section>
  </main>
</body>
</html>
