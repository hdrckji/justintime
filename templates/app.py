from __future__ import annotations

import os
import sqlite3
from contextlib import contextmanager
from datetime import datetime, date
from pathlib import Path
from typing import Any

from flask import Flask, jsonify, render_template, request

try:
    import pymysql
except ImportError:  # pragma: no cover - optional in local SQLite mode
    pymysql = None

BASE_DIR = Path(__file__).resolve().parent
SQLITE_PATH = BASE_DIR / "data" / "attendance.db"

DB_ENGINE = os.getenv("DB_ENGINE", "sqlite").strip().lower()
MYSQL_HOST = os.getenv("DB_HOST", "")
MYSQL_PORT = int(os.getenv("DB_PORT", "3306"))
MYSQL_NAME = os.getenv("DB_NAME", "")
MYSQL_USER = os.getenv("DB_USER", "")
MYSQL_PASSWORD = os.getenv("DB_PASSWORD", "")

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


def create_app() -> Flask:
    app = Flask(__name__)
    init_db()

    @app.get("/")
    def index() -> str:
        return render_template("index.html")

    @app.get("/api/dashboard")
    def dashboard() -> Any:
        with get_conn() as conn:
            employees = db_fetchall(
                conn,
                """
                SELECT id, name, badge_id
                FROM employees
                WHERE active = 1
                ORDER BY name
                """,
            )

            today_events_count = db_fetchone(
                conn,
                """
                SELECT COUNT(*) AS count
                FROM attendance_events
                WHERE date(timestamp) = ?
                """,
                (date.today().isoformat(),),
            )["count"]

            latest = db_fetchall(
                conn,
                """
                SELECT e.id AS employee_id, e.event_type
                FROM attendance_events e
                INNER JOIN (
                    SELECT employee_id, MAX(id) AS max_id
                    FROM attendance_events
                    GROUP BY employee_id
                ) last_event ON e.id = last_event.max_id
                """,
            )

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
                    }
                )

            absent_count = max(len(employee_statuses) - present_count, 0)

            recent_events = db_fetchall(
                conn,
                """
                SELECT a.id, a.timestamp, a.event_type, a.source, e.name, e.badge_id
                FROM attendance_events a
                JOIN employees e ON e.id = a.employee_id
                ORDER BY a.id DESC
                LIMIT 30
                """,
            )

            normalized_events = [normalize_event(row) for row in recent_events]

            return jsonify(
                {
                    "summary": {
                        "employees_total": len(employee_statuses),
                        "present": present_count,
                        "absent": absent_count,
                        "events_today": today_events_count,
                    },
                    "employees": employee_statuses,
                    "events": normalized_events,
                }
            )

    @app.post("/api/attendance/rfid")
    def scan_rfid() -> Any:
        payload = request.get_json(silent=True) or {}
        badge_id = str(payload.get("badge_id", "")).strip()

        if not badge_id:
            return jsonify({"error": "Badge RFID manquant."}), 400

        with get_conn() as conn:
            employee = db_fetchone(
                conn,
                """
                SELECT id, name, badge_id
                FROM employees
                WHERE badge_id = ? AND active = 1
                """,
                (badge_id,),
            )

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
            employee = db_fetchone(
                conn,
                """
                SELECT id, name
                FROM employees
                WHERE id = ? AND active = 1
                """,
                (employee_id,),
            )

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


def using_mysql() -> bool:
    return DB_ENGINE == "mysql"


def mysql_ready() -> bool:
    return all([MYSQL_HOST, MYSQL_NAME, MYSQL_USER, MYSQL_PASSWORD])


def sql(query: str) -> str:
    if using_mysql():
        return query.replace("?", "%s")
    return query


def db_fetchall(conn: Any, query: str, params: tuple[Any, ...] = ()) -> list[dict[str, Any]]:
    if using_mysql():
        with conn.cursor() as cursor:
            cursor.execute(sql(query), params)
            return list(cursor.fetchall())

    rows = conn.execute(query, params).fetchall()
    return [dict(row) for row in rows]


def db_fetchone(conn: Any, query: str, params: tuple[Any, ...] = ()) -> dict[str, Any] | None:
    if using_mysql():
        with conn.cursor() as cursor:
            cursor.execute(sql(query), params)
            return cursor.fetchone()

    row = conn.execute(query, params).fetchone()
    return dict(row) if row else None


def db_execute(conn: Any, query: str, params: tuple[Any, ...] = ()) -> int:
    if using_mysql():
        with conn.cursor() as cursor:
            cursor.execute(sql(query), params)
            return int(cursor.lastrowid or 0)

    cursor = conn.execute(query, params)
    return int(cursor.lastrowid or 0)


@contextmanager
def get_conn() -> Any:
    if using_mysql():
        if not mysql_ready():
            raise RuntimeError(
                "Configuration MySQL incomplete. Definissez DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD."
            )
        if pymysql is None:
            raise RuntimeError("PyMySQL est requis pour utiliser DB_ENGINE=mysql.")

        conn = pymysql.connect(
            host=MYSQL_HOST,
            port=MYSQL_PORT,
            user=MYSQL_USER,
            password=MYSQL_PASSWORD,
            database=MYSQL_NAME,
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=False,
        )
        try:
            yield conn
            conn.commit()
        except Exception:
            conn.rollback()
            raise
        finally:
            conn.close()
        return

    conn = sqlite3.connect(SQLITE_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    try:
        yield conn
        conn.commit()
    finally:
        conn.close()


def normalize_event(row: dict[str, Any]) -> dict[str, Any]:
    event = dict(row)
    ts = event.get("timestamp")
    if isinstance(ts, datetime):
        event["timestamp"] = ts.isoformat(timespec="seconds")
    elif isinstance(ts, str) and "T" not in ts and " " in ts:
        event["timestamp"] = ts.replace(" ", "T", 1)
    return event


def init_db() -> None:
    if not using_mysql():
        SQLITE_PATH.parent.mkdir(parents=True, exist_ok=True)

    with get_conn() as conn:
        if using_mysql():
            db_execute(
                conn,
                """
                CREATE TABLE IF NOT EXISTS employees (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(120) NOT NULL,
                    badge_id VARCHAR(40) NOT NULL UNIQUE,
                    active TINYINT(1) NOT NULL DEFAULT 1
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                """,
            )
            db_execute(
                conn,
                """
                CREATE TABLE IF NOT EXISTS attendance_events (
                    id BIGINT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    event_type ENUM('in', 'out') NOT NULL,
                    source ENUM('rfid', 'manual') NOT NULL,
                    timestamp DATETIME NOT NULL,
                    INDEX idx_attendance_employee (employee_id),
                    INDEX idx_attendance_timestamp (timestamp),
                    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                """,
            )
        else:
            conn.executescript(
                """
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
                """
            )

        current_count = db_fetchone(conn, "SELECT COUNT(*) AS count FROM employees")["count"]
        if current_count == 0:
            for idx, name in enumerate(SEED_EMPLOYEES, start=1):
                db_execute(
                    conn,
                    """
                    INSERT INTO employees (name, badge_id)
                    VALUES (?, ?)
                    """,
                    (name, f"RFID-{1000 + idx}"),
                )


def get_last_event_type(conn: sqlite3.Connection, employee_id: int) -> str | None:
    row = db_fetchone(
        conn,
        """
        SELECT event_type
        FROM attendance_events
        WHERE employee_id = ?
        ORDER BY id DESC
        LIMIT 1
        """,
        (employee_id,),
    )

    if row is None:
        return None
    return row["event_type"]


def infer_next_event(conn: sqlite3.Connection, employee_id: int) -> str:
    last = get_last_event_type(conn, employee_id)
    return "out" if last == "in" else "in"


def insert_event(conn: sqlite3.Connection, employee_id: int, event_type: str, source: str) -> dict[str, Any]:
    ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    event_id = db_execute(
        conn,
        """
        INSERT INTO attendance_events (employee_id, event_type, source, timestamp)
        VALUES (?, ?, ?, ?)
        """,
        (employee_id, event_type, source, ts),
    )

    row = db_fetchone(
        conn,
        """
        SELECT a.id, a.timestamp, a.event_type, a.source, e.name, e.badge_id
        FROM attendance_events a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.id = ?
        """,
        (event_id,),
    )

    return normalize_event(row)


app = create_app()

if __name__ == "__main__":
    app.run(debug=True, host="0.0.0.0", port=8080)
