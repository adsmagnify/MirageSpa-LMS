<?php

require __DIR__ . '/src/runtime.php';
require __DIR__ . '/src/store.php';
require __DIR__ . '/src/materials.php';
require __DIR__ . '/src/learning.php';
require __DIR__ . '/src/cima.php';

use Mirage\AppException;
use Mirage\FileDownload;

function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }
    return $path;
}

function request_method(): string
{
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

function route_is(string $method, string $pattern): ?array
{
    if (request_method() !== $method) {
        return null;
    }
    $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
    if (!preg_match($regex, request_path(), $matches)) {
        return null;
    }
    $params = [];
    foreach ($matches as $key => $value) {
        if (is_string($key)) {
            $params[$key] = $value;
        }
    }
    return $params;
}

function allow_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = ['http://127.0.0.1:5173', 'http://localhost:5173'];
    $extra = getenv('CORS_ORIGINS');
    if (is_string($extra) && $extra !== '') {
        foreach (explode(',', $extra) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $allowed[] = $item;
            }
        }
    }
    $ok = $origin !== '' && (in_array($origin, $allowed, true) || preg_match('#^https://.*\.vercel\.app$#', $origin) === 1);
    if ($ok) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
}

function require_user(): array
{
    $token = \Mirage\bearer_token();
    if ($token === '') {
        throw new AppException(401, 'Sign in to continue.');
    }
    $userId = \Mirage\read_token($token);
    $user = $userId ? \Mirage\Store\user_by_id($userId) : null;
    if (!$user) {
        throw new AppException(401, 'Session expired. Sign in again.');
    }
    return $user;
}

function optional_user(): ?array
{
    $token = \Mirage\bearer_token();
    if ($token === '') {
        $token = trim((string) ($_GET['token'] ?? ''));
    }
    if ($token === '') {
        return null;
    }
    $userId = \Mirage\read_token($token);
    return $userId ? \Mirage\Store\user_by_id($userId) : null;
}

function viewer(): array
{
    $user = optional_user();
    if (!$user) {
        throw new AppException(401, 'Sign in to open this file.');
    }
    return $user;
}

function body(): array
{
    return \Mirage\json_body();
}

function query_text(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? $value : '';
}

function send_file(FileDownload $file): void
{
    header('Content-Type: ' . $file->mime);
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $file->name) . '"');
    header('Content-Length: ' . (string) filesize($file->path));
    readfile($file->path);
}

function dispatch(): mixed
{
    if (($p = route_is('GET', '/api/health')) !== null) {
        return ['ok' => true, 'zoom_connected' => \Mirage\Store\zoom_connected()];
    }
    if (route_is('GET', '/api/auth/status') !== null) {
        return ['needs_setup' => \Mirage\Store\user_count() === 0];
    }
    if (route_is('POST', '/api/auth/signup') !== null) {
        if (\Mirage\Store\user_count()) {
            throw new AppException(403, 'An administrator creates accounts and assigns each role.');
        }
        $payload = body();
        $user = \Mirage\Store\create_user((string) ($payload['email'] ?? ''), (string) ($payload['password'] ?? ''), (string) ($payload['full_name'] ?? ''), 'admin');
        return ['token' => \Mirage\make_token((int) $user['id']), 'user' => $user];
    }
    if (route_is('POST', '/api/auth/login') !== null) {
        $payload = body();
        $user = \Mirage\Store\authenticate((string) ($payload['email'] ?? ''), (string) ($payload['password'] ?? ''));
        if (!$user) {
            throw new AppException(401, 'Those details do not match.');
        }
        return ['token' => \Mirage\make_token((int) $user['id']), 'user' => $user];
    }
    if (route_is('GET', '/api/auth/me') !== null) {
        return require_user();
    }
    if (route_is('GET', '/api/categories') !== null) {
        return \Mirage\Store\categories();
    }
    if (route_is('GET', '/api/courses') !== null) {
        return \Mirage\Store\list_courses(query_text('q'), query_text('category'));
    }
    if (($p = route_is('POST', '/api/courses/{course_id}/enroll')) !== null) {
        return \Mirage\Store\enroll(require_user(), (int) $p['course_id']);
    }
    if (($p = route_is('POST', '/api/courses/{course_id}/assign')) !== null) {
        $payload = body();
        return \Mirage\Store\assign_course(require_user(), (int) $p['course_id'], (string) ($payload['email'] ?? ''));
    }
    if (($p = route_is('GET', '/api/courses/{slug}/page')) !== null) {
        return \Mirage\Learning\course_page($p['slug'], optional_user());
    }
    if (($p = route_is('GET', '/api/courses/{slug}/certification')) !== null) {
        return \Mirage\Learning\certification(require_user(), $p['slug']);
    }
    if (($p = route_is('POST', '/api/courses/{course_id}/reviews')) !== null) {
        $payload = body();
        return \Mirage\Learning\post_review(require_user(), (int) $p['course_id'], (int) ($payload['rating'] ?? 0), (string) ($payload['body'] ?? ''));
    }
    if (($p = route_is('POST', '/api/courses/{course_id}/copy')) !== null) {
        return \Mirage\Cima\copy_course(require_user(), (int) $p['course_id']);
    }
    if (($p = route_is('POST', '/api/courses/{course_id}/trash')) !== null) {
        return \Mirage\Cima\trash_course(require_user(), (int) $p['course_id']);
    }
    if (($p = route_is('GET', '/api/courses/{course_id}/roster')) !== null) {
        return \Mirage\Cima\roster(require_user(), (int) $p['course_id']);
    }
    if (($p = route_is('POST', '/api/courses/{course_id}/learners/{learner_id}')) !== null) {
        $payload = body();
        return \Mirage\Cima\set_enrollment(require_user(), (int) $p['course_id'], (int) $p['learner_id'], (bool) ($payload['active'] ?? false));
    }
    if (($p = route_is('GET', '/api/courses/{slug}')) !== null) {
        return \Mirage\Store\get_public_course($p['slug'], optional_user());
    }
    if (route_is('GET', '/api/studio/courses') !== null) {
        $user = require_user();
        if ($user['role'] === 'admin') {
            return \Mirage\Store\list_courses('', '', true);
        }
        return \Mirage\Store\list_courses('', '', true, (int) $user['id']);
    }
    if (route_is('POST', '/api/studio/courses') !== null) {
        return \Mirage\Store\create_course(require_user(), body());
    }
    if (($p = route_is('GET', '/api/studio/courses/{course_id}')) !== null) {
        return \Mirage\Store\studio_course(require_user(), (int) $p['course_id']);
    }
    if (($p = route_is('PATCH', '/api/studio/courses/{course_id}')) !== null) {
        return \Mirage\Store\update_course(require_user(), (int) $p['course_id'], body());
    }
    if (route_is('POST', '/api/studio/chapters') !== null) {
        $payload = body();
        return \Mirage\Store\add_chapter(require_user(), (int) ($payload['course_id'] ?? 0), (string) ($payload['title'] ?? ''));
    }
    if (route_is('POST', '/api/studio/lessons') !== null) {
        return \Mirage\Store\add_lesson(require_user(), body());
    }
    if (($p = route_is('GET', '/api/learn/{slug}/{chapter_number}/{lesson_number}')) !== null) {
        return \Mirage\Learning\lesson_page(optional_user(), $p['slug'], (int) $p['chapter_number'], (int) $p['lesson_number']);
    }
    if (($p = route_is('GET', '/api/learn/{slug}')) !== null) {
        return \Mirage\Store\learn(require_user(), $p['slug']);
    }
    if (($p = route_is('POST', '/api/lessons/{lesson_id}/complete')) !== null) {
        return \Mirage\Store\complete_lesson(require_user(), (int) $p['lesson_id']);
    }
    if (($p = route_is('POST', '/api/quizzes/{quiz_id}/submit')) !== null) {
        $payload = body();
        return \Mirage\Store\submit_quiz(require_user(), (int) $p['quiz_id'], is_array($payload['answers'] ?? null) ? $payload['answers'] : []);
    }
    if (($p = route_is('POST', '/api/assignments/{assignment_id}/submit')) !== null) {
        $payload = body();
        return \Mirage\Store\submit_assignment(require_user(), (int) $p['assignment_id'], (string) ($payload['body'] ?? ''));
    }
    if (route_is('GET', '/api/submissions') !== null) {
        $user = require_user();
        if (!in_array($user['role'], ['admin', 'instructor', 'moderator'], true)) {
            throw new AppException(403, 'Grading is for instructors.');
        }
        return \Mirage\Store\list_submissions($user);
    }
    if (($p = route_is('POST', '/api/submissions/{submission_id}/grade')) !== null) {
        $payload = body();
        return \Mirage\Store\grade_submission(require_user(), (int) $p['submission_id'], $payload['score'] ?? null, (string) ($payload['feedback'] ?? ''));
    }
    if (route_is('GET', '/api/me/learning') !== null) {
        return \Mirage\Store\my_learning(require_user());
    }
    if (route_is('GET', '/api/me/classes') !== null) {
        return \Mirage\Store\my_classes(require_user());
    }
    if (route_is('GET', '/api/library') !== null) {
        return \Mirage\Materials\list_items(require_user());
    }
    if (route_is('POST', '/api/library/quizzes') !== null) {
        $payload = body();
        return \Mirage\Materials\save_quiz(require_user(), (string) ($payload['title'] ?? ''), is_array($payload['questions'] ?? null) ? $payload['questions'] : [], (int) ($payload['passing_score'] ?? 70));
    }
    if (route_is('POST', '/api/library') !== null) {
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) {
            throw new AppException(400, 'Choose a file.');
        }
        return \Mirage\Materials\save_file(require_user(), (string) ($_POST['title'] ?? ''), (string) ($_POST['kind'] ?? ''), $file);
    }
    if (($p = route_is('DELETE', '/api/library/{item_id}')) !== null) {
        return \Mirage\Materials\delete_item(require_user(), (int) $p['item_id']);
    }
    if (($p = route_is('POST', '/api/library/{item_id}/place')) !== null) {
        $payload = body();
        return \Mirage\Materials\place_in_course(require_user(), (int) $p['item_id'], (int) ($payload['chapter_id'] ?? 0));
    }
    if (($p = route_is('GET', '/api/library/{item_id}/file')) !== null) {
        [$path, $name, $mime] = \Mirage\Materials\file_for(viewer(), (int) $p['item_id']);
        return new FileDownload($path, $name, $mime);
    }
    if (route_is('GET', '/api/me/assessments') !== null) {
        return \Mirage\Store\my_assessments(require_user());
    }
    if (route_is('GET', '/api/batches') !== null) {
        return \Mirage\Store\list_batches(optional_user());
    }
    if (route_is('POST', '/api/batches') !== null) {
        return \Mirage\Store\create_batch(require_user(), body());
    }
    if (($p = route_is('GET', '/api/batches/{batch_id}/discussions')) !== null) {
        return \Mirage\Learning\list_batch_discussions((int) $p['batch_id']);
    }
    if (($p = route_is('GET', '/api/batches/{batch_id}/announcements')) !== null) {
        return \Mirage\Learning\list_announcements((int) $p['batch_id']);
    }
    if (($p = route_is('POST', '/api/batches/{batch_id}/announcements')) !== null) {
        $payload = body();
        return \Mirage\Learning\post_announcement(require_user(), (int) $p['batch_id'], (string) ($payload['title'] ?? ''), (string) ($payload['body'] ?? ''));
    }
    if (($p = route_is('GET', '/api/batches/{batch_id}')) !== null) {
        return \Mirage\Store\batch_detail(optional_user(), (int) $p['batch_id']);
    }
    if (($p = route_is('POST', '/api/batches/{batch_id}/join')) !== null) {
        return \Mirage\Store\join_batch(require_user(), (int) $p['batch_id']);
    }
    if (route_is('GET', '/api/live-classes') !== null) {
        return \Mirage\Store\list_live(optional_user());
    }
    if (route_is('POST', '/api/live-classes') !== null) {
        return \Mirage\Store\create_live(require_user(), body());
    }
    if (($p = route_is('POST', '/api/live-classes/{class_id}/status')) !== null) {
        $payload = body();
        return \Mirage\Store\set_live_status(require_user(), (int) $p['class_id'], (string) ($payload['status'] ?? ''));
    }
    if (($p = route_is('POST', '/api/live-classes/{class_id}/attend')) !== null) {
        return \Mirage\Store\attend_live(require_user(), (int) $p['class_id']);
    }
    if (route_is('GET', '/api/evaluations') !== null) {
        $user = optional_user();
        return ['zoom_connected' => \Mirage\Store\zoom_connected(), 'slots' => \Mirage\Store\list_slots($user)];
    }
    if (route_is('POST', '/api/evaluations/generate') !== null) {
        return \Mirage\Store\generate_slots(require_user());
    }
    if (route_is('POST', '/api/evaluations/book') !== null) {
        $payload = body();
        if (!isset($payload['course_id']) || !is_numeric($payload['course_id'])) {
            throw new AppException(400, 'Choose a course.');
        }
        return \Mirage\Store\book_evaluation(require_user(), (int) $payload['course_id']);
    }
    if (($p = route_is('POST', '/api/evaluations/{slot_id}/close')) !== null) {
        $payload = body();
        return \Mirage\Store\close_evaluation(require_user(), (int) $p['slot_id'], (string) ($payload['outcome'] ?? ''), (string) ($payload['notes'] ?? ''));
    }
    if (($p = route_is('POST', '/api/evaluations/{slot_id}/release')) !== null) {
        return \Mirage\Store\release_evaluation(require_user(), (int) $p['slot_id']);
    }
    if (route_is('GET', '/api/jobs') !== null) {
        return \Mirage\Store\list_jobs(query_text('q'));
    }
    if (route_is('POST', '/api/jobs') !== null) {
        return \Mirage\Store\create_job(require_user(), body());
    }
    if (($p = route_is('POST', '/api/jobs/{job_id}/apply')) !== null) {
        $payload = body();
        return \Mirage\Store\apply_job(require_user(), (int) $p['job_id'], (string) ($payload['note'] ?? ''));
    }
    if (($p = route_is('GET', '/api/jobs/{job_id}')) !== null) {
        $user = optional_user();
        $db = \Mirage\db();
        $job = \Mirage\one($db, "SELECT j.*, u.full_name AS posted_by_name, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS applicants FROM jobs j LEFT JOIN users u ON u.id = j.posted_by WHERE j.id = ?", [(int) $p['job_id']]);
        if (!$job) {
            throw new AppException(404, 'Role not found.');
        }
        $applications = [];
        if ($user && in_array($user['role'], ['admin', 'instructor', 'moderator'], true)) {
            $applications = \Mirage\many($db, "SELECT a.*, u.full_name, u.email FROM applications a JOIN users u ON u.id = a.user_id WHERE a.job_id = ? ORDER BY a.applied_at DESC", [(int) $p['job_id']]);
        }
        $applied = $user && \Mirage\one($db, 'SELECT id FROM applications WHERE job_id = ? AND user_id = ?', [(int) $p['job_id'], (int) $user['id']]) !== null;
        return ['job' => $job, 'applications' => $applications, 'applied' => $applied];
    }
    if (route_is('GET', '/api/me/applications') !== null) {
        return \Mirage\Store\my_applications(require_user());
    }
    if (route_is('GET', '/api/analytics') !== null) {
        return \Mirage\Store\analytics(require_user());
    }
    if (route_is('GET', '/api/users') !== null) {
        $user = require_user();
        if ($user['role'] !== 'admin') {
            throw new AppException(403, 'Only an administrator can see every account.');
        }
        return \Mirage\Store\list_users();
    }
    if (route_is('POST', '/api/users') !== null) {
        $user = require_user();
        if ($user['role'] !== 'admin') {
            throw new AppException(403, 'Only an administrator can create accounts.');
        }
        $payload = body();
        $role = (string) ($payload['role'] ?? 'student');
        if (!in_array($role, ['admin', 'instructor', 'student'], true)) {
            throw new AppException(400, 'Choose administrator, instructor, or learner.');
        }
        return \Mirage\Store\create_user((string) ($payload['email'] ?? ''), (string) ($payload['password'] ?? ''), (string) ($payload['full_name'] ?? ''), $role);
    }
    if (($p = route_is('PATCH', '/api/users/{user_id}')) !== null) {
        $user = require_user();
        if ($user['role'] !== 'admin') {
            throw new AppException(403, 'Only an administrator can change roles.');
        }
        $payload = body();
        return \Mirage\Store\set_role((int) $p['user_id'], (string) ($payload['role'] ?? ''));
    }
    if (($p = route_is('POST', '/api/notes/{lesson_id}')) !== null) {
        $payload = body();
        return \Mirage\Learning\save_note(require_user(), (int) $p['lesson_id'], (string) ($payload['body'] ?? ''));
    }
    if (route_is('POST', '/api/discussions') !== null) {
        $payload = body();
        return \Mirage\Learning\post_discussion(
            require_user(),
            (string) ($payload['body'] ?? ''),
            isset($payload['lesson_id']) ? (int) $payload['lesson_id'] : null,
            isset($payload['batch_id']) ? (int) $payload['batch_id'] : null,
            isset($payload['parent_id']) ? (int) $payload['parent_id'] : null,
        );
    }
    if (route_is('GET', '/api/programs') !== null) {
        return \Mirage\Learning\list_programs(optional_user());
    }
    if (($p = route_is('POST', '/api/programs/{program_id}/enroll')) !== null) {
        return \Mirage\Learning\enroll_program(require_user(), (int) $p['program_id']);
    }
    if (route_is('POST', '/api/certificates/request') !== null) {
        $payload = body();
        return \Mirage\Learning\request_certificate(require_user(), (int) ($payload['course_id'] ?? 0));
    }
    if (($p = route_is('POST', '/api/certificates/{request_id}/decide')) !== null) {
        $payload = body();
        return \Mirage\Learning\decide_certificate(require_user(), (int) $p['request_id'], (bool) ($payload['passed'] ?? false));
    }
    if (route_is('GET', '/api/certificates') !== null) {
        return \Mirage\Learning\list_certificates();
    }
    if (($p = route_is('GET', '/api/profiles/{username}')) !== null) {
        return \Mirage\Learning\profile($p['username'], optional_user());
    }
    if (route_is('GET', '/api/exercises') !== null) {
        return \Mirage\Learning\list_exercises();
    }
    if (route_is('GET', '/api/exercise-submissions') !== null) {
        return \Mirage\Learning\exercise_submissions(require_user());
    }
    if (($p = route_is('POST', '/api/exercises/{exercise_id}/submit')) !== null) {
        $payload = body();
        return \Mirage\Learning\submit_exercise(require_user(), (int) $p['exercise_id'], (string) ($payload['code'] ?? ''));
    }
    if (($p = route_is('POST', '/api/exercise-submissions/{submission_id}/grade')) !== null) {
        $payload = body();
        return \Mirage\Learning\grade_exercise(require_user(), (int) $p['submission_id'], (string) ($payload['status'] ?? ''), (string) ($payload['feedback'] ?? ''));
    }
    if (route_is('GET', '/api/notifications') !== null) {
        return \Mirage\Learning\notifications(require_user());
    }
    if (route_is('POST', '/api/notifications/read') !== null) {
        return \Mirage\Cima\mark_notifications(require_user());
    }
    if (route_is('GET', '/api/desk') !== null) {
        return \Mirage\Cima\desk(require_user());
    }
    if (route_is('GET', '/api/school') !== null) {
        return \Mirage\Cima\school(require_user());
    }
    if (route_is('PATCH', '/api/school') !== null) {
        $payload = body();
        return \Mirage\Cima\rename_school(require_user(), (string) ($payload['name'] ?? ''));
    }
    if (route_is('GET', '/api/groups') !== null) {
        return \Mirage\Cima\list_groups(require_user());
    }
    if (route_is('POST', '/api/groups') !== null) {
        $payload = body();
        return \Mirage\Cima\create_group(require_user(), (string) ($payload['name'] ?? ''), (string) ($payload['description'] ?? ''));
    }
    if (($p = route_is('GET', '/api/groups/{group_id}')) !== null) {
        return \Mirage\Cima\group_detail(require_user(), (int) $p['group_id']);
    }
    if (($p = route_is('POST', '/api/groups/{group_id}/members')) !== null) {
        $payload = body();
        return \Mirage\Cima\add_member(require_user(), (int) $p['group_id'], (string) ($payload['email'] ?? ''));
    }
    if (route_is('POST', '/api/feed') !== null) {
        $payload = body();
        $groupId = $payload['group_id'] ?? null;
        return \Mirage\Cima\post_news(require_user(), (string) ($payload['body'] ?? ''), $groupId ? (int) $groupId : null);
    }
    if (route_is('GET', '/api/trash') !== null) {
        return \Mirage\Cima\trash_bin(require_user());
    }
    if (($p = route_is('POST', '/api/trash/courses/{course_id}/restore')) !== null) {
        return \Mirage\Cima\restore_course(require_user(), (int) $p['course_id']);
    }
    if (($p = route_is('DELETE', '/api/trash/courses/{course_id}')) !== null) {
        return \Mirage\Cima\purge_course(require_user(), (int) $p['course_id']);
    }
    if (($p = route_is('POST', '/api/trash/resources/{item_id}/restore')) !== null) {
        return \Mirage\Cima\restore_resource(require_user(), (int) $p['item_id']);
    }
    if (($p = route_is('DELETE', '/api/trash/resources/{item_id}')) !== null) {
        return \Mirage\Cima\purge_resource(require_user(), (int) $p['item_id']);
    }
    if (route_is('GET', '/api/reports') !== null) {
        return \Mirage\Cima\reports(require_user());
    }
    if (route_is('GET', '/api/calendar') !== null) {
        return \Mirage\Cima\calendar(require_user());
    }
    if (route_is('GET', '/api/search') !== null) {
        return \Mirage\Cima\search(require_user(), query_text('q'));
    }
    if (route_is('GET', '/api/quizzes') !== null) {
        return \Mirage\Learning\list_quizzes(optional_user());
    }
    if (route_is('GET', '/api/quiz-submissions') !== null) {
        return \Mirage\Learning\quiz_submissions(require_user());
    }
    if (($p = route_is('GET', '/api/quizzes/{quiz_id}')) !== null) {
        $user = optional_user();
        $payload = \Mirage\Store\quiz_payload(\Mirage\db(), (int) $p['quiz_id'], false, $user ? (int) $user['id'] : null);
        if (!$payload) {
            throw new AppException(404, 'Quiz not found.');
        }
        return $payload;
    }
    throw new AppException(404, 'Not found.');
}

allow_cors();
if (request_method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    \Mirage\Store\init_db();
    $db = \Mirage\db();
    if (!$db->inTransaction()) {
        $db->beginTransaction();
    }
    $result = dispatch();
    if ($db->inTransaction()) {
        $db->commit();
    }
    if ($result instanceof FileDownload) {
        send_file($result);
    } else {
        \Mirage\send_json($result);
    }
} catch (AppException $error) {
    $pdo = \Mirage\db();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    \Mirage\send_error($error->status, $error->getMessage());
} catch (Throwable $error) {
    try {
        $pdo = \Mirage\db();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable) {
    }
    \Mirage\send_error(500, 'Something went wrong.');
}
