"""SQLite data layer for Mirage Academy."""

from __future__ import annotations

import base64
import hashlib
import hmac
import json
import os
import secrets
import sqlite3
import urllib.error
import urllib.parse
import urllib.request
from contextlib import contextmanager
from datetime import datetime, timedelta, timezone
from pathlib import Path

if os.environ.get("VERCEL"):
    _runtime = Path("/tmp/mirage")
    DB_PATH = _runtime / "mirage.db"
    UPLOADS = _runtime / "uploads"
else:
    DB_PATH = Path(os.environ.get("MIRAGE_DB", Path(__file__).with_name("mirage.db")))
    UPLOADS = Path(os.environ.get("MIRAGE_UPLOADS", Path(__file__).with_name("uploads")))
DB_PATH.parent.mkdir(parents=True, exist_ok=True)
UPLOADS.mkdir(parents=True, exist_ok=True)
SECRET = os.environ.get("MIRAGE_SECRET", "mirage-academy-dev-secret").encode()
TOKEN_DAYS = 7

SCHEMA = """
CREATE TABLE IF NOT EXISTS app_meta (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY,
  email TEXT UNIQUE NOT NULL,
  username TEXT UNIQUE,
  password_hash TEXT NOT NULL,
  full_name TEXT NOT NULL,
  role TEXT NOT NULL,
  headline TEXT DEFAULT '',
  bio TEXT DEFAULT '',
  looking_for_job INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS courses (
  id INTEGER PRIMARY KEY,
  title TEXT NOT NULL,
  slug TEXT UNIQUE NOT NULL,
  summary TEXT DEFAULT '',
  description TEXT DEFAULT '',
  category TEXT DEFAULT 'General',
  level TEXT DEFAULT 'Foundation',
  status TEXT NOT NULL DEFAULT 'draft',
  instructor_id INTEGER REFERENCES users(id),
  evaluator_id INTEGER REFERENCES users(id),
  enable_certification INTEGER NOT NULL DEFAULT 0,
  featured INTEGER NOT NULL DEFAULT 0,
  disable_self_learning INTEGER NOT NULL DEFAULT 0,
  paid INTEGER NOT NULL DEFAULT 0,
  price INTEGER NOT NULL DEFAULT 0,
  intro_video TEXT DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS chapters (
  id INTEGER PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  position INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS quizzes (
  id INTEGER PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  passing_score INTEGER NOT NULL DEFAULT 70,
  max_attempts INTEGER NOT NULL DEFAULT 0,
  duration_minutes INTEGER NOT NULL DEFAULT 0,
  show_answers INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS questions (
  id INTEGER PRIMARY KEY,
  quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
  prompt TEXT NOT NULL,
  options_json TEXT NOT NULL,
  answer_index INTEGER NOT NULL,
  explanation TEXT DEFAULT '',
  position INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS assignments (
  id INTEGER PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  instructions TEXT DEFAULT '',
  max_score INTEGER NOT NULL DEFAULT 100
);
CREATE TABLE IF NOT EXISTS lessons (
  id INTEGER PRIMARY KEY,
  chapter_id INTEGER NOT NULL REFERENCES chapters(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  kind TEXT NOT NULL,
  body TEXT DEFAULT '',
  video_url TEXT DEFAULT '',
  minutes INTEGER NOT NULL DEFAULT 8,
  position INTEGER NOT NULL,
  quiz_id INTEGER REFERENCES quizzes(id),
  assignment_id INTEGER REFERENCES assignments(id),
  include_in_preview INTEGER NOT NULL DEFAULT 0,
  instructor_notes TEXT DEFAULT '',
  content_json TEXT DEFAULT '',
  library_item_id INTEGER
);
CREATE TABLE IF NOT EXISTS enrollments (
  id INTEGER PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  enrolled_at TEXT NOT NULL,
  progress INTEGER NOT NULL DEFAULT 0,
  current_lesson_id INTEGER,
  payment_status TEXT NOT NULL DEFAULT 'paid',
  UNIQUE(course_id, user_id)
);
CREATE TABLE IF NOT EXISTS lesson_progress (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  lesson_id INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
  completed INTEGER NOT NULL DEFAULT 0,
  completed_at TEXT,
  PRIMARY KEY (user_id, lesson_id)
);
CREATE TABLE IF NOT EXISTS quiz_attempts (
  id INTEGER PRIMARY KEY,
  quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  score INTEGER NOT NULL,
  passed INTEGER NOT NULL,
  answers_json TEXT NOT NULL,
  submitted_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS submissions (
  id INTEGER PRIMARY KEY,
  assignment_id INTEGER NOT NULL REFERENCES assignments(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'submitted',
  score INTEGER,
  feedback TEXT DEFAULT '',
  submitted_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS batches (
  id INTEGER PRIMARY KEY,
  title TEXT NOT NULL,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  instructor_id INTEGER REFERENCES users(id),
  start_date TEXT,
  end_date TEXT,
  seat_limit INTEGER NOT NULL DEFAULT 16,
  description TEXT DEFAULT '',
  status TEXT NOT NULL DEFAULT 'open',
  certification INTEGER NOT NULL DEFAULT 0,
  allow_self_enrollment INTEGER NOT NULL DEFAULT 1,
  published INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS batch_students (
  batch_id INTEGER NOT NULL REFERENCES batches(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  joined_at TEXT NOT NULL,
  PRIMARY KEY (batch_id, user_id)
);
CREATE TABLE IF NOT EXISTS live_classes (
  id INTEGER PRIMARY KEY,
  batch_id INTEGER REFERENCES batches(id) ON DELETE SET NULL,
  course_id INTEGER REFERENCES courses(id) ON DELETE SET NULL,
  host_id INTEGER REFERENCES users(id),
  title TEXT NOT NULL,
  description TEXT DEFAULT '',
  starts_at TEXT NOT NULL,
  minutes INTEGER NOT NULL DEFAULT 60,
  provider TEXT NOT NULL DEFAULT 'demo',
  zoom_meeting_id TEXT DEFAULT '',
  zoom_join_url TEXT DEFAULT '',
  zoom_start_url TEXT DEFAULT '',
  status TEXT NOT NULL DEFAULT 'scheduled'
);
CREATE TABLE IF NOT EXISTS attendance (
  live_class_id INTEGER NOT NULL REFERENCES live_classes(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  joined_at TEXT NOT NULL,
  PRIMARY KEY (live_class_id, user_id)
);
CREATE TABLE IF NOT EXISTS eval_slots (
  id INTEGER PRIMARY KEY,
  instructor_id INTEGER NOT NULL REFERENCES users(id),
  starts_at TEXT NOT NULL,
  minutes INTEGER NOT NULL DEFAULT 30,
  status TEXT NOT NULL DEFAULT 'open',
  student_id INTEGER REFERENCES users(id),
  course_id INTEGER REFERENCES courses(id),
  notes TEXT DEFAULT '',
  meeting_url TEXT DEFAULT '',
  meeting_provider TEXT DEFAULT '',
  outcome TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS jobs (
  id INTEGER PRIMARY KEY,
  title TEXT NOT NULL,
  company TEXT NOT NULL,
  location TEXT DEFAULT '',
  job_type TEXT DEFAULT 'Full-time',
  description TEXT DEFAULT '',
  posted_by INTEGER REFERENCES users(id),
  status TEXT NOT NULL DEFAULT 'open',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS applications (
  id INTEGER PRIMARY KEY,
  job_id INTEGER NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  note TEXT DEFAULT '',
  applied_at TEXT NOT NULL,
  UNIQUE(job_id, user_id)
);
CREATE TABLE IF NOT EXISTS events (
  id INTEGER PRIMARY KEY,
  kind TEXT NOT NULL,
  user_id INTEGER,
  course_id INTEGER,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS programs (
  id INTEGER PRIMARY KEY,
  title TEXT NOT NULL,
  slug TEXT UNIQUE NOT NULL,
  description TEXT DEFAULT '',
  published INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS program_courses (
  program_id INTEGER NOT NULL REFERENCES programs(id) ON DELETE CASCADE,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  position INTEGER NOT NULL,
  PRIMARY KEY (program_id, course_id)
);
CREATE TABLE IF NOT EXISTS program_members (
  program_id INTEGER NOT NULL REFERENCES programs(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  enrolled_at TEXT NOT NULL,
  PRIMARY KEY (program_id, user_id)
);
CREATE TABLE IF NOT EXISTS reviews (
  id INTEGER PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  rating INTEGER NOT NULL,
  body TEXT DEFAULT '',
  created_at TEXT NOT NULL,
  UNIQUE(course_id, user_id)
);
CREATE TABLE IF NOT EXISTS discussions (
  id INTEGER PRIMARY KEY,
  course_id INTEGER,
  lesson_id INTEGER,
  batch_id INTEGER,
  parent_id INTEGER,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS announcements (
  id INTEGER PRIMARY KEY,
  batch_id INTEGER NOT NULL REFERENCES batches(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id),
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS lesson_notes (
  id INTEGER PRIMARY KEY,
  lesson_id INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(lesson_id, user_id)
);
CREATE TABLE IF NOT EXISTS certificates (
  id INTEGER PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  evaluator_id INTEGER REFERENCES users(id),
  issue_date TEXT NOT NULL,
  UNIQUE(course_id, user_id)
);
CREATE TABLE IF NOT EXISTS certificate_requests (
  id INTEGER PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  evaluator_id INTEGER,
  slot_id INTEGER,
  status TEXT NOT NULL DEFAULT 'pending',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS exercises (
  id INTEGER PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  problem TEXT NOT NULL,
  language TEXT NOT NULL DEFAULT 'text'
);
CREATE TABLE IF NOT EXISTS exercise_submissions (
  id INTEGER PRIMARY KEY,
  exercise_id INTEGER NOT NULL REFERENCES exercises(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  code TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  feedback TEXT DEFAULT '',
  submitted_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS notifications (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  href TEXT DEFAULT '',
  read INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS library_items (
  id INTEGER PRIMARY KEY,
  owner_id INTEGER NOT NULL REFERENCES users(id),
  kind TEXT NOT NULL,
  title TEXT NOT NULL,
  description TEXT DEFAULT '',
  stored_name TEXT DEFAULT '',
  original_name TEXT DEFAULT '',
  mime TEXT DEFAULT '',
  size INTEGER NOT NULL DEFAULT 0,
  quiz_json TEXT DEFAULT '',
  created_at TEXT NOT NULL
);
"""


def now() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


def hash_password(password: str, salt: str | None = None) -> str:
    salt = salt or secrets.token_hex(16)
    digest = hashlib.pbkdf2_hmac("sha256", password.encode(), salt.encode(), 120_000).hex()
    return f"{salt}${digest}"


def verify_password(password: str, stored: str) -> bool:
    salt = stored.split("$", 1)[0]
    return hmac.compare_digest(hash_password(password, salt), stored)


def make_token(user_id: int) -> str:
    payload = base64.urlsafe_b64encode(
        json.dumps({"sub": user_id, "exp": int(datetime.now(timezone.utc).timestamp()) + TOKEN_DAYS * 86400}).encode()
    ).decode().rstrip("=")
    sig = hmac.new(SECRET, payload.encode(), hashlib.sha256).hexdigest()
    return f"{payload}.{sig}"


def read_token(token: str) -> int | None:
    try:
        payload, sig = token.split(".", 1)
        expected = hmac.new(SECRET, payload.encode(), hashlib.sha256).hexdigest()
        if not hmac.compare_digest(expected, sig):
            return None
        pad = "=" * (-len(payload) % 4)
        data = json.loads(base64.urlsafe_b64decode(payload + pad))
        if data["exp"] < datetime.now(timezone.utc).timestamp():
            return None
        return int(data["sub"])
    except (ValueError, KeyError, json.JSONDecodeError):
        return None


def slugify(title: str) -> str:
    keep = []
    for ch in title.lower():
        if ch.isalnum():
            keep.append(ch)
        elif ch in " -_":
            keep.append("-")
    slug = "".join(keep).strip("-")
    while "--" in slug:
        slug = slug.replace("--", "-")
    return slug or "course"


@contextmanager
def connect():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def one(conn, sql, args=()):
    row = conn.execute(sql, args).fetchone()
    return dict(row) if row else None


def many(conn, sql, args=()):
    return [dict(row) for row in conn.execute(sql, args).fetchall()]


def frappe_role(role: str) -> str:
    return {
        "admin": "Administrator",
        "moderator": "Moderator",
        "instructor": "Instructor",
        "student": "Learner",
    }.get(role, role)


def public_user(row: dict | None) -> dict | None:
    if not row:
        return None
    return {
        "id": row["id"],
        "email": row["email"],
        "username": row.get("username") or "",
        "full_name": row["full_name"],
        "role": row["role"],
        "frappe_role": frappe_role(row["role"]),
        "headline": row["headline"],
        "bio": row.get("bio") or "",
        "looking_for_job": bool(row.get("looking_for_job")),
        "created_at": row["created_at"],
    }


def init_db() -> None:
    with connect() as conn:
        conn.execute("PRAGMA foreign_keys = OFF")
        version = None
        exists = conn.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='app_meta'").fetchone()
        if exists:
            row = conn.execute("SELECT value FROM app_meta WHERE key = 'schema'").fetchone()
            version = row["value"] if row else None
        if version != "6":
            tables = [
                record[0]
                for record in conn.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
            ]
            for table in tables:
                conn.execute(f'DROP TABLE IF EXISTS "{table}"')
            conn.executescript(SCHEMA)
            seed(conn)
            conn.execute("INSERT INTO app_meta (key, value) VALUES ('schema', '6')")
        ensure_library(conn)
        from server import cima
        cima.ensure(conn)
        conn.execute("PRAGMA foreign_keys = ON")


def ensure_library(conn) -> None:
    conn.executescript(
        """
        CREATE TABLE IF NOT EXISTS library_items (
          id INTEGER PRIMARY KEY,
          owner_id INTEGER NOT NULL REFERENCES users(id),
          kind TEXT NOT NULL,
          title TEXT NOT NULL,
          description TEXT DEFAULT '',
          stored_name TEXT DEFAULT '',
          original_name TEXT DEFAULT '',
          mime TEXT DEFAULT '',
          size INTEGER NOT NULL DEFAULT 0,
          quiz_json TEXT DEFAULT '',
          created_at TEXT NOT NULL
        );
        """
    )
    columns = {row[1] for row in conn.execute("PRAGMA table_info(lessons)")}
    if "library_item_id" not in columns:
        conn.execute("ALTER TABLE lessons ADD COLUMN library_item_id INTEGER")


def unique_username(conn, base: str) -> str:
    username = slugify(base) or "member"
    candidate = username
    number = 2
    while one(conn, "SELECT id FROM users WHERE username = ?", (candidate,)):
        candidate = f"{username}-{number}"
        number += 1
    return candidate


def sync_progress(conn, user_id: int, course_id: int, lesson_id: int | None = None) -> int:
    progress = progress_percent(conn, user_id, course_id)
    if lesson_id:
        conn.execute(
            "UPDATE enrollments SET progress = ?, current_lesson_id = ? WHERE user_id = ? AND course_id = ?",
            (progress, lesson_id, user_id, course_id),
        )
    else:
        conn.execute(
            "UPDATE enrollments SET progress = ? WHERE user_id = ? AND course_id = ?",
            (progress, user_id, course_id),
        )
    return progress


def log_event(conn, kind: str, user_id: int | None = None, course_id: int | None = None, created_at: str | None = None) -> None:
    conn.execute(
        "INSERT INTO events (kind, user_id, course_id, created_at) VALUES (?, ?, ?, ?)",
        (kind, user_id, course_id, created_at or now()),
    )


def create_user(email: str, password: str, full_name: str, role: str = "student", headline: str = "", created_at: str | None = None) -> dict:
    email = email.strip().lower()
    if "@" not in email or len(password) < 4 or len(full_name.strip()) < 2:
        raise ValueError("Use a real name, a valid email, and a password of at least 4 characters.")
    if role not in {"admin", "instructor", "student", "moderator"}:
        raise ValueError("Unknown role.")
    with connect() as conn:
        if one(conn, "SELECT id FROM users WHERE email = ?", (email,)):
            raise ValueError("An account with that email already exists.")
        stamp = created_at or now()
        username = unique_username(conn, email.split("@", 1)[0])
        cur = conn.execute(
            "INSERT INTO users (email, username, password_hash, full_name, role, headline, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
            (email, username, hash_password(password), full_name.strip(), role, headline, stamp),
        )
        log_event(conn, "signup", cur.lastrowid, None, stamp)
        user = public_user(one(conn, "SELECT * FROM users WHERE id = ?", (cur.lastrowid,)))
    return user


def authenticate(email: str, password: str) -> dict | None:
    with connect() as conn:
        row = one(conn, "SELECT * FROM users WHERE email = ?", (email.strip().lower(),))
    if not row or not verify_password(password, row["password_hash"]):
        return None
    return public_user(row)


def user_by_id(user_id: int) -> dict | None:
    with connect() as conn:
        return public_user(one(conn, "SELECT * FROM users WHERE id = ?", (user_id,)))


def user_count() -> int:
    with connect() as conn:
        return conn.execute("SELECT COUNT(*) AS n FROM users").fetchone()["n"]


def list_users() -> list[dict]:
    with connect() as conn:
        return [public_user(row) for row in many(conn, "SELECT * FROM users ORDER BY created_at DESC")]


def set_role(user_id: int, role: str) -> dict:
    if role not in {"admin", "instructor", "student", "moderator"}:
        raise ValueError("Unknown role.")
    with connect() as conn:
        target = one(conn, "SELECT * FROM users WHERE id = ?", (user_id,))
        if not target:
            raise LookupError("Person not found.")
        if target["role"] == "admin" and role != "admin":
            admins = conn.execute("SELECT COUNT(*) AS n FROM users WHERE role = 'admin'").fetchone()["n"]
            if admins <= 1:
                raise ValueError("Keep at least one administrator.")
        conn.execute("UPDATE users SET role = ? WHERE id = ?", (role, user_id))
        return public_user(one(conn, "SELECT * FROM users WHERE id = ?", (user_id,)))


def staff(user: dict) -> bool:
    return user["role"] in {"admin", "instructor", "moderator"}


def can_edit_course(user: dict, course: dict) -> bool:
    return user["role"] == "admin" or (user["role"] == "instructor" and course["instructor_id"] == user["id"])


def course_row(conn, course_id: int) -> dict:
    row = one(
        conn,
        """
        SELECT c.*, u.full_name AS instructor_name, u.headline AS instructor_headline,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS learners
        FROM courses c JOIN users u ON u.id = c.instructor_id
        WHERE c.id = ?
        """,
        (course_id,),
    )
    if not row:
        raise LookupError("Course not found.")
    return row


def course_by_slug(conn, slug: str) -> dict:
    row = one(conn, "SELECT id FROM courses WHERE slug = ?", (slug,))
    if not row:
        raise LookupError("Course not found.")
    return course_row(conn, row["id"])


def outline(conn, course_id: int, with_content: bool = False, with_answers: bool = False) -> list[dict]:
    chapters = many(conn, "SELECT * FROM chapters WHERE course_id = ? ORDER BY position, id", (course_id,))
    for chapter in chapters:
        lessons = many(conn, "SELECT * FROM lessons WHERE chapter_id = ? ORDER BY position, id", (chapter["id"],))
        cleaned = []
        for lesson in lessons:
            item = {
                "id": lesson["id"],
                "title": lesson["title"],
                "kind": lesson["kind"],
                "minutes": lesson["minutes"],
                "position": lesson["position"],
            }
            if with_content:
                item["body"] = lesson["body"]
                item["video_url"] = lesson["video_url"]
                item["quiz"] = quiz_payload(conn, lesson["quiz_id"], with_answers) if lesson["quiz_id"] else None
                item["assignment"] = assignment_payload(conn, lesson["assignment_id"]) if lesson["assignment_id"] else None
            cleaned.append(item)
        chapter["lessons"] = cleaned
    return chapters


def quiz_payload(conn, quiz_id: int, with_answers: bool = False, user_id: int | None = None) -> dict | None:
    quiz = one(conn, "SELECT * FROM quizzes WHERE id = ?", (quiz_id,))
    if not quiz:
        return None
    questions = []
    for question in many(conn, "SELECT * FROM questions WHERE quiz_id = ? ORDER BY position, id", (quiz_id,)):
        item = {
            "id": question["id"],
            "prompt": question["prompt"],
            "options": json.loads(question["options_json"]),
        }
        if with_answers:
            item["answer_index"] = question["answer_index"]
            item["explanation"] = question["explanation"]
        questions.append(item)
    payload = {
        "id": quiz["id"],
        "title": quiz["title"],
        "passing_score": quiz["passing_score"],
        "max_attempts": quiz.get("max_attempts") or 0,
        "duration_minutes": quiz.get("duration_minutes") or 0,
        "show_answers": bool(quiz.get("show_answers", 1)),
        "questions": questions,
    }
    if user_id:
        attempt = one(
            conn,
            "SELECT * FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1",
            (quiz_id, user_id),
        )
        if attempt:
            payload["last_attempt"] = {
                "score": attempt["score"],
                "passed": bool(attempt["passed"]),
                "submitted_at": attempt["submitted_at"],
                "answers": json.loads(attempt["answers_json"]),
            }
            if not with_answers and quiz.get("show_answers", 1):
                for question in questions:
                    raw = one(conn, "SELECT answer_index, explanation FROM questions WHERE id = ?", (question["id"],))
                    question["answer_index"] = raw["answer_index"]
                    question["explanation"] = raw["explanation"]
    return payload


def assignment_payload(conn, assignment_id: int, user_id: int | None = None) -> dict | None:
    row = one(conn, "SELECT * FROM assignments WHERE id = ?", (assignment_id,))
    if not row:
        return None
    payload = {
        "id": row["id"],
        "title": row["title"],
        "instructions": row["instructions"],
        "max_score": row["max_score"],
    }
    if user_id:
        sub = one(
            conn,
            "SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1",
            (assignment_id, user_id),
        )
        payload["submission"] = (
            {
                "id": sub["id"],
                "body": sub["body"],
                "status": sub["status"],
                "score": sub["score"],
                "feedback": sub["feedback"],
                "submitted_at": sub["submitted_at"],
            }
            if sub
            else None
        )
    return payload


def progress_percent(conn, user_id: int, course_id: int) -> int:
    total = conn.execute(
        "SELECT COUNT(*) AS n FROM lessons l JOIN chapters c ON c.id = l.chapter_id WHERE c.course_id = ?",
        (course_id,),
    ).fetchone()["n"]
    if not total:
        return 0
    done = conn.execute(
        """
        SELECT COUNT(*) AS n FROM lesson_progress p
        JOIN lessons l ON l.id = p.lesson_id
        JOIN chapters c ON c.id = l.chapter_id
        WHERE c.course_id = ? AND p.user_id = ? AND p.completed = 1
        """,
        (course_id, user_id),
    ).fetchone()["n"]
    return round(done * 100 / total)


def list_courses(query: str = "", category: str = "", include_drafts: bool = False, instructor_id: int | None = None) -> list[dict]:
    sql = """
        SELECT c.*, u.full_name AS instructor_name,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS learners,
               (SELECT COUNT(*) FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id WHERE ch.course_id = c.id) AS lesson_count
        FROM courses c JOIN users u ON u.id = c.instructor_id
        WHERE c.deleted_at IS NULL
    """
    args: list = []
    if not include_drafts:
        sql += " AND c.status = 'published'"
    if instructor_id:
        sql += " AND c.instructor_id = ?"
        args.append(instructor_id)
    if category:
        sql += " AND c.category = ?"
        args.append(category)
    if query:
        sql += " AND (c.title LIKE ? OR c.summary LIKE ? OR c.category LIKE ?)"
        like = f"%{query}%"
        args.extend([like, like, like])
    sql += " ORDER BY c.created_at DESC"
    with connect() as conn:
        return many(conn, sql, args)


def get_public_course(slug: str, user: dict | None) -> dict:
    with connect() as conn:
        course = course_by_slug(conn, slug)
        if course.get("deleted_at") and not (user and can_edit_course(user, course)):
            raise LookupError("Course not found.")
        if course["status"] != "published" and not (user and can_edit_course(user, course)):
            raise LookupError("Course not found.")
        enrolled = False
        progress = 0
        if user:
            enrolled = bool(one(conn, "SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?", (course["id"], user["id"])))
            progress = progress_percent(conn, user["id"], course["id"]) if enrolled else 0
        return {
            "course": course,
            "chapters": outline(conn, course["id"]),
            "enrolled": enrolled,
            "progress": progress,
        }


def unique_slug(conn, title: str) -> str:
    base = slugify(title)
    slug = base
    n = 2
    while one(conn, "SELECT id FROM courses WHERE slug = ?", (slug,)):
        slug = f"{base}-{n}"
        n += 1
    return slug


def create_course(user: dict, payload: dict) -> dict:
    if user["role"] not in {"admin", "instructor"}:
        raise PermissionError("Instructors upload course materials. Learners open assigned classes.")
    title = (payload.get("title") or "").strip()
    if len(title) < 3:
        raise ValueError("Give the course a title.")
    with connect() as conn:
        cur = conn.execute(
            """
            INSERT INTO courses (title, slug, summary, description, category, level, status, instructor_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?)
            """,
            (
                title,
                unique_slug(conn, title),
                (payload.get("summary") or "").strip(),
                (payload.get("description") or "").strip(),
                (payload.get("category") or "General").strip(),
                (payload.get("level") or "Foundation").strip(),
                user["id"] if user["role"] != "admin" or not payload.get("instructor_id") else payload.get("instructor_id") or user["id"],
                now(),
            ),
        )
        return course_row(conn, cur.lastrowid)


def update_course(user: dict, course_id: int, payload: dict) -> dict:
    with connect() as conn:
        course = course_row(conn, course_id)
        if not can_edit_course(user, course):
            raise PermissionError("You cannot edit this course.")
        fields = []
        args = []
        for key in ("title", "summary", "description", "category", "level"):
            if key in payload:
                fields.append(f"{key} = ?")
                args.append((payload.get(key) or "").strip())
        if "title" in payload and len((payload.get("title") or "").strip()) < 3:
            raise ValueError("Give the course a title.")
        if "status" in payload:
            if payload["status"] not in {"draft", "published"}:
                raise ValueError("Status must be draft or published.")
            if payload["status"] == "published":
                lessons = conn.execute(
                    "SELECT COUNT(*) AS n FROM lessons l JOIN chapters c ON c.id = l.chapter_id WHERE c.course_id = ?",
                    (course_id,),
                ).fetchone()["n"]
                if lessons < 1:
                    raise ValueError("Add at least one lesson before publishing.")
            fields.append("status = ?")
            args.append(payload["status"])
        if fields:
            args.append(course_id)
            conn.execute(f"UPDATE courses SET {', '.join(fields)} WHERE id = ?", args)
        return course_row(conn, course_id)


def studio_course(user: dict, course_id: int) -> dict:
    with connect() as conn:
        course = course_row(conn, course_id)
        if not can_edit_course(user, course):
            raise PermissionError("You cannot edit this course.")
        return {"course": course, "chapters": outline(conn, course_id, with_content=True, with_answers=True)}


def add_chapter(user: dict, course_id: int, title: str) -> dict:
    title = title.strip()
    if len(title) < 2:
        raise ValueError("Name the chapter.")
    with connect() as conn:
        course = course_row(conn, course_id)
        if not can_edit_course(user, course):
            raise PermissionError("You cannot edit this course.")
        position = conn.execute("SELECT COALESCE(MAX(position), 0) + 1 AS n FROM chapters WHERE course_id = ?", (course_id,)).fetchone()["n"]
        cur = conn.execute(
            "INSERT INTO chapters (course_id, title, position) VALUES (?, ?, ?)",
            (course_id, title, position),
        )
        return one(conn, "SELECT * FROM chapters WHERE id = ?", (cur.lastrowid,))


def add_lesson(user: dict, payload: dict) -> dict:
    kind = payload.get("kind") or "text"
    if kind not in {"text", "video", "quiz", "assignment"}:
        raise ValueError("Lesson type must be text, video, quiz, or assignment.")
    title = (payload.get("title") or "").strip()
    if len(title) < 2:
        raise ValueError("Name the lesson.")
    with connect() as conn:
        chapter = one(conn, "SELECT * FROM chapters WHERE id = ?", (payload.get("chapter_id"),))
        if not chapter:
            raise LookupError("Chapter not found.")
        course = course_row(conn, chapter["course_id"])
        if not can_edit_course(user, course):
            raise PermissionError("You cannot edit this course.")
        quiz_id = None
        assignment_id = None
        if kind == "quiz":
            quiz = payload.get("quiz") or {}
            questions = quiz.get("questions") or []
            if len(questions) < 1:
                raise ValueError("A quiz needs at least one question.")
            cur = conn.execute(
                "INSERT INTO quizzes (course_id, title, passing_score) VALUES (?, ?, ?)",
                (course["id"], (quiz.get("title") or title).strip(), int(quiz.get("passing_score") or 70)),
            )
            quiz_id = cur.lastrowid
            for index, question in enumerate(questions, start=1):
                options = [str(option).strip() for option in (question.get("options") or []) if str(option).strip()]
                if len(options) < 2:
                    raise ValueError("Each question needs at least two choices.")
                answer = int(question.get("answer_index") or 0)
                if answer < 0 or answer >= len(options):
                    raise ValueError("Mark a correct choice for every question.")
                conn.execute(
                    """
                    INSERT INTO questions (quiz_id, prompt, options_json, answer_index, explanation, position)
                    VALUES (?, ?, ?, ?, ?, ?)
                    """,
                    (
                        quiz_id,
                        (question.get("prompt") or "").strip() or "Question",
                        json.dumps(options),
                        answer,
                        (question.get("explanation") or "").strip(),
                        index,
                    ),
                )
        if kind == "assignment":
            task = payload.get("assignment") or {}
            cur = conn.execute(
                "INSERT INTO assignments (course_id, title, instructions, max_score) VALUES (?, ?, ?, ?)",
                (
                    course["id"],
                    (task.get("title") or title).strip(),
                    (task.get("instructions") or payload.get("body") or "").strip(),
                    int(task.get("max_score") or 100),
                ),
            )
            assignment_id = cur.lastrowid
        position = conn.execute(
            "SELECT COALESCE(MAX(position), 0) + 1 AS n FROM lessons WHERE chapter_id = ?",
            (chapter["id"],),
        ).fetchone()["n"]
        cur = conn.execute(
            """
            INSERT INTO lessons (chapter_id, title, kind, body, video_url, minutes, position, quiz_id, assignment_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                chapter["id"],
                title,
                kind,
                (payload.get("body") or "").strip(),
                (payload.get("video_url") or "").strip(),
                int(payload.get("minutes") or 8),
                position,
                quiz_id,
                assignment_id,
            ),
        )
        return one(conn, "SELECT * FROM lessons WHERE id = ?", (cur.lastrowid,))


def assign_course(actor: dict, course_id: int, email: str) -> dict:
    if actor["role"] not in {"admin", "instructor"}:
        raise PermissionError("Only an instructor or administrator can assign a class.")
    email = email.strip().lower()
    with connect() as conn:
        course = course_row(conn, course_id)
        if not can_edit_course(actor, course):
            raise PermissionError("You can assign learners to courses you teach.")
        person = one(conn, "SELECT * FROM users WHERE email = ?", (email,))
        if not person:
            raise LookupError("No account uses that email. Create the learner first.")
        if person["role"] != "student":
            raise ValueError("Assign classes to learners. Instructors and administrators are not enrolled this way.")
        enroll_silent(conn, person["id"], course_id)
        return {"enrolled": True, "user": public_user(person), "course_id": course_id}


def enroll(user: dict, course_id: int) -> dict:
    if user["role"] == "student":
        raise PermissionError("A learner opens classes an instructor has assigned.")
    with connect() as conn:
        course = course_row(conn, course_id)
        if course["status"] != "published":
            raise ValueError("This course is not open for enrollment.")
        if not one(conn, "SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?", (course_id, user["id"])):
            stamp = now()
            conn.execute(
                "INSERT INTO enrollments (course_id, user_id, enrolled_at) VALUES (?, ?, ?)",
                (course_id, user["id"], stamp),
            )
            log_event(conn, "enrollment", user["id"], course_id, stamp)
        return {"enrolled": True, "progress": progress_percent(conn, user["id"], course_id)}


def learn(user: dict, slug: str) -> dict:
    with connect() as conn:
        course = course_by_slug(conn, slug)
        enrolled = bool(one(conn, "SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?", (course["id"], user["id"])))
        editor = can_edit_course(user, course)
        if course["status"] != "published" and not editor:
            raise LookupError("Course not found.")
        if not enrolled and not editor:
            raise PermissionError("Enroll to open the lessons.")
        chapters = outline(conn, course["id"], with_content=True, with_answers=editor)
        done = {
            row["lesson_id"]
            for row in many(conn, "SELECT lesson_id FROM lesson_progress WHERE user_id = ? AND completed = 1", (user["id"],))
        }
        for chapter in chapters:
            for lesson in chapter["lessons"]:
                lesson["completed"] = lesson["id"] in done
                if lesson.get("quiz"):
                    lesson["quiz"] = quiz_payload(conn, lesson["quiz"]["id"], editor, user["id"])
                if lesson.get("assignment"):
                    lesson["assignment"] = assignment_payload(conn, lesson["assignment"]["id"], user["id"])
        return {
            "course": course,
            "chapters": chapters,
            "enrolled": enrolled or editor,
            "progress": progress_percent(conn, user["id"], course["id"]),
        }


def complete_lesson(user: dict, lesson_id: int) -> dict:
    with connect() as conn:
        lesson = one(
            conn,
            """
            SELECT l.*, c.course_id FROM lessons l
            JOIN chapters c ON c.id = l.chapter_id WHERE l.id = ?
            """,
            (lesson_id,),
        )
        if not lesson:
            raise LookupError("Lesson not found.")
        if not one(conn, "SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?", (lesson["course_id"], user["id"])):
            if not can_edit_course(user, course_row(conn, lesson["course_id"])):
                raise PermissionError("Enroll before marking lessons complete.")
            enroll_silent(conn, user["id"], lesson["course_id"])
        conn.execute(
            """
            INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at)
            VALUES (?, ?, 1, ?)
            ON CONFLICT(user_id, lesson_id) DO UPDATE SET completed = 1, completed_at = excluded.completed_at
            """,
            (user["id"], lesson_id, now()),
        )
        progress = sync_progress(conn, user["id"], lesson["course_id"], lesson_id)
        return {"completed": True, "progress": progress}


def enroll_silent(conn, user_id: int, course_id: int) -> None:
    if not one(conn, "SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?", (course_id, user_id)):
        stamp = now()
        conn.execute(
            "INSERT INTO enrollments (course_id, user_id, enrolled_at) VALUES (?, ?, ?)",
            (course_id, user_id, stamp),
        )
        log_event(conn, "enrollment", user_id, course_id, stamp)


def submit_quiz(user: dict, quiz_id: int, answers: dict) -> dict:
    with connect() as conn:
        quiz = one(conn, "SELECT * FROM quizzes WHERE id = ?", (quiz_id,))
        if not quiz:
            raise LookupError("Quiz not found.")
        questions = many(conn, "SELECT * FROM questions WHERE quiz_id = ? ORDER BY position", (quiz_id,))
        if not questions:
            raise ValueError("This quiz has no questions.")
        correct = 0
        review = {}
        for question in questions:
            chosen = answers.get(str(question["id"]), answers.get(question["id"]))
            try:
                chosen = int(chosen)
            except (TypeError, ValueError):
                chosen = -1
            is_right = chosen == question["answer_index"]
            correct += int(is_right)
            review[str(question["id"])] = {"chosen": chosen, "correct": is_right}
        score = round(correct * 100 / len(questions))
        passed = score >= quiz["passing_score"]
        max_attempts = quiz.get("max_attempts") or 0
        if max_attempts:
            taken = conn.execute(
                "SELECT COUNT(*) AS n FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?",
                (quiz_id, user["id"]),
            ).fetchone()["n"]
            if taken >= max_attempts:
                raise ValueError("You have used every attempt on this quiz.")
        conn.execute(
            """
            INSERT INTO quiz_attempts (quiz_id, user_id, score, passed, answers_json, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?)
            """,
            (quiz_id, user["id"], score, int(passed), json.dumps(review), now()),
        )
        if passed:
            lesson = one(conn, "SELECT id, chapter_id FROM lessons WHERE quiz_id = ?", (quiz_id,))
            if lesson:
                chapter = one(conn, "SELECT course_id FROM chapters WHERE id = ?", (lesson["chapter_id"],))
                enroll_silent(conn, user["id"], chapter["course_id"])
                conn.execute(
                    """
                    INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at)
                    VALUES (?, ?, 1, ?)
                    ON CONFLICT(user_id, lesson_id) DO UPDATE SET completed = 1, completed_at = excluded.completed_at
                    """,
                    (user["id"], lesson["id"], now()),
                )
                sync_progress(conn, user["id"], chapter["course_id"], lesson["id"])
        payload = quiz_payload(conn, quiz_id, False, user["id"])
        payload["score"] = score
        payload["passed"] = passed
        return payload


def submit_assignment(user: dict, assignment_id: int, body: str) -> dict:
    body = body.strip()
    if len(body) < 12:
        raise ValueError("Write a little more before handing this in.")
    with connect() as conn:
        assignment = one(conn, "SELECT * FROM assignments WHERE id = ?", (assignment_id,))
        if not assignment:
            raise LookupError("Assignment not found.")
        existing = one(
            conn,
            "SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1",
            (assignment_id, user["id"]),
        )
        if existing and existing["status"] == "graded":
            raise ValueError("This assignment is already graded.")
        if existing:
            conn.execute(
                "UPDATE submissions SET body = ?, status = 'submitted', submitted_at = ?, score = NULL, feedback = '' WHERE id = ?",
                (body, now(), existing["id"]),
            )
        else:
            conn.execute(
                "INSERT INTO submissions (assignment_id, user_id, body, status, submitted_at) VALUES (?, ?, ?, 'submitted', ?)",
                (assignment_id, user["id"], body, now()),
            )
        lesson = one(conn, "SELECT id FROM lessons WHERE assignment_id = ?", (assignment_id,))
        if lesson:
            chapter = one(
                conn,
                "SELECT c.course_id FROM lessons l JOIN chapters c ON c.id = l.chapter_id WHERE l.id = ?",
                (lesson["id"],),
            )
            enroll_silent(conn, user["id"], chapter["course_id"])
            conn.execute(
                """
                INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at)
                VALUES (?, ?, 1, ?)
                ON CONFLICT(user_id, lesson_id) DO UPDATE SET completed = 1, completed_at = excluded.completed_at
                """,
                (user["id"], lesson["id"], now()),
            )
        return assignment_payload(conn, assignment_id, user["id"])


def list_submissions(user: dict) -> list[dict]:
    with connect() as conn:
        if user["role"] == "admin":
            clause, args = "1 = 1", []
        elif user["role"] == "instructor":
            clause, args = "c.instructor_id = ?", [user["id"]]
        else:
            clause, args = "s.user_id = ?", [user["id"]]
        return many(
            conn,
            f"""
            SELECT s.*, a.title AS assignment_title, a.max_score, c.title AS course_title, c.id AS course_id,
                   u.full_name AS student_name
            FROM submissions s
            JOIN assignments a ON a.id = s.assignment_id
            JOIN courses c ON c.id = a.course_id
            JOIN users u ON u.id = s.user_id
            WHERE {clause}
            ORDER BY s.submitted_at DESC
            """,
            args,
        )


def grade_submission(user: dict, submission_id: int, score: int, feedback: str) -> dict:
    with connect() as conn:
        row = one(
            conn,
            """
            SELECT s.*, a.max_score, c.instructor_id FROM submissions s
            JOIN assignments a ON a.id = s.assignment_id
            JOIN courses c ON c.id = a.course_id
            WHERE s.id = ?
            """,
            (submission_id,),
        )
        if not row:
            raise LookupError("Submission not found.")
        if user["role"] != "admin" and row["instructor_id"] != user["id"]:
            raise PermissionError("You cannot grade this submission.")
        try:
            score = int(score)
        except (TypeError, ValueError):
            raise ValueError("Add a score.") from None
        if score < 0 or score > row["max_score"]:
            raise ValueError(f"Score must be between 0 and {row['max_score']}.")
        conn.execute(
            "UPDATE submissions SET score = ?, feedback = ?, status = 'graded' WHERE id = ?",
            (score, feedback.strip(), submission_id),
        )
        return one(conn, "SELECT * FROM submissions WHERE id = ?", (submission_id,))


def my_learning(user: dict) -> list[dict]:
    with connect() as conn:
        rows = many(
            conn,
            """
            SELECT c.*, u.full_name AS instructor_name, e.enrolled_at
            FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            JOIN users u ON u.id = c.instructor_id
            WHERE e.user_id = ? AND c.deleted_at IS NULL AND COALESCE(e.active, 1) = 1
            ORDER BY e.enrolled_at DESC
            """,
            (user["id"],),
        )
        for row in rows:
            row["progress"] = progress_percent(conn, user["id"], row["id"])
        return rows


def my_classes(user: dict) -> dict:
    courses = my_learning(user)
    batches = [row for row in list_batches(user) if row.get("joined")]
    return {"courses": courses, "batches": batches}


def my_assessments(user: dict) -> dict:
    with connect() as conn:
        quizzes = many(
            conn,
            """
            SELECT q.id, q.title, q.passing_score, c.title AS course_title, c.slug,
                   l.title AS lesson_title
            FROM quizzes q
            JOIN courses c ON c.id = q.course_id
            JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
            LEFT JOIN lessons l ON l.quiz_id = q.id
            ORDER BY c.title
            """,
            (user["id"],),
        )
        for quiz in quizzes:
            attempt = one(
                conn,
                "SELECT score, passed, submitted_at FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1",
                (quiz["id"], user["id"]),
            )
            quiz["last_attempt"] = dict(attempt) if attempt else None
            if quiz["last_attempt"]:
                quiz["last_attempt"]["passed"] = bool(quiz["last_attempt"]["passed"])
        submissions = many(
            conn,
            """
            SELECT s.*, a.title AS assignment_title, a.max_score, c.title AS course_title, c.slug
            FROM submissions s
            JOIN assignments a ON a.id = s.assignment_id
            JOIN courses c ON c.id = a.course_id
            WHERE s.user_id = ?
            ORDER BY s.submitted_at DESC
            """,
            (user["id"],),
        )
        return {"quizzes": quizzes, "submissions": submissions}


def list_batches(user: dict | None) -> list[dict]:
    with connect() as conn:
        rows = many(
            conn,
            """
            SELECT b.*, c.title AS course_title, c.slug AS course_slug, u.full_name AS instructor_name,
                   (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id) AS seats_taken
            FROM batches b
            JOIN courses c ON c.id = b.course_id
            JOIN users u ON u.id = b.instructor_id
            ORDER BY b.start_date
            """
        )
        if user:
            mine = {row["batch_id"] for row in many(conn, "SELECT batch_id FROM batch_students WHERE user_id = ?", (user["id"],))}
            for row in rows:
                row["joined"] = row["id"] in mine
                if row["joined"]:
                    row["progress"] = progress_percent(conn, user["id"], row["course_id"])
        return rows


def batch_detail(user: dict | None, batch_id: int) -> dict:
    with connect() as conn:
        batch = one(
            conn,
            """
            SELECT b.*, c.title AS course_title, c.slug AS course_slug, u.full_name AS instructor_name
            FROM batches b
            JOIN courses c ON c.id = b.course_id
            JOIN users u ON u.id = b.instructor_id
            WHERE b.id = ?
            """,
            (batch_id,),
        )
        if not batch:
            raise LookupError("Batch not found.")
        students = many(
            conn,
            """
            SELECT u.id, u.full_name, u.email, u.headline, bs.joined_at
            FROM batch_students bs JOIN users u ON u.id = bs.user_id
            WHERE bs.batch_id = ? ORDER BY u.full_name
            """,
            (batch_id,),
        )
        total = conn.execute(
            "SELECT COUNT(*) AS n FROM lessons l JOIN chapters c ON c.id = l.chapter_id WHERE c.course_id = ?",
            (batch["course_id"],),
        ).fetchone()["n"]
        classes = many(conn, "SELECT id FROM live_classes WHERE batch_id = ?", (batch_id,))
        class_ids = [row["id"] for row in classes]
        for student in students:
            done = conn.execute(
                """
                SELECT COUNT(*) AS n FROM lesson_progress p
                JOIN lessons l ON l.id = p.lesson_id
                JOIN chapters c ON c.id = l.chapter_id
                WHERE c.course_id = ? AND p.user_id = ? AND p.completed = 1
                """,
                (batch["course_id"], student["id"]),
            ).fetchone()["n"]
            passed = conn.execute(
                """
                SELECT COUNT(DISTINCT qa.quiz_id) AS n FROM quiz_attempts qa
                JOIN quizzes q ON q.id = qa.quiz_id
                WHERE q.course_id = ? AND qa.user_id = ? AND qa.passed = 1
                """,
                (batch["course_id"], student["id"]),
            ).fetchone()["n"]
            attended = 0
            if class_ids:
                marks = ",".join("?" for _ in class_ids)
                attended = conn.execute(
                    f"SELECT COUNT(*) AS n FROM attendance WHERE user_id = ? AND live_class_id IN ({marks})",
                    [student["id"], *class_ids],
                ).fetchone()["n"]
            last = one(
                conn,
                """
                SELECT MAX(completed_at) AS at FROM lesson_progress p
                JOIN lessons l ON l.id = p.lesson_id
                JOIN chapters c ON c.id = l.chapter_id
                WHERE c.course_id = ? AND p.user_id = ?
                """,
                (batch["course_id"], student["id"]),
            )
            progress = round(done * 100 / total) if total else 0
            attendance_rate = round(attended * 100 / len(class_ids)) if class_ids else 0
            student["lessons_done"] = done
            student["lessons_total"] = total
            student["progress"] = progress
            student["quizzes_passed"] = passed
            student["live_attended"] = attended
            student["engagement"] = round(progress * 0.6 + attendance_rate * 0.25 + min(passed, 1) * 15)
            student["last_active"] = last["at"] if last else None
            if not user or user["role"] not in {"admin", "instructor", "moderator"}:
                student.pop("email", None)
        batch["joined"] = bool(user and one(conn, "SELECT 1 FROM batch_students WHERE batch_id = ? AND user_id = ?", (batch_id, user["id"])))
        batch["seats_taken"] = len(students)
        return {"batch": batch, "students": students, "live_classes": live_for_batch(conn, batch_id)}


def live_for_batch(conn, batch_id: int) -> list[dict]:
    return many(
        conn,
        """
        SELECT lc.*, u.full_name AS host_name,
               (SELECT COUNT(*) FROM attendance a WHERE a.live_class_id = lc.id) AS attendance
        FROM live_classes lc JOIN users u ON u.id = lc.host_id
        WHERE lc.batch_id = ? ORDER BY lc.starts_at
        """,
        (batch_id,),
    )


def create_batch(user: dict, payload: dict) -> dict:
    if user["role"] not in {"admin", "instructor"}:
        raise PermissionError("Only instructors can open a batch.")
    title = (payload.get("title") or "").strip()
    if len(title) < 3:
        raise ValueError("Name the batch.")
    with connect() as conn:
        course = course_row(conn, int(payload.get("course_id")))
        if not can_edit_course(user, course) and user["role"] != "admin":
            raise PermissionError("Open batches for your own courses.")
        cur = conn.execute(
            """
            INSERT INTO batches (title, course_id, instructor_id, start_date, end_date, seat_limit, description, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
            """,
            (
                title,
                course["id"],
                course["instructor_id"],
                payload.get("start_date") or "",
                payload.get("end_date") or "",
                int(payload.get("seat_limit") or 16),
                (payload.get("description") or "").strip(),
            ),
        )
        return one(conn, "SELECT * FROM batches WHERE id = ?", (cur.lastrowid,))


def join_batch(user: dict, batch_id: int) -> dict:
    with connect() as conn:
        batch = one(conn, "SELECT * FROM batches WHERE id = ?", (batch_id,))
        if not batch:
            raise LookupError("Batch not found.")
        if batch["status"] != "open":
            raise ValueError("This batch is closed.")
        taken = conn.execute("SELECT COUNT(*) AS n FROM batch_students WHERE batch_id = ?", (batch_id,)).fetchone()["n"]
        if taken >= batch["seat_limit"] and not one(conn, "SELECT 1 FROM batch_students WHERE batch_id = ? AND user_id = ?", (batch_id, user["id"])):
            raise ValueError("This batch is full.")
        if not one(conn, "SELECT 1 FROM batch_students WHERE batch_id = ? AND user_id = ?", (batch_id, user["id"])):
            conn.execute(
                "INSERT INTO batch_students (batch_id, user_id, joined_at) VALUES (?, ?, ?)",
                (batch_id, user["id"], now()),
            )
        enroll_silent(conn, user["id"], batch["course_id"])
    return batch_detail(user, batch_id)


def zoom_connected() -> bool:
    return bool(os.environ.get("ZOOM_ACCOUNT_ID") and os.environ.get("ZOOM_CLIENT_ID") and os.environ.get("ZOOM_CLIENT_SECRET"))


def zoom_access_token() -> str | None:
    account = os.environ.get("ZOOM_ACCOUNT_ID")
    client = os.environ.get("ZOOM_CLIENT_ID")
    secret = os.environ.get("ZOOM_CLIENT_SECRET")
    if not (account and client and secret):
        return None
    basic = base64.b64encode(f"{client}:{secret}".encode()).decode()
    url = "https://zoom.us/oauth/token?" + urllib.parse.urlencode(
        {"grant_type": "account_credentials", "account_id": account}
    )
    req = urllib.request.Request(url, data=b"", headers={"Authorization": f"Basic {basic}"}, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=20) as res:
            data = json.loads(res.read().decode())
        return data.get("access_token")
    except (urllib.error.URLError, TimeoutError, json.JSONDecodeError):
        return None


def create_zoom_meeting(title: str, starts_at: str, minutes: int) -> dict:
    token = zoom_access_token()
    if not token:
        meeting_id = str(80000000000 + secrets.randbelow(999999999))
        return {
            "provider": "demo",
            "zoom_meeting_id": meeting_id,
            "zoom_join_url": "",
            "zoom_start_url": "",
        }
    body = json.dumps(
        {
            "topic": title,
            "type": 2,
            "start_time": starts_at.replace("+00:00", "Z"),
            "duration": minutes,
            "timezone": "UTC",
            "settings": {"waiting_room": True, "join_before_host": False, "approval_type": 2},
        }
    ).encode()
    req = urllib.request.Request(
        "https://api.zoom.us/v2/users/me/meetings",
        data=body,
        headers={"Authorization": f"Bearer {token}", "Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=20) as res:
            data = json.loads(res.read().decode())
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode()[:240]
        raise ValueError(f"Zoom could not create the meeting. {detail}") from exc
    return {
        "provider": "zoom",
        "zoom_meeting_id": str(data.get("id", "")),
        "zoom_join_url": data.get("join_url", ""),
        "zoom_start_url": data.get("start_url", ""),
    }


def decorate_live(conn, row: dict, user: dict | None) -> dict:
    row["attendance"] = conn.execute(
        "SELECT COUNT(*) AS n FROM attendance WHERE live_class_id = ?",
        (row["id"],),
    ).fetchone()["n"]
    row["attending"] = False
    if user:
        row["attending"] = bool(
            one(conn, "SELECT 1 FROM attendance WHERE live_class_id = ? AND user_id = ?", (row["id"], user["id"]))
        )
    if row["provider"] != "zoom":
        row["zoom_start_url"] = ""
    elif user and user["id"] != row["host_id"] and user["role"] not in {"admin"}:
        row["zoom_start_url"] = ""
    return row


def list_live(user: dict | None) -> dict:
    with connect() as conn:
        rows = many(
            conn,
            """
            SELECT lc.*, u.full_name AS host_name, b.title AS batch_title, c.title AS course_title, c.slug AS course_slug
            FROM live_classes lc
            JOIN users u ON u.id = lc.host_id
            LEFT JOIN batches b ON b.id = lc.batch_id
            LEFT JOIN courses c ON c.id = lc.course_id
            ORDER BY lc.starts_at
            """
        )
        return {"zoom_connected": zoom_connected(), "classes": [decorate_live(conn, row, user) for row in rows]}


def create_live(user: dict, payload: dict) -> dict:
    if user["role"] not in {"admin", "instructor"}:
        raise PermissionError("Only instructors can schedule a live class.")
    title = (payload.get("title") or "").strip()
    starts_at = payload.get("starts_at") or ""
    if len(title) < 3 or not starts_at:
        raise ValueError("A live class needs a title and a start time.")
    minutes = int(payload.get("minutes") or 60)
    meeting = create_zoom_meeting(title, starts_at, minutes)
    with connect() as conn:
        batch_id = payload.get("batch_id") or None
        course_id = payload.get("course_id") or None
        if batch_id:
            batch = one(conn, "SELECT * FROM batches WHERE id = ?", (int(batch_id),))
            if not batch:
                raise LookupError("Batch not found.")
            course_id = batch["course_id"]
        cur = conn.execute(
            """
            INSERT INTO live_classes (
              batch_id, course_id, host_id, title, description, starts_at, minutes,
              provider, zoom_meeting_id, zoom_join_url, zoom_start_url, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
            """,
            (
                int(batch_id) if batch_id else None,
                int(course_id) if course_id else None,
                user["id"],
                title,
                (payload.get("description") or "").strip(),
                starts_at,
                minutes,
                meeting["provider"],
                meeting["zoom_meeting_id"],
                meeting["zoom_join_url"],
                meeting["zoom_start_url"],
            ),
        )
        row = one(
            conn,
            """
            SELECT lc.*, u.full_name AS host_name, b.title AS batch_title, c.title AS course_title, c.slug AS course_slug
            FROM live_classes lc
            JOIN users u ON u.id = lc.host_id
            LEFT JOIN batches b ON b.id = lc.batch_id
            LEFT JOIN courses c ON c.id = lc.course_id
            WHERE lc.id = ?
            """,
            (cur.lastrowid,),
        )
        return decorate_live(conn, row, user)


def set_live_status(user: dict, class_id: int, status: str) -> dict:
    if status not in {"scheduled", "live", "ended"}:
        raise ValueError("Unknown class status.")
    with connect() as conn:
        row = one(conn, "SELECT * FROM live_classes WHERE id = ?", (class_id,))
        if not row:
            raise LookupError("Class not found.")
        if user["role"] != "admin" and row["host_id"] != user["id"]:
            raise PermissionError("Only the host can change this class.")
        conn.execute("UPDATE live_classes SET status = ? WHERE id = ?", (status, class_id))
        full = one(
            conn,
            """
            SELECT lc.*, u.full_name AS host_name, b.title AS batch_title, c.title AS course_title, c.slug AS course_slug
            FROM live_classes lc
            JOIN users u ON u.id = lc.host_id
            LEFT JOIN batches b ON b.id = lc.batch_id
            LEFT JOIN courses c ON c.id = lc.course_id
            WHERE lc.id = ?
            """,
            (class_id,),
        )
        return decorate_live(conn, full, user)


def attend_live(user: dict, class_id: int) -> dict:
    with connect() as conn:
        row = one(conn, "SELECT * FROM live_classes WHERE id = ?", (class_id,))
        if not row:
            raise LookupError("Class not found.")
        if row["status"] == "ended":
            raise ValueError("This class has ended.")
        if row["course_id"] and user["role"] == "student":
            enrolled = one(conn, "SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?", (row["course_id"], user["id"]))
            in_batch = False
            if row["batch_id"]:
                in_batch = bool(one(conn, "SELECT 1 FROM batch_students WHERE batch_id = ? AND user_id = ?", (row["batch_id"], user["id"])))
            if not enrolled and not in_batch:
                raise PermissionError("Join the batch or enroll in the course to enter this room.")
        conn.execute(
            "INSERT INTO attendance (live_class_id, user_id, joined_at) VALUES (?, ?, ?) ON CONFLICT DO NOTHING",
            (class_id, user["id"], now()),
        )
        if row["status"] == "scheduled":
            conn.execute("UPDATE live_classes SET status = 'live' WHERE id = ? AND provider = 'demo'", (class_id,))
        full = one(
            conn,
            """
            SELECT lc.*, u.full_name AS host_name, b.title AS batch_title, c.title AS course_title, c.slug AS course_slug
            FROM live_classes lc
            JOIN users u ON u.id = lc.host_id
            LEFT JOIN batches b ON b.id = lc.batch_id
            LEFT JOIN courses c ON c.id = lc.course_id
            WHERE lc.id = ?
            """,
            (class_id,),
        )
        return decorate_live(conn, full, user)


def list_slots(user: dict | None) -> list[dict]:
    with connect() as conn:
        rows = many(
            conn,
            """
            SELECT s.*, i.full_name AS instructor_name, st.full_name AS student_name, c.title AS course_title, c.slug AS course_slug
            FROM eval_slots s
            JOIN users i ON i.id = s.instructor_id
            LEFT JOIN users st ON st.id = s.student_id
            LEFT JOIN courses c ON c.id = s.course_id
            ORDER BY s.starts_at
            """
        )
        if user and user["role"] == "student":
            for row in rows:
                if row["student_id"] not in (None, user["id"]) and row["status"] != "open":
                    row["notes"] = ""
                    row["outcome"] = ""
                    row["student_name"] = None
                    row["meeting_url"] = ""
        return rows


def generate_slots(user: dict, days: int = 10) -> list[dict]:
    if user["role"] not in {"admin", "instructor"}:
        raise PermissionError("Only instructors can publish evaluation hours.")
    instructor_id = user["id"]
    created = 0
    with connect() as conn:
        if user["role"] == "admin":
            instructor_id = user["id"]
        start = datetime.now(timezone.utc).replace(minute=0, second=0, microsecond=0)
        for offset in range(1, days + 1):
            day = start + timedelta(days=offset)
            if day.weekday() >= 5:
                continue
            for hour, minute in ((9, 30), (16, 0)):
                slot = day.replace(hour=hour, minute=minute).isoformat()
                if one(conn, "SELECT id FROM eval_slots WHERE instructor_id = ? AND starts_at = ?", (instructor_id, slot)):
                    continue
                conn.execute(
                    "INSERT INTO eval_slots (instructor_id, starts_at, minutes, status) VALUES (?, ?, 30, 'open')",
                    (instructor_id, slot),
                )
                created += 1
    return {"created": created, "slots": list_slots(user)}


def book_evaluation(user: dict, course_id: int) -> dict:
    with connect() as conn:
        course = course_row(conn, course_id)
        if not one(conn, "SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?", (course_id, user["id"])):
            raise PermissionError("Enroll in the course before booking an evaluation.")
        existing = one(
            conn,
            """
            SELECT * FROM eval_slots
            WHERE student_id = ? AND course_id = ? AND status = 'booked' AND starts_at >= ?
            """,
            (user["id"], course_id, now()),
        )
        if existing:
            raise ValueError("You already have an evaluation booked for this course.")
        slot = one(
            conn,
            """
            SELECT * FROM eval_slots
            WHERE instructor_id = ? AND status = 'open' AND starts_at >= ?
            ORDER BY starts_at LIMIT 1
            """,
            (course["instructor_id"], now()),
        )
        if not slot:
            slot = one(
                conn,
                "SELECT * FROM eval_slots WHERE status = 'open' AND starts_at >= ? ORDER BY starts_at LIMIT 1",
                (now(),),
            )
        if not slot:
            raise ValueError("No open evaluation times. An instructor needs to publish hours first.")
        meeting = create_zoom_meeting(f"Evaluation · {course['title']}", slot["starts_at"], slot["minutes"])
        meeting_url = meeting["zoom_join_url"] or f"/live?evaluation={slot['id']}"
        conn.execute(
            """
            UPDATE eval_slots
            SET status = 'booked', student_id = ?, course_id = ?, meeting_url = ?, meeting_provider = ?
            WHERE id = ?
            """,
            (user["id"], course_id, meeting_url, meeting["provider"], slot["id"]),
        )
        return one(
            conn,
            """
            SELECT s.*, i.full_name AS instructor_name, st.full_name AS student_name, c.title AS course_title
            FROM eval_slots s
            JOIN users i ON i.id = s.instructor_id
            LEFT JOIN users st ON st.id = s.student_id
            LEFT JOIN courses c ON c.id = s.course_id
            WHERE s.id = ?
            """,
            (slot["id"],),
        )


def close_evaluation(user: dict, slot_id: int, outcome: str, notes: str) -> dict:
    with connect() as conn:
        slot = one(conn, "SELECT * FROM eval_slots WHERE id = ?", (slot_id,))
        if not slot:
            raise LookupError("Evaluation not found.")
        if user["role"] != "admin" and slot["instructor_id"] != user["id"]:
            raise PermissionError("Only the instructor can close this evaluation.")
        if slot["status"] != "booked":
            raise ValueError("Only a booked evaluation can be closed.")
        conn.execute(
            "UPDATE eval_slots SET status = 'completed', outcome = ?, notes = ? WHERE id = ?",
            (outcome.strip() or "Complete", notes.strip(), slot_id),
        )
        return one(conn, "SELECT * FROM eval_slots WHERE id = ?", (slot_id,))


def release_evaluation(user: dict, slot_id: int) -> dict:
    with connect() as conn:
        slot = one(conn, "SELECT * FROM eval_slots WHERE id = ?", (slot_id,))
        if not slot:
            raise LookupError("Evaluation not found.")
        if user["id"] not in {slot["student_id"], slot["instructor_id"]} and user["role"] != "admin":
            raise PermissionError("You cannot release this time.")
        conn.execute(
            """
            UPDATE eval_slots
            SET status = 'open', student_id = NULL, course_id = NULL, meeting_url = '', outcome = '', notes = ''
            WHERE id = ?
            """,
            (slot_id,),
        )
        return one(conn, "SELECT * FROM eval_slots WHERE id = ?", (slot_id,))


def list_jobs(query: str = "") -> list[dict]:
    sql = """
        SELECT j.*, u.full_name AS posted_by_name,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS applicants
        FROM jobs j LEFT JOIN users u ON u.id = j.posted_by
        WHERE j.status = 'open'
    """
    args: list = []
    if query:
        sql += " AND (j.title LIKE ? OR j.company LIKE ? OR j.location LIKE ?)"
        like = f"%{query}%"
        args.extend([like, like, like])
    sql += " ORDER BY j.created_at DESC"
    with connect() as conn:
        return many(conn, sql, args)


def create_job(user: dict, payload: dict) -> dict:
    if not staff(user):
        raise PermissionError("Only the studio can post a role.")
    title = (payload.get("title") or "").strip()
    company = (payload.get("company") or "").strip()
    if len(title) < 3 or len(company) < 2:
        raise ValueError("A role needs a title and a company.")
    with connect() as conn:
        cur = conn.execute(
            """
            INSERT INTO jobs (title, company, location, job_type, description, posted_by, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'open', ?)
            """,
            (
                title,
                company,
                (payload.get("location") or "").strip(),
                (payload.get("job_type") or "Full-time").strip(),
                (payload.get("description") or "").strip(),
                user["id"],
                now(),
            ),
        )
        return one(conn, "SELECT * FROM jobs WHERE id = ?", (cur.lastrowid,))


def apply_job(user: dict, job_id: int, note: str) -> dict:
    with connect() as conn:
        job = one(conn, "SELECT * FROM jobs WHERE id = ? AND status = 'open'", (job_id,))
        if not job:
            raise LookupError("Role not found.")
        if one(conn, "SELECT id FROM applications WHERE job_id = ? AND user_id = ?", (job_id, user["id"])):
            raise ValueError("You already put your name forward for this role.")
        conn.execute(
            "INSERT INTO applications (job_id, user_id, note, applied_at) VALUES (?, ?, ?, ?)",
            (job_id, user["id"], note.strip(), now()),
        )
        return {"applied": True}


def my_applications(user: dict) -> list[int]:
    with connect() as conn:
        return [row["job_id"] for row in many(conn, "SELECT job_id FROM applications WHERE user_id = ?", (user["id"],))]


def day_span(days: int = 14) -> list[str]:
    today = datetime.now(timezone.utc).date()
    return [(today - timedelta(days=offset)).isoformat() for offset in range(days - 1, -1, -1)]


def analytics(user: dict) -> dict:
    if user["role"] not in {"admin", "instructor", "moderator"}:
        raise PermissionError("Insights are for the studio.")
    since = (datetime.now(timezone.utc) - timedelta(days=13)).date().isoformat()
    with connect() as conn:
        signups = many(conn, "SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS n FROM events WHERE kind = 'signup' AND substr(created_at, 1, 10) >= ? GROUP BY day", (since,))
        enroll_sql = "SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS n FROM events WHERE kind = 'enrollment' AND substr(created_at, 1, 10) >= ?"
        enroll_args = [since]
        if user["role"] == "instructor":
            enroll_sql += " AND course_id IN (SELECT id FROM courses WHERE instructor_id = ?)"
            enroll_args.append(user["id"])
        enroll_sql += " GROUP BY day"
        enrollments = many(conn, enroll_sql, enroll_args)
        signup_map = {row["day"]: row["n"] for row in signups}
        enroll_map = {row["day"]: row["n"] for row in enrollments}
        series = [
            {"day": day, "signups": signup_map.get(day, 0), "enrollments": enroll_map.get(day, 0)}
            for day in day_span(14)
        ]
        course_sql = """
            SELECT c.id, c.title, c.slug, c.status,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrollments
            FROM courses c
        """
        if user["role"] == "instructor":
            course_sql += " WHERE c.instructor_id = ?"
            courses = many(conn, course_sql, (user["id"],))
        else:
            courses = many(conn, course_sql)
        for course in courses:
            learners = many(conn, "SELECT user_id FROM enrollments WHERE course_id = ?", (course["id"],))
            if learners:
                course["avg_progress"] = round(sum(progress_percent(conn, row["user_id"], course["id"]) for row in learners) / len(learners))
            else:
                course["avg_progress"] = 0
        if user["role"] == "instructor":
            quiz_stats = one(
                conn,
                """
                SELECT COUNT(*) AS n, COALESCE(SUM(passed), 0) AS passed
                FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
                JOIN courses c ON c.id = q.course_id
                WHERE c.instructor_id = ?
                """,
                (user["id"],),
            )
            batch_stats = one(
                conn,
                """
                SELECT COUNT(*) AS batches,
                       COALESCE(SUM((SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id)), 0) AS seats
                FROM batches b JOIN courses c ON c.id = b.course_id
                WHERE c.instructor_id = ?
                """,
                (user["id"],),
            )
        else:
            quiz_stats = one(conn, "SELECT COUNT(*) AS n, COALESCE(SUM(passed), 0) AS passed FROM quiz_attempts")
            batch_stats = one(
                conn,
                "SELECT COUNT(*) AS batches, COALESCE((SELECT COUNT(*) FROM batch_students), 0) AS seats FROM batches",
            )
        feed_sql = """
            SELECT e.kind, e.created_at, u.full_name, c.title AS course_title
            FROM events e
            LEFT JOIN users u ON u.id = e.user_id
            LEFT JOIN courses c ON c.id = e.course_id
        """
        feed_args: list = []
        if user["role"] == "instructor":
            feed_sql += " WHERE e.kind = 'signup' OR c.instructor_id = ?"
            feed_args.append(user["id"])
        feed_sql += " ORDER BY e.created_at DESC LIMIT 12"
        feed = many(conn, feed_sql, feed_args)
        live_count = one(conn, "SELECT COUNT(*) AS n FROM attendance")["n"]
        return {
            "series": series,
            "signups_14d": sum(point["signups"] for point in series),
            "enrollments_14d": sum(point["enrollments"] for point in series),
            "courses": courses,
            "quiz_attempts": quiz_stats["n"],
            "quiz_passes": quiz_stats["passed"],
            "batches": batch_stats["batches"] if batch_stats else 0,
            "batch_seats": batch_stats["seats"] if batch_stats else 0,
            "live_joins": live_count,
            "feed": feed,
            "generated_at": now(),
        }


def categories() -> list[str]:
    with connect() as conn:
        return [row["category"] for row in many(conn, "SELECT DISTINCT category FROM courses WHERE status = 'published' ORDER BY category")]


def seed(conn) -> None:
    return
