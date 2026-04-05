from __future__ import annotations

import sqlite3
from datetime import datetime, date
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


def create_app() -> Flask:
    app = Flask(__name__)
    init_db()

    @app.get("/")
    def index() -> str:
        return render_template("index.html")

    @app.get("/api/dashboard")
    def dashboard() -> Any:
        with get_conn() as conn:
            employees = conn.execute(
                """
                SELECT id, name, badge_id
                FROM employees
                WHERE active = 1
                ORDER BY name
                """
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
                SELECT e.id AS employee_id, e.event_type
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
                    }
                )

            absent_count = max(len(employee_statuses) - present_count, 0)

            recent_events = conn.execute(
                """
                SELECT a.id, a.timestamp, a.event_type, a.source, e.name, e.badge_id
                FROM attendance_events a
                JOIN employees e ON e.id = a.employee_id
                ORDER BY a.id DESC
                LIMIT 30
                """
            ).fetchall()

            return jsonify(
                {
                    "summary": {
                        "employees_total": len(employee_statuses),
                        "present": present_count,
                        "absent": absent_count,
                        "events_today": today_events_count,
                    },
                    "employees": employee_statuses,
                    "events": [dict(row) for row in recent_events],
                }
            )

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

        action = "entree" if next_event_type == "in" else "sortie"
        return jsonify(
            {
                "message": f"{employee['name']} enregistre: {action}.",
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


def get_last_event_type(conn: sqlite3.Connection, employee_id: int) -> str | None:
    row = conn.execute(
        """
        SELECT event_type
        FROM attendance_events
        WHERE employee_id = ?
        ORDER BY id DESC
        LIMIT 1
        """,
        (employee_id,),
    ).fetchone()

    if row is None:
        return None
    return row["event_type"]


def infer_next_event(conn: sqlite3.Connection, employee_id: int) -> str:
    last = get_last_event_type(conn, employee_id)
    return "out" if last == "in" else "in"


def insert_event(conn: sqlite3.Connection, employee_id: int, event_type: str, source: str) -> dict[str, Any]:
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

    return dict(row)


app = create_app()

if __name__ == "__main__":
    app.run(debug=True, host="0.0.0.0", port=8080)
