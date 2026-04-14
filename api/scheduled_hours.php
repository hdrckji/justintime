<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

require_login();
$auth = get_auth_user();

header('Content-Type: application/json; charset=utf-8');

function monday_of(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        throw new InvalidArgumentException('Date de semaine invalide.');
    }
    return date('Y-m-d', strtotime('monday this week', $ts));
}

function cycle_slot_for_week(string $weekStart, int $interval): int
{
    if ($interval <= 1) {
        return 1;
    }

    $anchor = '2024-01-01'; // lundi de reference pour la recurrence
    $weekTs = strtotime($weekStart);
    $anchorTs = strtotime($anchor);
    if ($weekTs === false || $anchorTs === false) {
        return 1;
    }

    $diffDays = (int) floor(($weekTs - $anchorTs) / 86400);
    $diffWeeks = (int) floor($diffDays / 7);
    $mod = (($diffWeeks % $interval) + $interval) % $interval;
    return $mod + 1;
}

function employee_exists(PDO $pdo, int $employeeId): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE id = ?');
    $stmt->execute([$employeeId]);
    return (int) $stmt->fetchColumn() > 0;
}

function can_manage_target_employee(PDO $pdo, array $auth, int $employeeId): bool
{
    $role = (string) ($auth['role'] ?? '');
    if (in_array($role, ['admin', 'hr'], true)) {
        return true;
    }

    $managerEmployeeId = (int) ($auth['employee_id'] ?? 0);
    return jit_can_manage_employee($pdo, $managerEmployeeId, $employeeId);
}

function scheduled_hours_fk_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.referential_constraints
         WHERE constraint_schema = DATABASE()
           AND table_name = 'scheduled_hours'
           AND constraint_name = 'fk_scheduled_employee'"
    );
    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
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
    if (!$has('break_minutes')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60 AFTER end_time");
    }
    if (!$has('entry_mode')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN entry_mode ENUM('daily','reference','weekly') NOT NULL DEFAULT 'daily' AFTER break_minutes");
    }
    if (!$has('recurrence_interval')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN recurrence_interval TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER entry_mode");
    }
    if (!$has('recurrence_slot')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN recurrence_slot TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER recurrence_interval");
    }
    if (!$has('is_working_day')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN is_working_day TINYINT(1) NOT NULL DEFAULT 1 AFTER recurrence_slot");
    }
    if (!$has('is_paid_off')) {
        $pdo->exec("ALTER TABLE scheduled_hours ADD COLUMN is_paid_off TINYINT(1) NOT NULL DEFAULT 0 AFTER is_working_day");
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

    // Certains déploiements historiques gardent une FK cassée sur employee_id,
    // ce qui bloque l'enregistrement des horaires même pour des employés valides.
    if (scheduled_hours_fk_exists($pdo)) {
        $pdo->exec('ALTER TABLE scheduled_hours DROP FOREIGN KEY fk_scheduled_employee');
    }
}

function normalize_scheduled_rows(array $rows): array
{
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
}

try {
    $pdo = get_pdo();
    ensure_scheduled_hours_schema($pdo);
    $action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'save' : 'get');

    if ($action === 'get') {
        $emp_id = (int) ($_GET['employee_id'] ?? 0);
        if ($emp_id <= 0 || !employee_exists($pdo, $emp_id)) {
            json_response(['error' => 'Collaborateur invalide.'], 400);
            exit;
        }
        if (!can_manage_target_employee($pdo, $auth, $emp_id)) {
            json_response(['error' => 'Acces refuse pour ce collaborateur.'], 403);
            exit;
        }

        $requestedWeek = trim((string) ($_GET['week_start'] ?? ''));
        $weekStart = $requestedWeek !== '' ? monday_of($requestedWeek) : null;

        $requestedInterval = max(1, min(3, (int) ($_GET['recurrence_interval'] ?? 1)));
        $requestedSlot = max(1, min($requestedInterval, (int) ($_GET['recurrence_slot'] ?? 1)));

        if ($weekStart !== null) {
            $stmt = $pdo->prepare(
                 'SELECT id, day_of_week, hours, start_time, end_time, break_minutes, entry_mode, week_start, recurrence_interval, recurrence_slot
                 FROM scheduled_hours
                 WHERE employee_id = ? AND week_start = ?
                  ORDER BY day_of_week ASC, id DESC'
            );
            $stmt->execute([$emp_id, $weekStart]);
            $rows = $stmt->fetchAll();

            if (!$rows) {
                $targetSlot = cycle_slot_for_week($weekStart, $requestedInterval);
                $stmt = $pdo->prepare(
                                                                                'SELECT id, day_of_week, hours, start_time, end_time, break_minutes, entry_mode, week_start, recurrence_interval, recurrence_slot
                     FROM scheduled_hours
                     WHERE employee_id = ? AND week_start IS NULL
                       AND recurrence_interval = ?
                       AND recurrence_slot = ?
                                         ORDER BY day_of_week ASC, id DESC'
                );
                $stmt->execute([$emp_id, $requestedInterval, $targetSlot]);
                $rows = $stmt->fetchAll();

                if (!$rows) {
                    $stmt = $pdo->prepare(
                                                                                                'SELECT id, day_of_week, hours, start_time, end_time, break_minutes, entry_mode, week_start, recurrence_interval, recurrence_slot
                         FROM scheduled_hours
                         WHERE employee_id = ? AND week_start IS NULL
                           AND recurrence_interval = 1
                                                 ORDER BY day_of_week ASC, id DESC'
                    );
                    $stmt->execute([$emp_id]);
                    $rows = $stmt->fetchAll();
                }
            }
        } else {
            $stmt = $pdo->prepare(
                                                                'SELECT id, day_of_week, hours, start_time, end_time, break_minutes, entry_mode, week_start, recurrence_interval, recurrence_slot
                 FROM scheduled_hours
                 WHERE employee_id = ? AND week_start IS NULL
                   AND recurrence_interval = ?
                   AND recurrence_slot = ?
                                 ORDER BY day_of_week ASC, id DESC'
            );
            $stmt->execute([$emp_id, $requestedInterval, $requestedSlot]);
            $rows = $stmt->fetchAll();

            if (!$rows && ($requestedInterval > 1 || $requestedSlot > 1)) {
                $stmt = $pdo->prepare(
                                                                                'SELECT id, day_of_week, hours, start_time, end_time, break_minutes, entry_mode, week_start, recurrence_interval, recurrence_slot
                     FROM scheduled_hours
                     WHERE employee_id = ? AND week_start IS NULL
                       AND recurrence_interval = 1
                                         ORDER BY day_of_week ASC, id DESC'
                );
                $stmt->execute([$emp_id]);
                $rows = $stmt->fetchAll();
            }
        }

                $rows = normalize_scheduled_rows($rows);
                foreach ($rows as &$row) {
                        unset($row['id']);
                }
                unset($row);

        json_response(['hours' => $rows]);
        exit;
    }

    if ($action === 'calculate') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $days = $payload['days'] ?? [];
        
        if (!is_array($days)) {
            json_response(['error' => 'Format invalide pour les jours.'], 400);
            exit;
        }

        $totalHours = 0;
        $workingDays = 0;

        foreach ($days as $dayData) {
            $dayOfWeek = (int) ($dayData['day_of_week'] ?? -1);
            $hours = (float) ($dayData['hours'] ?? 0);
            $isWorkingDay = (bool) ($dayData['is_working_day'] ?? true);
            $isPaidOff = (bool) ($dayData['is_paid_off'] ?? false);

            if ($dayOfWeek < 0 || $dayOfWeek > 6 || !$isWorkingDay) {
                continue;
            }

            $totalHours += max(0, $hours);
            if ($hours > 0 || $isPaidOff) {
                $workingDays++;
            }
        }

        json_response([
            'total_hours' => round($totalHours, 2),
            'working_days' => $workingDays,
            'average_hours_per_day' => $workingDays > 0 ? round($totalHours / $workingDays, 2) : 0
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $emp_id = (int) ($payload['employee_id'] ?? 0);
        if ($emp_id <= 0 || !employee_exists($pdo, $emp_id)) {
            json_response(['error' => 'Collaborateur invalide.'], 400);
            exit;
        }
        if (!can_manage_target_employee($pdo, $auth, $emp_id)) {
            json_response(['error' => 'Acces refuse pour ce collaborateur.'], 403);
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

        $recurrenceInterval = max(1, min(3, (int) ($payload['recurrence_interval'] ?? 1)));
        $recurrenceSlot = max(1, min($recurrenceInterval, (int) ($payload['recurrence_slot'] ?? 1)));
        if ($applyTo === 'week') {
            $recurrenceInterval = 1;
            $recurrenceSlot = 1;
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
                $rowsToInsert[] = [$d, $h, null, null, 0, 'daily'];
            }
        }

        if ($mode === 'reference') {
            $referenceDays = is_array($payload['reference_days'] ?? null)
                ? $payload['reference_days']
                : [];

            if ($referenceDays) {
                foreach ($referenceDays as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $d = (int) ($entry['day'] ?? -1);
                    $start = (string) ($entry['start_time'] ?? '');
                    $end = (string) ($entry['end_time'] ?? '');

                    if ($d < 0 || $d > 6) {
                        continue;
                    }
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

                    $breakMinutes = max(0, (int) ($entry['break_minutes'] ?? 60));
                    if ($breakMinutes >= ($endMins - $startMins)) {
                        json_response(['error' => 'Le temps de pause doit etre inferieur a la plage de travail.'], 400);
                        exit;
                    }

                    $hours = round(max(0, ($endMins - $startMins - $breakMinutes) / 60), 2);
                    $rowsToInsert[] = [$d, $hours, $start . ':00', $end . ':00', $breakMinutes, 'reference'];
                }
            } else {
                // Compatibilite ascendante: ancien format start/end + days
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

                $breakMinutes = max(0, (int) ($payload['break_minutes'] ?? 60));
                if ($breakMinutes >= ($endMins - $startMins)) {
                    json_response(['error' => 'Le temps de pause doit etre inferieur a la plage de travail.'], 400);
                    exit;
                }
                $hours = round(max(0, ($endMins - $startMins - $breakMinutes) / 60), 2);

                foreach ($days as $day) {
                    $d = (int) $day;
                    if ($d < 0 || $d > 6) {
                        continue;
                    }
                    $rowsToInsert[] = [$d, $hours, $start . ':00', $end . ':00', $breakMinutes, 'reference'];
                }
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
                $rowsToInsert[] = [$d, $h, null, null, 0, 'weekly'];
            }
        }

        if (!$rowsToInsert) {
            json_response(['error' => 'Aucune ligne horaire a enregistrer.'], 400);
            exit;
        }

        // Supprimer la portée ciblée seulement (référence globale ou semaine précise)
        if ($targetWeek === null) {
            $del = $pdo->prepare('DELETE FROM scheduled_hours WHERE employee_id = ? AND week_start IS NULL AND recurrence_interval = ? AND recurrence_slot = ?');
            $del->execute([$emp_id, $recurrenceInterval, $recurrenceSlot]);
        } else {
            $del = $pdo->prepare('DELETE FROM scheduled_hours WHERE employee_id = ? AND week_start = ?');
            $del->execute([$emp_id, $targetWeek]);
        }

        $ins = $pdo->prepare(
            'INSERT INTO scheduled_hours (employee_id, week_start, day_of_week, hours, start_time, end_time, break_minutes, entry_mode, recurrence_interval, recurrence_slot, is_working_day, is_paid_off)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($rowsToInsert as [$day, $hours, $startTime, $endTime, $breakMinutes, $entryMode, $isWorkingDay, $isPaidOff]) {
            $ins->execute([$emp_id, $targetWeek, $day, $hours, $startTime, $endTime, $breakMinutes ?? 0, $entryMode, $recurrenceInterval, $recurrenceSlot, $isWorkingDay ?? 1, $isPaidOff ?? 0]);
        }

        json_response([
            'message' => 'Horaires enregistres.',
            'mode' => $mode,
            'week_start' => $targetWeek,
            'recurrence_interval' => $recurrenceInterval,
            'recurrence_slot' => $recurrenceSlot,
        ]);
        exit;
    }

    json_response(['error' => 'Methode non autorisee.'], 405);
} catch (Throwable $e) {
    $message = $e->getMessage();
    $status = 500;

    if ($e instanceof PDOException) {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        if ($sqlState === '23000') {
            $status = 409;
            $message = 'Impossible d\'enregistrer l\'horaire pour ce collaborateur.';
        }
    }

    json_response(['error' => $message], $status);
}
