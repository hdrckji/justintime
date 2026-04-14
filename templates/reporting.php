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
$hasRecurrence = in_array('recurrence_interval', $scheduledCols, true) && in_array('recurrence_slot', $scheduledCols, true);

// Reporting: vue departement / rayon
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

$selected_scope = $_GET['scope'] ?? 'department';
if (!in_array($selected_scope, ['department', 'rayon'], true)) {
  $selected_scope = 'department';
}

$selected_department_id = (int) ($_GET['department_id'] ?? ($departments[0]['id'] ?? 0));
$rayons = [];
if ($selected_department_id > 0) {
  $stmt = $pdo->prepare(
    "SELECT DISTINCT TRIM(COALESCE(rayon, '')) AS rayon
     FROM employees
     WHERE active = 1
       AND department_id = ?
       AND TRIM(COALESCE(rayon, '')) <> ''
     ORDER BY rayon ASC"
  );
  $stmt->execute([$selected_department_id]);
  $rayons = array_values(array_filter(array_map(static fn($r) => (string) ($r['rayon'] ?? ''), $stmt->fetchAll())));
}
$selected_rayon = trim((string) ($_GET['rayon'] ?? ''));
if ($selected_scope === 'rayon') {
  if ($selected_rayon === '' && !empty($rayons)) {
    $selected_rayon = (string) $rayons[0];
  }
}

$selected_week = $_GET['week'] ?? $week_start;
$week_start_selected = date('Y-m-d', strtotime('monday', strtotime($selected_week)));
$week_end_selected = date('Y-m-d', strtotime('sunday', strtotime($selected_week)));

$dayLabelsByNum = [
  1 => 'Lundi',
  2 => 'Mardi',
  3 => 'Mercredi',
  4 => 'Jeudi',
  5 => 'Vendredi',
  6 => 'Samedi',
  0 => 'Dimanche',
];
$calendarDayOrder = [1, 2, 3, 4, 5, 6, 0];
$dateByDayNum = [];
for ($offset = 0; $offset < 7; $offset++) {
  $dayNum = $calendarDayOrder[$offset];
  $dateByDayNum[$dayNum] = date('Y-m-d', strtotime("+$offset day", strtotime($week_start_selected)));
}

$dedupePlannedRowsByDay = static function (array $rows): array {
  $byDay = [];
  foreach ($rows as $row) {
    $day = (int) ($row['day_of_week'] ?? -1);
    if ($day < 0 || $day > 6) {
      continue;
    }
    if (!array_key_exists($day, $byDay)) {
      $byDay[$day] = $row;
    }
  }

  ksort($byDay);
  return array_values($byDay);
};

  $loadPlannedRowsForEmployee = static function (PDO $pdo, int $employeeId, bool $hasWeekStart, bool $hasRecurrence, string $weekStartSelected) use ($dedupePlannedRowsByDay): array {
    if ($employeeId <= 0) {
      return [];
    }

    if ($hasWeekStart) {
      $stmt = $pdo->prepare(
        'SELECT day_of_week, hours, start_time, end_time
         FROM scheduled_hours
         WHERE employee_id = ? AND week_start = ?'
      );
      $stmt->execute([$employeeId, $weekStartSelected]);
      $rows = $stmt->fetchAll();

      if (!$rows) {
        if ($hasRecurrence) {
          $stmt = $pdo->prepare(
            'SELECT day_of_week, hours, start_time, end_time
             FROM scheduled_hours
             WHERE employee_id = ?
               AND week_start IS NULL
               AND recurrence_interval >= 1
               AND recurrence_slot = (MOD(TIMESTAMPDIFF(WEEK, "2024-01-01", ?), recurrence_interval) + 1)'
          );
          $stmt->execute([$employeeId, $weekStartSelected]);
          $rows = $stmt->fetchAll();

          if (!$rows) {
            $stmt = $pdo->prepare(
              'SELECT day_of_week, hours, start_time, end_time
               FROM scheduled_hours
               WHERE employee_id = ?
                 AND week_start IS NULL
                 AND recurrence_interval = 1'
            );
            $stmt->execute([$employeeId]);
            $rows = $stmt->fetchAll();
          }
        } else {
          $stmt = $pdo->prepare(
            'SELECT day_of_week, hours, start_time, end_time
             FROM scheduled_hours
             WHERE employee_id = ? AND week_start IS NULL'
          );
          $stmt->execute([$employeeId]);
          $rows = $stmt->fetchAll();
        }
      }
    } else {
      $stmt = $pdo->prepare(
        'SELECT day_of_week, hours, start_time, end_time
         FROM scheduled_hours
         WHERE employee_id = ?'
      );
      $stmt->execute([$employeeId]);
      $rows = $stmt->fetchAll();
    }

    return $dedupePlannedRowsByDay($rows);
  };

  $loadUnavailableDaysForEmployees = static function (PDO $pdo, array $employeeIds, string $weekStartSelected, string $weekEndSelected): array {
    $unavailableByEmployee = [];
    if (!$employeeIds) {
      return $unavailableByEmployee;
    }

    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));

    $absenceSql =
      "SELECT employee_id, start_date, end_date
       FROM absences
       WHERE employee_id IN ($placeholders)
         AND end_date >= ?
         AND start_date <= ?";
    $absenceStmt = $pdo->prepare($absenceSql);
    $absenceStmt->execute(array_merge($employeeIds, [$weekStartSelected, $weekEndSelected]));
    $absenceRows = $absenceStmt->fetchAll();

    $vacationSql =
      "SELECT employee_id, start_date, end_date
       FROM vacation_requests
       WHERE employee_id IN ($placeholders)
         AND status = 'approved'
         AND end_date >= ?
         AND start_date <= ?";
    $vacationStmt = $pdo->prepare($vacationSql);
    $vacationStmt->execute(array_merge($employeeIds, [$weekStartSelected, $weekEndSelected]));
    $vacationRows = $vacationStmt->fetchAll();

    foreach (array_merge($absenceRows, $vacationRows) as $row) {
      $employeeId = (int) ($row['employee_id'] ?? 0);
      $startDate = (string) ($row['start_date'] ?? '');
      $endDate = (string) ($row['end_date'] ?? '');
      if ($employeeId <= 0 || $startDate === '' || $endDate === '') {
        continue;
      }

      $startAt = max(strtotime($startDate), strtotime($weekStartSelected));
      $endAt = min(strtotime($endDate), strtotime($weekEndSelected));
      if ($startAt === false || $endAt === false || $startAt > $endAt) {
        continue;
      }

      for ($cursor = $startAt; $cursor <= $endAt; $cursor = strtotime('+1 day', $cursor)) {
        if ($cursor === false) {
          break;
        }
        $dateKey = date('Y-m-d', $cursor);
        $unavailableByEmployee[$employeeId][$dateKey] = true;
      }
    }

    return $unavailableByEmployee;
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
    .calendar-card {
      margin-top: 1.2rem;
      padding: 1rem;
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 10px;
      overflow-x: auto;
    }
    .calendar-card h3 { margin: 0 0 0.75rem; font-size: 1rem; }
    .calendar-grid {
      width: 100%;
      border-collapse: collapse;
      min-width: 920px;
    }
    .calendar-grid th,
    .calendar-grid td {
      border-bottom: 1px solid var(--line);
      border-right: 1px solid var(--line);
      text-align: center;
      padding: 0.45rem;
      font-size: 0.85rem;
    }
    .calendar-grid th:first-child,
    .calendar-grid td:first-child {
      text-align: left;
      font-weight: 600;
      color: var(--ink-soft);
      width: 86px;
      position: sticky;
      left: 0;
      background: var(--surface);
      z-index: 1;
    }
    .calendar-grid thead th {
      position: sticky;
      top: 0;
      background: var(--surface-2);
      z-index: 2;
    }
    .cell-empty { background: rgba(255,255,255,0.02); color: var(--ink-soft); }
    .cell-l1 { background: rgba(67, 97, 238, 0.15); }
    .cell-l2 { background: rgba(67, 97, 238, 0.3); }
    .cell-l3 { background: rgba(67, 97, 238, 0.45); color: #fff; }
    .cell-l4 { background: rgba(67, 97, 238, 0.6); color: #fff; font-weight: 700; }
    .calendar-note { margin: 0.6rem 0 0; color: var(--ink-soft); font-size: 0.82rem; }
    @media (max-width: 920px) {
      .filter { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
      .filter { gap: 0.8rem; }
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
          <a href="corrections.php">✏️ Corrections</a>
          <span class="app-nav-user">👤 <strong><?= htmlspecialchars($user['username']) ?></strong></span>
        <a href="logout.php">🚪 Logout</a>
      </div>
    </div>
  </nav>

  <main class="layout">
    <header class="hero">
      <h1>📊 Heures Travaillées</h1>
      <p class="subtitle">Vue département/rayon en mode calendrier hebdomadaire</p>
    </header>

    <div class="panel">
      <form method="GET" class="filter">
        <div>
          <label for="scope-select" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Vue</label>
          <select name="scope" id="scope-select" onchange="this.form.submit()">
            <option value="department" <?= $selected_scope === 'department' ? 'selected' : '' ?>>Par département</option>
            <option value="rayon" <?= $selected_scope === 'rayon' ? 'selected' : '' ?>>Par rayon</option>
          </select>
        </div>

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

        <div>
          <label for="rayon-select" style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Rayon</label>
          <select name="rayon" id="rayon-select" onchange="this.form.submit()" <?= $selected_scope === 'rayon' ? '' : 'disabled' ?>>
            <option value="">Tous les rayons</option>
            <?php foreach ($rayons as $rayon): ?>
              <option value="<?= htmlspecialchars($rayon) ?>" <?= $selected_rayon === $rayon ? 'selected' : '' ?>>
                <?= htmlspecialchars($rayon) ?>
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
      $dailyScheduledByDay = array_fill_keys(range(0, 6), 0.0);
      $dailyWorkedByDay = array_fill_keys(range(0, 6), 0.0);
      $displayHours = range(0, 23);
      $staffingByDayHour = [];
      foreach (range(0, 6) as $dayNum) {
        $staffingByDayHour[$dayNum] = array_fill(0, 24, 0);
      }
      $staffingRowsWithoutTime = 0;
      $contextLabel = 'Département';

      $accumulateStaffing = static function (array &$staffingGrid, array $rows, int &$missingCount): void {
        foreach ($rows as $row) {
          $day = (int) ($row['day_of_week'] ?? -1);
          $hours = (float) ($row['hours'] ?? 0);
          if ($day < 0 || $day > 6 || $hours <= 0) {
            continue;
          }

          $startRaw = (string) ($row['start_time'] ?? '');
          $endRaw = (string) ($row['end_time'] ?? '');
          if ($startRaw === '' || $endRaw === '') {
            $missingCount++;
            continue;
          }

          [$sh, $sm] = array_map('intval', explode(':', substr($startRaw, 0, 5)));
          [$eh, $em] = array_map('intval', explode(':', substr($endRaw, 0, 5)));
          $startMin = ($sh * 60) + $sm;
          $endMin = ($eh * 60) + $em;
          if ($endMin <= $startMin) {
            continue;
          }

          for ($hour = 0; $hour < 24; $hour++) {
            $bucketStart = $hour * 60;
            $bucketEnd = ($hour + 1) * 60;
            if ($endMin > $bucketStart && $startMin < $bucketEnd) {
              $staffingGrid[$day][$hour] += 1;
            }
          }
        }
      };

      $stmt = $pdo->prepare('SELECT id, name FROM departments WHERE id = ? LIMIT 1');
      $stmt->execute([$selected_department_id]);
      $department = $stmt->fetch();

      if ($selected_scope === 'rayon' && $selected_rayon !== '') {
        $contextLabel = $department
          ? ($department['name'] . ' / Rayon ' . $selected_rayon)
          : ('Rayon ' . $selected_rayon);
        $stmt = $pdo->prepare(
          'SELECT id
           FROM employees
           WHERE active = 1
             AND department_id = ?
             AND TRIM(COALESCE(rayon, "")) = ?
           ORDER BY id'
        );
        $stmt->execute([$selected_department_id, $selected_rayon]);
      } else {
        $contextLabel = $department ? $department['name'] : 'Département';
        $stmt = $pdo->prepare(
          'SELECT id FROM employees WHERE active = 1 AND department_id = ? ORDER BY id'
        );
        $stmt->execute([$selected_department_id]);
      }
      $employeeIds = array_map(static fn($row) => (int) $row['id'], $stmt->fetchAll());

      $unavailableByEmployee = $loadUnavailableDaysForEmployees($pdo, $employeeIds, $week_start_selected, $week_end_selected);

      foreach ($employeeIds as $employeeId) {
        $plannedRows = $loadPlannedRowsForEmployee($pdo, $employeeId, $hasWeekStart, $hasRecurrence, $week_start_selected);
        $filteredRows = [];

        foreach ($plannedRows as $row) {
          $day = (int) ($row['day_of_week'] ?? -1);
          if ($day < 0 || $day > 6) {
            continue;
          }

          $dayDate = $dateByDayNum[$day] ?? null;
          if ($dayDate === null) {
            continue;
          }

          $isUnavailable = !empty($unavailableByEmployee[$employeeId][$dayDate]);
          if ($isUnavailable) {
            continue;
          }

          $dailyScheduledByDay[$day] += (float) ($row['hours'] ?? 0);
          $filteredRows[] = $row;
        }

        $accumulateStaffing($staffingByDayHour, $filteredRows, $staffingRowsWithoutTime);
      }

      if ($employeeIds) {
        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $params = array_merge($employeeIds, [$week_start_selected, $week_end_selected]);

        $stmt = $pdo->prepare(
          "SELECT DATE(timestamp) AS event_date,
                  COALESCE(TIMESTAMPDIFF(SECOND, MIN(timestamp), MAX(timestamp)), 0) AS worked_seconds
           FROM attendance_events
           WHERE employee_id IN ($placeholders)
             AND DATE(timestamp) BETWEEN ? AND ?
           GROUP BY employee_id, DATE(timestamp)"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
          $eventDate = (string) ($row['event_date'] ?? '');
          if ($eventDate === '') {
            continue;
          }
          $dayNum = (int) date('w', strtotime($eventDate));
          $seconds = (int) ($row['worked_seconds'] ?? 0);
          $hours = $seconds > 0 ? round($seconds / 3600, 2) : 0.0;
          $dailyWorkedByDay[$dayNum] += $hours;
        }
      }

      $maxStaffing = 0;
      foreach ($staffingByDayHour as $hours) {
        $maxStaffing = max($maxStaffing, max($hours));
      }
      ?>

      <div class="calendar-card">
        <h3>Semaine du <?= date('d/m/Y', strtotime($week_start_selected)) ?> au <?= date('d/m/Y', strtotime($week_end_selected)) ?> — <?= htmlspecialchars($contextLabel) ?></h3>
        <p class="calendar-note" style="margin-top: 0;">Calendrier de charge: personnes prévues par heure.</p>
        <table class="calendar-grid">
          <thead>
            <tr>
              <th>Heure</th>
              <?php foreach ($calendarDayOrder as $day): ?>
                <th><?= htmlspecialchars(substr($dayLabelsByNum[$day], 0, 3)) ?><br><small><?= date('d/m', strtotime($dateByDayNum[$day])) ?></small></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($displayHours as $hour): ?>
              <tr>
                <td><?= sprintf('%02d:00', $hour) ?></td>
                <?php foreach ($calendarDayOrder as $day): ?>
                  <?php
                    $count = (int) ($staffingByDayHour[$day][$hour] ?? 0);
                    if ($count <= 0) {
                      $cellClass = 'cell-empty';
                    } else {
                      $ratio = $maxStaffing > 0 ? ($count / $maxStaffing) : 0;
                      if ($ratio >= 0.75) {
                        $cellClass = 'cell-l4';
                      } elseif ($ratio >= 0.5) {
                        $cellClass = 'cell-l3';
                      } elseif ($ratio >= 0.25) {
                        $cellClass = 'cell-l2';
                      } else {
                        $cellClass = 'cell-l1';
                      }
                    }
                  ?>
                  <td class="<?= $cellClass ?>"><?= $count > 0 ? $count : '' ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="calendar-note">Lecture: plus la case est foncée, plus le nombre de personnes prévues est élevé.</p>
        <?php if ($staffingRowsWithoutTime > 0): ?>
          <p class="calendar-note">Note: <?= (int) $staffingRowsWithoutTime ?> plage(s) sans heure début/fin ne peuvent pas être affichées dans ce calendrier horaire.</p>
        <?php endif; ?>
      </div>
    </div>

    <section id="toast" class="toast" role="status" aria-live="polite"></section>
  </main>
</body>
</html>
