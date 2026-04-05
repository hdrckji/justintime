<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login('admin');

header('Content-Type: application/json; charset=utf-8');

function monday_of(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        throw new InvalidArgumentException('Date de semaine invalide.');
    }
    return date('Y-m-d', strtotime('monday this week', $ts));
}

function ensure_scheduled_hours_schema(PDO $pdo): void
{
    $cols = $pdo->query('SHOW COLUMNS FROM scheduled_hours')->fetchAll(PDO::FETCH_COLUMN);
    $has = fn(string $c): bool => in_array($c, $cols, true);

    if (!$has('week_start')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN week_start DATE NULL AFTER employee_id");
    }
    if (!$has('start_time')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN start_time TIME NULL AFTER hours");
    }
    if (!$has('end_time')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN end_time TIME NULL AFTER start_time");
    }
    if (!$has('entry_mode')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN entry_mode ENUM('daily','reference','weekly') NOT NULL DEFAULT 'daily' AFTER end_time");
    }

    $indexes = $pdo->query('SHOW INDEX FROM scheduled_hours')->fetchAll();
    $hasOld = false;
    $hasNew = false;
    $hasEmployeeIdx = false;
    foreach ($indexes as $idx) {
        $name = $idx['Key_name'] ?? '';
        if ($name === 'uq_employee_day') {
            $hasOld = true;
        }
        if ($name === 'uq_employee_day_week') {
            $hasNew = true;
        }
        if ($name === 'idx_scheduled_employee') {
            $hasEmployeeIdx = true;
        }
    }

    if (!$hasEmployeeIdx) {
        $pdo->exec('ALTER TABLE scheduled_hours ADD INDEX idx_scheduled_employee (employee_id)');
    }

    if ($hasOld) {
        $pdo->exec('ALTER TABLE scheduled_hours DROP INDEX uq_employee_day');
    }
    if (!$hasNew) {
        $pdo->exec('ALTER TABLE scheduled_hours ADD UNIQUE KEY uq_employee_day_week (employee_id, day_of_week, week_start)');
    }
}

try {
    $pdo = get_pdo();
    ensure_scheduled_hours_schema($pdo);
    $action = $_GET['action'] ?? 'get';

    if ($action === 'get') {
        $emp_id = (int) ($_GET['employee_id'] ?? 0);
        if ($emp_id <= 0) {
            json_response(['error' => 'Collaborateur invalide.'], 400);
            exit;
        }

        $requestedWeek = trim((string) ($_GET['week_start'] ?? ''));
        $weekStart = $requestedWeek !== '' ? monday_of($requestedWeek) : null;

        if ($weekStart !== null) {
            $stmt = $pdo->prepare(
                'SELECT day_of_week, hours, start_time, end_time, entry_mode, week_start
                 FROM scheduled_hours
                 WHERE employee_id = ? AND week_start = ?
                 ORDER BY day_of_week'
            );
            $stmt->execute([$emp_id, $weekStart]);
            $rows = $stmt->fetchAll();

            if (!$rows) {
                $stmt = $pdo->prepare(
                    'SELECT day_of_week, hours, start_time, end_time, entry_mode, week_start
                     FROM scheduled_hours
                     WHERE employee_id = ? AND week_start IS NULL
                     ORDER BY day_of_week'
                );
                $stmt->execute([$emp_id]);
                $rows = $stmt->fetchAll();
            }
        } else {
            $stmt = $pdo->prepare(
                'SELECT day_of_week, hours, start_time, end_time, entry_mode, week_start
                 FROM scheduled_hours
                 WHERE employee_id = ? AND week_start IS NULL
                 ORDER BY day_of_week'
            );
            $stmt->execute([$emp_id]);
            $rows = $stmt->fetchAll();
        }

        json_response(['hours' => $rows]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $emp_id = (int) ($payload['employee_id'] ?? 0);
        if ($emp_id <= 0) {
            json_response(['error' => 'Collaborateur invalide.'], 400);
            exit;
        }

        $mode = (string) ($payload['mode'] ?? 'daily');
        if (!in_array($mode, ['daily', 'reference', 'weekly'], true)) {
            json_response(['error' => 'Mode horaire invalide.'], 400);
            exit;
        }

        $applyTo = (string) ($payload['apply_to'] ?? 'default');
        if (!in_array($applyTo, ['default', 'week'], true)) {
            json_response(['error' => 'Portee invalide.'], 400);
            exit;
        }

        $targetWeek = null;
        if ($applyTo === 'week') {
            $week = trim((string) ($payload['week_start'] ?? ''));
            if ($week === '') {
                json_response(['error' => 'Semaine requise pour une planification hebdomadaire.'], 400);
                exit;
            }
            $targetWeek = monday_of($week);
        }

        $rowsToInsert = [];

        if ($mode === 'daily') {
            $hoursData = $payload['hours'] ?? [];
            foreach ($hoursData as $day => $hours) {
                $d = (int) $day;
                if ($d < 0 || $d > 6) {
                    continue;
                }
                $h = max(0, (float) $hours);
                $rowsToInsert[] = [$d, $h, null, null, 'daily'];
            }
        }

        if ($mode === 'reference') {
            $start = (string) ($payload['start_time'] ?? '');
            $end = (string) ($payload['end_time'] ?? '');
            $days = $payload['days'] ?? [1, 2, 3, 4, 5];

            if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
                json_response(['error' => 'Heure de debut/fin invalide (HH:MM).'], 400);
                exit;
            }

            [$sh, $sm] = array_map('intval', explode(':', $start));
            [$eh, $em] = array_map('intval', explode(':', $end));
            $startMins = $sh * 60 + $sm;
            $endMins = $eh * 60 + $em;
            if ($endMins <= $startMins) {
                json_response(['error' => 'L\'heure de fin doit etre apres l\'heure de debut.'], 400);
                exit;
            }
            $hours = round(($endMins - $startMins) / 60, 2);

            foreach ($days as $day) {
                $d = (int) $day;
                if ($d < 0 || $d > 6) {
                    continue;
                }
                $rowsToInsert[] = [$d, $hours, $start . ':00', $end . ':00', 'reference'];
            }
        }

        if ($mode === 'weekly') {
            $total = max(0, (float) ($payload['weekly_hours'] ?? 0));
            $days = $payload['days'] ?? [1, 2, 3, 4, 5];
            $days = array_values(array_filter(array_map('intval', $days), fn($d) => $d >= 0 && $d <= 6));
            $days = array_values(array_unique($days));
            sort($days);

            if (!$days) {
                json_response(['error' => 'Selectionnez au moins un jour de prestation.'], 400);
                exit;
            }

            $count = count($days);
            $base = floor(($total / $count) * 100) / 100;
            $remaining = round($total - ($base * $count), 2);

            foreach ($days as $index => $d) {
                $h = $base;
                if ($index === $count - 1) {
                    $h = round($base + $remaining, 2);
                }
                $rowsToInsert[] = [$d, $h, null, null, 'weekly'];
            }
        }

        if (!$rowsToInsert) {
            json_response(['error' => 'Aucune ligne horaire a enregistrer.'], 400);
            exit;
        }

        // Supprimer la portée ciblée seulement (référence globale ou semaine précise)
        if ($targetWeek === null) {
            $del = $pdo->prepare('DELETE FROM scheduled_hours WHERE employee_id = ? AND week_start IS NULL');
            $del->execute([$emp_id]);
        } else {
            $del = $pdo->prepare('DELETE FROM scheduled_hours WHERE employee_id = ? AND week_start = ?');
            $del->execute([$emp_id, $targetWeek]);
        }

        $ins = $pdo->prepare(
            'INSERT INTO scheduled_hours (employee_id, week_start, day_of_week, hours, start_time, end_time, entry_mode)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($rowsToInsert as [$day, $hours, $startTime, $endTime, $entryMode]) {
            $ins->execute([$emp_id, $targetWeek, $day, $hours, $startTime, $endTime, $entryMode]);
        }

        json_response(['message' => 'Horaires enregistres.', 'mode' => $mode, 'week_start' => $targetWeek]);
        exit;
    }

    json_response(['error' => 'Methode non autorisee.'], 405);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
