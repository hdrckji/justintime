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

$reportView = $_GET['view'] ?? 'employee';
if (!in_array($reportView, ['employee', 'department'], true)) {
  $reportView = 'employee';
}

// Récupérer les employés
$employees = $pdo->query(
    "SELECT id,
        COALESCE(first_name, '') AS first_name,
        COALESCE(last_name, '') AS last_name
     FROM employees WHERE active = 1 ORDER BY last_name, first_name"
)->fetchAll();

  $departments = $pdo->query(
    "SELECT d.id,
        d.name,
        COUNT(e.id) AS employee_count
     FROM departments d
     LEFT JOIN employees e ON e.department_id = d.id AND e.active = 1
     GROUP BY d.id, d.name
     ORDER BY d.name"
  )->fetchAll();

// Semaine courante
$week_start = date('Y-m-d', strtotime('monday this week'));

  $selected_emp_id = (int) ($_GET['emp_id'] ?? ($employees[0]['id'] ?? 0));
  $selected_department_id = (int) ($_GET['department_id'] ?? ($departments[0]['id'] ?? 0));
$selected_week = $_GET['week'] ?? $week_start;
$week_start_selected = date('Y-m-d', strtotime('monday', strtotime($selected_week)));
$week_end_selected = date('Y-m-d', strtotime('sunday', strtotime($selected_week)));

  $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
  $dates = [];
  for ($i = 0; $i < 7; $i++) {
    $dates[$i] = date('Y-m-d', strtotime("+$i day", strtotime($week_start_selected)));
  }

  $loadScheduledForEmployee = static function (PDO $pdo, int $employeeId, bool $hasWeekStart, string $weekStartSelected): array {
    if ($employeeId <= 0) {
      return [];
    }

    if ($hasWeekStart) {
      $stmt = $pdo->prepare(
        'SELECT day_of_week, hours
         FROM scheduled_hours
         WHERE employee_id = ? AND week_start = ?'
      );
      $stmt->execute([$employeeId, $weekStartSelected]);
      $rows = $stmt->fetchAll();

      if (!$rows) {
        $stmt = $pdo->prepare(
          'SELECT day_of_week, hours
           FROM scheduled_hours
           WHERE employee_id = ? AND week_start IS NULL'
        );
        $stmt->execute([$employeeId]);
        $rows = $stmt->fetchAll();
      }
    } else {
      $stmt = $pdo->prepare(
        'SELECT day_of_week, hours
         FROM scheduled_hours
         WHERE employee_id = ?'
      );
      $stmt->execute([$employeeId]);
      $rows = $stmt->fetchAll();
    }

    $scheduled = [];
    foreach ($rows as $row) {
      $scheduled[(int) $row['day_of_week']] = (float) $row['hours'];
    }

    return $scheduled;
  };
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
          <label for="view-select" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Vue</label>
          <select name="view" id="view-select" onchange="this.form.submit()">
            <option value="employee" <?= $reportView === 'employee' ? 'selected' : '' ?>>Par collaborateur</option>
            <option value="department" <?= $reportView === 'department' ? 'selected' : '' ?>>Par département</option>
          </select>
        </div>

        <?php if ($reportView === 'employee'): ?>
          <div>
            <label for="emp-select" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Collaborateur</label>
            <select name="emp_id" id="emp-select" onchange="this.form.submit()">
              <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= (int) $emp['id'] === $selected_emp_id ? 'selected' : '' ?>>
                  <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <div>
            <label for="department-select" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Département</label>
            <select name="department_id" id="department-select" onchange="this.form.submit()">
              <?php foreach ($departments as $department): ?>
                <option value="<?= $department['id'] ?>" <?= (int) $department['id'] === $selected_department_id ? 'selected' : '' ?>>
                  <?= htmlspecialchars($department['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div>
          <label for="week-select" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Semaine du</label>
          <input type="date" name="week" id="week-select" value="<?= $selected_week ?>" onchange="this.form.submit()" />
        </div>
      </form>

      <?php
      $dailyScheduled = array_fill(0, 7, 0.0);
      $dailyWorked = array_fill(0, 7, 0.0);
      $contextLabel = 'Sélection';
      $contextCountLabel = 'Statut';
      $contextCountValue = 'N/A';

      if ($reportView === 'employee') {
          $stmt = $pdo->prepare('SELECT first_name, last_name FROM employees WHERE id = ?');
          $stmt->execute([$selected_emp_id]);
          $employee = $stmt->fetch();
          $contextLabel = $employee ? trim($employee['first_name'] . ' ' . $employee['last_name']) : 'Collaborateur';

          $scheduled = $loadScheduledForEmployee($pdo, $selected_emp_id, $hasWeekStart, $week_start_selected);
          for ($day = 0; $day < 7; $day++) {
              $dailyScheduled[$day] = (float) ($scheduled[$day] ?? 0);

              $stmt = $pdo->prepare(
                  'SELECT COALESCE(TIMESTAMPDIFF(SECOND, MIN(timestamp), MAX(timestamp)), 0)
                   FROM attendance_events
                   WHERE employee_id = ? AND DATE(timestamp) = ?'
              );
              $stmt->execute([$selected_emp_id, $dates[$day]]);
              $seconds = (int) $stmt->fetchColumn();
              $dailyWorked[$day] = $seconds > 0 ? round($seconds / 3600, 2) : 0.0;
          }

          $contextCountValue = 'Individuel';
      } else {
          $stmt = $pdo->prepare('SELECT id, name FROM departments WHERE id = ? LIMIT 1');
          $stmt->execute([$selected_department_id]);
          $department = $stmt->fetch();
          $contextLabel = $department ? $department['name'] : 'Département';

          $stmt = $pdo->prepare(
              'SELECT id FROM employees WHERE active = 1 AND department_id = ? ORDER BY id'
          );
          $stmt->execute([$selected_department_id]);
          $employeeIds = array_map(static fn($row) => (int) $row['id'], $stmt->fetchAll());

          $contextCountLabel = 'Collaborateurs';
          $contextCountValue = (string) count($employeeIds);

          foreach ($employeeIds as $employeeId) {
              $employeeSchedule = $loadScheduledForEmployee($pdo, $employeeId, $hasWeekStart, $week_start_selected);
              for ($day = 0; $day < 7; $day++) {
                  $dailyScheduled[$day] += (float) ($employeeSchedule[$day] ?? 0);
              }
          }

          if ($employeeIds) {
              $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
              $params = array_merge($employeeIds, [$week_start_selected, $week_end_selected]);

              $stmt = $pdo->prepare(
                  "SELECT employee_id,
                          DATE(timestamp) AS event_date,
                          COALESCE(TIMESTAMPDIFF(SECOND, MIN(timestamp), MAX(timestamp)), 0) AS worked_seconds
                   FROM attendance_events
                   WHERE employee_id IN ($placeholders)
                     AND DATE(timestamp) BETWEEN ? AND ?
                   GROUP BY employee_id, DATE(timestamp)"
              );
              $stmt->execute($params);
              $rows = $stmt->fetchAll();

              foreach ($rows as $row) {
                  $eventDate = (string) $row['event_date'];
                  $dateIndex = array_search($eventDate, $dates, true);
                  if ($dateIndex === false) {
                      continue;
                  }

                  $seconds = (int) ($row['worked_seconds'] ?? 0);
                  $hours = $seconds > 0 ? round($seconds / 3600, 2) : 0.0;
                  $dailyWorked[$dateIndex] += $hours;
              }
          }
      }

      $totalScheduled = round(array_sum($dailyScheduled), 2);
      $totalWorked = round(array_sum($dailyWorked), 2);
      $diff = round($totalWorked - $totalScheduled, 2);
      $diffClass = $diff >= 0 ? 'status-ok' : 'status-diff';
      ?>

      <h2 style="margin-top: 2rem;">Semaine du <?= date('d/m/Y', strtotime($week_start_selected)) ?> au <?= date('d/m/Y', strtotime($week_end_selected)) ?> — <?= htmlspecialchars($contextLabel) ?></h2>

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
            $scheduled_h = round((float) $dailyScheduled[$day], 2);
            $worked_h = round((float) $dailyWorked[$day], 2);
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
          <strong><?= number_format($totalScheduled, 2, ',', '') ?></strong>
        </div>
        <div class="summary-card">
          <p>Total Travaille (h)</p>
          <strong><?= number_format($totalWorked, 2, ',', '') ?></strong>
        </div>
        <div class="summary-card">
          <p>Solde Heures</p>
          <strong class="<?= $diffClass ?>">
            <?= ($diff >= 0 ? '+' : '') . number_format($diff, 2, ',', '') ?>
          </strong>
        </div>
        <div class="summary-card">
          <p><?= htmlspecialchars($contextCountLabel) ?></p>
          <strong class="<?= $diffClass ?>">
            <?= htmlspecialchars($contextCountValue) ?>
          </strong>
        </div>
      </div>
    </div>

    <section id="toast" class="toast" role="status" aria-live="polite"></section>
  </main>
</body>
</html>
