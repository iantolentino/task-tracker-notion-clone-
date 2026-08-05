from flask import Flask, jsonify, request, send_from_directory
import sqlite3
import os
from datetime import datetime

app = Flask(__name__)
DB = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'tasks.db')

SEED = [
    {"name":"Service Offer: After Hours Support",               "assignee":"Akio Adachi",       "dueDate":"2026-01-14","effort":"Large","priority":"High",  "status":"Done",                         "type":"Graphics, Marketing",        "desc":""},
    {"name":"OSH Transcription & Graphic",                      "assignee":"",                  "dueDate":"2026-01-14","effort":"Small","priority":"High",  "status":"Done",                         "type":"Graphics, HR Request",       "desc":""},
    {"name":"Updated Email Banners",                            "assignee":"Akio Adachi, Ivan", "dueDate":"2026-01-20","effort":"Large","priority":"Medium","status":"On Hold/Waiting for Material", "type":"Graphics, Multimedia",       "desc":""},
    {"name":"PRTNA Virtual Background",                         "assignee":"",                  "dueDate":"2026-01-15","effort":"Small","priority":"Low",   "status":"Done",                         "type":"Graphics, HR Request",       "desc":""},
    {"name":"Strata Staff Tarp",                                "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Not started",                  "type":"Marketing",                  "desc":""},
    {"name":"Socmed Cover Photo and Profile Picture",           "assignee":"Akio Adachi, Ivan", "dueDate":"2026-01-19","effort":"",     "priority":"",      "status":"Done",                         "type":"Marketing",                  "desc":""},
    {"name":"Throwback Thursday: YEP 2025",                    "assignee":"Akio Adachi",       "dueDate":"2026-01-15","effort":"",     "priority":"Medium","status":"Done",                         "type":"Marketing",                  "desc":""},
    {"name":"Requested Updates for Canada Deck",               "assignee":"Ivan",              "dueDate":"2026-01-16","effort":"",     "priority":"Medium","status":"Done",                         "type":"Sales Request",              "desc":""},
    {"name":"PRTNA PVC ID For Ms. Des",                        "assignee":"Ivan",              "dueDate":"2026-01-16","effort":"",     "priority":"High",  "status":"On Hold/Waiting for Material", "type":"Multimedia",                 "desc":""},
    {"name":"Marketing and Multimedia Process",                 "assignee":"Akio Adachi",       "dueDate":"",          "effort":"",     "priority":"",      "status":"In progress",                  "type":"",                           "desc":""},
    {"name":"Marketing and Multimedia Policy",                  "assignee":"Akio Adachi",       "dueDate":"",          "effort":"",     "priority":"",      "status":"In progress",                  "type":"Marketing, Multimedia",      "desc":""},
    {"name":"Customer Service Day Content",                     "assignee":"Akio Adachi, Ivan", "dueDate":"2026-01-16","effort":"",     "priority":"Medium","status":"Done",                         "type":"Graphics, Holiday, Marketing","desc":""},
    {"name":"Sales Deck Update for AU",                        "assignee":"Ivan, Akio Adachi", "dueDate":"",          "effort":"",     "priority":"Low",   "status":"Done",                         "type":"Marketing",                  "desc":""},
    {"name":"FB Ads Additional (Property Management Admin)",   "assignee":"Akio Adachi",       "dueDate":"2026-01-19","effort":"",     "priority":"Medium","status":"Done",                         "type":"HR Request",                 "desc":""},
    {"name":"Photoshoot for Email Banner",                     "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Not started",                  "type":"Marketing, Multimedia",      "desc":""},
    {"name":"QBR Report Marketing",                            "assignee":"Akio Adachi",       "dueDate":"",          "effort":"",     "priority":"",      "status":"Not started",                  "type":"Marketing",                  "desc":""},
    {"name":"QBR Report Multimedia",                           "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Not started",                  "type":"Multimedia",                 "desc":""},
    {"name":"Monthly Report Marketing",                        "assignee":"Akio Adachi",       "dueDate":"",          "effort":"",     "priority":"",      "status":"In progress",                  "type":"Marketing",                  "desc":""},
    {"name":"Monthly Report Multimedia",                       "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Not started",                  "type":"Multimedia",                 "desc":""},
    {"name":"LinkedIn Ad Content",                             "assignee":"Akio Adachi",       "dueDate":"",          "effort":"",     "priority":"",      "status":"Not started",                  "type":"Marketing",                  "desc":""},
    {"name":"FB Careers Ads: January",                        "assignee":"Akio Adachi",       "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"HR Request",                 "desc":""},
    {"name":"FB Main Ads: January",                           "assignee":"Akio Adachi",       "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"Marketing",                  "desc":""},
    {"name":"Horner Staff Photoshoot",                        "assignee":"Ivan",              "dueDate":"2026-01-19","effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Canada Content Marketing",                       "assignee":"Akio Adachi",       "dueDate":"",          "effort":"",     "priority":"",      "status":"In progress",                  "type":"Sales Request",              "desc":""},
    {"name":"Wrap Up Friday 01/30",                           "assignee":"Akio Adachi, Ivan", "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Retention & Engagement Update",                  "assignee":"Ivan",              "dueDate":"2026-01-20","effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Stratify: SD Request (Reactive Leadership)",     "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"SD Request",                 "desc":""},
    {"name":"Careers: BDR Job Post",                         "assignee":"Akio Adachi, Ivan", "dueDate":"",          "effort":"",     "priority":"High",  "status":"Done",                         "type":"HR Request",                 "desc":""},
    {"name":"Careers: Carousel Post",                        "assignee":"Akio Adachi",       "dueDate":"",          "effort":"",     "priority":"High",  "status":"Done",                         "type":"HR Request",                 "desc":""},
    {"name":"Marketing Content: Page12",                     "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Happy Australia Day (Jan 26)",                  "assignee":"Akio Adachi, Ivan", "dueDate":"2026-01-26","effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Strata Academy Graduation",                     "assignee":"Ivan, Akio Adachi", "dueDate":"2026-01-26","effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Updated Desktop Wallpaper",                     "assignee":"Ivan",              "dueDate":"2026-01-30","effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"L&D Modules, Lesson Plan, Visual Presentation, Certificates","assignee":"Ivan", "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Birthday Celebrants (February)",                "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"HR Request",                 "desc":""},
    {"name":"Anniversaries (February)",                      "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Regularizations (February)",                    "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Signature Strata Mock Up Polo Shirt",           "assignee":"Akio Adachi, Ivan", "dueDate":"2026-02-03","effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"FB Careers: January New Hires",                 "assignee":"Akio Adachi, Ivan", "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"HR Content",                                    "assignee":"Akio Adachi, Ivan", "dueDate":"",          "effort":"",     "priority":"",      "status":"In progress",                  "type":"",                           "desc":""},
    {"name":"The Knight Holiday Swap Graphic",               "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Wrap Up Friday 1/13",                           "assignee":"Akio Adachi, Ivan", "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"SD Stratafy",                                   "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Catch Up Deck for CSM",                         "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"VDAY PREP HR",                                  "assignee":"Ivan, Akio Adachi", "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"CA Sales Deck Update",                         "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"New PRTNA Banner",                             "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Corporate Anniversary Graphic",                "assignee":"Akio Adachi, Ivan", "dueDate":"",          "effort":"",     "priority":"",      "status":"Not started",                  "type":"",                           "desc":""},
    {"name":"300 Employees Term and Graphics",              "assignee":"Akio Adachi, Ivan", "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Congratulatory Graphic for ESM Patty",         "assignee":"",                  "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Email Header (You are Valued)",                "assignee":"",                  "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Urgent Hiring CC Graphic",                     "assignee":"Ivan, Akio Adachi", "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Welcome Banner for CNG",                       "assignee":"Ivan",              "dueDate":"2026-02-20","effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"SD: Strata Staff Collaboration + Teams Working Together = Great Outcomes","assignee":"","dueDate":"", "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"Urgent Hiring Graphic: Accountant",            "assignee":"Ivan, Akio Adachi", "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
    {"name":"March Marketing Post",                         "assignee":"Akio Adachi, Ivan", "dueDate":"",          "effort":"",     "priority":"",      "status":"Not started",                  "type":"",                           "desc":""},
    {"name":"Hyper Care Support Banner",                    "assignee":"Ivan",              "dueDate":"",          "effort":"",     "priority":"",      "status":"Done",                         "type":"",                           "desc":""},
]


def get_db():
    conn = sqlite3.connect(DB)
    conn.row_factory = sqlite3.Row
    return conn


def init_db():
    with get_db() as conn:
        conn.execute("""
            CREATE TABLE IF NOT EXISTS tasks (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL DEFAULT '',
                assignee   TEXT    DEFAULT '',
                due_date   TEXT    DEFAULT '',
                effort     TEXT    DEFAULT '',
                priority   TEXT    DEFAULT '',
                status     TEXT    DEFAULT 'Not started',
                type       TEXT    DEFAULT '',
                desc       TEXT    DEFAULT '',
                updated_at TEXT    DEFAULT ''
            )
        """)
        if conn.execute("SELECT COUNT(*) FROM tasks").fetchone()[0] == 0:
            now = datetime.now().isoformat()
            conn.executemany(
                "INSERT INTO tasks (name,assignee,due_date,effort,priority,status,type,desc,updated_at) VALUES (?,?,?,?,?,?,?,?,?)",
                [(t["name"], t["assignee"], t["dueDate"], t["effort"], t["priority"], t["status"], t["type"], t["desc"], now) for t in SEED]
            )
    print(f"Database ready: {DB}")


def row_to_dict(row):
    d = dict(row)
    d["dueDate"] = d.pop("due_date", "")
    return d


# ── Routes ──────────────────────────────────────────────────────────────────

@app.route("/")
def index():
    return send_from_directory(os.path.dirname(os.path.abspath(__file__)), "tasks-tracker.html")


@app.route("/db")
def db_viewer():
    with get_db() as conn:
        rows = conn.execute("SELECT * FROM tasks ORDER BY id DESC").fetchall()
    rows = [row_to_dict(r) for r in rows]
    cols = ["id","name","assignee","dueDate","status","priority","effort","type","desc","updated_at"]
    rows_html = ""
    for r in rows:
        cells = "".join(f"<td>{r.get(c,'')}</td>" for c in cols)
        rows_html += f"<tr>{cells}</tr>"
    return f"""<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>DB Viewer</title>
<style>
body{{font-family:monospace;padding:20px;background:#f9f9f9}}
h2{{margin-bottom:8px}}
p{{color:#888;font-size:12px;margin-bottom:12px}}
table{{border-collapse:collapse;background:#fff;width:100%}}
th,td{{border:1px solid #ccc;padding:5px 9px;font-size:12px;text-align:left}}
th{{background:#eee}}
tr:nth-child(even){{background:#f5f5f5}}
a{{font-size:13px}}
</style></head>
<body>
<h2>tasks.db — {len(rows)} rows (latest first)</h2>
<p><a href="/">&#8592; Back to Tasks Tracker</a> &nbsp;|&nbsp; <a href="/db">Refresh</a></p>
<table>
<thead><tr>{"".join(f"<th>{c}</th>" for c in cols)}</tr></thead>
<tbody>{rows_html}</tbody>
</table>
</body></html>"""


@app.get("/api/tasks")
def get_tasks():
    with get_db() as conn:
        rows = conn.execute("SELECT * FROM tasks ORDER BY id").fetchall()
    return jsonify([row_to_dict(r) for r in rows])


@app.post("/api/tasks")
def create_task():
    d = request.get_json(force=True)
    now = datetime.now().isoformat()
    with get_db() as conn:
        cur = conn.execute(
            "INSERT INTO tasks (name,assignee,due_date,effort,priority,status,type,desc,updated_at) VALUES (?,?,?,?,?,?,?,?,?)",
            (d.get("name",""), d.get("assignee",""), d.get("dueDate",""), d.get("effort",""),
             d.get("priority",""), d.get("status","Not started"), d.get("type",""), d.get("desc",""), now)
        )
        row = conn.execute("SELECT * FROM tasks WHERE id=?", (cur.lastrowid,)).fetchone()
    return jsonify(row_to_dict(row)), 201


@app.put("/api/tasks/<int:tid>")
def update_task(tid):
    d = request.get_json(force=True)
    now = datetime.now().isoformat()
    with get_db() as conn:
        conn.execute(
            "UPDATE tasks SET name=?,assignee=?,due_date=?,effort=?,priority=?,status=?,type=?,desc=?,updated_at=? WHERE id=?",
            (d.get("name",""), d.get("assignee",""), d.get("dueDate",""), d.get("effort",""),
             d.get("priority",""), d.get("status","Not started"), d.get("type",""), d.get("desc",""), now, tid)
        )
        row = conn.execute("SELECT * FROM tasks WHERE id=?", (tid,)).fetchone()
    if not row:
        return jsonify({"error": "Not found"}), 404
    return jsonify(row_to_dict(row))


@app.delete("/api/tasks/<int:tid>")
def delete_task(tid):
    with get_db() as conn:
        conn.execute("DELETE FROM tasks WHERE id=?", (tid,))
    return "", 204


if __name__ == "__main__":
    init_db()
    print("Open http://localhost:5000 in your browser")
    app.run(debug=False, port=5000)
