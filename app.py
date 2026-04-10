from __future__ import annotations

import sqlite3
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Any

from flask import Flask, jsonify, render_template, request

BASE_DIR = Path(__file__).resolve().parent
DB_PATH = BASE_DIR / "data" / "attendance.db"

SEED_EMPLOYEES = [
    "Alice Martin",
    "Benoit Lefevre",
    "Camille Rousseau",
    "David Petit",
    "Emma Moreau",
    "Florent Girard",
    "Gaelle Lambert",
    "Hugo Mercier",
    "Ines Caron",
    "Julien Faure",
    "Karim Dupuis",
    "Laura Garnier",
    "Mathieu Renard",
    "Nadia Henry",
    "Olivier Chevalier",
    "Pauline Bonnet",
    "Quentin Robin",
    "Rania Marchand",
    "Sophie Noel",
    "Thomas Gauthier",
]

ATTENDANCE_DUPLICATE_WINDOW_SECONDS = 60
DEFAULT_WEEKLY_TARGET_HOURS = 35.0
UNASSIGNED_DEPARTMENT_NAME = "Sans departement"


def create_app() -> Flask:
    app = Flask(__name__)
    init_db()

    @app.get("/")
    def index() -> str:
        return render_template("index.html")

    @app.get("/health")
    def health() -> Any:
        return jsonify({"status": "ok"}), 200

    @app.get("/api/dashboard")
    def dashboard() -> Any:
        with get_conn() as conn:
            employees = conn.execute(
                """
                SELECT e.id,
                       e.name,
                       e.badge_id,
                       e.department_id,
                       COALESCE(d.name, ?) AS department_name,
                       COALESCE(d.weekly_target_hours, ?) AS weekly_target_hours
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE e.active = 1
                ORDER BY COALESCE(d.name, ?), e.name
                """,
                (UNASSIGNED_DEPARTMENT_NAME, DEFAULT_WEEKLY_TARGET_HOURS, UNASSIGNED_DEPARTMENT_NAME),
            ).fetchall()

            today_events_count = conn.execute(
                """
                SELECT COUNT(*) AS count
                FROM attendance_events
                WHERE date(timestamp) = ?
                """,
                (date.today().isoformat(),),
            ).fetchone()["count"]

            latest = conn.execute(
                """
                SELECT e.employee_id, e.event_type
                FROM attendance_events e
                INNER JOIN (
                    SELECT employee_id, MAX(id) AS max_id
                    FROM attendance_events
                    GROUP BY employee_id
                ) last_event ON e.id = last_event.max_id
                """
            ).fetchall()

            status_by_employee = {row["employee_id"]: row["event_type"] for row in latest}

            employee_statuses = []
            present_count = 0
            for emp in employees:
                current = status_by_employee.get(emp["id"], "out")
                is_present = current == "in"
                if is_present:
                    present_count += 1

                employee_statuses.append(
                    {
                        "id": emp["id"],
                        "name": emp["name"],
                        "badge_id": emp["badge_id"],
                        "status": "present" if is_present else "absent",
                        "department_id": emp["department_id"],
                        "department_name": emp["department_name"],
                        "weekly_target_hours": float(emp["weekly_target_hours"] or DEFAULT_WEEKLY_TARGET_HOURS),
                    }
                )

            absent_count = max(len(employee_statuses) - present_count, 0)

            recent_events = conn.execute(
                """
                SELECT a.id,
                       a.timestamp,
                       a.event_type,
                       a.source,
                       e.name,
                       e.badge_id,
                       COALESCE(d.name, ?) AS department_name
                FROM attendance_events a
                JOIN employees e ON e.id = a.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                ORDER BY a.id DESC
                LIMIT 30
                """,
                (UNASSIGNED_DEPARTMENT_NAME,),
            ).fetchall()

            departments = conn.execute(
                """
                SELECT d.id,
                       d.name,
                       d.weekly_target_hours,
                       COUNT(e.id) AS employee_count
                FROM departments d
                LEFT JOIN employees e ON e.department_id = d.id AND e.active = 1
                GROUP BY d.id, d.name, d.weekly_target_hours
                ORDER BY d.name
                """
            ).fetchall()

            return jsonify(
                {
                    "summary": {
                        "employees_total": len(employee_statuses),
                        "present": present_count,
                        "absent": absent_count,
                        "events_today": today_events_count,
                        "departments_total": len(departments),
                    },
                    "employees": employee_statuses,
                    "departments": [dict(row) for row in departments],
                    "department_stats": build_department_stats(conn, employee_statuses),
                    "events": [normalize_event(dict(row)) for row in recent_events],
                }
            )

    @app.post("/api/departments")
    def create_department() -> Any:
        payload = request.get_json(silent=True) or {}
        name = str(payload.get("name", "")).strip()
        weekly_target_hours = parse_weekly_target_hours(payload.get("weekly_target_hours"))

        if not name:
            return jsonify({"error": "Nom du departement requis."}), 400

        with get_conn() as conn:
            existing = conn.execute(
                "SELECT id FROM departments WHERE lower(name) = lower(?)",
                (name,),
            ).fetchone()
            if existing is not None:
                return jsonify({"error": "Ce departement existe deja."}), 409

            cursor = conn.execute(
                "INSERT INTO departments (name, weekly_target_hours) VALUES (?, ?)",
                (name, weekly_target_hours),
            )
            department = conn.execute(
                "SELECT id, name, weekly_target_hours FROM departments WHERE id = ?",
                (cursor.lastrowid,),
            ).fetchone()

        return jsonify({"message": "Departement ajoute.", "department": dict(department)}), 201

    @app.put("/api/departments/<int:department_id>")
    def update_department(department_id: int) -> Any:
        payload = request.get_json(silent=True) or {}
        name = str(payload.get("name", "")).strip()
        weekly_target_hours = parse_weekly_target_hours(payload.get("weekly_target_hours"))

        if not name:
            return jsonify({"error": "Nom du departement requis."}), 400

        with get_conn() as conn:
            department = conn.execute(
                "SELECT id FROM departments WHERE id = ?",
                (department_id,),
            ).fetchone()
            if department is None:
                return jsonify({"error": "Departement introuvable."}), 404

            existing = conn.execute(
                "SELECT id FROM departments WHERE lower(name) = lower(?) AND id <> ?",
                (name, department_id),
            ).fetchone()
            if existing is not None:
                return jsonify({"error": "Un autre departement porte deja ce nom."}), 409

            conn.execute(
                "UPDATE departments SET name = ?, weekly_target_hours = ? WHERE id = ?",
                (name, weekly_target_hours, department_id),
            )

        return jsonify({"message": "Departement mis a jour."})

    @app.post("/api/employees/<int:employee_id>/department")
    def assign_department(employee_id: int) -> Any:
        payload = request.get_json(silent=True) or {}
        raw_department_id = payload.get("department_id")

        with get_conn() as conn:
            employee = conn.execute(
                "SELECT id, name FROM employees WHERE id = ? AND active = 1",
                (employee_id,),
            ).fetchone()
            if employee is None:
                return jsonify({"error": "Collaborateur introuvable."}), 404

            if raw_department_id in (None, "", 0, "0"):
                conn.execute("UPDATE employees SET department_id = NULL WHERE id = ?", (employee_id,))
                return jsonify({"message": f"{employee['name']} retire du departement."})

            try:
                department_id = int(raw_department_id)
            except (TypeError, ValueError):
                return jsonify({"error": "Departement invalide."}), 400

            department = conn.execute(
                "SELECT id, name FROM departments WHERE id = ?",
                (department_id,),
            ).fetchone()
            if department is None:
                return jsonify({"error": "Departement introuvable."}), 404

            conn.execute(
                "UPDATE employees SET department_id = ? WHERE id = ?",
                (department_id, employee_id),
            )

        return jsonify({"message": f"{employee['name']} affecte a {department['name']}."})

    @app.post("/api/attendance/rfid")
    def scan_rfid() -> Any:
        payload = request.get_json(silent=True) or {}
        badge_id = str(payload.get("badge_id", "")).strip()

        if not badge_id:
            return jsonify({"error": "Badge RFID manquant."}), 400

        with get_conn() as conn:
            employee = conn.execute(
                """
                SELECT id, name, badge_id
                FROM employees
                WHERE badge_id = ? AND active = 1
                """,
                (badge_id,),
            ).fetchone()

            if employee is None:
                return jsonify({"error": "Badge inconnu."}), 404

            next_event_type = infer_next_event(conn, employee["id"])
            event = insert_event(conn, employee["id"], next_event_type, "rfid")

        if event.get("duplicate"):
            original_event_type = event.get("event_type", next_event_type)
            return jsonify(
                {
                    "message": f"{employee['name']} : pointage deja pris en compte.",
                    "name": event.get("name", employee["name"]),
                    "badge_id": event.get("badge_id", badge_id),
                    "event_type": "duplicate",
                    "original_event_type": original_event_type,
                    "timestamp": event.get("timestamp"),
                    "duplicate": True,
                    "seconds_since_last": event.get("seconds_since_last"),
                    "event": event,
                }
            )

        action = "entree" if next_event_type == "in" else "sortie"
        return jsonify(
            {
                "message": f"{employee['name']} enregistre: {action}.",
                "name": event.get("name", employee["name"]),
                "badge_id": event.get("badge_id", badge_id),
                "event_type": event.get("event_type", next_event_type),
                "timestamp": event.get("timestamp"),
                "event": event,
            }
        )

    @app.post("/api/attendance/manual")
    def manual_pointage() -> Any:
        payload = request.get_json(silent=True) or {}

        try:
            employee_id = int(payload.get("employee_id"))
        except (TypeError, ValueError):
            return jsonify({"error": "Employe invalide."}), 400

        event_type = str(payload.get("event_type", "")).strip().lower()
        if event_type not in {"in", "out"}:
            return jsonify({"error": "Action invalide. Utilisez 'in' ou 'out'."}), 400

        with get_conn() as conn:
            employee = conn.execute(
                """
                SELECT id, name
                FROM employees
                WHERE id = ? AND active = 1
                """,
                (employee_id,),
            ).fetchone()

            if employee is None:
                return jsonify({"error": "Employe introuvable."}), 404

            last_event = get_last_event_type(conn, employee_id)

            if event_type == "in" and last_event == "in":
                return jsonify({"error": f"{employee['name']} est deja present."}), 409

            if event_type == "out" and last_event != "in":
                return jsonify({"error": f"{employee['name']} n'est pas en presence active."}), 409

            event = insert_event(conn, employee_id, event_type, "manual")

        if event.get("duplicate"):
            return jsonify(
                {
                    "message": f"{employee['name']} : pointage deja pris en compte.",
                    "duplicate": True,
                    "event": event,
                }
            )

        action = "entree" if event_type == "in" else "sortie"
        return jsonify({"message": f"{employee['name']} enregistre: {action}.", "event": event})

    return app


def get_conn() -> sqlite3.Connection:
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


def init_db() -> None:
    DB_PATH.parent.mkdir(parents=True, exist_ok=True)

    with get_conn() as conn:
        conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS departments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                weekly_target_hours REAL NOT NULL DEFAULT 35
            );

            CREATE TABLE IF NOT EXISTS employees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                badge_id TEXT NOT NULL UNIQUE,
                active INTEGER NOT NULL DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS attendance_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                event_type TEXT NOT NULL CHECK(event_type IN ('in', 'out')),
                source TEXT NOT NULL CHECK(source IN ('rfid', 'manual')),
                timestamp TEXT NOT NULL,
                FOREIGN KEY(employee_id) REFERENCES employees(id)
            );

            CREATE INDEX IF NOT EXISTS idx_attendance_employee ON attendance_events(employee_id);
            CREATE INDEX IF NOT EXISTS idx_attendance_timestamp ON attendance_events(timestamp);
            CREATE INDEX IF NOT EXISTS idx_departments_name ON departments(name);
            """
        )

        ensure_employee_columns(conn)
        conn.execute("CREATE INDEX IF NOT EXISTS idx_employees_department ON employees(department_id)")

        current_count = conn.execute("SELECT COUNT(*) AS count FROM employees").fetchone()["count"]
        if current_count == 0:
            for idx, name in enumerate(SEED_EMPLOYEES, start=1):
                conn.execute(
                    """
                    INSERT INTO employees (name, badge_id)
                    VALUES (?, ?)
                    """,
                    (name, f"RFID-{1000 + idx}"),
                )


def ensure_employee_columns(conn: sqlite3.Connection) -> None:
    columns = {row["name"] for row in conn.execute("PRAGMA table_info(employees)").fetchall()}
    if "department_id" not in columns:
        conn.execute("ALTER TABLE employees ADD COLUMN department_id INTEGER REFERENCES departments(id)")


def normalize_event(row: dict[str, Any]) -> dict[str, Any]:
    event = dict(row)
    timestamp = str(event.get("timestamp", "")).strip()
    if timestamp and "T" not in timestamp and " " in timestamp:
        event["timestamp"] = timestamp.replace(" ", "T", 1)
    return event


def parse_timestamp(value: Any) -> datetime | None:
    raw = str(value or "").strip()
    if not raw:
        return None

    try:
        return datetime.fromisoformat(raw.replace("Z", "+00:00"))
    except ValueError:
        return None


def parse_weekly_target_hours(value: Any) -> float:
    try:
        hours = float(value)
    except (TypeError, ValueError):
        return DEFAULT_WEEKLY_TARGET_HOURS

    return hours if hours > 0 else DEFAULT_WEEKLY_TARGET_HOURS


def get_last_event(conn: sqlite3.Connection, employee_id: int) -> dict[str, Any] | None:
    row = conn.execute(
        """
        SELECT a.id, a.timestamp, a.event_type, a.source, e.name, e.badge_id
        FROM attendance_events a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.employee_id = ?
        ORDER BY a.id DESC
        LIMIT 1
        """,
        (employee_id,),
    ).fetchone()

    if row is None:
        return None
    return dict(row)


def get_last_event_type(conn: sqlite3.Connection, employee_id: int) -> str | None:
    last_event = get_last_event(conn, employee_id)
    if last_event is None:
        return None
    return str(last_event["event_type"])


def get_seconds_since_timestamp(timestamp: str) -> int | None:
    event_time = parse_timestamp(timestamp)
    if event_time is None:
        return None

    now = datetime.now(event_time.tzinfo) if event_time.tzinfo else datetime.now()
    return int((now - event_time).total_seconds())


def infer_next_event(conn: sqlite3.Connection, employee_id: int) -> str:
    last = get_last_event_type(conn, employee_id)
    return "out" if last == "in" else "in"


def insert_event(conn: sqlite3.Connection, employee_id: int, event_type: str, source: str) -> dict[str, Any]:
    last_event = get_last_event(conn, employee_id)
    if last_event is not None:
        seconds_since_last = get_seconds_since_timestamp(str(last_event.get("timestamp", "")))
        if seconds_since_last is not None and 0 <= seconds_since_last < ATTENDANCE_DUPLICATE_WINDOW_SECONDS:
            last_event["duplicate"] = True
            last_event["seconds_since_last"] = seconds_since_last
            last_event["duplicate_window_seconds"] = ATTENDANCE_DUPLICATE_WINDOW_SECONDS
            return last_event

    ts = datetime.now().isoformat(timespec="seconds")
    cursor = conn.execute(
        """
        INSERT INTO attendance_events (employee_id, event_type, source, timestamp)
        VALUES (?, ?, ?, ?)
        """,
        (employee_id, event_type, source, ts),
    )
    event_id = cursor.lastrowid

    row = conn.execute(
        """
        SELECT a.id, a.timestamp, a.event_type, a.source, e.name, e.badge_id
        FROM attendance_events a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.id = ?
        """,
        (event_id,),
    ).fetchone()

    event = dict(row)
    event["duplicate"] = False
    return event


def get_employee_worked_hours_this_week(conn: sqlite3.Connection, employee_id: int) -> float:
    rows = conn.execute(
        """
        SELECT event_type, timestamp
        FROM attendance_events
        WHERE employee_id = ?
        ORDER BY timestamp ASC, id ASC
        """,
        (employee_id,),
    ).fetchall()

    if not rows:
        return 0.0

    now = datetime.now()
    week_start = (now - timedelta(days=now.weekday())).replace(hour=0, minute=0, second=0, microsecond=0)
    total_seconds = 0.0
    open_in: datetime | None = None

    for row in rows:
        ts = parse_timestamp(row["timestamp"])
        if ts is None:
            continue

        if row["event_type"] == "in":
            if open_in is None:
                open_in = ts
            continue

        if row["event_type"] == "out" and open_in is not None:
            start = max(open_in, week_start)
            end = min(ts, now)
            if end > start:
                total_seconds += (end - start).total_seconds()
            open_in = None

    if open_in is not None:
        start = max(open_in, week_start)
        if now > start:
            total_seconds += (now - start).total_seconds()

    return round(total_seconds / 3600, 2)


def build_department_stats(conn: sqlite3.Connection, employees: list[dict[str, Any]]) -> list[dict[str, Any]]:
    stats_by_key: dict[str, dict[str, Any]] = {}

    for employee in employees:
        department_id = employee.get("department_id")
        key = str(department_id) if department_id is not None else "unassigned"
        department_name = employee.get("department_name") or UNASSIGNED_DEPARTMENT_NAME

        if key not in stats_by_key:
            stats_by_key[key] = {
                "id": department_id,
                "name": department_name,
                "employees_total": 0,
                "present": 0,
                "absent": 0,
                "planned_week_hours": 0.0,
                "worked_week_hours": 0.0,
                "absenteeism_rate": 0.0,
            }

        item = stats_by_key[key]
        item["employees_total"] += 1
        item["planned_week_hours"] += float(employee.get("weekly_target_hours") or DEFAULT_WEEKLY_TARGET_HOURS)
        item["worked_week_hours"] += get_employee_worked_hours_this_week(conn, int(employee["id"]))

        if employee.get("status") == "present":
            item["present"] += 1
        else:
            item["absent"] += 1

    stats = list(stats_by_key.values())
    for item in stats:
        total = item["employees_total"]
        item["planned_week_hours"] = round(item["planned_week_hours"], 1)
        item["worked_week_hours"] = round(item["worked_week_hours"], 1)
        item["absenteeism_rate"] = round((item["absent"] / total) * 100, 1) if total else 0.0

    stats.sort(key=lambda item: (item["name"] == UNASSIGNED_DEPARTMENT_NAME, item["name"].lower()))
    return stats


app = create_app()

if __name__ == "__main__":
    app.run(debug=True, host="0.0.0.0", port=8080)
