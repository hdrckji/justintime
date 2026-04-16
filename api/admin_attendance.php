<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/payroll_helpers.php';

require_login('admin');

function format_admin_attendance_day_label(string $dateIso): string
{
    static $weekdays = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    $ts = strtotime($dateIso);
    if ($ts === false) {
        return $dateIso;
    }

    return $weekdays[(int) date('w', $ts)] . ' ' . date('d/m/Y', $ts);
}

function resolve_admin_attendance_period(array $query): array
{
    $period = trim((string) ($query['period'] ?? 'day'));
    if (!in_array($period, ['day', 'week', 'month', 'custom'], true)) {
        $period = 'day';
    }

    $anchorDate = trim((string) ($query['anchor_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchorDate)) {
        $anchorDate = date('Y-m-d');
    }

    if ($period === 'day') {
        return [$period, $anchorDate, $anchorDate, $anchorDate];
    }

    if ($period === 'week') {
        $anchorTs = strtotime($anchorDate) ?: time();
        $from = date('Y-m-d', strtotime('monday this week', $anchorTs));
        $to = date('Y-m-d', strtotime('sunday this week', $anchorTs));
        return [$period, $from, $to, $anchorDate];
    }

    if ($period === 'month') {
        $anchorTs = strtotime($anchorDate) ?: time();
        $from = date('Y-m-01', $anchorTs);
        $to = date('Y-m-t', $anchorTs);
        return [$period, $from, $to, $anchorDate];
    }

    $from = trim((string) ($query['from_date'] ?? ''));
    $to = trim((string) ($query['to_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        throw new InvalidArgumentException('Periode personnalisee invalide.');
    }
    if ($to < $from) {
        [$from, $to] = [$to, $from];
    }
    if (count(jit_each_date($from, $to)) > 93) {
        throw new InvalidArgumentException('La periode personnalisee est limitee a 93 jours.');
    }

    return [$period, $from, $to, $anchorDate];
}

function build_admin_attendance_datetime_range(string $fromDate, string $toDate): array
{
    $fromTs = strtotime($fromDate . ' 00:00:00');
    $toTs = strtotime($toDate . ' 00:00:00');
    if ($fromTs === false || $toTs === false) {
        throw new InvalidArgumentException('Periode invalide.');
    }
    if ($toTs < $fromTs) {
        [$fromTs, $toTs] = [$toTs, $fromTs];
    }

    return [
        date('Y-m-d H:i:s', $fromTs),
        date('Y-m-d H:i:s', strtotime('+1 day', $toTs) ?: ($toTs + 86400)),
    ];
}

function summarize_admin_attendance_rows(array $rows): array
{
    $openIn = null;
    $minutes = 0;
    $firstIn = null;
    $lastOut = null;
    $countIn = 0;
    $countOut = 0;
    $events = [];

    foreach ($rows as $row) {
        $eventType = (string) ($row['event_type'] ?? '');
        $timestamp = format_iso_timestamp((string) ($row['timestamp'] ?? ''));
        $ts = strtotime($timestamp);
        if ($ts === false) {
            continue;
        }

        if ($eventType === 'in') {
            $countIn++;
            if ($openIn === null) {
                $openIn = $ts;
                if ($firstIn === null) {
                    $firstIn = date('H:i', $ts);
                }
            }
        } elseif ($eventType === 'out') {
            $countOut++;
            if ($openIn !== null && $ts > $openIn) {
                $minutes += (int) round(($ts - $openIn) / 60);
                $lastOut = date('H:i', $ts);
                $openIn = null;
            }
        }

        $events[] = [
            'id' => (int) ($row['id'] ?? 0),
            'time' => date('H:i', $ts),
            'timestamp' => $timestamp,
            'event_type' => $eventType,
            'source' => (string) ($row['source'] ?? ''),
        ];
    }

    $status = 'empty';
    if (!empty($events)) {
        $status = $countIn === $countOut ? 'complete' : 'incomplete';
    }

    return [
        'worked_hours' => round($minutes / 60, 2),
        'first_in' => $firstIn,
        'last_out' => $lastOut,
        'event_count' => count($events),
        'status' => $status,
        'events' => $events,
    ];
}

function build_admin_scope_filter(string $scope, int $employeeId, int $departmentId, string $rayon): array
{
    $employeeId = max(0, $employeeId);
    $departmentId = max(0, $departmentId);
    $rayon = trim($rayon);

    $sql = '';
    $params = [];
    $label = 'Tous les collaborateurs';

    if ($scope === 'employee') {
        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Selectionnez un collaborateur.');
        }
        $sql = ' AND e.id = ?';
        $params[] = $employeeId;
        $label = 'Collaborateur';
    } elseif ($scope === 'department') {
        if ($departmentId <= 0) {
            throw new InvalidArgumentException('Selectionnez un departement.');
        }
        $sql = ' AND e.department_id = ?';
        $params[] = $departmentId;
        $label = 'Departement';
    } elseif ($scope === 'rayon') {
        if ($departmentId <= 0 || $rayon === '') {
            throw new InvalidArgumentException('Selectionnez un departement et un rayon.');
        }
        $sql = ' AND e.department_id = ? AND TRIM(COALESCE(e.rayon, "")) = ?';
        $params[] = $departmentId;
        $params[] = $rayon;
        $label = 'Rayon';
    } else {
        throw new InvalidArgumentException('Perimetre invalide.');
    }

    return [
        'sql' => $sql,
        'params' => $params,
        'employee_id' => $employeeId,
        'department_id' => $departmentId,
        'rayon' => $rayon,
        'label' => $label,
        'type' => $scope,
    ];
}

try {
    $pdo = get_pdo();

    $scope = trim((string) ($_GET['scope'] ?? 'employee'));
    [$periodType, $fromDate, $toDate, $anchorDate] = resolve_admin_attendance_period($_GET);
    $scopeFilter = build_admin_scope_filter(
        $scope,
        (int) ($_GET['employee_id'] ?? 0),
        (int) ($_GET['department_id'] ?? 0),
        (string) ($_GET['rayon'] ?? '')
    );

    [$fromDateTime, $toDateTimeExclusive] = build_admin_attendance_datetime_range($fromDate, $toDate);

    $scopeEmployeesStmt = $pdo->prepare(
        "SELECT e.id,
                TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS name,
                e.badge_id,
                e.department_id,
                COALESCE(d.name, '') AS department_name,
                TRIM(COALESCE(e.rayon, '')) AS rayon
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE e.active = 1" . $scopeFilter['sql'] . "
         ORDER BY e.last_name ASC, e.first_name ASC"
    );
    $scopeEmployeesStmt->execute($scopeFilter['params']);
    $scopeEmployees = $scopeEmployeesStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$scopeEmployees) {
        json_response([
            'scope' => [
                'type' => $scopeFilter['type'],
                'label' => $scopeFilter['label'],
                'employee_id' => $scopeFilter['employee_id'],
                'department_id' => $scopeFilter['department_id'],
                'rayon' => $scopeFilter['rayon'],
                'display_name' => 'Aucun collaborateur dans ce perimetre',
            ],
            'period' => [
                'type' => $periodType,
                'anchor_date' => $anchorDate,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'summary' => [
                'people_in_scope' => 0,
                'days_in_period' => count(jit_each_date($fromDate, $toDate)),
                'days_with_events' => 0,
                'total_hours' => 0,
                'total_events' => 0,
            ],
            'days' => [],
        ]);
        exit;
    }

    $employeeMetaById = [];
    foreach ($scopeEmployees as $employee) {
        $employeeMetaById[(int) $employee['id']] = [
            'employee_id' => (int) $employee['id'],
            'name' => (string) ($employee['name'] ?? ''),
            'badge_id' => (string) ($employee['badge_id'] ?? ''),
            'department_id' => (int) ($employee['department_id'] ?? 0),
            'department_name' => (string) ($employee['department_name'] ?? ''),
            'rayon' => (string) ($employee['rayon'] ?? ''),
        ];
    }

    $eventsStmt = $pdo->prepare(
        "SELECT ae.id,
                ae.employee_id,
                ae.event_type,
                ae.source,
                ae.timestamp,
                e.badge_id,
                TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS employee_name,
                e.department_id,
                COALESCE(d.name, '') AS department_name,
                TRIM(COALESCE(e.rayon, '')) AS rayon
         FROM attendance_events ae
         JOIN employees e ON e.id = ae.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE ae.timestamp >= ?
           AND ae.timestamp < ?" . $scopeFilter['sql'] . "
         ORDER BY ae.timestamp ASC, ae.id ASC"
    );
    $eventsStmt->execute(array_merge([$fromDateTime, $toDateTimeExclusive], $scopeFilter['params']));
    $eventRows = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

    $eventsByDayAndEmployee = [];
    foreach ($eventRows as $row) {
        $row['timestamp'] = format_iso_timestamp((string) ($row['timestamp'] ?? ''));
        $dateKey = substr((string) ($row['timestamp'] ?? ''), 0, 10);
        $employeeId = (int) ($row['employee_id'] ?? 0);
        if ($dateKey === '' || $employeeId <= 0) {
            continue;
        }
        $eventsByDayAndEmployee[$dateKey][$employeeId][] = $row;
    }

    $days = [];
    $daysWithEvents = 0;
    $totalHours = 0.0;
    $totalEvents = 0;
    $displayName = $scopeFilter['label'];

    if ($scopeFilter['type'] === 'employee') {
        $employee = reset($scopeEmployees) ?: [];
        $displayName = trim((string) ($employee['name'] ?? 'Collaborateur'));
    } elseif ($scopeFilter['type'] === 'department') {
        $departmentName = trim((string) (($scopeEmployees[0]['department_name'] ?? '') ?: 'Departement'));
        $displayName = $departmentName;
    } elseif ($scopeFilter['type'] === 'rayon') {
        $departmentName = trim((string) (($scopeEmployees[0]['department_name'] ?? '') ?: 'Departement'));
        $displayName = $departmentName . ' · ' . $scopeFilter['rayon'];
    }

    foreach (jit_each_date($fromDate, $toDate) as $dateIso) {
        $people = [];
        $peopleRows = $eventsByDayAndEmployee[$dateIso] ?? [];

        if ($scopeFilter['type'] === 'employee') {
            $employeeId = $scopeFilter['employee_id'];
            $summary = summarize_admin_attendance_rows($peopleRows[$employeeId] ?? []);
            $meta = $employeeMetaById[$employeeId] ?? [
                'employee_id' => $employeeId,
                'name' => $displayName,
                'badge_id' => '',
                'department_id' => 0,
                'department_name' => '',
                'rayon' => '',
            ];
            $people[] = array_merge($meta, $summary);
        } else {
            foreach ($peopleRows as $employeeId => $rows) {
                $summary = summarize_admin_attendance_rows($rows);
                $meta = $employeeMetaById[(int) $employeeId] ?? null;
                if (!$meta) {
                    continue;
                }
                $people[] = array_merge($meta, $summary);
            }

            usort($people, static function (array $left, array $right): int {
                return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            });
        }

        $dayHasEvents = array_reduce($people, static function (bool $carry, array $person): bool {
            return $carry || (int) ($person['event_count'] ?? 0) > 0;
        }, false);

        $dayTotalHours = array_reduce($people, static function (float $carry, array $person): float {
            return $carry + (float) ($person['worked_hours'] ?? 0);
        }, 0.0);
        $dayTotalEvents = array_reduce($people, static function (int $carry, array $person): int {
            return $carry + (int) ($person['event_count'] ?? 0);
        }, 0);

        if ($dayHasEvents) {
            $daysWithEvents++;
        }
        $totalHours += $dayTotalHours;
        $totalEvents += $dayTotalEvents;

        $days[] = [
            'date' => $dateIso,
            'label' => format_admin_attendance_day_label($dateIso),
            'weekday' => (int) date('w', strtotime($dateIso) ?: time()),
            'people_count' => count(array_filter($people, static function (array $person): bool {
                return (int) ($person['event_count'] ?? 0) > 0;
            })),
            'total_hours' => round($dayTotalHours, 2),
            'total_events' => $dayTotalEvents,
            'people' => $people,
        ];
    }

    json_response([
        'scope' => [
            'type' => $scopeFilter['type'],
            'label' => $scopeFilter['label'],
            'employee_id' => $scopeFilter['employee_id'],
            'department_id' => $scopeFilter['department_id'],
            'rayon' => $scopeFilter['rayon'],
            'display_name' => $displayName,
        ],
        'period' => [
            'type' => $periodType,
            'anchor_date' => $anchorDate,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ],
        'summary' => [
            'people_in_scope' => count($scopeEmployees),
            'days_in_period' => count($days),
            'days_with_events' => $daysWithEvents,
            'total_hours' => round($totalHours, 2),
            'total_events' => $totalEvents,
        ],
        'days' => $days,
    ]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}