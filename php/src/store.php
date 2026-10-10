<?php
namespace Mirage\Store;

const SCHEMA = <<<'SQL'

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

SQL;

function init_db(): void
{
    $db = \Mirage\db();
    \Mirage\run($db, 'PRAGMA foreign_keys = OFF');
    $version = null;
    $exists = \Mirage\one($db, "SELECT name FROM sqlite_master WHERE type='table' AND name='app_meta'");
    if ($exists) {
        $row = \Mirage\one($db, "SELECT value FROM app_meta WHERE key = 'schema'");
        $version = $row ? $row['value'] : null;
    }
    if ($version !== '6') {
        $tables = \Mirage\many($db, "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        foreach ($tables as $record) {
            \Mirage\run($db, 'DROP TABLE IF EXISTS "' . $record['name'] . '"');
        }
        \Mirage\script($db, SCHEMA);
        seed($db);
        \Mirage\run($db, "INSERT INTO app_meta (key, value) VALUES ('schema', '6')");
    }
    ensure_library($db);
    \Mirage\Cima\ensure($db);
    \Mirage\run($db, 'PRAGMA foreign_keys = ON');
}

function ensure_library(\PDO $db): void
{
    \Mirage\script($db, <<<'SQL'

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
        
SQL);
    $columns = [];
    foreach (\Mirage\many($db, 'PRAGMA table_info(lessons)') as $row) {
        $columns[$row['name']] = true;
    }
    if (!isset($columns['library_item_id'])) {
        \Mirage\run($db, 'ALTER TABLE lessons ADD COLUMN library_item_id INTEGER');
    }
}

function unique_username(\PDO $db, string $base): string
{
    $username = \Mirage\slugify($base) ?: 'member';
    $candidate = $username;
    $number = 2;
    while (\Mirage\one($db, 'SELECT id FROM users WHERE username = ?', [$candidate])) {
        $candidate = $username . '-' . $number;
        $number += 1;
    }
    return $candidate;
}

function sync_progress(\PDO $db, int $user_id, int $course_id, ?int $lesson_id = null): int
{
    $progress = progress_percent($db, $user_id, $course_id);
    if ($lesson_id) {
        \Mirage\run(
            $db,
            'UPDATE enrollments SET progress = ?, current_lesson_id = ? WHERE user_id = ? AND course_id = ?',
            [$progress, $lesson_id, $user_id, $course_id]
        );
    } else {
        \Mirage\run(
            $db,
            'UPDATE enrollments SET progress = ? WHERE user_id = ? AND course_id = ?',
            [$progress, $user_id, $course_id]
        );
    }
    return $progress;
}

function log_event(\PDO $db, string $kind, ?int $user_id = null, ?int $course_id = null, ?string $created_at = null): void
{
    \Mirage\run(
        $db,
        'INSERT INTO events (kind, user_id, course_id, created_at) VALUES (?, ?, ?, ?)',
        [$kind, $user_id, $course_id, $created_at ?: \Mirage\app_now()]
    );
}

function create_user(string $email, string $password, string $full_name, string $role = 'student', string $headline = '', ?string $created_at = null): array
{
    $email = strtolower(trim($email));
    if (!str_contains($email, '@') || strlen($password) < 4 || strlen(trim($full_name)) < 2) {
        throw new \Mirage\AppException(400, 'Use a real name, a valid email, and a password of at least 4 characters.');
    }
    if (!in_array($role, ['admin', 'instructor', 'student', 'moderator'], true)) {
        throw new \Mirage\AppException(400, 'Unknown role.');
    }
    $db = \Mirage\db();
    if (\Mirage\one($db, 'SELECT id FROM users WHERE email = ?', [$email])) {
        throw new \Mirage\AppException(400, 'An account with that email already exists.');
    }
    $stamp = $created_at ?: \Mirage\app_now();
    $username = unique_username($db, explode('@', $email, 2)[0]);
    \Mirage\run(
        $db,
        'INSERT INTO users (email, username, password_hash, full_name, role, headline, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$email, $username, \Mirage\hash_password($password), trim($full_name), $role, $headline, $stamp]
    );
    $user_id = \Mirage\insert_id($db);
    log_event($db, 'signup', $user_id, null, $stamp);
    return \Mirage\public_user(\Mirage\one($db, 'SELECT * FROM users WHERE id = ?', [$user_id]));
}

function authenticate(string $email, string $password): ?array
{
    $db = \Mirage\db();
    $row = \Mirage\one($db, 'SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
    if (!$row || !\Mirage\verify_password($password, $row['password_hash'])) {
        return null;
    }
    return \Mirage\public_user($row);
}

function user_by_id(int $user_id): ?array
{
    $db = \Mirage\db();
    return \Mirage\public_user(\Mirage\one($db, 'SELECT * FROM users WHERE id = ?', [$user_id]));
}

function user_count(): int
{
    $db = \Mirage\db();
    $row = \Mirage\one($db, 'SELECT COUNT(*) AS n FROM users');
    return $row['n'];
}

function list_users(): array
{
    $db = \Mirage\db();
    $users = [];
    foreach (\Mirage\many($db, 'SELECT * FROM users ORDER BY created_at DESC') as $row) {
        $users[] = \Mirage\public_user($row);
    }
    return $users;
}

function set_role(int $user_id, string $role): array
{
    if (!in_array($role, ['admin', 'instructor', 'student', 'moderator'], true)) {
        throw new \Mirage\AppException(400, 'Unknown role.');
    }
    $db = \Mirage\db();
    $target = \Mirage\one($db, 'SELECT * FROM users WHERE id = ?', [$user_id]);
    if (!$target) {
        throw new \Mirage\AppException(404, 'Person not found.');
    }
    if ($target['role'] === 'admin' && $role !== 'admin') {
        $admins = \Mirage\one($db, "SELECT COUNT(*) AS n FROM users WHERE role = 'admin'");
        if ($admins['n'] <= 1) {
            throw new \Mirage\AppException(400, 'Keep at least one administrator.');
        }
    }
    \Mirage\run($db, 'UPDATE users SET role = ? WHERE id = ?', [$role, $user_id]);
    return \Mirage\public_user(\Mirage\one($db, 'SELECT * FROM users WHERE id = ?', [$user_id]));
}

function staff(array $user): bool
{
    return in_array($user['role'], ['admin', 'instructor', 'moderator'], true);
}

function can_edit_course(array $user, array $course): bool
{
    return $user['role'] === 'admin' || ($user['role'] === 'instructor' && $course['instructor_id'] == $user['id']);
}

function course_row(\PDO $db, int $course_id): array
{
    $row = \Mirage\one(
        $db,
        <<<'SQL'

        SELECT c.*, u.full_name AS instructor_name, u.headline AS instructor_headline,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS learners
        FROM courses c JOIN users u ON u.id = c.instructor_id
        WHERE c.id = ?
        
SQL,
        [$course_id]
    );
    if (!$row) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    return $row;
}

function course_by_slug(\PDO $db, string $slug): array
{
    $row = \Mirage\one($db, 'SELECT id FROM courses WHERE slug = ?', [$slug]);
    if (!$row) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    return course_row($db, $row['id']);
}

function outline(\PDO $db, int $course_id, bool $with_content = false, bool $with_answers = false): array
{
    $chapters = \Mirage\many($db, 'SELECT * FROM chapters WHERE course_id = ? ORDER BY position, id', [$course_id]);
    foreach ($chapters as $index => $chapter) {
        $lessons = \Mirage\many($db, 'SELECT * FROM lessons WHERE chapter_id = ? ORDER BY position, id', [$chapter['id']]);
        $cleaned = [];
        foreach ($lessons as $lesson) {
            $item = [
                'id' => $lesson['id'],
                'title' => $lesson['title'],
                'kind' => $lesson['kind'],
                'minutes' => $lesson['minutes'],
                'position' => $lesson['position'],
            ];
            if ($with_content) {
                $item['body'] = $lesson['body'];
                $item['video_url'] = $lesson['video_url'];
                $item['quiz'] = $lesson['quiz_id'] ? quiz_payload($db, (int) $lesson['quiz_id'], $with_answers) : null;
                $item['assignment'] = $lesson['assignment_id'] ? assignment_payload($db, (int) $lesson['assignment_id']) : null;
            }
            $cleaned[] = $item;
        }
        $chapters[$index]['lessons'] = $cleaned;
    }
    return $chapters;
}

function quiz_payload(\PDO $db, int $quiz_id, bool $with_answers = false, ?int $user_id = null): ?array
{
    $quiz = \Mirage\one($db, 'SELECT * FROM quizzes WHERE id = ?', [$quiz_id]);
    if (!$quiz) {
        return null;
    }
    $questions = [];
    foreach (\Mirage\many($db, 'SELECT * FROM questions WHERE quiz_id = ? ORDER BY position, id', [$quiz_id]) as $question) {
        $item = [
            'id' => $question['id'],
            'prompt' => $question['prompt'],
            'options' => json_decode($question['options_json'], true),
        ];
        if ($with_answers) {
            $item['answer_index'] = $question['answer_index'];
            $item['explanation'] = $question['explanation'];
        }
        $questions[] = $item;
    }
    $show_answers = array_key_exists('show_answers', $quiz) ? $quiz['show_answers'] : 1;
    $payload = [
        'id' => $quiz['id'],
        'title' => $quiz['title'],
        'passing_score' => $quiz['passing_score'],
        'max_attempts' => ($quiz['max_attempts'] ?? 0) ?: 0,
        'duration_minutes' => ($quiz['duration_minutes'] ?? 0) ?: 0,
        'show_answers' => (bool) $show_answers,
        'questions' => $questions,
    ];
    if ($user_id) {
        $attempt = \Mirage\one(
            $db,
            'SELECT * FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1',
            [$quiz_id, $user_id]
        );
        if ($attempt) {
            $payload['last_attempt'] = [
                'score' => $attempt['score'],
                'passed' => (bool) $attempt['passed'],
                'submitted_at' => $attempt['submitted_at'],
                'answers' => json_decode($attempt['answers_json'], true),
            ];
            if (!$with_answers && $show_answers) {
                foreach ($payload['questions'] as $qi => $question) {
                    $raw = \Mirage\one($db, 'SELECT answer_index, explanation FROM questions WHERE id = ?', [$question['id']]);
                    $payload['questions'][$qi]['answer_index'] = $raw['answer_index'];
                    $payload['questions'][$qi]['explanation'] = $raw['explanation'];
                }
            }
        }
    }
    return $payload;
}

function assignment_payload(\PDO $db, int $assignment_id, ?int $user_id = null): ?array
{
    $row = \Mirage\one($db, 'SELECT * FROM assignments WHERE id = ?', [$assignment_id]);
    if (!$row) {
        return null;
    }
    $payload = [
        'id' => $row['id'],
        'title' => $row['title'],
        'instructions' => $row['instructions'],
        'max_score' => $row['max_score'],
    ];
    if ($user_id) {
        $sub = \Mirage\one(
            $db,
            'SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1',
            [$assignment_id, $user_id]
        );
        $payload['submission'] = $sub ? [
            'id' => $sub['id'],
            'body' => $sub['body'],
            'status' => $sub['status'],
            'score' => $sub['score'],
            'feedback' => $sub['feedback'],
            'submitted_at' => $sub['submitted_at'],
        ] : null;
    }
    return $payload;
}

function progress_percent(\PDO $db, int $user_id, int $course_id): int
{
    $total = \Mirage\one(
        $db,
        'SELECT COUNT(*) AS n FROM lessons l JOIN chapters c ON c.id = l.chapter_id WHERE c.course_id = ?',
        [$course_id]
    );
    if (!$total['n']) {
        return 0;
    }
    $done = \Mirage\one(
        $db,
        <<<'SQL'

        SELECT COUNT(*) AS n FROM lesson_progress p
        JOIN lessons l ON l.id = p.lesson_id
        JOIN chapters c ON c.id = l.chapter_id
        WHERE c.course_id = ? AND p.user_id = ? AND p.completed = 1
        
SQL,
        [$course_id, $user_id]
    );
    return (int) round($done['n'] * 100 / $total['n']);
}

function list_courses(string $query = '', string $category = '', bool $include_drafts = false, ?int $instructor_id = null): array
{
    $sql = <<<'SQL'

        SELECT c.*, u.full_name AS instructor_name,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS learners,
               (SELECT COUNT(*) FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id WHERE ch.course_id = c.id) AS lesson_count
        FROM courses c JOIN users u ON u.id = c.instructor_id
        WHERE c.deleted_at IS NULL
SQL;
    $args = [];
    if (!$include_drafts) {
        $sql .= " AND c.status = 'published'";
    }
    if ($instructor_id) {
        $sql .= ' AND c.instructor_id = ?';
        $args[] = $instructor_id;
    }
    if ($category) {
        $sql .= ' AND c.category = ?';
        $args[] = $category;
    }
    if ($query) {
        $sql .= ' AND (c.title LIKE ? OR c.summary LIKE ? OR c.category LIKE ?)';
        $like = '%' . $query . '%';
        array_push($args, $like, $like, $like);
    }
    $sql .= ' ORDER BY c.created_at DESC';
    $db = \Mirage\db();
    return \Mirage\many($db, $sql, $args);
}

function get_public_course(string $slug, ?array $user): array
{
    $db = \Mirage\db();
    $course = course_by_slug($db, $slug);
    if (!empty($course['deleted_at']) && !($user && can_edit_course($user, $course))) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    if ($course['status'] !== 'published' && !($user && can_edit_course($user, $course))) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    $enrolled = false;
    $progress = 0;
    if ($user) {
        $enrolled = (bool) \Mirage\one($db, 'SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?', [$course['id'], $user['id']]);
        $progress = $enrolled ? progress_percent($db, (int) $user['id'], (int) $course['id']) : 0;
    }
    return [
        'course' => $course,
        'chapters' => outline($db, (int) $course['id']),
        'enrolled' => $enrolled,
        'progress' => $progress,
    ];
}

function unique_slug(\PDO $db, string $title): string
{
    $base = \Mirage\slugify($title);
    $slug = $base;
    $n = 2;
    while (\Mirage\one($db, 'SELECT id FROM courses WHERE slug = ?', [$slug])) {
        $slug = $base . '-' . $n;
        $n += 1;
    }
    return $slug;
}

function create_course(array $user, array $payload): array
{
    if (!in_array($user['role'], ['admin', 'instructor'], true)) {
        throw new \Mirage\AppException(403, 'Instructors upload course materials. Learners open assigned classes.');
    }
    $title = trim((string) ($payload['title'] ?? ''));
    if (strlen($title) < 3) {
        throw new \Mirage\AppException(400, 'Give the course a title.');
    }
    $requested = $payload['instructor_id'] ?? null;
    $instructor_id = ($user['role'] !== 'admin' || !$requested)
        ? $user['id']
        : ($requested ?: $user['id']);
    $db = \Mirage\db();
    \Mirage\run(
        $db,
        <<<'SQL'

            INSERT INTO courses (title, slug, summary, description, category, level, status, instructor_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?)
            
SQL,
        [
            $title,
            unique_slug($db, $title),
            trim((string) ($payload['summary'] ?? '')),
            trim((string) ($payload['description'] ?? '')),
            trim((string) (($payload['category'] ?? null) ?: 'General')),
            trim((string) (($payload['level'] ?? null) ?: 'Foundation')),
            $instructor_id,
            \Mirage\app_now(),
        ]
    );
    return course_row($db, \Mirage\insert_id($db));
}

function update_course(array $user, int $course_id, array $payload): array
{
    $db = \Mirage\db();
    $course = course_row($db, $course_id);
    if (!can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot edit this course.');
    }
    $fields = [];
    $args = [];
    foreach (['title', 'summary', 'description', 'category', 'level'] as $key) {
        if (array_key_exists($key, $payload)) {
            $fields[] = $key . ' = ?';
            $args[] = trim((string) ($payload[$key] ?? ''));
        }
    }
    if (array_key_exists('title', $payload) && strlen(trim((string) ($payload['title'] ?? ''))) < 3) {
        throw new \Mirage\AppException(400, 'Give the course a title.');
    }
    if (array_key_exists('status', $payload)) {
        if (!in_array($payload['status'], ['draft', 'published'], true)) {
            throw new \Mirage\AppException(400, 'Status must be draft or published.');
        }
        if ($payload['status'] === 'published') {
            $lessons = \Mirage\one(
                $db,
                'SELECT COUNT(*) AS n FROM lessons l JOIN chapters c ON c.id = l.chapter_id WHERE c.course_id = ?',
                [$course_id]
            );
            if ($lessons['n'] < 1) {
                throw new \Mirage\AppException(400, 'Add at least one lesson before publishing.');
            }
        }
        $fields[] = 'status = ?';
        $args[] = $payload['status'];
    }
    if ($fields) {
        $args[] = $course_id;
        \Mirage\run($db, 'UPDATE courses SET ' . implode(', ', $fields) . ' WHERE id = ?', $args);
    }
    return course_row($db, $course_id);
}

function studio_course(array $user, int $course_id): array
{
    $db = \Mirage\db();
    $course = course_row($db, $course_id);
    if (!can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot edit this course.');
    }
    return ['course' => $course, 'chapters' => outline($db, $course_id, true, true)];
}

function add_chapter(array $user, int $course_id, string $title): array
{
    $title = trim($title);
    if (strlen($title) < 2) {
        throw new \Mirage\AppException(400, 'Name the chapter.');
    }
    $db = \Mirage\db();
    $course = course_row($db, $course_id);
    if (!can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot edit this course.');
    }
    $position = \Mirage\one($db, 'SELECT COALESCE(MAX(position), 0) + 1 AS n FROM chapters WHERE course_id = ?', [$course_id]);
    \Mirage\run(
        $db,
        'INSERT INTO chapters (course_id, title, position) VALUES (?, ?, ?)',
        [$course_id, $title, $position['n']]
    );
    return \Mirage\one($db, 'SELECT * FROM chapters WHERE id = ?', [\Mirage\insert_id($db)]);
}

function add_lesson(array $user, array $payload): array
{
    $kind = ($payload['kind'] ?? null) ?: 'text';
    if (!in_array($kind, ['text', 'video', 'quiz', 'assignment'], true)) {
        throw new \Mirage\AppException(400, 'Lesson type must be text, video, quiz, or assignment.');
    }
    $title = trim((string) ($payload['title'] ?? ''));
    if (strlen($title) < 2) {
        throw new \Mirage\AppException(400, 'Name the lesson.');
    }
    $db = \Mirage\db();
    $chapter = \Mirage\one($db, 'SELECT * FROM chapters WHERE id = ?', [$payload['chapter_id'] ?? null]);
    if (!$chapter) {
        throw new \Mirage\AppException(404, 'Chapter not found.');
    }
    $course = course_row($db, (int) $chapter['course_id']);
    if (!can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot edit this course.');
    }
    $quiz_id = null;
    $assignment_id = null;
    if ($kind === 'quiz') {
        $quiz = $payload['quiz'] ?? [];
        if (!is_array($quiz)) {
            $quiz = [];
        }
        $questions = $quiz['questions'] ?? [];
        if (!is_array($questions) || count($questions) < 1) {
            throw new \Mirage\AppException(400, 'A quiz needs at least one question.');
        }
        \Mirage\run(
            $db,
            'INSERT INTO quizzes (course_id, title, passing_score) VALUES (?, ?, ?)',
            [$course['id'], trim((string) (($quiz['title'] ?? null) ?: $title)), (int) (($quiz['passing_score'] ?? null) ?: 70)]
        );
        $quiz_id = \Mirage\insert_id($db);
        $index = 1;
        foreach ($questions as $question) {
            if (!is_array($question)) {
                $question = [];
            }
            $options = [];
            foreach (($question['options'] ?? []) ?: [] as $option) {
                if (is_array($option) || is_object($option)) {
                    continue;
                }
                $text = trim((string) $option);
                if ($text !== '') {
                    $options[] = $text;
                }
            }
            if (count($options) < 2) {
                throw new \Mirage\AppException(400, 'Each question needs at least two choices.');
            }
            $answer = (int) (($question['answer_index'] ?? null) ?: 0);
            if ($answer < 0 || $answer >= count($options)) {
                throw new \Mirage\AppException(400, 'Mark a correct choice for every question.');
            }
            $prompt = trim((string) ($question['prompt'] ?? ''));
            \Mirage\run(
                $db,
                <<<'SQL'

                    INSERT INTO questions (quiz_id, prompt, options_json, answer_index, explanation, position)
                    VALUES (?, ?, ?, ?, ?, ?)
                    
SQL,
                [
                    $quiz_id,
                    $prompt !== '' ? $prompt : 'Question',
                    json_encode($options, JSON_UNESCAPED_SLASHES),
                    $answer,
                    trim((string) ($question['explanation'] ?? '')),
                    $index,
                ]
            );
            $index += 1;
        }
    }
    if ($kind === 'assignment') {
        $task = $payload['assignment'] ?? [];
        if (!is_array($task)) {
            $task = [];
        }
        \Mirage\run(
            $db,
            'INSERT INTO assignments (course_id, title, instructions, max_score) VALUES (?, ?, ?, ?)',
            [
                $course['id'],
                trim((string) (($task['title'] ?? null) ?: $title)),
                trim((string) (($task['instructions'] ?? null) ?: (($payload['body'] ?? null) ?: ''))),
                (int) (($task['max_score'] ?? null) ?: 100),
            ]
        );
        $assignment_id = \Mirage\insert_id($db);
    }
    $position = \Mirage\one(
        $db,
        'SELECT COALESCE(MAX(position), 0) + 1 AS n FROM lessons WHERE chapter_id = ?',
        [$chapter['id']]
    );
    \Mirage\run(
        $db,
        <<<'SQL'

            INSERT INTO lessons (chapter_id, title, kind, body, video_url, minutes, position, quiz_id, assignment_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            
SQL,
        [
            $chapter['id'],
            $title,
            $kind,
            trim((string) ($payload['body'] ?? '')),
            trim((string) ($payload['video_url'] ?? '')),
            (int) (($payload['minutes'] ?? null) ?: 8),
            $position['n'],
            $quiz_id,
            $assignment_id,
        ]
    );
    return \Mirage\one($db, 'SELECT * FROM lessons WHERE id = ?', [\Mirage\insert_id($db)]);
}

function assign_course(array $actor, int $course_id, string $email): array
{
    if (!in_array($actor['role'], ['admin', 'instructor'], true)) {
        throw new \Mirage\AppException(403, 'Only an instructor or administrator can assign a class.');
    }
    $email = strtolower(trim($email));
    $db = \Mirage\db();
    $course = course_row($db, $course_id);
    if (!can_edit_course($actor, $course)) {
        throw new \Mirage\AppException(403, 'You can assign learners to courses you teach.');
    }
    $person = \Mirage\one($db, 'SELECT * FROM users WHERE email = ?', [$email]);
    if (!$person) {
        throw new \Mirage\AppException(404, 'No account uses that email. Create the learner first.');
    }
    if ($person['role'] !== 'student') {
        throw new \Mirage\AppException(400, 'Assign classes to learners. Instructors and administrators are not enrolled this way.');
    }
    enroll_silent($db, (int) $person['id'], $course_id);
    return ['enrolled' => true, 'user' => \Mirage\public_user($person), 'course_id' => $course_id];
}

function enroll(array $user, int $course_id): array
{
    if ($user['role'] === 'student') {
        throw new \Mirage\AppException(403, 'A learner opens classes an instructor has assigned.');
    }
    $db = \Mirage\db();
    $course = course_row($db, $course_id);
    if ($course['status'] !== 'published') {
        throw new \Mirage\AppException(400, 'This course is not open for enrollment.');
    }
    if (!\Mirage\one($db, 'SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?', [$course_id, $user['id']])) {
        $stamp = \Mirage\app_now();
        \Mirage\run(
            $db,
            'INSERT INTO enrollments (course_id, user_id, enrolled_at) VALUES (?, ?, ?)',
            [$course_id, $user['id'], $stamp]
        );
        log_event($db, 'enrollment', (int) $user['id'], $course_id, $stamp);
    }
    return ['enrolled' => true, 'progress' => progress_percent($db, (int) $user['id'], $course_id)];
}

function learn(array $user, string $slug): array
{
    $db = \Mirage\db();
    $course = course_by_slug($db, $slug);
    $enrolled = (bool) \Mirage\one($db, 'SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?', [$course['id'], $user['id']]);
    $editor = can_edit_course($user, $course);
    if ($course['status'] !== 'published' && !$editor) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    if (!$enrolled && !$editor) {
        throw new \Mirage\AppException(403, 'Enroll to open the lessons.');
    }
    $chapters = outline($db, (int) $course['id'], true, $editor);
    $done = [];
    foreach (\Mirage\many($db, 'SELECT lesson_id FROM lesson_progress WHERE user_id = ? AND completed = 1', [$user['id']]) as $row) {
        $done[(int) $row['lesson_id']] = true;
    }
    foreach ($chapters as $ci => $chapter) {
        foreach ($chapter['lessons'] as $li => $lesson) {
            $chapters[$ci]['lessons'][$li]['completed'] = isset($done[(int) $lesson['id']]);
            if (!empty($lesson['quiz'])) {
                $chapters[$ci]['lessons'][$li]['quiz'] = quiz_payload($db, (int) $lesson['quiz']['id'], $editor, (int) $user['id']);
            }
            if (!empty($lesson['assignment'])) {
                $chapters[$ci]['lessons'][$li]['assignment'] = assignment_payload($db, (int) $lesson['assignment']['id'], (int) $user['id']);
            }
        }
    }
    return [
        'course' => $course,
        'chapters' => $chapters,
        'enrolled' => $enrolled || $editor,
        'progress' => progress_percent($db, (int) $user['id'], (int) $course['id']),
    ];
}

function complete_lesson(array $user, int $lesson_id): array
{
    $db = \Mirage\db();
    $lesson = \Mirage\one(
        $db,
        <<<'SQL'

            SELECT l.*, c.course_id FROM lessons l
            JOIN chapters c ON c.id = l.chapter_id WHERE l.id = ?
            
SQL,
        [$lesson_id]
    );
    if (!$lesson) {
        throw new \Mirage\AppException(404, 'Lesson not found.');
    }
    if (!\Mirage\one($db, 'SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?', [$lesson['course_id'], $user['id']])) {
        if (!can_edit_course($user, course_row($db, (int) $lesson['course_id']))) {
            throw new \Mirage\AppException(403, 'Enroll before marking lessons complete.');
        }
        enroll_silent($db, (int) $user['id'], (int) $lesson['course_id']);
    }
    \Mirage\run(
        $db,
        <<<'SQL'

            INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at)
            VALUES (?, ?, 1, ?)
            ON CONFLICT(user_id, lesson_id) DO UPDATE SET completed = 1, completed_at = excluded.completed_at
            
SQL,
        [$user['id'], $lesson_id, \Mirage\app_now()]
    );
    $progress = sync_progress($db, (int) $user['id'], (int) $lesson['course_id'], (int) $lesson_id);
    return ['completed' => true, 'progress' => $progress];
}

function enroll_silent(\PDO $db, int $user_id, int $course_id): void
{
    if (!\Mirage\one($db, 'SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?', [$course_id, $user_id])) {
        $stamp = \Mirage\app_now();
        \Mirage\run(
            $db,
            'INSERT INTO enrollments (course_id, user_id, enrolled_at) VALUES (?, ?, ?)',
            [$course_id, $user_id, $stamp]
        );
        log_event($db, 'enrollment', $user_id, $course_id, $stamp);
    }
}

function submit_quiz(array $user, int $quiz_id, array $answers): array
{
    $db = \Mirage\db();
    $quiz = \Mirage\one($db, 'SELECT * FROM quizzes WHERE id = ?', [$quiz_id]);
    if (!$quiz) {
        throw new \Mirage\AppException(404, 'Quiz not found.');
    }
    $questions = \Mirage\many($db, 'SELECT * FROM questions WHERE quiz_id = ? ORDER BY position', [$quiz_id]);
    if (!$questions) {
        throw new \Mirage\AppException(400, 'This quiz has no questions.');
    }
    $correct = 0;
    $review = [];
    foreach ($questions as $question) {
        $qid = $question['id'];
        if (array_key_exists((string) $qid, $answers)) {
            $chosen = $answers[(string) $qid];
        } elseif (array_key_exists($qid, $answers)) {
            $chosen = $answers[$qid];
        } else {
            $chosen = null;
        }
        if (is_int($chosen) || is_bool($chosen) || (is_float($chosen) && is_finite($chosen)) || (is_string($chosen) && preg_match('/^\s*[+-]?\d+\s*$/', $chosen))) {
            $chosen = (int) $chosen;
        } else {
            $chosen = -1;
        }
        $is_right = $chosen === (int) $question['answer_index'];
        $correct += (int) $is_right;
        $review[(string) $question['id']] = ['chosen' => $chosen, 'correct' => $is_right];
    }
    $score = (int) round($correct * 100 / count($questions));
    $passed = $score >= (int) $quiz['passing_score'];
    $max_attempts = ($quiz['max_attempts'] ?? null) ?: 0;
    if ($max_attempts) {
        $taken = \Mirage\one(
            $db,
            'SELECT COUNT(*) AS n FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?',
            [$quiz_id, $user['id']]
        );
        if ($taken['n'] >= $max_attempts) {
            throw new \Mirage\AppException(400, 'You have used every attempt on this quiz.');
        }
    }
    \Mirage\run(
        $db,
        <<<'SQL'

            INSERT INTO quiz_attempts (quiz_id, user_id, score, passed, answers_json, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?)
            
SQL,
        [$quiz_id, $user['id'], $score, (int) $passed, json_encode($review, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES), \Mirage\app_now()]
    );
    if ($passed) {
        $lesson = \Mirage\one($db, 'SELECT id, chapter_id FROM lessons WHERE quiz_id = ?', [$quiz_id]);
        if ($lesson) {
            $chapter = \Mirage\one($db, 'SELECT course_id FROM chapters WHERE id = ?', [$lesson['chapter_id']]);
            enroll_silent($db, (int) $user['id'], (int) $chapter['course_id']);
            \Mirage\run(
                $db,
                <<<'SQL'

                    INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at)
                    VALUES (?, ?, 1, ?)
                    ON CONFLICT(user_id, lesson_id) DO UPDATE SET completed = 1, completed_at = excluded.completed_at
                    
SQL,
                [$user['id'], $lesson['id'], \Mirage\app_now()]
            );
            sync_progress($db, (int) $user['id'], (int) $chapter['course_id'], (int) $lesson['id']);
        }
    }
    $payload = quiz_payload($db, $quiz_id, false, (int) $user['id']);
    $payload['score'] = $score;
    $payload['passed'] = $passed;
    return $payload;
}

function submit_assignment(array $user, int $assignment_id, string $body): array
{
    $body = trim($body);
    if (strlen($body) < 12) {
        throw new \Mirage\AppException(400, 'Write a little more before handing this in.');
    }
    $db = \Mirage\db();
    $assignment = \Mirage\one($db, 'SELECT * FROM assignments WHERE id = ?', [$assignment_id]);
    if (!$assignment) {
        throw new \Mirage\AppException(404, 'Assignment not found.');
    }
    $existing = \Mirage\one(
        $db,
        'SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1',
        [$assignment_id, $user['id']]
    );
    if ($existing && $existing['status'] === 'graded') {
        throw new \Mirage\AppException(400, 'This assignment is already graded.');
    }
    if ($existing) {
        \Mirage\run(
            $db,
            "UPDATE submissions SET body = ?, status = 'submitted', submitted_at = ?, score = NULL, feedback = '' WHERE id = ?",
            [$body, \Mirage\app_now(), $existing['id']]
        );
    } else {
        \Mirage\run(
            $db,
            "INSERT INTO submissions (assignment_id, user_id, body, status, submitted_at) VALUES (?, ?, ?, 'submitted', ?)",
            [$assignment_id, $user['id'], $body, \Mirage\app_now()]
        );
    }
    $lesson = \Mirage\one($db, 'SELECT id FROM lessons WHERE assignment_id = ?', [$assignment_id]);
    if ($lesson) {
        $chapter = \Mirage\one(
            $db,
            'SELECT c.course_id FROM lessons l JOIN chapters c ON c.id = l.chapter_id WHERE l.id = ?',
            [$lesson['id']]
        );
        enroll_silent($db, (int) $user['id'], (int) $chapter['course_id']);
        \Mirage\run(
            $db,
            <<<'SQL'

                INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at)
                VALUES (?, ?, 1, ?)
                ON CONFLICT(user_id, lesson_id) DO UPDATE SET completed = 1, completed_at = excluded.completed_at
                
SQL,
            [$user['id'], $lesson['id'], \Mirage\app_now()]
        );
    }
    return assignment_payload($db, $assignment_id, (int) $user['id']);
}

function list_submissions(array $user): array
{
    $db = \Mirage\db();
    if ($user['role'] === 'admin') {
        $clause = '1 = 1';
        $args = [];
    } elseif ($user['role'] === 'instructor') {
        $clause = 'c.instructor_id = ?';
        $args = [$user['id']];
    } else {
        $clause = 's.user_id = ?';
        $args = [$user['id']];
    }
    return \Mirage\many(
        $db,
        <<<SQL

            SELECT s.*, a.title AS assignment_title, a.max_score, c.title AS course_title, c.id AS course_id,
                   u.full_name AS student_name
            FROM submissions s
            JOIN assignments a ON a.id = s.assignment_id
            JOIN courses c ON c.id = a.course_id
            JOIN users u ON u.id = s.user_id
            WHERE {$clause}
            ORDER BY s.submitted_at DESC
            
SQL,
        $args
    );
}

function grade_submission(array $user, int $submission_id, mixed $score, string $feedback): array
{
    $db = \Mirage\db();
    $row = \Mirage\one(
        $db,
        <<<'SQL'

            SELECT s.*, a.max_score, c.instructor_id FROM submissions s
            JOIN assignments a ON a.id = s.assignment_id
            JOIN courses c ON c.id = a.course_id
            WHERE s.id = ?
            
SQL,
        [$submission_id]
    );
    if (!$row) {
        throw new \Mirage\AppException(404, 'Submission not found.');
    }
    if ($user['role'] !== 'admin' && $row['instructor_id'] != $user['id']) {
        throw new \Mirage\AppException(403, 'You cannot grade this submission.');
    }
    $score_ok = is_int($score)
        || is_bool($score)
        || (is_float($score) && is_finite($score))
        || (is_string($score) && preg_match('/^\s*[+-]?\d+\s*$/', $score));
    if (!$score_ok) {
        throw new \Mirage\AppException(400, 'Add a score.');
    }
    $score = (int) $score;
    if ($score < 0 || $score > $row['max_score']) {
        throw new \Mirage\AppException(400, 'Score must be between 0 and ' . $row['max_score'] . '.');
    }
    \Mirage\run(
        $db,
        "UPDATE submissions SET score = ?, feedback = ?, status = 'graded' WHERE id = ?",
        [$score, trim($feedback), $submission_id]
    );
    return \Mirage\one($db, 'SELECT * FROM submissions WHERE id = ?', [$submission_id]);
}

function my_learning(array $user): array
{
    $db = \Mirage\db();
    $rows = \Mirage\many(
        $db,
        <<<'SQL'

            SELECT c.*, u.full_name AS instructor_name, e.enrolled_at
            FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            JOIN users u ON u.id = c.instructor_id
            WHERE e.user_id = ? AND c.deleted_at IS NULL AND COALESCE(e.active, 1) = 1
            ORDER BY e.enrolled_at DESC
            
SQL,
        [$user['id']]
    );
    foreach ($rows as $index => $row) {
        $rows[$index]['progress'] = progress_percent($db, (int) $user['id'], (int) $row['id']);
    }
    return $rows;
}

function my_classes(array $user): array
{
    $courses = my_learning($user);
    $batches = [];
    foreach (list_batches($user) as $row) {
        if (!empty($row['joined'])) {
            $batches[] = $row;
        }
    }
    return ['courses' => $courses, 'batches' => $batches];
}

function my_assessments(array $user): array
{
    $db = \Mirage\db();
    $quizzes = \Mirage\many(
        $db,
        <<<'SQL'

            SELECT q.id, q.title, q.passing_score, c.title AS course_title, c.slug,
                   l.title AS lesson_title
            FROM quizzes q
            JOIN courses c ON c.id = q.course_id
            JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
            LEFT JOIN lessons l ON l.quiz_id = q.id
            ORDER BY c.title
            
SQL,
        [$user['id']]
    );
    foreach ($quizzes as $index => $quiz) {
        $attempt = \Mirage\one(
            $db,
            'SELECT score, passed, submitted_at FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1',
            [$quiz['id'], $user['id']]
        );
        $quizzes[$index]['last_attempt'] = $attempt ?: null;
        if ($quizzes[$index]['last_attempt']) {
            $quizzes[$index]['last_attempt']['passed'] = (bool) $quizzes[$index]['last_attempt']['passed'];
        }
    }
    $submissions = \Mirage\many(
        $db,
        <<<'SQL'

            SELECT s.*, a.title AS assignment_title, a.max_score, c.title AS course_title, c.slug
            FROM submissions s
            JOIN assignments a ON a.id = s.assignment_id
            JOIN courses c ON c.id = a.course_id
            WHERE s.user_id = ?
            ORDER BY s.submitted_at DESC
            
SQL,
        [$user['id']]
    );
    return ['quizzes' => $quizzes, 'submissions' => $submissions];
}

function list_batches(?array $user): array
{
    $db = \Mirage\db();
    $rows = \Mirage\many(
        $db,
        <<<'SQL'

            SELECT b.*, c.title AS course_title, c.slug AS course_slug, u.full_name AS instructor_name,
                   (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id) AS seats_taken
            FROM batches b
            JOIN courses c ON c.id = b.course_id
            JOIN users u ON u.id = b.instructor_id
            ORDER BY b.start_date
            
SQL
    );
    if ($user) {
        $mine = [];
        foreach (\Mirage\many($db, 'SELECT batch_id FROM batch_students WHERE user_id = ?', [$user['id']]) as $row) {
            $mine[(int) $row['batch_id']] = true;
        }
        foreach ($rows as $index => $row) {
            $rows[$index]['joined'] = isset($mine[(int) $row['id']]);
            if ($rows[$index]['joined']) {
                $rows[$index]['progress'] = progress_percent($db, (int) $user['id'], (int) $row['course_id']);
            }
        }
    }
    return $rows;
}

function batch_detail(?array $user, int $batch_id): array
{
    $db = \Mirage\db();
    $batch = \Mirage\one(
        $db,
        <<<'SQL'

            SELECT b.*, c.title AS course_title, c.slug AS course_slug, u.full_name AS instructor_name
            FROM batches b
            JOIN courses c ON c.id = b.course_id
            JOIN users u ON u.id = b.instructor_id
            WHERE b.id = ?
            
SQL,
        [$batch_id]
    );
    if (!$batch) {
        throw new \Mirage\AppException(404, 'Batch not found.');
    }
    $students = \Mirage\many(
        $db,
        <<<'SQL'

            SELECT u.id, u.full_name, u.email, u.headline, bs.joined_at
            FROM batch_students bs JOIN users u ON u.id = bs.user_id
            WHERE bs.batch_id = ? ORDER BY u.full_name
            
SQL,
        [$batch_id]
    );
    $total_row = \Mirage\one(
        $db,
        'SELECT COUNT(*) AS n FROM lessons l JOIN chapters c ON c.id = l.chapter_id WHERE c.course_id = ?',
        [$batch['course_id']]
    );
    $total = $total_row['n'];
    $classes = \Mirage\many($db, 'SELECT id FROM live_classes WHERE batch_id = ?', [$batch_id]);
    $class_ids = [];
    foreach ($classes as $class_row) {
        $class_ids[] = $class_row['id'];
    }
    foreach ($students as $index => $student) {
        $done = \Mirage\one(
            $db,
            <<<'SQL'

                SELECT COUNT(*) AS n FROM lesson_progress p
                JOIN lessons l ON l.id = p.lesson_id
                JOIN chapters c ON c.id = l.chapter_id
                WHERE c.course_id = ? AND p.user_id = ? AND p.completed = 1
                
SQL,
            [$batch['course_id'], $student['id']]
        )['n'];
        $passed = \Mirage\one(
            $db,
            <<<'SQL'

                SELECT COUNT(DISTINCT qa.quiz_id) AS n FROM quiz_attempts qa
                JOIN quizzes q ON q.id = qa.quiz_id
                WHERE q.course_id = ? AND qa.user_id = ? AND qa.passed = 1
                
SQL,
            [$batch['course_id'], $student['id']]
        )['n'];
        $attended = 0;
        if ($class_ids) {
            $marks = implode(',', array_fill(0, count($class_ids), '?'));
            $attended = \Mirage\one(
                $db,
                "SELECT COUNT(*) AS n FROM attendance WHERE user_id = ? AND live_class_id IN ({$marks})",
                array_merge([$student['id']], $class_ids)
            )['n'];
        }
        $last = \Mirage\one(
            $db,
            <<<'SQL'

                SELECT MAX(completed_at) AS at FROM lesson_progress p
                JOIN lessons l ON l.id = p.lesson_id
                JOIN chapters c ON c.id = l.chapter_id
                WHERE c.course_id = ? AND p.user_id = ?
                
SQL,
            [$batch['course_id'], $student['id']]
        );
        $progress = $total ? (int) round($done * 100 / $total) : 0;
        $attendance_rate = $class_ids ? (int) round($attended * 100 / count($class_ids)) : 0;
        $students[$index]['lessons_done'] = $done;
        $students[$index]['lessons_total'] = $total;
        $students[$index]['progress'] = $progress;
        $students[$index]['quizzes_passed'] = $passed;
        $students[$index]['live_attended'] = $attended;
        $students[$index]['engagement'] = (int) round($progress * 0.6 + $attendance_rate * 0.25 + min((int) $passed, 1) * 15);
        $students[$index]['last_active'] = $last ? $last['at'] : null;
        if (!$user || !in_array($user['role'], ['admin', 'instructor', 'moderator'], true)) {
            unset($students[$index]['email']);
        }
    }
    $batch['joined'] = (bool) ($user && \Mirage\one($db, 'SELECT 1 FROM batch_students WHERE batch_id = ? AND user_id = ?', [$batch_id, $user['id']]));
    $batch['seats_taken'] = count($students);
    return ['batch' => $batch, 'students' => $students, 'live_classes' => live_for_batch($db, $batch_id)];
}

function live_for_batch(\PDO $db, int $batch_id): array
{
    return \Mirage\many(
        $db,
        <<<'SQL'

        SELECT lc.*, u.full_name AS host_name,
               (SELECT COUNT(*) FROM attendance a WHERE a.live_class_id = lc.id) AS attendance
        FROM live_classes lc JOIN users u ON u.id = lc.host_id
        WHERE lc.batch_id = ? ORDER BY lc.starts_at
        
SQL,
        [$batch_id]
    );
}

function create_batch(array $user, array $payload): array
{
    if (!in_array($user['role'], ['admin', 'instructor'], true)) {
        throw new \Mirage\AppException(403, 'Only instructors can open a batch.');
    }
    $title = trim((string) ($payload['title'] ?? ''));
    if (strlen($title) < 3) {
        throw new \Mirage\AppException(400, 'Name the batch.');
    }
    $db = \Mirage\db();
    $course = course_row($db, (int) ($payload['course_id'] ?? 0));
    if (!can_edit_course($user, $course) && $user['role'] !== 'admin') {
        throw new \Mirage\AppException(403, 'Open batches for your own courses.');
    }
    \Mirage\run(
        $db,
        <<<'SQL'

            INSERT INTO batches (title, course_id, instructor_id, start_date, end_date, seat_limit, description, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
            
SQL,
        [
            $title,
            $course['id'],
            $course['instructor_id'],
            ($payload['start_date'] ?? null) ?: '',
            ($payload['end_date'] ?? null) ?: '',
            (int) (($payload['seat_limit'] ?? null) ?: 16),
            trim((string) ($payload['description'] ?? '')),
        ]
    );
    return \Mirage\one($db, 'SELECT * FROM batches WHERE id = ?', [\Mirage\insert_id($db)]);
}

function join_batch(array $user, int $batch_id): array
{
    $db = \Mirage\db();
    $batch = \Mirage\one($db, 'SELECT * FROM batches WHERE id = ?', [$batch_id]);
    if (!$batch) {
        throw new \Mirage\AppException(404, 'Batch not found.');
    }
    if ($batch['status'] !== 'open') {
        throw new \Mirage\AppException(400, 'This batch is closed.');
    }
    $taken = \Mirage\one($db, 'SELECT COUNT(*) AS n FROM batch_students WHERE batch_id = ?', [$batch_id]);
    if ($taken['n'] >= $batch['seat_limit'] && !\Mirage\one($db, 'SELECT 1 FROM batch_students WHERE batch_id = ? AND user_id = ?', [$batch_id, $user['id']])) {
        throw new \Mirage\AppException(400, 'This batch is full.');
    }
    if (!\Mirage\one($db, 'SELECT 1 FROM batch_students WHERE batch_id = ? AND user_id = ?', [$batch_id, $user['id']])) {
        \Mirage\run(
            $db,
            'INSERT INTO batch_students (batch_id, user_id, joined_at) VALUES (?, ?, ?)',
            [$batch_id, $user['id'], \Mirage\app_now()]
        );
    }
    enroll_silent($db, (int) $user['id'], (int) $batch['course_id']);
    return batch_detail($user, $batch_id);
}

function zoom_connected(): bool
{
    return (bool) (getenv('ZOOM_ACCOUNT_ID') && getenv('ZOOM_CLIENT_ID') && getenv('ZOOM_CLIENT_SECRET'));
}

function zoom_access_token(): ?string
{
    $account = getenv('ZOOM_ACCOUNT_ID');
    $client = getenv('ZOOM_CLIENT_ID');
    $secret = getenv('ZOOM_CLIENT_SECRET');
    if (!$account || !$client || !$secret) {
        return null;
    }
    $basic = base64_encode($client . ':' . $secret);
    $url = 'https://zoom.us/oauth/token?' . http_build_query([
        'grant_type' => 'account_credentials',
        'account_id' => $account,
    ]);
    $result = zoom_post($url, ['Authorization: Basic ' . $basic], '');
    if ($result === null || $result['status'] < 200 || $result['status'] >= 300) {
        return null;
    }
    $data = json_decode($result['body'], true);
    if (!is_array($data)) {
        return null;
    }
    $token = $data['access_token'] ?? null;
    return is_string($token) ? $token : null;
}

function create_zoom_meeting(string $title, string $starts_at, int $minutes): array
{
    $token = zoom_access_token();
    if (!$token) {
        $meeting_id = (string) (80000000000 + random_int(0, 999999998));
        return [
            'provider' => 'demo',
            'zoom_meeting_id' => $meeting_id,
            'zoom_join_url' => '',
            'zoom_start_url' => '',
        ];
    }
    $body = json_encode([
        'topic' => $title,
        'type' => 2,
        'start_time' => str_replace('+00:00', 'Z', $starts_at),
        'duration' => $minutes,
        'timezone' => 'UTC',
        'settings' => [
            'waiting_room' => true,
            'join_before_host' => false,
            'approval_type' => 2,
        ],
    ], JSON_UNESCAPED_SLASHES);
    $result = zoom_post(
        'https://api.zoom.us/v2/users/me/meetings',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        $body === false ? '' : $body
    );
    if ($result === null) {
        throw new \RuntimeException('Zoom request failed');
    }
    if ($result['status'] < 200 || $result['status'] >= 300) {
        throw new \Mirage\AppException(400, 'Zoom could not create the meeting. ' . substr($result['body'], 0, 240));
    }
    $data = json_decode($result['body'], true);
    if (!is_array($data)) {
        throw new \JsonException('Invalid Zoom response');
    }
    return [
        'provider' => 'zoom',
        'zoom_meeting_id' => (string) ($data['id'] ?? ''),
        'zoom_join_url' => $data['join_url'] ?? '',
        'zoom_start_url' => $data['start_url'] ?? '',
    ];
}

function zoom_post(string $url, array $headers, string $body): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $failed = $raw === false;
        curl_close($ch);
        if ($failed) {
            return null;
        }
        return ['status' => $status, 'body' => (string) $raw];
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        return null;
    }
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
        $status = (int) $match[1];
    }
    return ['status' => $status, 'body' => $raw];
}

function decorate_live(\PDO $db, array $row, ?array $user): array
{
    $count = \Mirage\one($db, 'SELECT COUNT(*) AS n FROM attendance WHERE live_class_id = ?', [$row['id']]);
    $row['attendance'] = $count['n'];
    $row['attending'] = false;
    if ($user) {
        $row['attending'] = (bool) \Mirage\one(
            $db,
            'SELECT 1 FROM attendance WHERE live_class_id = ? AND user_id = ?',
            [$row['id'], $user['id']]
        );
    }
    if ($row['provider'] !== 'zoom') {
        $row['zoom_start_url'] = '';
    } elseif ($user && $user['id'] != $row['host_id'] && !in_array($user['role'], ['admin'], true)) {
        $row['zoom_start_url'] = '';
    }
    return $row;
}

function list_live(?array $user): array
{
    $db = \Mirage\db();
    $rows = \Mirage\many(
        $db,
        <<<'SQL'

            SELECT lc.*, u.full_name AS host_name, b.title AS batch_title, c.title AS course_title, c.slug AS course_slug
            FROM live_classes lc
            JOIN users u ON u.id = lc.host_id
            LEFT JOIN batches b ON b.id = lc.batch_id
            LEFT JOIN courses c ON c.id = lc.course_id
            ORDER BY lc.starts_at
            
SQL
    );
    $classes = [];
    foreach ($rows as $row) {
        $classes[] = decorate_live($db, $row, $user);
    }
    return ['zoom_connected' => zoom_connected(), 'classes' => $classes];
}

function create_live(array $user, array $payload): array
{
    if (!in_array($user['role'], ['admin', 'instructor'], true)) {
        throw new \Mirage\AppException(403, 'Only instructors can schedule a live class.');
    }
    $title = trim((string) ($payload['title'] ?? ''));
    $starts_at = $payload['starts_at'] ?? '';
    if (strlen($title) < 3 || !$starts_at) {
        throw new \Mirage\AppException(400, 'A live class needs a title and a start time.');
    }
    $minutes = (int) (($payload['minutes'] ?? null) ?: 60);
    $meeting = create_zoom_meeting($title, (string) $starts_at, $minutes);
    $db = \Mirage\db();
    $batch_id = $payload['batch_id'] ?? null;
    $course_id = $payload['course_id'] ?? null;
    if (!$batch_id) {
        $batch_id = null;
    }
    if ($batch_id) {
        $batch = \Mirage\one($db, 'SELECT * FROM batches WHERE id = ?', [(int) $batch_id]);
        if (!$batch) {
            throw new \Mirage\AppException(404, 'Batch not found.');
        }
        $course_id = $batch['course_id'];
    }
    \Mirage\run(
        $db,
        <<<'SQL'

            INSERT INTO live_classes (
              batch_id, course_id, host_id, title, description, starts_at, minutes,
              provider, zoom_meeting_id, zoom_join_url, zoom_start_url, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
            
SQL,
        [
            $batch_id ? (int) $batch_id : null,
            $course_id ? (int) $course_id : null,
            $user['id'],
            $title,
            trim((string) ($payload['description'] ?? '')),
            $starts_at,
            $minutes,
            $meeting['provider'],
            $meeting['zoom_meeting_id'],
            $meeting['zoom_join_url'],
            $meeting['zoom_start_url'],
        ]
    );
    $live_id = \Mirage\insert_id($db);
    $row = \Mirage\one(
        $db,
        <<<'SQL'

            SELECT lc.*, u.full_name AS host_name, b.title AS batch_title, c.title AS course_title, c.slug AS course_slug
            FROM live_classes lc
            JOIN users u ON u.id = lc.host_id
            LEFT JOIN batches b ON b.id = lc.batch_id
            LEFT JOIN courses c ON c.id = lc.course_id
            WHERE lc.id = ?
            
SQL,
        [$live_id]
    );
    return decorate_live($db, $row, $user);
}

function set_live_status(array $user, int $class_id, string $status): array
{
    if (!in_array($status, ['scheduled', 'live', 'ended'], true)) {
        throw new \Mirage\AppException(400, 'Unknown class status.');
    }
    $db = \Mirage\db();
    $row = \Mirage\one($db, 'SELECT * FROM live_classes WHERE id = ?', [$class_id]);
    if (!$row) {
        throw new \Mirage\AppException(404, 'Class not found.');
    }
    if ($user['role'] !== 'admin' && $row['host_id'] != $user['id']) {
        throw new \Mirage\AppException(403, 'Only the host can change this class.');
    }
    \Mirage\run($db, 'UPDATE live_classes SET status = ? WHERE id = ?', [$status, $class_id]);
    $full = \Mirage\one(
        $db,
        <<<'SQL'

            SELECT lc.*, u.full_name AS host_name, b.title AS batch_title, c.title AS course_title, c.slug AS course_slug
            FROM live_classes lc
            JOIN users u ON u.id = lc.host_id
            LEFT JOIN batches b ON b.id = lc.batch_id
            LEFT JOIN courses c ON c.id = lc.course_id
            WHERE lc.id = ?
            
SQL,
        [$class_id]
    );
    return decorate_live($db, $full, $user);
}

function attend_live(array $user, int $class_id): array
{
    $db = \Mirage\db();
    $row = \Mirage\one($db, 'SELECT * FROM live_classes WHERE id = ?', [$class_id]);
    if (!$row) {
        throw new \Mirage\AppException(404, 'Class not found.');
    }
    if ($row['status'] === 'ended') {
        throw new \Mirage\AppException(400, 'This class has ended.');
    }
    if ($row['course_id'] && $user['role'] === 'student') {
        $enrolled = \Mirage\one($db, 'SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?', [$row['course_id'], $user['id']]);
        $in_batch = false;
        if ($row['batch_id']) {
            $in_batch = (bool) \Mirage\one($db, 'SELECT 1 FROM batch_students WHERE batch_id = ? AND user_id = ?', [$row['batch_id'], $user['id']]);
        }
        if (!$enrolled && !$in_batch) {
            throw new \Mirage\AppException(403, 'Join the batch or enroll in the course to enter this room.');
        }
    }
    \Mirage\run(
        $db,
        'INSERT INTO attendance (live_class_id, user_id, joined_at) VALUES (?, ?, ?) ON CONFLICT DO NOTHING',
        [$class_id, $user['id'], \Mirage\app_now()]
    );
    if ($row['status'] === 'scheduled') {
        \Mirage\run($db, "UPDATE live_classes SET status = 'live' WHERE id = ? AND provider = 'demo'", [$class_id]);
    }
    $full = \Mirage\one(
        $db,
        <<<'SQL'

            SELECT lc.*, u.full_name AS host_name, b.title AS batch_title, c.title AS course_title, c.slug AS course_slug
            FROM live_classes lc
            JOIN users u ON u.id = lc.host_id
            LEFT JOIN batches b ON b.id = lc.batch_id
            LEFT JOIN courses c ON c.id = lc.course_id
            WHERE lc.id = ?
            
SQL,
        [$class_id]
    );
    return decorate_live($db, $full, $user);
}

function list_slots(?array $user): array
{
    $db = \Mirage\db();
    $rows = \Mirage\many(
        $db,
        <<<'SQL'

            SELECT s.*, i.full_name AS instructor_name, st.full_name AS student_name, c.title AS course_title, c.slug AS course_slug
            FROM eval_slots s
            JOIN users i ON i.id = s.instructor_id
            LEFT JOIN users st ON st.id = s.student_id
            LEFT JOIN courses c ON c.id = s.course_id
            ORDER BY s.starts_at
            
SQL
    );
    if ($user && $user['role'] === 'student') {
        foreach ($rows as $index => $row) {
            if ($row['student_id'] != null && $row['student_id'] != $user['id'] && $row['status'] !== 'open') {
                $rows[$index]['notes'] = '';
                $rows[$index]['outcome'] = '';
                $rows[$index]['student_name'] = null;
                $rows[$index]['meeting_url'] = '';
            }
        }
    }
    return $rows;
}

function generate_slots(array $user, int $days = 10): array
{
    if (!in_array($user['role'], ['admin', 'instructor'], true)) {
        throw new \Mirage\AppException(403, 'Only instructors can publish evaluation hours.');
    }
    $instructor_id = $user['id'];
    $created = 0;
    $db = \Mirage\db();
    if ($user['role'] === 'admin') {
        $instructor_id = $user['id'];
    }
    $start = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $start = $start->setTime((int) $start->format('G'), 0, 0);
    for ($offset = 1; $offset <= $days; $offset++) {
        $day = $start->modify('+' . $offset . ' days');
        if ((int) $day->format('N') >= 6) {
            continue;
        }
        foreach ([[9, 30], [16, 0]] as $hour_minute) {
            $slot = $day->setTime($hour_minute[0], $hour_minute[1], 0)->format('Y-m-d\TH:i:sP');
            if (\Mirage\one($db, 'SELECT id FROM eval_slots WHERE instructor_id = ? AND starts_at = ?', [$instructor_id, $slot])) {
                continue;
            }
            \Mirage\run(
                $db,
                "INSERT INTO eval_slots (instructor_id, starts_at, minutes, status) VALUES (?, ?, 30, 'open')",
                [$instructor_id, $slot]
            );
            $created += 1;
        }
    }
    return ['created' => $created, 'slots' => list_slots($user)];
}

function book_evaluation(array $user, int $course_id): array
{
    $db = \Mirage\db();
    $course = course_row($db, $course_id);
    if (!\Mirage\one($db, 'SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?', [$course_id, $user['id']])) {
        throw new \Mirage\AppException(403, 'Enroll in the course before booking an evaluation.');
    }
    $existing = \Mirage\one(
        $db,
        <<<'SQL'

            SELECT * FROM eval_slots
            WHERE student_id = ? AND course_id = ? AND status = 'booked' AND starts_at >= ?
            
SQL,
        [$user['id'], $course_id, \Mirage\app_now()]
    );
    if ($existing) {
        throw new \Mirage\AppException(400, 'You already have an evaluation booked for this course.');
    }
    $slot = \Mirage\one(
        $db,
        <<<'SQL'

            SELECT * FROM eval_slots
            WHERE instructor_id = ? AND status = 'open' AND starts_at >= ?
            ORDER BY starts_at LIMIT 1
            
SQL,
        [$course['instructor_id'], \Mirage\app_now()]
    );
    if (!$slot) {
        $slot = \Mirage\one(
            $db,
            "SELECT * FROM eval_slots WHERE status = 'open' AND starts_at >= ? ORDER BY starts_at LIMIT 1",
            [\Mirage\app_now()]
        );
    }
    if (!$slot) {
        throw new \Mirage\AppException(400, 'No open evaluation times. An instructor needs to publish hours first.');
    }
    $meeting = create_zoom_meeting('Evaluation · ' . $course['title'], $slot['starts_at'], (int) $slot['minutes']);
    $meeting_url = $meeting['zoom_join_url'] ?: ('/live?evaluation=' . $slot['id']);
    \Mirage\run(
        $db,
        <<<'SQL'

            UPDATE eval_slots
            SET status = 'booked', student_id = ?, course_id = ?, meeting_url = ?, meeting_provider = ?
            WHERE id = ?
            
SQL,
        [$user['id'], $course_id, $meeting_url, $meeting['provider'], $slot['id']]
    );
    return \Mirage\one(
        $db,
        <<<'SQL'

            SELECT s.*, i.full_name AS instructor_name, st.full_name AS student_name, c.title AS course_title
            FROM eval_slots s
            JOIN users i ON i.id = s.instructor_id
            LEFT JOIN users st ON st.id = s.student_id
            LEFT JOIN courses c ON c.id = s.course_id
            WHERE s.id = ?
            
SQL,
        [$slot['id']]
    );
}

function close_evaluation(array $user, int $slot_id, string $outcome, string $notes): array
{
    $db = \Mirage\db();
    $slot = \Mirage\one($db, 'SELECT * FROM eval_slots WHERE id = ?', [$slot_id]);
    if (!$slot) {
        throw new \Mirage\AppException(404, 'Evaluation not found.');
    }
    if ($user['role'] !== 'admin' && $slot['instructor_id'] != $user['id']) {
        throw new \Mirage\AppException(403, 'Only the instructor can close this evaluation.');
    }
    if ($slot['status'] !== 'booked') {
        throw new \Mirage\AppException(400, 'Only a booked evaluation can be closed.');
    }
    \Mirage\run(
        $db,
        "UPDATE eval_slots SET status = 'completed', outcome = ?, notes = ? WHERE id = ?",
        [trim($outcome) !== '' ? trim($outcome) : 'Complete', trim($notes), $slot_id]
    );
    return \Mirage\one($db, 'SELECT * FROM eval_slots WHERE id = ?', [$slot_id]);
}

function release_evaluation(array $user, int $slot_id): array
{
    $db = \Mirage\db();
    $slot = \Mirage\one($db, 'SELECT * FROM eval_slots WHERE id = ?', [$slot_id]);
    if (!$slot) {
        throw new \Mirage\AppException(404, 'Evaluation not found.');
    }
    $allowed = false;
    foreach ([$slot['student_id'], $slot['instructor_id']] as $id) {
        if ($id !== null && $id == $user['id']) {
            $allowed = true;
        }
    }
    if (!$allowed && $user['role'] !== 'admin') {
        throw new \Mirage\AppException(403, 'You cannot release this time.');
    }
    \Mirage\run(
        $db,
        <<<'SQL'

            UPDATE eval_slots
            SET status = 'open', student_id = NULL, course_id = NULL, meeting_url = '', outcome = '', notes = ''
            WHERE id = ?
            
SQL,
        [$slot_id]
    );
    return \Mirage\one($db, 'SELECT * FROM eval_slots WHERE id = ?', [$slot_id]);
}

function list_jobs(string $query = ''): array
{
    $sql = <<<'SQL'

        SELECT j.*, u.full_name AS posted_by_name,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS applicants
        FROM jobs j LEFT JOIN users u ON u.id = j.posted_by
        WHERE j.status = 'open'
SQL;
    $args = [];
    if ($query) {
        $sql .= ' AND (j.title LIKE ? OR j.company LIKE ? OR j.location LIKE ?)';
        $like = '%' . $query . '%';
        array_push($args, $like, $like, $like);
    }
    $sql .= ' ORDER BY j.created_at DESC';
    $db = \Mirage\db();
    return \Mirage\many($db, $sql, $args);
}

function create_job(array $user, array $payload): array
{
    if (!staff($user)) {
        throw new \Mirage\AppException(403, 'Only the studio can post a role.');
    }
    $title = trim((string) ($payload['title'] ?? ''));
    $company = trim((string) ($payload['company'] ?? ''));
    if (strlen($title) < 3 || strlen($company) < 2) {
        throw new \Mirage\AppException(400, 'A role needs a title and a company.');
    }
    $db = \Mirage\db();
    \Mirage\run(
        $db,
        <<<'SQL'

            INSERT INTO jobs (title, company, location, job_type, description, posted_by, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'open', ?)
            
SQL,
        [
            $title,
            $company,
            trim((string) ($payload['location'] ?? '')),
            trim((string) (($payload['job_type'] ?? null) ?: 'Full-time')),
            trim((string) ($payload['description'] ?? '')),
            $user['id'],
            \Mirage\app_now(),
        ]
    );
    return \Mirage\one($db, 'SELECT * FROM jobs WHERE id = ?', [\Mirage\insert_id($db)]);
}

function apply_job(array $user, int $job_id, string $note): array
{
    $db = \Mirage\db();
    $job = \Mirage\one($db, "SELECT * FROM jobs WHERE id = ? AND status = 'open'", [$job_id]);
    if (!$job) {
        throw new \Mirage\AppException(404, 'Role not found.');
    }
    if (\Mirage\one($db, 'SELECT id FROM applications WHERE job_id = ? AND user_id = ?', [$job_id, $user['id']])) {
        throw new \Mirage\AppException(400, 'You already put your name forward for this role.');
    }
    \Mirage\run(
        $db,
        'INSERT INTO applications (job_id, user_id, note, applied_at) VALUES (?, ?, ?, ?)',
        [$job_id, $user['id'], trim($note), \Mirage\app_now()]
    );
    return ['applied' => true];
}

function my_applications(array $user): array
{
    $db = \Mirage\db();
    $ids = [];
    foreach (\Mirage\many($db, 'SELECT job_id FROM applications WHERE user_id = ?', [$user['id']]) as $row) {
        $ids[] = $row['job_id'];
    }
    return $ids;
}

function day_span(int $days = 14): array
{
    $today = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $today = $today->setTime(0, 0, 0);
    $span = [];
    for ($offset = $days - 1; $offset >= 0; $offset--) {
        $span[] = $today->modify('-' . $offset . ' days')->format('Y-m-d');
    }
    return $span;
}

function analytics(array $user): array
{
    if (!in_array($user['role'], ['admin', 'instructor', 'moderator'], true)) {
        throw new \Mirage\AppException(403, 'Insights are for the studio.');
    }
    $since = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-13 days')->format('Y-m-d');
    $db = \Mirage\db();
    $signups = \Mirage\many(
        $db,
        "SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS n FROM events WHERE kind = 'signup' AND substr(created_at, 1, 10) >= ? GROUP BY day",
        [$since]
    );
    $enroll_sql = "SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS n FROM events WHERE kind = 'enrollment' AND substr(created_at, 1, 10) >= ?";
    $enroll_args = [$since];
    if ($user['role'] === 'instructor') {
        $enroll_sql .= ' AND course_id IN (SELECT id FROM courses WHERE instructor_id = ?)';
        $enroll_args[] = $user['id'];
    }
    $enroll_sql .= ' GROUP BY day';
    $enrollments = \Mirage\many($db, $enroll_sql, $enroll_args);
    $signup_map = [];
    foreach ($signups as $row) {
        $signup_map[$row['day']] = $row['n'];
    }
    $enroll_map = [];
    foreach ($enrollments as $row) {
        $enroll_map[$row['day']] = $row['n'];
    }
    $series = [];
    foreach (day_span(14) as $day) {
        $series[] = [
            'day' => $day,
            'signups' => $signup_map[$day] ?? 0,
            'enrollments' => $enroll_map[$day] ?? 0,
        ];
    }
    $course_sql = <<<'SQL'

            SELECT c.id, c.title, c.slug, c.status,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrollments
            FROM courses c
SQL;
    if ($user['role'] === 'instructor') {
        $course_sql .= ' WHERE c.instructor_id = ?';
        $courses = \Mirage\many($db, $course_sql, [$user['id']]);
    } else {
        $courses = \Mirage\many($db, $course_sql);
    }
    foreach ($courses as $index => $course) {
        $learners = \Mirage\many($db, 'SELECT user_id FROM enrollments WHERE course_id = ?', [$course['id']]);
        if ($learners) {
            $sum = 0;
            foreach ($learners as $learner) {
                $sum += progress_percent($db, (int) $learner['user_id'], (int) $course['id']);
            }
            $courses[$index]['avg_progress'] = (int) round($sum / count($learners));
        } else {
            $courses[$index]['avg_progress'] = 0;
        }
    }
    if ($user['role'] === 'instructor') {
        $quiz_stats = \Mirage\one(
            $db,
            <<<'SQL'

                SELECT COUNT(*) AS n, COALESCE(SUM(passed), 0) AS passed
                FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
                JOIN courses c ON c.id = q.course_id
                WHERE c.instructor_id = ?
                
SQL,
            [$user['id']]
        );
        $batch_stats = \Mirage\one(
            $db,
            <<<'SQL'

                SELECT COUNT(*) AS batches,
                       COALESCE(SUM((SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id)), 0) AS seats
                FROM batches b JOIN courses c ON c.id = b.course_id
                WHERE c.instructor_id = ?
                
SQL,
            [$user['id']]
        );
    } else {
        $quiz_stats = \Mirage\one($db, 'SELECT COUNT(*) AS n, COALESCE(SUM(passed), 0) AS passed FROM quiz_attempts');
        $batch_stats = \Mirage\one(
            $db,
            'SELECT COUNT(*) AS batches, COALESCE((SELECT COUNT(*) FROM batch_students), 0) AS seats FROM batches'
        );
    }
    $feed_sql = <<<'SQL'

            SELECT e.kind, e.created_at, u.full_name, c.title AS course_title
            FROM events e
            LEFT JOIN users u ON u.id = e.user_id
            LEFT JOIN courses c ON c.id = e.course_id
SQL;
    $feed_args = [];
    if ($user['role'] === 'instructor') {
        $feed_sql .= " WHERE e.kind = 'signup' OR c.instructor_id = ?";
        $feed_args[] = $user['id'];
    }
    $feed_sql .= ' ORDER BY e.created_at DESC LIMIT 12';
    $feed = \Mirage\many($db, $feed_sql, $feed_args);
    $live_count = \Mirage\one($db, 'SELECT COUNT(*) AS n FROM attendance')['n'];
    $signups_14d = 0;
    $enrollments_14d = 0;
    foreach ($series as $point) {
        $signups_14d += $point['signups'];
        $enrollments_14d += $point['enrollments'];
    }
    return [
        'series' => $series,
        'signups_14d' => $signups_14d,
        'enrollments_14d' => $enrollments_14d,
        'courses' => $courses,
        'quiz_attempts' => $quiz_stats['n'],
        'quiz_passes' => $quiz_stats['passed'],
        'batches' => $batch_stats ? $batch_stats['batches'] : 0,
        'batch_seats' => $batch_stats ? $batch_stats['seats'] : 0,
        'live_joins' => $live_count,
        'feed' => $feed,
        'generated_at' => \Mirage\app_now(),
    ];
}

function categories(): array
{
    $db = \Mirage\db();
    $names = [];
    foreach (\Mirage\many($db, "SELECT DISTINCT category FROM courses WHERE status = 'published' ORDER BY category") as $row) {
        $names[] = $row['category'];
    }
    return $names;
}

function seed(\PDO $db): void
{
}
