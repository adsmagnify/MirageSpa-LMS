"""School desk: courses, groups, news, trash, reports, and calendar."""

from __future__ import annotations

from server import db


def ensure(conn) -> None:
    conn.executescript(
        """
        CREATE TABLE IF NOT EXISTS schools (
          id INTEGER PRIMARY KEY,
          name TEXT NOT NULL,
          city TEXT DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS study_groups (
          id INTEGER PRIMARY KEY,
          name TEXT NOT NULL,
          description TEXT DEFAULT '',
          owner_id INTEGER NOT NULL REFERENCES users(id),
          created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS study_group_members (
          group_id INTEGER NOT NULL REFERENCES study_groups(id) ON DELETE CASCADE,
          user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
          PRIMARY KEY (group_id, user_id)
        );
        CREATE TABLE IF NOT EXISTS feed_posts (
          id INTEGER PRIMARY KEY,
          user_id INTEGER NOT NULL REFERENCES users(id),
          group_id INTEGER REFERENCES study_groups(id) ON DELETE CASCADE,
          body TEXT NOT NULL,
          created_at TEXT NOT NULL
        );
        """
    )
    _column(conn, "courses", "deleted_at", "deleted_at TEXT")
    _column(conn, "courses", "parent_id", "parent_id INTEGER")
    _column(conn, "courses", "starts_on", "starts_on TEXT")
    _column(conn, "courses", "ends_on", "ends_on TEXT")
    _column(conn, "courses", "product_code", "product_code TEXT DEFAULT ''")
    _column(conn, "enrollments", "active", "active INTEGER NOT NULL DEFAULT 1")
    _column(conn, "library_items", "deleted_at", "deleted_at TEXT")
    if not db.one(conn, "SELECT id FROM schools"):
        conn.execute("INSERT INTO schools (name, city) VALUES ('Mirage Spa Education', '')")


def _column(conn, table: str, name: str, ddl: str) -> None:
    have = {row[1] for row in conn.execute(f"PRAGMA table_info({table})")}
    if name not in have:
        conn.execute(f"ALTER TABLE {table} ADD COLUMN {ddl}")


def _staff(user: dict) -> None:
    if user["role"] not in {"admin", "instructor"}:
        raise PermissionError("This desk is for instructors.")


def school(user: dict) -> dict:
    _staff(user)
    with db.connect() as conn:
        row = db.one(conn, "SELECT * FROM schools ORDER BY id LIMIT 1")
        instructors = db.many(
            conn,
            "SELECT id, full_name, email, role FROM users WHERE role IN ('admin', 'instructor') ORDER BY full_name",
        )
        learners = conn.execute("SELECT COUNT(*) AS n FROM users WHERE role = 'student'").fetchone()["n"]
        courses = conn.execute("SELECT COUNT(*) AS n FROM courses WHERE deleted_at IS NULL").fetchone()["n"]
    return {"school": row, "instructors": instructors, "learners": learners, "courses": courses}


def rename_school(user: dict, name: str) -> dict:
    if user["role"] != "admin":
        raise PermissionError("An administrator names the school.")
    name = name.strip()
    if len(name) < 2:
        raise ValueError("Give the school a name.")
    with db.connect() as conn:
        conn.execute(
            "UPDATE schools SET name = ? WHERE id = (SELECT id FROM schools ORDER BY id LIMIT 1)",
            (name,),
        )
        return db.one(conn, "SELECT * FROM schools ORDER BY id LIMIT 1")


def _stats(conn, course_id: int) -> dict:
    learners = conn.execute(
        """
        SELECT COUNT(*) AS n FROM enrollments e
        JOIN users u ON u.id = e.user_id
        WHERE e.course_id = ? AND u.role = 'student' AND COALESCE(e.active, 1) = 1
        """,
        (course_id,),
    ).fetchone()["n"]
    deactivated = conn.execute(
        "SELECT COUNT(*) AS n FROM enrollments WHERE course_id = ? AND COALESCE(active, 1) = 0",
        (course_id,),
    ).fetchone()["n"]
    to_score = conn.execute(
        """
        SELECT COUNT(*) AS n FROM submissions s
        JOIN assignments a ON a.id = s.assignment_id
        WHERE a.course_id = ? AND s.status != 'graded'
        """,
        (course_id,),
    ).fetchone()["n"]
    enrolled = db.many(
        conn,
        "SELECT user_id FROM enrollments WHERE course_id = ? AND COALESCE(active, 1) = 1",
        (course_id,),
    )
    completed = 0
    progress_total = 0
    for row in enrolled:
        progress = db.progress_percent(conn, row["user_id"], course_id)
        progress_total += progress
        if progress >= 100:
            completed += 1
    average = round(progress_total / len(enrolled)) if enrolled else 0
    return {
        "learners": learners,
        "completed": completed,
        "deactivated": deactivated,
        "to_score": to_score,
        "average_progress": average,
    }


def _course_card(conn, row: dict, user: dict) -> dict:
    return {
        "id": row["id"],
        "title": row["title"],
        "slug": row["slug"],
        "category": row.get("category") or "",
        "status": row.get("status") or "",
        "instructor_name": row.get("instructor_name") or "",
        "created_at": row.get("created_at") or "",
        "starts_on": row.get("starts_on") or "",
        "ends_on": row.get("ends_on") or "",
        "product_code": row.get("product_code") or "",
        "parent_id": row.get("parent_id"),
        "can_edit": db.can_edit_course(user, row),
        **_stats(conn, row["id"]),
    }


def desk(user: dict) -> dict:
    with db.connect() as conn:
        school_row = db.one(conn, "SELECT * FROM schools ORDER BY id LIMIT 1")
        if user["role"] == "admin":
            clause, args = "c.deleted_at IS NULL", []
        elif user["role"] == "instructor":
            clause, args = "c.deleted_at IS NULL AND c.instructor_id = ?", [user["id"]]
        else:
            clause, args = "0 = 1", []
        teaching_rows = db.many(
            conn,
            f"""
            SELECT c.*, u.full_name AS instructor_name
            FROM courses c JOIN users u ON u.id = c.instructor_id
            WHERE {clause}
            ORDER BY c.created_at DESC
            """,
            args,
        )
        teaching = [_course_card(conn, row, user) for row in teaching_rows]
        enrolled_rows = db.many(
            conn,
            """
            SELECT c.*, u.full_name AS instructor_name
            FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            JOIN users u ON u.id = c.instructor_id
            WHERE e.user_id = ? AND c.deleted_at IS NULL AND COALESCE(e.active, 1) = 1
            ORDER BY e.enrolled_at DESC
            """,
            (user["id"],),
        )
        enrolled = []
        for row in enrolled_rows:
            card = _course_card(conn, row, user)
            card["progress"] = db.progress_percent(conn, user["id"], row["id"])
            enrolled.append(card)
        news = db.many(
            conn,
            """
            SELECT p.*, u.full_name AS author_name, g.name AS group_name
            FROM feed_posts p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN study_groups g ON g.id = p.group_id
            ORDER BY p.created_at DESC
            LIMIT 8
            """,
        )
        unread = conn.execute(
            "SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND read = 0",
            (user["id"],),
        ).fetchone()["n"]
        groups = _my_groups(conn, user["id"])
    return {
        "school": school_row,
        "teaching": teaching,
        "enrolled": enrolled,
        "groups": groups,
        "news": news,
        "unread": unread,
    }


def _my_groups(conn, user_id: int) -> list[dict]:
    return db.many(
        conn,
        """
        SELECT g.*, u.full_name AS owner_name,
               (SELECT COUNT(*) FROM study_group_members m WHERE m.group_id = g.id) AS members
        FROM study_groups g
        JOIN users u ON u.id = g.owner_id
        WHERE g.owner_id = ? OR g.id IN (SELECT group_id FROM study_group_members WHERE user_id = ?)
        ORDER BY g.created_at DESC
        """,
        (user_id, user_id),
    )


def list_groups(user: dict) -> list[dict]:
    with db.connect() as conn:
        if user["role"] in {"admin", "instructor"}:
            return db.many(
                conn,
                """
                SELECT g.*, u.full_name AS owner_name,
                       (SELECT COUNT(*) FROM study_group_members m WHERE m.group_id = g.id) AS members
                FROM study_groups g JOIN users u ON u.id = g.owner_id
                ORDER BY g.created_at DESC
                """,
            )
        return _my_groups(conn, user["id"])


def create_group(user: dict, name: str, description: str) -> dict:
    _staff(user)
    name = name.strip()
    if len(name) < 2:
        raise ValueError("Name the group.")
    with db.connect() as conn:
        cur = conn.execute(
            "INSERT INTO study_groups (name, description, owner_id, created_at) VALUES (?, ?, ?, ?)",
            (name, description.strip(), user["id"], db.now()),
        )
        conn.execute(
            "INSERT INTO study_group_members (group_id, user_id) VALUES (?, ?)",
            (cur.lastrowid, user["id"]),
        )
        return db.one(
            conn,
            """
            SELECT g.*, u.full_name AS owner_name, 1 AS members
            FROM study_groups g JOIN users u ON u.id = g.owner_id WHERE g.id = ?
            """,
            (cur.lastrowid,),
        )


def group_detail(user: dict, group_id: int) -> dict:
    with db.connect() as conn:
        group = _group_or_404(conn, group_id)
        _can_see_group(conn, user, group)
        members = db.many(
            conn,
            """
            SELECT u.id, u.full_name, u.email, u.role
            FROM study_group_members m JOIN users u ON u.id = m.user_id
            WHERE m.group_id = ?
            ORDER BY u.full_name
            """,
            (group_id,),
        )
        posts = db.many(
            conn,
            """
            SELECT p.*, u.full_name AS author_name
            FROM feed_posts p JOIN users u ON u.id = p.user_id
            WHERE p.group_id = ?
            ORDER BY p.created_at DESC
            """,
            (group_id,),
        )
    group["members_list"] = members
    group["posts"] = posts
    return group


def _group_or_404(conn, group_id: int) -> dict:
    group = db.one(
        conn,
        """
        SELECT g.*, u.full_name AS owner_name
        FROM study_groups g JOIN users u ON u.id = g.owner_id WHERE g.id = ?
        """,
        (group_id,),
    )
    if not group:
        raise LookupError("Group not found.")
    return group


def _can_see_group(conn, user: dict, group: dict) -> None:
    if user["role"] in {"admin", "instructor"}:
        return
    member = db.one(
        conn,
        "SELECT 1 AS ok FROM study_group_members WHERE group_id = ? AND user_id = ?",
        (group["id"], user["id"]),
    )
    if not member:
        raise PermissionError("You are not in this group.")


def add_member(user: dict, group_id: int, email: str) -> dict:
    _staff(user)
    email = email.strip().lower()
    with db.connect() as conn:
        group = _group_or_404(conn, group_id)
        if user["role"] != "admin" and group["owner_id"] != user["id"]:
            raise PermissionError("The group owner adds people.")
        person = db.one(conn, "SELECT * FROM users WHERE lower(email) = ?", (email,))
        if not person:
            raise LookupError("No account uses that email.")
        conn.execute(
            "INSERT OR IGNORE INTO study_group_members (group_id, user_id) VALUES (?, ?)",
            (group_id, person["id"]),
        )
        conn.execute(
            "INSERT INTO notifications (user_id, body, href, created_at) VALUES (?, ?, ?, ?)",
            (person["id"], f"You were added to {group['name']}.", f"/groups/{group_id}", db.now()),
        )
    return group_detail(user, group_id)


def post_news(user: dict, body: str, group_id: int | None) -> dict:
    body = body.strip()
    if len(body) < 2:
        raise ValueError("Write a message.")
    with db.connect() as conn:
        if group_id:
            group = _group_or_404(conn, group_id)
            _can_see_group(conn, user, group)
        elif user["role"] not in {"admin", "instructor"}:
            raise PermissionError("Instructors post school news.")
        cur = conn.execute(
            "INSERT INTO feed_posts (user_id, group_id, body, created_at) VALUES (?, ?, ?, ?)",
            (user["id"], group_id, body, db.now()),
        )
        row = db.one(
            conn,
            """
            SELECT p.*, u.full_name AS author_name, g.name AS group_name
            FROM feed_posts p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN study_groups g ON g.id = p.group_id
            WHERE p.id = ?
            """,
            (cur.lastrowid,),
        )
    return row


def copy_course(user: dict, course_id: int) -> dict:
    _staff(user)
    with db.connect() as conn:
        course = db.course_row(conn, course_id)
        if not course or course.get("deleted_at"):
            raise LookupError("Course not found.")
        if not db.can_edit_course(user, course):
            raise PermissionError("You cannot copy this course.")
        title = course["title"] if course["title"].lower().startswith("copy of ") else f"Copy of {course['title']}"
        cur = conn.execute(
            """
            INSERT INTO courses
              (title, slug, summary, description, category, level, status, instructor_id, created_at,
               parent_id, starts_on, ends_on, product_code)
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?)
            """,
            (
                title,
                db.unique_slug(conn, title),
                course.get("summary") or "",
                course.get("description") or "",
                course.get("category") or "General",
                course.get("level") or "Foundation",
                user["id"],
                db.now(),
                course["id"],
                course.get("starts_on") or "",
                course.get("ends_on") or "",
                course.get("product_code") or "",
            ),
        )
        new_id = cur.lastrowid
        assignment_map = {}
        for item in db.many(conn, "SELECT * FROM assignments WHERE course_id = ?", (course["id"],)):
            copied = conn.execute(
                "INSERT INTO assignments (course_id, title, instructions, max_score) VALUES (?, ?, ?, ?)",
                (new_id, item["title"], item["instructions"], item["max_score"]),
            )
            assignment_map[item["id"]] = copied.lastrowid
        quiz_map = {}
        for item in db.many(conn, "SELECT * FROM quizzes WHERE course_id = ?", (course["id"],)):
            copied = conn.execute(
                """
                INSERT INTO quizzes (course_id, title, passing_score, max_attempts, duration_minutes, show_answers)
                VALUES (?, ?, ?, ?, ?, ?)
                """,
                (new_id, item["title"], item["passing_score"], item["max_attempts"], item["duration_minutes"], item["show_answers"]),
            )
            quiz_map[item["id"]] = copied.lastrowid
            for question in db.many(conn, "SELECT * FROM questions WHERE quiz_id = ? ORDER BY position", (item["id"],)):
                conn.execute(
                    """
                    INSERT INTO questions (quiz_id, prompt, options_json, answer_index, explanation, position)
                    VALUES (?, ?, ?, ?, ?, ?)
                    """,
                    (
                        copied.lastrowid,
                        question["prompt"],
                        question["options_json"],
                        question["answer_index"],
                        question["explanation"],
                        question["position"],
                    ),
                )
        chapter_map = {}
        for chapter in db.many(conn, "SELECT * FROM chapters WHERE course_id = ? ORDER BY position", (course["id"],)):
            copied = conn.execute(
                "INSERT INTO chapters (course_id, title, position) VALUES (?, ?, ?)",
                (new_id, chapter["title"], chapter["position"]),
            )
            chapter_map[chapter["id"]] = copied.lastrowid
        lessons = db.many(
            conn,
            """
            SELECT l.* FROM lessons l
            JOIN chapters c ON c.id = l.chapter_id
            WHERE c.course_id = ?
            ORDER BY l.position
            """,
            (course["id"],),
        )
        for lesson in lessons:
            conn.execute(
                """
                INSERT INTO lessons
                  (chapter_id, title, kind, body, video_url, minutes, position, quiz_id, assignment_id,
                   include_in_preview, instructor_notes, content_json, library_item_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    chapter_map[lesson["chapter_id"]],
                    lesson["title"],
                    lesson["kind"],
                    lesson.get("body") or "",
                    lesson.get("video_url") or "",
                    lesson.get("minutes") or 8,
                    lesson["position"],
                    quiz_map.get(lesson.get("quiz_id")),
                    assignment_map.get(lesson.get("assignment_id")),
                    lesson.get("include_in_preview") or 0,
                    lesson.get("instructor_notes") or "",
                    lesson.get("content_json") or "",
                    lesson.get("library_item_id"),
                ),
            )
        copied_row = db.course_row(conn, new_id)
    return {"course": {"id": copied_row["id"], "title": copied_row["title"], "slug": copied_row["slug"]}}


def trash_course(user: dict, course_id: int) -> dict:
    with db.connect() as conn:
        course = db.course_row(conn, course_id)
        if not course:
            raise LookupError("Course not found.")
        if not db.can_edit_course(user, course):
            raise PermissionError("You cannot move this course to trash.")
        conn.execute("UPDATE courses SET deleted_at = ? WHERE id = ?", (db.now(), course_id))
    return {"trashed": True}


def trash_bin(user: dict) -> dict:
    _staff(user)
    with db.connect() as conn:
        if user["role"] == "admin":
            course_clause, args = "c.deleted_at IS NOT NULL", []
            item_clause, item_args = "i.deleted_at IS NOT NULL", []
        else:
            course_clause, args = "c.deleted_at IS NOT NULL AND c.instructor_id = ?", [user["id"]]
            item_clause, item_args = "i.deleted_at IS NOT NULL AND i.owner_id = ?", [user["id"]]
        courses = db.many(
            conn,
            f"""
            SELECT c.id, c.title, c.slug, c.deleted_at, u.full_name AS instructor_name
            FROM courses c JOIN users u ON u.id = c.instructor_id
            WHERE {course_clause}
            ORDER BY c.deleted_at DESC
            """,
            args,
        )
        items = db.many(
            conn,
            f"""
            SELECT i.id, i.title, i.kind, i.deleted_at, u.full_name AS owner_name
            FROM library_items i JOIN users u ON u.id = i.owner_id
            WHERE {item_clause}
            ORDER BY i.deleted_at DESC
            """,
            item_args,
        )
    return {"courses": courses, "resources": items}


def restore_course(user: dict, course_id: int) -> dict:
    with db.connect() as conn:
        course = db.course_row(conn, course_id)
        if not course or not course.get("deleted_at"):
            raise LookupError("That course is not in the trash.")
        if not db.can_edit_course(user, course):
            raise PermissionError("You cannot restore this course.")
        conn.execute("UPDATE courses SET deleted_at = NULL WHERE id = ?", (course_id,))
    return {"restored": True}


def purge_course(user: dict, course_id: int) -> dict:
    with db.connect() as conn:
        course = db.course_row(conn, course_id)
        if not course or not course.get("deleted_at"):
            raise LookupError("That course is not in the trash.")
        if not db.can_edit_course(user, course):
            raise PermissionError("You cannot remove this course.")
        conn.execute("DELETE FROM courses WHERE id = ?", (course_id,))
    return {"purged": True}


def restore_resource(user: dict, item_id: int) -> dict:
    with db.connect() as conn:
        item = db.one(conn, "SELECT * FROM library_items WHERE id = ?", (item_id,))
        if not item or not item.get("deleted_at"):
            raise LookupError("That resource is not in the trash.")
        if user["role"] != "admin" and item["owner_id"] != user["id"]:
            raise PermissionError("You cannot restore this resource.")
        conn.execute("UPDATE library_items SET deleted_at = NULL WHERE id = ?", (item_id,))
    return {"restored": True}


def purge_resource(user: dict, item_id: int) -> dict:
    with db.connect() as conn:
        item = db.one(conn, "SELECT * FROM library_items WHERE id = ?", (item_id,))
        if not item or not item.get("deleted_at"):
            raise LookupError("That resource is not in the trash.")
        if user["role"] != "admin" and item["owner_id"] != user["id"]:
            raise PermissionError("You cannot remove this resource.")
        conn.execute("DELETE FROM library_items WHERE id = ?", (item_id,))
    if item.get("stored_name"):
        path = db.UPLOADS / item["stored_name"]
        if path.is_file():
            path.unlink()
    return {"purged": True}


def reports(user: dict) -> dict:
    data = desk(user)
    rows = data["teaching"] if user["role"] in {"admin", "instructor"} else data["enrolled"]
    return {
        "school": data["school"],
        "courses": rows,
        "totals": {
            "courses": len(rows),
            "learners": sum(row["learners"] for row in rows),
            "completed": sum(row["completed"] for row in rows),
            "to_score": sum(row["to_score"] for row in rows),
        },
    }


def calendar(user: dict) -> dict:
    with db.connect() as conn:
        live = db.many(
            conn,
            """
            SELECT id, title, starts_at, minutes, status, course_id
            FROM live_classes
            ORDER BY starts_at
            """,
        )
        batches = db.many(
            conn,
            """
            SELECT b.id, b.title, b.start_date, b.end_date, c.title AS course_title
            FROM batches b JOIN courses c ON c.id = b.course_id
            WHERE c.deleted_at IS NULL
            ORDER BY b.start_date
            """,
        )
        slots = db.many(
            conn,
            "SELECT id, starts_at, minutes, status FROM eval_slots ORDER BY starts_at",
        )
    return {"live": live, "batches": batches, "evaluations": slots}


def search(user: dict, query: str) -> dict:
    query = query.strip()
    if len(query) < 2:
        return {"courses": [], "people": [], "resources": [], "groups": []}
    like = f"%{query}%"
    with db.connect() as conn:
        courses = db.many(
            conn,
            """
            SELECT id, title, slug, category FROM courses
            WHERE deleted_at IS NULL AND (title LIKE ? OR category LIKE ?)
            ORDER BY title LIMIT 12
            """,
            (like, like),
        )
        people = []
        if user["role"] in {"admin", "instructor"}:
            people = db.many(
                conn,
                """
                SELECT id, full_name, email, role, username FROM users
                WHERE full_name LIKE ? OR email LIKE ?
                ORDER BY full_name LIMIT 12
                """,
                (like, like),
            )
        resources = []
        if user["role"] in {"admin", "instructor"}:
            resources = db.many(
                conn,
                """
                SELECT id, title, kind FROM library_items
                WHERE deleted_at IS NULL AND title LIKE ?
                ORDER BY title LIMIT 12
                """,
                (like,),
            )
        groups = db.many(
            conn,
            "SELECT id, name FROM study_groups WHERE name LIKE ? ORDER BY name LIMIT 12",
            (like,),
        )
    return {"courses": courses, "people": people, "resources": resources, "groups": groups}


def mark_notifications(user: dict) -> dict:
    with db.connect() as conn:
        conn.execute("UPDATE notifications SET read = 1 WHERE user_id = ? AND read = 0", (user["id"],))
    return {"read": True}


def roster(user: dict, course_id: int) -> list[dict]:
    with db.connect() as conn:
        course = db.course_row(conn, course_id)
        if not course:
            raise LookupError("Course not found.")
        if not db.can_edit_course(user, course):
            raise PermissionError("You cannot see this roster.")
        return db.many(
            conn,
            """
            SELECT u.id, u.full_name, u.email, COALESCE(e.active, 1) AS active, e.enrolled_at
            FROM enrollments e JOIN users u ON u.id = e.user_id
            WHERE e.course_id = ?
            ORDER BY u.full_name
            """,
            (course_id,),
        )


def set_enrollment(user: dict, course_id: int, learner_id: int, active: bool) -> dict:
    with db.connect() as conn:
        course = db.course_row(conn, course_id)
        if not course:
            raise LookupError("Course not found.")
        if not db.can_edit_course(user, course):
            raise PermissionError("You cannot change this class.")
        row = db.one(
            conn,
            "SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?",
            (course_id, learner_id),
        )
        if not row:
            raise LookupError("That learner is not in this class.")
        conn.execute("UPDATE enrollments SET active = ? WHERE id = ?", (1 if active else 0, row["id"]))
    return {"active": active}
