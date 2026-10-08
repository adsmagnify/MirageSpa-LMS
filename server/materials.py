"""Central library of videos, documents, and quizzes."""

from __future__ import annotations

import json
import secrets
from pathlib import Path

try:
    from server import db
except ImportError:
    import db

MIME = {
    ".mp4": "video/mp4",
    ".webm": "video/webm",
    ".mov": "video/quicktime",
    ".m4v": "video/mp4",
    ".pdf": "application/pdf",
    ".txt": "text/plain",
    ".doc": "application/msword",
    ".docx": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    ".ppt": "application/vnd.ms-powerpoint",
    ".pptx": "application/vnd.openxmlformats-officedocument.presentationml.presentation",
}
KINDS = {
    "video": {".mp4", ".webm", ".mov", ".m4v"},
    "document": {".pdf", ".txt", ".doc", ".docx", ".ppt", ".pptx"},
}


def _public(row: dict) -> dict:
    item = {
        "id": row["id"],
        "owner_id": row["owner_id"],
        "owner_name": row.get("owner_name") or "",
        "kind": row["kind"],
        "title": row["title"],
        "description": row["description"],
        "original_name": row["original_name"],
        "mime": row["mime"],
        "size": row["size"],
        "created_at": row["created_at"],
        "question_count": 0,
    }
    if row["kind"] == "quiz" and row.get("quiz_json"):
        try:
            item["question_count"] = len(json.loads(row["quiz_json"]).get("questions") or [])
        except json.JSONDecodeError:
            item["question_count"] = 0
    return item


def _require_teacher(user: dict) -> None:
    if user["role"] not in {"admin", "instructor"}:
        raise PermissionError("The library is for instructors.")


def list_items(user: dict) -> list[dict]:
    _require_teacher(user)
    with db.connect() as conn:
        rows = db.many(
            conn,
            """
            SELECT i.*, u.full_name AS owner_name
            FROM library_items i JOIN users u ON u.id = i.owner_id
            WHERE i.deleted_at IS NULL
            ORDER BY i.created_at DESC
            """,
        )
    return [_public(row) for row in rows]


def save_file(user: dict, title: str, kind: str, filename: str, stream) -> dict:
    _require_teacher(user)
    if kind not in KINDS:
        raise ValueError("Upload a video or a document. Quizzes are written in the library.")
    title = (title or "").strip()
    original = Path(filename or "file").name
    suffix = Path(original).suffix.lower()
    if suffix not in KINDS[kind]:
        allowed = ", ".join(sorted(KINDS[kind]))
        raise ValueError(f"That file is not a {kind}. Use {allowed}.")
    if len(title) < 2:
        title = Path(original).stem.replace("_", " ").strip() or "Untitled"
    db.UPLOADS.mkdir(exist_ok=True)
    stored = f"{secrets.token_hex(16)}{suffix}"
    dest = db.UPLOADS / stored
    size = 0
    try:
        with dest.open("wb") as out:
            while True:
                chunk = stream.read(1024 * 1024)
                if not chunk:
                    break
                size += len(chunk)
                out.write(chunk)
    except Exception:
        dest.unlink(missing_ok=True)
        raise
    if size == 0:
        dest.unlink(missing_ok=True)
        raise ValueError("The file is empty.")
    try:
        with db.connect() as conn:
            cur = conn.execute(
                """
                INSERT INTO library_items
                  (owner_id, kind, title, stored_name, original_name, mime, size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (user["id"], kind, title, stored, original, MIME.get(suffix, "application/octet-stream"), size, db.now()),
            )
            row = db.one(
                conn,
                "SELECT i.*, u.full_name AS owner_name FROM library_items i JOIN users u ON u.id = i.owner_id WHERE i.id = ?",
                (cur.lastrowid,),
            )
    except Exception:
        dest.unlink(missing_ok=True)
        raise
    return _public(row)


def save_quiz(user: dict, title: str, questions: list, passing_score: int = 70) -> dict:
    _require_teacher(user)
    title = title.strip()
    if len(title) < 2:
        raise ValueError("Name the quiz.")
    if len(questions) < 1:
        raise ValueError("A quiz needs at least one question.")
    cleaned = []
    for question in questions:
        options = [str(option).strip() for option in (question.get("options") or []) if str(option).strip()]
        if len(options) < 2:
            raise ValueError("Each question needs at least two choices.")
        answer = int(question.get("answer_index") or 0)
        if answer < 0 or answer >= len(options):
            raise ValueError("Mark a correct choice for every question.")
        prompt = (question.get("prompt") or "").strip()
        if len(prompt) < 2:
            raise ValueError("Write the question.")
        cleaned.append(
            {
                "prompt": prompt,
                "options": options,
                "answer_index": answer,
                "explanation": (question.get("explanation") or "").strip(),
            }
        )
    payload = {"passing_score": max(1, min(int(passing_score or 70), 100)), "questions": cleaned}
    with db.connect() as conn:
        cur = conn.execute(
            "INSERT INTO library_items (owner_id, kind, title, quiz_json, created_at) VALUES (?, 'quiz', ?, ?, ?)",
            (user["id"], title, json.dumps(payload), db.now()),
        )
        row = db.one(
            conn,
            "SELECT i.*, u.full_name AS owner_name FROM library_items i JOIN users u ON u.id = i.owner_id WHERE i.id = ?",
            (cur.lastrowid,),
        )
    return _public(row)


def delete_item(user: dict, item_id: int) -> dict:
    with db.connect() as conn:
        item = db.one(conn, "SELECT * FROM library_items WHERE id = ?", (item_id,))
        if not item:
            raise LookupError("That material is not in the library.")
        if user["role"] != "admin" and item["owner_id"] != user["id"]:
            raise PermissionError("You can remove materials you uploaded.")
        if item.get("deleted_at"):
            return {"deleted": True}
        conn.execute("UPDATE library_items SET deleted_at = ? WHERE id = ?", (db.now(), item_id))
    return {"deleted": True}


def file_for(user: dict | None, item_id: int) -> tuple[Path, str, str]:
    if not user:
        raise PermissionError("Sign in to open this file.")
    with db.connect() as conn:
        item = db.one(conn, "SELECT * FROM library_items WHERE id = ?", (item_id,))
        if not item or not item["stored_name"]:
            raise LookupError("File not found.")
        if user["role"] not in {"admin", "instructor"}:
            enrolled = db.one(
                conn,
                """
                SELECT e.id FROM enrollments e
                JOIN chapters c ON c.course_id = e.course_id
                JOIN lessons l ON l.chapter_id = c.id
                WHERE e.user_id = ? AND l.library_item_id = ?
                """,
                (user["id"], item_id),
            )
            if not enrolled and item["owner_id"] != user["id"]:
                raise PermissionError("This file is not in a class assigned to you.")
    path = (db.UPLOADS / item["stored_name"]).resolve()
    if path.parent != db.UPLOADS.resolve() or not path.is_file():
        raise LookupError("File not found.")
    return path, item["original_name"] or path.name, item["mime"] or "application/octet-stream"


def place_in_course(user: dict, item_id: int, chapter_id: int) -> dict:
    _require_teacher(user)
    with db.connect() as conn:
        item = db.one(conn, "SELECT * FROM library_items WHERE id = ?", (item_id,))
        if not item:
            raise LookupError("That material is not in the library.")
        chapter = db.one(conn, "SELECT * FROM chapters WHERE id = ?", (chapter_id,))
        if not chapter:
            raise LookupError("Chapter not found.")
        course = db.course_row(conn, chapter["course_id"])
        if not db.can_edit_course(user, course):
            raise PermissionError("You cannot edit this course.")
        position = conn.execute(
            "SELECT COALESCE(MAX(position), 0) + 1 AS n FROM lessons WHERE chapter_id = ?",
            (chapter["id"],),
        ).fetchone()["n"]
        quiz_id = None
        content = ""
        video_url = ""
        kind = item["kind"]
        if kind == "video":
            video_url = f"/api/library/{item['id']}/file"
            content = json.dumps([{"type": "video", "url": video_url}])
        elif kind == "document":
            content = json.dumps(
                [
                    {
                        "type": "document",
                        "url": f"/api/library/{item['id']}/file",
                        "name": item["original_name"] or item["title"],
                        "mime": item["mime"],
                    }
                ]
            )
        elif kind == "quiz":
            spec = json.loads(item["quiz_json"] or "{}")
            cur = conn.execute(
                "INSERT INTO quizzes (course_id, title, passing_score) VALUES (?, ?, ?)",
                (course["id"], item["title"], int(spec.get("passing_score") or 70)),
            )
            quiz_id = cur.lastrowid
            for index, question in enumerate(spec.get("questions") or [], start=1):
                conn.execute(
                    """
                    INSERT INTO questions (quiz_id, prompt, options_json, answer_index, explanation, position)
                    VALUES (?, ?, ?, ?, ?, ?)
                    """,
                    (
                        quiz_id,
                        question["prompt"],
                        json.dumps(question["options"]),
                        int(question["answer_index"]),
                        question.get("explanation") or "",
                        index,
                    ),
                )
        else:
            raise ValueError("This library item cannot be placed in a course.")
        cur = conn.execute(
            """
            INSERT INTO lessons
              (chapter_id, title, kind, body, video_url, minutes, position, quiz_id, content_json, library_item_id)
            VALUES (?, ?, ?, '', ?, 8, ?, ?, ?, ?)
            """,
            (chapter["id"], item["title"], kind, video_url, position, quiz_id, content, item["id"]),
        )
        lesson = db.one(conn, "SELECT id, title, kind FROM lessons WHERE id = ?", (cur.lastrowid,))
    return {"lesson": lesson, "course": {"id": course["id"], "title": course["title"], "slug": course["slug"]}}
