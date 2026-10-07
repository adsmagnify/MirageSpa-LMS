"""Frappe Learning workflows: lessons, programs, certificates, discussions."""

from __future__ import annotations

import json

from server import db


def blocks_for(conn, lesson: dict, user: dict | None, editor: bool) -> list[dict]:
    raw = []
    if lesson.get("content_json"):
        try:
            raw = json.loads(lesson["content_json"])
        except json.JSONDecodeError:
            raw = []
    if not raw:
        if lesson.get("body"):
            raw.append({"type": "markdown", "text": lesson["body"]})
        if lesson.get("video_url"):
            raw.append({"type": "video", "url": lesson["video_url"]})
        if lesson.get("quiz_id"):
            raw.append({"type": "quiz", "quiz_id": lesson["quiz_id"]})
        if lesson.get("assignment_id"):
            raw.append({"type": "assignment", "assignment_id": lesson["assignment_id"]})
    user_id = user["id"] if user else None
    rendered = []
    for block in raw:
        kind = block.get("type")
        if kind == "markdown":
            rendered.append({"type": "markdown", "text": block.get("text") or ""})
        elif kind == "video":
            rendered.append({"type": "video", "url": block.get("url") or lesson.get("video_url") or ""})
        elif kind == "document":
            rendered.append(
                {
                    "type": "document",
                    "url": block.get("url") or "",
                    "name": block.get("name") or "Document",
                    "mime": block.get("mime") or "",
                }
            )
        elif kind == "quiz" and block.get("quiz_id"):
            rendered.append({"type": "quiz", "quiz": db.quiz_payload(conn, block["quiz_id"], editor, user_id)})
        elif kind == "assignment" and block.get("assignment_id"):
            rendered.append(
                {"type": "assignment", "assignment": db.assignment_payload(conn, block["assignment_id"], user_id)}
            )
    return rendered


def numbered_outline(conn, course_id: int, user_id: int | None) -> list[dict]:
    done = set()
    if user_id:
        done = {
            row["lesson_id"]
            for row in db.many(conn, "SELECT lesson_id FROM lesson_progress WHERE user_id = ? AND completed = 1", (user_id,))
        }
    chapters = []
    for chapter_number, chapter in enumerate(
        db.many(conn, "SELECT * FROM chapters WHERE course_id = ? ORDER BY position, id", (course_id,)), start=1
    ):
        lessons = []
        for lesson_number, lesson in enumerate(
            db.many(conn, "SELECT * FROM lessons WHERE chapter_id = ? ORDER BY position, id", (chapter["id"],)), start=1
        ):
            lessons.append(
                {
                    "id": lesson["id"],
                    "number": lesson_number,
                    "title": lesson["title"],
                    "kind": lesson["kind"],
                    "minutes": lesson["minutes"],
                    "completed": lesson["id"] in done,
                    "include_in_preview": bool(lesson["include_in_preview"]),
                }
            )
        chapters.append({"id": chapter["id"], "number": chapter_number, "title": chapter["title"], "lessons": lessons})
    return chapters


def lesson_at(conn, course_id: int, chapter_number: int, lesson_number: int) -> tuple[dict, dict]:
    chapters = db.many(conn, "SELECT * FROM chapters WHERE course_id = ? ORDER BY position, id", (course_id,))
    if chapter_number < 1 or chapter_number > len(chapters):
        raise LookupError("Chapter not found.")
    chapter = chapters[chapter_number - 1]
    lessons = db.many(conn, "SELECT * FROM lessons WHERE chapter_id = ? ORDER BY position, id", (chapter["id"],))
    if lesson_number < 1 or lesson_number > len(lessons):
        raise LookupError("Lesson not found.")
    return chapter, lessons[lesson_number - 1]


def flat_positions(outline: list[dict]) -> list[dict]:
    rows = []
    for chapter in outline:
        for lesson in chapter["lessons"]:
            rows.append({"chapter": chapter["number"], "lesson": lesson["number"], "id": lesson["id"]})
    return rows


def course_page(slug: str, user: dict | None) -> dict:
    payload = db.get_public_course(slug, user)
    course = payload["course"]
    with db.connect() as conn:
        payload["outline"] = numbered_outline(conn, course["id"], user["id"] if user else None)
        payload["reviews"] = db.many(
            conn,
            """
            SELECT r.rating, r.body, r.created_at, u.full_name, u.username
            FROM reviews r JOIN users u ON u.id = r.user_id
            WHERE r.course_id = ? ORDER BY r.created_at DESC
            """,
            (course["id"],),
        )
        ratings = [row["rating"] for row in payload["reviews"]]
        payload["rating"] = round(sum(ratings) / len(ratings), 1) if ratings else None
        payload["certificate"] = None
        if user:
            payload["certificate"] = db.one(
                conn,
                "SELECT id, issue_date FROM certificates WHERE course_id = ? AND user_id = ?",
                (course["id"], user["id"]),
            )
    return payload


def lesson_page(user: dict | None, slug: str, chapter_number: int, lesson_number: int) -> dict:
    with db.connect() as conn:
        course = db.course_by_slug(conn, slug)
        editor = bool(user and db.can_edit_course(user, course))
        enrolled = bool(user and db.one(conn, "SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?", (course["id"], user["id"])))
        chapter, lesson = lesson_at(conn, course["id"], chapter_number, lesson_number)
        if course["status"] != "published" and not editor:
            raise LookupError("Course not found.")
        if not enrolled and not editor:
            raise PermissionError("This class has not been assigned to you.")
        outline = numbered_outline(conn, course["id"], user["id"] if user else None)
        positions = flat_positions(outline)
        index = next((i for i, row in enumerate(positions) if row["chapter"] == chapter_number and row["lesson"] == lesson_number), 0)
        notes = []
        if user:
            note = db.one(conn, "SELECT body FROM lesson_notes WHERE lesson_id = ? AND user_id = ?", (lesson["id"], user["id"]))
            notes = note["body"] if note else ""
        discussions = db.many(
            conn,
            """
            SELECT d.*, u.full_name, u.username FROM discussions d
            JOIN users u ON u.id = d.user_id
            WHERE d.lesson_id = ? ORDER BY d.created_at
            """,
            (lesson["id"],),
        )
        return {
            "course": course,
            "enrolled": enrolled or editor,
            "progress": db.progress_percent(conn, user["id"], course["id"]) if user and (enrolled or editor) else 0,
            "outline": outline,
            "chapter_number": chapter_number,
            "lesson_number": lesson_number,
            "chapter_title": chapter["title"],
            "lesson": {
                "id": lesson["id"],
                "title": lesson["title"],
                "kind": lesson["kind"],
                "minutes": lesson["minutes"],
                "completed": bool(user and db.one(conn, "SELECT 1 FROM lesson_progress WHERE user_id = ? AND lesson_id = ? AND completed = 1", (user["id"], lesson["id"]))),
                "include_in_preview": bool(lesson["include_in_preview"]),
                "instructor_notes": lesson["instructor_notes"] if editor else "",
                "blocks": blocks_for(conn, lesson, user, editor),
            },
            "prev": positions[index - 1] if index > 0 else None,
            "next": positions[index + 1] if index + 1 < len(positions) else None,
            "notes": notes,
            "discussions": discussions,
        }


def save_note(user: dict, lesson_id: int, body: str) -> dict:
    with db.connect() as conn:
        conn.execute(
            """
            INSERT INTO lesson_notes (lesson_id, user_id, body, updated_at) VALUES (?, ?, ?, ?)
            ON CONFLICT(lesson_id, user_id) DO UPDATE SET body = excluded.body, updated_at = excluded.updated_at
            """,
            (lesson_id, user["id"], body.strip(), db.now()),
        )
    return {"saved": True}


def post_discussion(user: dict, body: str, lesson_id: int | None = None, batch_id: int | None = None, parent_id: int | None = None) -> dict:
    body = body.strip()
    if len(body) < 2:
        raise ValueError("Write a comment first.")
    if not lesson_id and not batch_id:
        raise ValueError("A comment needs a lesson or a batch.")
    with db.connect() as conn:
        course_id = None
        if lesson_id:
            row = db.one(conn, "SELECT c.course_id FROM lessons l JOIN chapters c ON c.id = l.chapter_id WHERE l.id = ?", (lesson_id,))
            if not row:
                raise LookupError("Lesson not found.")
            course_id = row["course_id"]
        if batch_id and not db.one(conn, "SELECT id FROM batches WHERE id = ?", (batch_id,)):
            raise LookupError("Batch not found.")
        cur = conn.execute(
            """
            INSERT INTO discussions (course_id, lesson_id, batch_id, parent_id, user_id, body, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            """,
            (course_id, lesson_id, batch_id, parent_id, user["id"], body, db.now()),
        )
        return db.one(
            conn,
            "SELECT d.*, u.full_name, u.username FROM discussions d JOIN users u ON u.id = d.user_id WHERE d.id = ?",
            (cur.lastrowid,),
        )


def list_batch_discussions(batch_id: int) -> list[dict]:
    with db.connect() as conn:
        return db.many(
            conn,
            """
            SELECT d.*, u.full_name, u.username FROM discussions d
            JOIN users u ON u.id = d.user_id
            WHERE d.batch_id = ? ORDER BY d.created_at
            """,
            (batch_id,),
        )


def list_announcements(batch_id: int) -> list[dict]:
    with db.connect() as conn:
        return db.many(
            conn,
            """
            SELECT a.*, u.full_name FROM announcements a JOIN users u ON u.id = a.user_id
            WHERE a.batch_id = ? ORDER BY a.created_at DESC
            """,
            (batch_id,),
        )


def post_announcement(user: dict, batch_id: int, title: str, body: str) -> dict:
    if user["role"] not in {"admin", "instructor", "moderator"}:
        raise PermissionError("Only the batch team can post an announcement.")
    title, body = title.strip(), body.strip()
    if len(title) < 2 or len(body) < 2:
        raise ValueError("An announcement needs a title and a message.")
    with db.connect() as conn:
        batch = db.one(conn, "SELECT * FROM batches WHERE id = ?", (batch_id,))
        if not batch:
            raise LookupError("Batch not found.")
        cur = conn.execute(
            "INSERT INTO announcements (batch_id, user_id, title, body, created_at) VALUES (?, ?, ?, ?, ?)",
            (batch_id, user["id"], title, body, db.now()),
        )
        members = db.many(conn, "SELECT user_id FROM batch_students WHERE batch_id = ?", (batch_id,))
        for member in members:
            conn.execute(
                "INSERT INTO notifications (user_id, body, href, created_at) VALUES (?, ?, ?, ?)",
                (member["user_id"], title, f"/batches/{batch_id}", db.now()),
            )
        return db.one(conn, "SELECT * FROM announcements WHERE id = ?", (cur.lastrowid,))


def post_review(user: dict, course_id: int, rating: int, body: str) -> dict:
    rating = int(rating)
    if rating < 1 or rating > 5:
        raise ValueError("Rate the course from 1 to 5.")
    with db.connect() as conn:
        if not db.one(conn, "SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?", (course_id, user["id"])):
            raise PermissionError("Enroll before reviewing.")
        conn.execute(
            """
            INSERT INTO reviews (course_id, user_id, rating, body, created_at) VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(course_id, user_id) DO UPDATE SET rating = excluded.rating, body = excluded.body, created_at = excluded.created_at
            """,
            (course_id, user["id"], rating, body.strip(), db.now()),
        )
    return course_page(db.course_row_slug(course_id), user) if False else {"saved": True}


def course_row_slug(course_id: int) -> str:
    with db.connect() as conn:
        row = db.one(conn, "SELECT slug FROM courses WHERE id = ?", (course_id,))
    if not row:
        raise LookupError("Course not found.")
    return row["slug"]


def list_programs(user: dict | None) -> list[dict]:
    with db.connect() as conn:
        programs = db.many(conn, "SELECT * FROM programs WHERE published = 1 ORDER BY title")
        for program in programs:
            program["courses"] = db.many(
                conn,
                """
                SELECT c.id, c.title, c.slug, pc.position FROM program_courses pc
                JOIN courses c ON c.id = pc.course_id
                WHERE pc.program_id = ? ORDER BY pc.position
                """,
                (program["id"],),
            )
            program["members"] = conn.execute(
                "SELECT COUNT(*) AS n FROM program_members WHERE program_id = ?", (program["id"],)
            ).fetchone()["n"]
            program["joined"] = bool(
                user and db.one(conn, "SELECT 1 FROM program_members WHERE program_id = ? AND user_id = ?", (program["id"], user["id"]))
            )
            if program["joined"]:
                progresses = [
                    db.progress_percent(conn, user["id"], course["id"]) for course in program["courses"]
                ]
                program["progress"] = round(sum(progresses) / len(progresses)) if progresses else 0
        return programs


def enroll_program(user: dict, program_id: int) -> dict:
    with db.connect() as conn:
        program = db.one(conn, "SELECT * FROM programs WHERE id = ? AND published = 1", (program_id,))
        if not program:
            raise LookupError("Program not found.")
        if not db.one(conn, "SELECT 1 FROM program_members WHERE program_id = ? AND user_id = ?", (program_id, user["id"])):
            conn.execute(
                "INSERT INTO program_members (program_id, user_id, enrolled_at) VALUES (?, ?, ?)",
                (program_id, user["id"], db.now()),
            )
        courses = db.many(conn, "SELECT course_id FROM program_courses WHERE program_id = ?", (program_id,))
        for course in courses:
            db.enroll_silent(conn, user["id"], course["course_id"])
    return {"joined": True}


def certification(user: dict, slug: str) -> dict:
    with db.connect() as conn:
        course = db.course_by_slug(conn, slug)
        progress = db.progress_percent(conn, user["id"], course["id"])
        certificate = db.one(
            conn,
            """
            SELECT c.*, u.full_name AS member_name, e.full_name AS evaluator_name
            FROM certificates c
            JOIN users u ON u.id = c.user_id
            LEFT JOIN users e ON e.id = c.evaluator_id
            WHERE c.course_id = ? AND c.user_id = ?
            """,
            (course["id"], user["id"]),
        )
        request = db.one(
            conn,
            """
            SELECT r.*, s.starts_at, s.meeting_url, s.status AS slot_status
            FROM certificate_requests r
            LEFT JOIN eval_slots s ON s.id = r.slot_id
            WHERE r.course_id = ? AND r.user_id = ?
            ORDER BY r.id DESC LIMIT 1
            """,
            (course["id"], user["id"]),
        )
        return {
            "course": {"id": course["id"], "title": course["title"], "slug": course["slug"], "enable_certification": bool(course["enable_certification"])},
            "progress": progress,
            "eligible": bool(course["enable_certification"]) and progress >= 100,
            "certificate": certificate,
            "request": request,
        }


def request_certificate(user: dict, course_id: int) -> dict:
    with db.connect() as conn:
        course = db.course_row(conn, course_id)
        if not course["enable_certification"]:
            raise ValueError("This course does not issue a certificate.")
        if db.progress_percent(conn, user["id"], course_id) < 100:
            raise ValueError("Finish every lesson before requesting a certificate.")
        if db.one(conn, "SELECT id FROM certificates WHERE course_id = ? AND user_id = ?", (course_id, user["id"])):
            raise ValueError("You already hold this certificate.")
        existing = db.one(
            conn,
            "SELECT * FROM certificate_requests WHERE course_id = ? AND user_id = ? AND status IN ('pending', 'scheduled')",
            (course_id, user["id"]),
        )
        if existing:
            raise ValueError("An evaluation is already booked for this certificate.")
    booked = db.book_evaluation(user, course_id)
    with db.connect() as conn:
        cur = conn.execute(
            """
            INSERT INTO certificate_requests (course_id, user_id, evaluator_id, slot_id, status, created_at)
            VALUES (?, ?, ?, ?, 'scheduled', ?)
            """,
            (course_id, user["id"], booked["instructor_id"], booked["id"], db.now()),
        )
        conn.execute(
            "INSERT INTO notifications (user_id, body, href, created_at) VALUES (?, ?, ?, ?)",
            (booked["instructor_id"], f"{user['full_name']} requested a certificate evaluation.", f"/courses/{course['slug']}/certification", db.now()),
        )
        return db.one(conn, "SELECT * FROM certificate_requests WHERE id = ?", (cur.lastrowid,))


def decide_certificate(user: dict, request_id: int, passed: bool) -> dict:
    with db.connect() as conn:
        request = db.one(conn, "SELECT * FROM certificate_requests WHERE id = ?", (request_id,))
        if not request:
            raise LookupError("Certificate request not found.")
        course = db.course_row(conn, request["course_id"])
        if user["role"] != "admin" and user["id"] not in {course["evaluator_id"], course["instructor_id"]}:
            raise PermissionError("Only the evaluator can issue this certificate.")
        status = "passed" if passed else "failed"
        conn.execute("UPDATE certificate_requests SET status = ? WHERE id = ?", (status, request_id))
        if passed and not db.one(conn, "SELECT id FROM certificates WHERE course_id = ? AND user_id = ?", (course["id"], request["user_id"])):
            conn.execute(
                "INSERT INTO certificates (course_id, user_id, evaluator_id, issue_date) VALUES (?, ?, ?, ?)",
                (course["id"], request["user_id"], user["id"], db.now()),
            )
            conn.execute(
                "INSERT INTO notifications (user_id, body, href, created_at) VALUES (?, ?, ?, ?)",
                (request["user_id"], f"Certificate issued for {course['title']}.", "/certified-participants", db.now()),
            )
        return {"status": status}


def list_certificates() -> list[dict]:
    with db.connect() as conn:
        return db.many(
            conn,
            """
            SELECT c.id, c.issue_date, u.full_name, u.username, k.title AS course_title, k.slug
            FROM certificates c
            JOIN users u ON u.id = c.user_id
            JOIN courses k ON k.id = c.course_id
            ORDER BY c.issue_date DESC
            """
        )


def profile(username: str, viewer: dict | None) -> dict:
    with db.connect() as conn:
        person = db.one(conn, "SELECT * FROM users WHERE username = ?", (username,))
        if not person:
            raise LookupError("Profile not found.")
        public = db.public_user(person)
        if not viewer or viewer["id"] != person["id"]:
            public.pop("email", None)
        courses = db.many(
            conn,
            """
            SELECT c.title, c.slug, e.progress FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            WHERE e.user_id = ? ORDER BY e.enrolled_at DESC
            """,
            (person["id"],),
        )
        certificates = db.many(
            conn,
            """
            SELECT c.issue_date, k.title, k.slug FROM certificates c
            JOIN courses k ON k.id = c.course_id WHERE c.user_id = ?
            """,
            (person["id"],),
        )
        slots = []
        requests = []
        if viewer and viewer["id"] == person["id"] and person["role"] in {"admin", "instructor"}:
            slots = db.many(conn, "SELECT * FROM eval_slots WHERE instructor_id = ? ORDER BY starts_at", (person["id"],))
            requests = db.many(
                conn,
                """
                SELECT r.*, u.full_name AS student_name, k.title AS course_title, k.slug
                FROM certificate_requests r
                JOIN users u ON u.id = r.user_id
                JOIN courses k ON k.id = r.course_id
                WHERE r.evaluator_id = ? OR k.instructor_id = ?
                ORDER BY r.created_at DESC
                """,
                (person["id"], person["id"]),
            )
        return {"profile": public, "courses": courses, "certificates": certificates, "slots": slots, "requests": requests}


def list_exercises() -> list[dict]:
    with db.connect() as conn:
        return db.many(
            conn,
            """
            SELECT e.*, c.title AS course_title, c.slug,
                   (SELECT COUNT(*) FROM exercise_submissions s WHERE s.exercise_id = e.id) AS submissions
            FROM exercises e JOIN courses c ON c.id = e.course_id ORDER BY e.id
            """
        )


def submit_exercise(user: dict, exercise_id: int, code: str) -> dict:
    code = code.strip()
    if len(code) < 8:
        raise ValueError("Write an answer before submitting.")
    with db.connect() as conn:
        if not db.one(conn, "SELECT id FROM exercises WHERE id = ?", (exercise_id,)):
            raise LookupError("Exercise not found.")
        cur = conn.execute(
            "INSERT INTO exercise_submissions (exercise_id, user_id, code, status, submitted_at) VALUES (?, ?, ?, 'pending', ?)",
            (exercise_id, user["id"], code, db.now()),
        )
        return db.one(conn, "SELECT * FROM exercise_submissions WHERE id = ?", (cur.lastrowid,))


def exercise_submissions(user: dict) -> list[dict]:
    with db.connect() as conn:
        if user["role"] in {"admin", "instructor", "moderator"}:
            clause, args = "1 = 1", []
        else:
            clause, args = "s.user_id = ?", [user["id"]]
        return db.many(
            conn,
            f"""
            SELECT s.*, e.title, u.full_name AS student_name FROM exercise_submissions s
            JOIN exercises e ON e.id = s.exercise_id
            JOIN users u ON u.id = s.user_id
            WHERE {clause} ORDER BY s.submitted_at DESC
            """,
            args,
        )


def grade_exercise(user: dict, submission_id: int, status: str, feedback: str) -> dict:
    if status not in {"pass", "fail"}:
        raise ValueError("Mark the exercise pass or fail.")
    if user["role"] not in {"admin", "instructor"}:
        raise PermissionError("Only an instructor can mark an exercise.")
    with db.connect() as conn:
        conn.execute(
            "UPDATE exercise_submissions SET status = ?, feedback = ? WHERE id = ?",
            (status, feedback.strip(), submission_id),
        )
        return db.one(conn, "SELECT * FROM exercise_submissions WHERE id = ?", (submission_id,))


def notifications(user: dict) -> list[dict]:
    with db.connect() as conn:
        return db.many(conn, "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", (user["id"],))


def list_quizzes(user: dict | None) -> list[dict]:
    with db.connect() as conn:
        rows = db.many(
            conn,
            """
            SELECT q.*, c.title AS course_title, c.slug FROM quizzes q
            JOIN courses c ON c.id = q.course_id ORDER BY c.title, q.title
            """
        )
        if user:
            for row in rows:
                attempt = db.one(
                    conn,
                    "SELECT score, passed, submitted_at FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1",
                    (row["id"], user["id"]),
                )
                row["last_attempt"] = dict(attempt) if attempt else None
                if row["last_attempt"]:
                    row["last_attempt"]["passed"] = bool(row["last_attempt"]["passed"])
                row["attempts"] = conn.execute(
                    "SELECT COUNT(*) AS n FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?",
                    (row["id"], user["id"]),
                ).fetchone()["n"]
        return rows


def quiz_submissions(user: dict) -> list[dict]:
    if user["role"] not in {"admin", "instructor", "moderator"}:
        raise PermissionError("Submissions are for instructors.")
    with db.connect() as conn:
        clause = ""
        args: list = []
        if user["role"] == "instructor":
            clause = "WHERE c.instructor_id = ?"
            args.append(user["id"])
        return db.many(
            conn,
            f"""
            SELECT a.id, a.score, a.passed, a.submitted_at, q.title AS quiz_title, u.full_name, c.title AS course_title
            FROM quiz_attempts a
            JOIN quizzes q ON q.id = a.quiz_id
            JOIN courses c ON c.id = q.course_id
            JOIN users u ON u.id = a.user_id
            {clause}
            ORDER BY a.submitted_at DESC
            """,
            args,
        )
