"""Mirage Academy HTTP API."""

import os
from contextlib import asynccontextmanager

from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, JSONResponse

import sys
from pathlib import Path

_here = Path(__file__).resolve().parent
for _path in (str(_here), str(_here.parent)):
    if _path not in sys.path:
        sys.path.insert(0, _path)

try:
    from server import cima
    from server import db
    from server import learning
    from server import materials
except ImportError:
    import cima
    import db
    import learning
    import materials


@asynccontextmanager
async def lifespan(_app: FastAPI):
    db.init_db()
    yield


app = FastAPI(title="Mirage Academy", lifespan=lifespan)
_cors_origins = [
    origin.strip()
    for origin in os.environ.get("CORS_ORIGINS", "http://127.0.0.1:5173,http://localhost:5173").split(",")
    if origin.strip()
]
_cors = {
    "allow_origins": _cors_origins,
    "allow_credentials": True,
    "allow_methods": ["*"],
    "allow_headers": ["*"],
}
_cors_regex = os.environ.get("CORS_ORIGIN_REGEX", r"https://.*\.vercel\.app").strip()
if _cors_regex:
    _cors["allow_origin_regex"] = _cors_regex
app.add_middleware(CORSMiddleware, **_cors)


@app.get("/api/health")
def health():
    return {"ok": True}


@app.exception_handler(ValueError)
async def value_error(_request, exc: ValueError):
    return JSONResponse(status_code=400, content={"detail": str(exc)})


@app.exception_handler(PermissionError)
async def permission_error(_request, exc: PermissionError):
    return JSONResponse(status_code=403, content={"detail": str(exc)})


@app.exception_handler(LookupError)
async def lookup_error(_request, exc: LookupError):
    return JSONResponse(status_code=404, content={"detail": str(exc)})


def current_user(authorization: str | None = Header(default=None)) -> dict:
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Sign in to continue.")
    user_id = db.read_token(authorization.removeprefix("Bearer ").strip())
    user = db.user_by_id(user_id) if user_id else None
    if not user:
        raise HTTPException(status_code=401, detail="Session expired. Sign in again.")
    return user


def optional_user(authorization: str | None = Header(default=None)) -> dict | None:
    if not authorization or not authorization.startswith("Bearer "):
        return None
    user_id = db.read_token(authorization.removeprefix("Bearer ").strip())
    return db.user_by_id(user_id) if user_id else None


@app.get("/api/health")
def health():
    return {"ok": True, "zoom_connected": db.zoom_connected()}


@app.get("/api/auth/status")
def auth_status():
    return {"needs_setup": db.user_count() == 0}


@app.post("/api/auth/signup")
def signup(payload: dict):
    if db.user_count():
        raise PermissionError("An administrator creates accounts and assigns each role.")
    user = db.create_user(payload.get("email") or "", payload.get("password") or "", payload.get("full_name") or "", "admin")
    return {"token": db.make_token(user["id"]), "user": user}


@app.post("/api/auth/login")
def login(payload: dict):
    user = db.authenticate(payload.get("email") or "", payload.get("password") or "")
    if not user:
        raise HTTPException(status_code=401, detail="Those details do not match.")
    return {"token": db.make_token(user["id"]), "user": user}


@app.get("/api/auth/me")
def me(user: dict = Depends(current_user)):
    return user


@app.get("/api/categories")
def categories():
    return db.categories()


@app.get("/api/courses")
def courses(q: str = "", category: str = ""):
    return db.list_courses(q, category)


@app.get("/api/courses/{slug}")
def course(slug: str, user: dict | None = Depends(optional_user)):
    return db.get_public_course(slug, user)


@app.post("/api/courses/{course_id}/enroll")
def enroll(course_id: int, user: dict = Depends(current_user)):
    return db.enroll(user, course_id)


@app.post("/api/courses/{course_id}/assign")
def assign_course(course_id: int, payload: dict, user: dict = Depends(current_user)):
    return db.assign_course(user, course_id, payload.get("email") or "")


@app.get("/api/studio/courses")
def studio_courses(user: dict = Depends(current_user)):
    if user["role"] == "admin":
        return db.list_courses(include_drafts=True)
    return db.list_courses(include_drafts=True, instructor_id=user["id"])


@app.post("/api/studio/courses")
def studio_create(payload: dict, user: dict = Depends(current_user)):
    return db.create_course(user, payload)


@app.get("/api/studio/courses/{course_id}")
def studio_one(course_id: int, user: dict = Depends(current_user)):
    return db.studio_course(user, course_id)


@app.patch("/api/studio/courses/{course_id}")
def studio_update(course_id: int, payload: dict, user: dict = Depends(current_user)):
    return db.update_course(user, course_id, payload)


@app.post("/api/studio/chapters")
def studio_chapter(payload: dict, user: dict = Depends(current_user)):
    return db.add_chapter(user, int(payload.get("course_id")), payload.get("title") or "")


@app.post("/api/studio/lessons")
def studio_lesson(payload: dict, user: dict = Depends(current_user)):
    return db.add_lesson(user, payload)


@app.get("/api/learn/{slug}")
def learn(slug: str, user: dict = Depends(current_user)):
    return db.learn(user, slug)


@app.post("/api/lessons/{lesson_id}/complete")
def complete(lesson_id: int, user: dict = Depends(current_user)):
    return db.complete_lesson(user, lesson_id)


@app.post("/api/quizzes/{quiz_id}/submit")
def submit_quiz(quiz_id: int, payload: dict, user: dict = Depends(current_user)):
    return db.submit_quiz(user, quiz_id, payload.get("answers") or {})


@app.post("/api/assignments/{assignment_id}/submit")
def submit_assignment(assignment_id: int, payload: dict, user: dict = Depends(current_user)):
    return db.submit_assignment(user, assignment_id, payload.get("body") or "")


@app.get("/api/submissions")
def submissions(user: dict = Depends(current_user)):
    if user["role"] not in {"admin", "instructor", "moderator"}:
        raise PermissionError("Grading is for instructors.")
    return db.list_submissions(user)


@app.post("/api/submissions/{submission_id}/grade")
def grade(submission_id: int, payload: dict, user: dict = Depends(current_user)):
    return db.grade_submission(user, submission_id, payload.get("score"), payload.get("feedback") or "")


@app.get("/api/me/learning")
def my_learning(user: dict = Depends(current_user)):
    return db.my_learning(user)


@app.get("/api/me/classes")
def my_classes(user: dict = Depends(current_user)):
    return db.my_classes(user)


def _viewer(authorization: str | None, token: str) -> dict:
    raw = (token or "").strip()
    if not raw and authorization and authorization.lower().startswith("bearer "):
        raw = authorization.split(" ", 1)[1].strip()
    user_id = db.read_token(raw) if raw else None
    user = db.user_by_id(user_id) if user_id else None
    if not user:
        raise HTTPException(status_code=401, detail="Sign in to open this file.")
    return user


@app.get("/api/library")
def library(user: dict = Depends(current_user)):
    return materials.list_items(user)


@app.post("/api/library")
async def upload_library(
    title: str = Form(""),
    kind: str = Form(...),
    file: UploadFile = File(...),
    user: dict = Depends(current_user),
):
    return materials.save_file(user, title, kind, file.filename or "", file.file)


@app.post("/api/library/quizzes")
def upload_quiz(payload: dict, user: dict = Depends(current_user)):
    return materials.save_quiz(user, payload.get("title") or "", payload.get("questions") or [], payload.get("passing_score") or 70)


@app.delete("/api/library/{item_id}")
def delete_library_item(item_id: int, user: dict = Depends(current_user)):
    return materials.delete_item(user, item_id)


@app.post("/api/library/{item_id}/place")
def place_library_item(item_id: int, payload: dict, user: dict = Depends(current_user)):
    return materials.place_in_course(user, item_id, int(payload.get("chapter_id") or 0))


@app.get("/api/library/{item_id}/file")
def library_file(item_id: int, token: str = "", authorization: str | None = Header(default=None)):
    user = _viewer(authorization, token)
    path, name, mime = materials.file_for(user, item_id)
    return FileResponse(path, media_type=mime, filename=name, content_disposition_type="inline")


@app.get("/api/me/assessments")
def assessments(user: dict = Depends(current_user)):
    return db.my_assessments(user)


@app.get("/api/batches")
def batches(user: dict | None = Depends(optional_user)):
    return db.list_batches(user)


@app.post("/api/batches")
def create_batch(payload: dict, user: dict = Depends(current_user)):
    return db.create_batch(user, payload)


@app.get("/api/batches/{batch_id}")
def batch(batch_id: int, user: dict | None = Depends(optional_user)):
    return db.batch_detail(user, batch_id)


@app.post("/api/batches/{batch_id}/join")
def join_batch(batch_id: int, user: dict = Depends(current_user)):
    return db.join_batch(user, batch_id)


@app.get("/api/live-classes")
def live_classes(user: dict | None = Depends(optional_user)):
    return db.list_live(user)


@app.post("/api/live-classes")
def create_live(payload: dict, user: dict = Depends(current_user)):
    return db.create_live(user, payload)


@app.post("/api/live-classes/{class_id}/status")
def live_status(class_id: int, payload: dict, user: dict = Depends(current_user)):
    return db.set_live_status(user, class_id, payload.get("status") or "")


@app.post("/api/live-classes/{class_id}/attend")
def attend(class_id: int, user: dict = Depends(current_user)):
    return db.attend_live(user, class_id)


@app.get("/api/evaluations")
def evaluations(user: dict | None = Depends(optional_user)):
    return {"zoom_connected": db.zoom_connected(), "slots": db.list_slots(user)}


@app.post("/api/evaluations/generate")
def generate_evaluations(user: dict = Depends(current_user)):
    return db.generate_slots(user)


@app.post("/api/evaluations/book")
def book_evaluation(payload: dict, user: dict = Depends(current_user)):
    try:
        course_id = int(payload.get("course_id"))
    except (TypeError, ValueError):
        raise ValueError("Choose a course.") from None
    return db.book_evaluation(user, course_id)


@app.post("/api/evaluations/{slot_id}/close")
def close_evaluation(slot_id: int, payload: dict, user: dict = Depends(current_user)):
    return db.close_evaluation(user, slot_id, payload.get("outcome") or "", payload.get("notes") or "")


@app.post("/api/evaluations/{slot_id}/release")
def release_evaluation(slot_id: int, user: dict = Depends(current_user)):
    return db.release_evaluation(user, slot_id)


@app.get("/api/jobs")
def jobs(q: str = ""):
    return db.list_jobs(q)


@app.post("/api/jobs")
def create_job(payload: dict, user: dict = Depends(current_user)):
    return db.create_job(user, payload)


@app.post("/api/jobs/{job_id}/apply")
def apply_job(job_id: int, payload: dict, user: dict = Depends(current_user)):
    return db.apply_job(user, job_id, payload.get("note") or "")


@app.get("/api/me/applications")
def applications(user: dict = Depends(current_user)):
    return db.my_applications(user)


@app.get("/api/analytics")
def analytics(user: dict = Depends(current_user)):
    return db.analytics(user)


@app.get("/api/users")
def users(user: dict = Depends(current_user)):
    if user["role"] != "admin":
        raise PermissionError("Only an administrator can see every account.")
    return db.list_users()


@app.post("/api/users")
def create_account(payload: dict, user: dict = Depends(current_user)):
    if user["role"] != "admin":
        raise PermissionError("Only an administrator can create accounts.")
    role = payload.get("role") or "student"
    if role not in {"admin", "instructor", "student"}:
        raise ValueError("Choose administrator, instructor, or learner.")
    return db.create_user(payload.get("email") or "", payload.get("password") or "", payload.get("full_name") or "", role)


@app.patch("/api/users/{user_id}")
def update_user(user_id: int, payload: dict, user: dict = Depends(current_user)):
    if user["role"] != "admin":
        raise PermissionError("Only an administrator can change roles.")
    return db.set_role(user_id, payload.get("role") or "")


@app.get("/api/courses/{slug}/page")
def course_page(slug: str, user: dict | None = Depends(optional_user)):
    return learning.course_page(slug, user)


@app.get("/api/learn/{slug}/{chapter_number}/{lesson_number}")
def lesson_page(slug: str, chapter_number: int, lesson_number: int, user: dict | None = Depends(optional_user)):
    return learning.lesson_page(user, slug, chapter_number, lesson_number)


@app.post("/api/notes/{lesson_id}")
def save_note(lesson_id: int, payload: dict, user: dict = Depends(current_user)):
    return learning.save_note(user, lesson_id, payload.get("body") or "")


@app.post("/api/discussions")
def post_discussion(payload: dict, user: dict = Depends(current_user)):
    return learning.post_discussion(
        user,
        payload.get("body") or "",
        payload.get("lesson_id"),
        payload.get("batch_id"),
        payload.get("parent_id"),
    )


@app.get("/api/batches/{batch_id}/discussions")
def batch_discussions(batch_id: int):
    return learning.list_batch_discussions(batch_id)


@app.get("/api/batches/{batch_id}/announcements")
def batch_announcements(batch_id: int):
    return learning.list_announcements(batch_id)


@app.post("/api/batches/{batch_id}/announcements")
def create_announcement(batch_id: int, payload: dict, user: dict = Depends(current_user)):
    return learning.post_announcement(user, batch_id, payload.get("title") or "", payload.get("body") or "")


@app.post("/api/courses/{course_id}/reviews")
def review(course_id: int, payload: dict, user: dict = Depends(current_user)):
    return learning.post_review(user, course_id, payload.get("rating") or 0, payload.get("body") or "")


@app.get("/api/programs")
def programs(user: dict | None = Depends(optional_user)):
    return learning.list_programs(user)


@app.post("/api/programs/{program_id}/enroll")
def enroll_program(program_id: int, user: dict = Depends(current_user)):
    return learning.enroll_program(user, program_id)


@app.get("/api/courses/{slug}/certification")
def certification(slug: str, user: dict = Depends(current_user)):
    return learning.certification(user, slug)


@app.post("/api/certificates/request")
def request_certificate(payload: dict, user: dict = Depends(current_user)):
    return learning.request_certificate(user, int(payload.get("course_id")))


@app.post("/api/certificates/{request_id}/decide")
def decide_certificate(request_id: int, payload: dict, user: dict = Depends(current_user)):
    return learning.decide_certificate(user, request_id, bool(payload.get("passed")))


@app.get("/api/certificates")
def certificates():
    return learning.list_certificates()


@app.get("/api/profiles/{username}")
def profile(username: str, user: dict | None = Depends(optional_user)):
    return learning.profile(username, user)


@app.get("/api/exercises")
def exercises():
    return learning.list_exercises()


@app.get("/api/exercise-submissions")
def exercise_submissions(user: dict = Depends(current_user)):
    return learning.exercise_submissions(user)


@app.post("/api/exercises/{exercise_id}/submit")
def submit_exercise(exercise_id: int, payload: dict, user: dict = Depends(current_user)):
    return learning.submit_exercise(user, exercise_id, payload.get("code") or "")


@app.post("/api/exercise-submissions/{submission_id}/grade")
def grade_exercise(submission_id: int, payload: dict, user: dict = Depends(current_user)):
    return learning.grade_exercise(user, submission_id, payload.get("status") or "", payload.get("feedback") or "")


@app.get("/api/notifications")
def notifications(user: dict = Depends(current_user)):
    return learning.notifications(user)


@app.post("/api/notifications/read")
def read_notifications(user: dict = Depends(current_user)):
    return cima.mark_notifications(user)


@app.get("/api/desk")
def school_desk(user: dict = Depends(current_user)):
    return cima.desk(user)


@app.get("/api/school")
def school(user: dict = Depends(current_user)):
    return cima.school(user)


@app.patch("/api/school")
def rename_school(payload: dict, user: dict = Depends(current_user)):
    return cima.rename_school(user, payload.get("name") or "")


@app.get("/api/groups")
def groups(user: dict = Depends(current_user)):
    return cima.list_groups(user)


@app.post("/api/groups")
def create_group(payload: dict, user: dict = Depends(current_user)):
    return cima.create_group(user, payload.get("name") or "", payload.get("description") or "")


@app.get("/api/groups/{group_id}")
def group_one(group_id: int, user: dict = Depends(current_user)):
    return cima.group_detail(user, group_id)


@app.post("/api/groups/{group_id}/members")
def group_member(group_id: int, payload: dict, user: dict = Depends(current_user)):
    return cima.add_member(user, group_id, payload.get("email") or "")


@app.post("/api/feed")
def post_feed(payload: dict, user: dict = Depends(current_user)):
    group_id = payload.get("group_id")
    return cima.post_news(user, payload.get("body") or "", int(group_id) if group_id else None)


@app.post("/api/courses/{course_id}/copy")
def copy_course(course_id: int, user: dict = Depends(current_user)):
    return cima.copy_course(user, course_id)


@app.post("/api/courses/{course_id}/trash")
def trash_course(course_id: int, user: dict = Depends(current_user)):
    return cima.trash_course(user, course_id)


@app.get("/api/courses/{course_id}/roster")
def course_roster(course_id: int, user: dict = Depends(current_user)):
    return cima.roster(user, course_id)


@app.post("/api/courses/{course_id}/learners/{learner_id}")
def enrollment_active(course_id: int, learner_id: int, payload: dict, user: dict = Depends(current_user)):
    return cima.set_enrollment(user, course_id, learner_id, bool(payload.get("active")))


@app.get("/api/trash")
def trash(user: dict = Depends(current_user)):
    return cima.trash_bin(user)


@app.post("/api/trash/courses/{course_id}/restore")
def restore_course(course_id: int, user: dict = Depends(current_user)):
    return cima.restore_course(user, course_id)


@app.delete("/api/trash/courses/{course_id}")
def purge_course(course_id: int, user: dict = Depends(current_user)):
    return cima.purge_course(user, course_id)


@app.post("/api/trash/resources/{item_id}/restore")
def restore_resource(item_id: int, user: dict = Depends(current_user)):
    return cima.restore_resource(user, item_id)


@app.delete("/api/trash/resources/{item_id}")
def purge_resource(item_id: int, user: dict = Depends(current_user)):
    return cima.purge_resource(user, item_id)


@app.get("/api/reports")
def reports(user: dict = Depends(current_user)):
    return cima.reports(user)


@app.get("/api/calendar")
def calendar(user: dict = Depends(current_user)):
    return cima.calendar(user)


@app.get("/api/search")
def search(q: str = "", user: dict = Depends(current_user)):
    return cima.search(user, q)


@app.get("/api/quizzes")
def quizzes(user: dict | None = Depends(optional_user)):
    return learning.list_quizzes(user)


@app.get("/api/quiz-submissions")
def quiz_submissions(user: dict = Depends(current_user)):
    return learning.quiz_submissions(user)


@app.get("/api/quizzes/{quiz_id}")
def quiz_one(quiz_id: int, user: dict | None = Depends(optional_user)):
    with db.connect() as conn:
        payload = db.quiz_payload(conn, quiz_id, False, user["id"] if user else None)
        if not payload:
            raise LookupError("Quiz not found.")
        return payload


@app.get("/api/jobs/{job_id}")
def job_detail(job_id: int, user: dict | None = Depends(optional_user)):
    with db.connect() as conn:
        job = db.one(
            conn,
            """
            SELECT j.*, u.full_name AS posted_by_name,
                   (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS applicants
            FROM jobs j LEFT JOIN users u ON u.id = j.posted_by WHERE j.id = ?
            """,
            (job_id,),
        )
        if not job:
            raise LookupError("Role not found.")
        applications = []
        if user and user["role"] in {"admin", "instructor", "moderator"}:
            applications = db.many(
                conn,
                """
                SELECT a.*, u.full_name, u.email FROM applications a
                JOIN users u ON u.id = a.user_id WHERE a.job_id = ? ORDER BY a.applied_at DESC
                """,
                (job_id,),
            )
        applied = bool(user and db.one(conn, "SELECT id FROM applications WHERE job_id = ? AND user_id = ?", (job_id, user["id"])))
    return {"job": job, "applications": applications, "applied": applied}


_frontend = getattr(app, "frontend", None)
_dist = _here.parent / "dist"
if _frontend is not None and _dist.is_dir():
    _frontend("/", directory=str(_dist), fallback="index.html")
