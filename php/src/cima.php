<?php

namespace Mirage\Cima;

function _or(mixed $value, mixed $default = ''): mixed
{
    if ($value === null || $value === false || $value === '' || $value === 0 || $value === 0.0 || $value === []) {
        return $default;
    }
    return $value;
}

function _text_length(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function ensure(\PDO $db): void
{
    \Mirage\script($db, <<<'SQL'
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
SQL);
    _column($db, 'courses', 'deleted_at', 'deleted_at TEXT');
    _column($db, 'courses', 'parent_id', 'parent_id INTEGER');
    _column($db, 'courses', 'starts_on', 'starts_on TEXT');
    _column($db, 'courses', 'ends_on', 'ends_on TEXT');
    _column($db, 'courses', 'product_code', "product_code TEXT DEFAULT ''");
    _column($db, 'enrollments', 'active', 'active INTEGER NOT NULL DEFAULT 1');
    _column($db, 'library_items', 'deleted_at', 'deleted_at TEXT');
    if (!\Mirage\one($db, 'SELECT id FROM schools')) {
        \Mirage\run($db, "INSERT INTO schools (name, city) VALUES ('Mirage Spa Education', '')");
    }
}

function _column(\PDO $db, string $table, string $name, string $ddl): void
{
    $have = [];
    foreach (\Mirage\many($db, 'PRAGMA table_info(' . $table . ')') as $row) {
        $have[$row['name']] = true;
    }
    if (!isset($have[$name])) {
        \Mirage\script($db, 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $ddl);
    }
}

function _staff(array $user): void
{
    if (!in_array($user['role'], ['admin', 'instructor'], true)) {
        throw new \Mirage\AppException(403, 'This desk is for instructors.');
    }
}

function school(array $user): array
{
    _staff($user);
    $db = \Mirage\db();
    $row = \Mirage\one($db, 'SELECT * FROM schools ORDER BY id LIMIT 1');
    $instructors = \Mirage\many(
        $db,
        "SELECT id, full_name, email, role FROM users WHERE role IN ('admin', 'instructor') ORDER BY full_name"
    );
    $learners = \Mirage\one($db, "SELECT COUNT(*) AS n FROM users WHERE role = 'student'")['n'];
    $courses = \Mirage\one($db, 'SELECT COUNT(*) AS n FROM courses WHERE deleted_at IS NULL')['n'];
    return ['school' => $row, 'instructors' => $instructors, 'learners' => $learners, 'courses' => $courses];
}

function rename_school(array $user, string $name): ?array
{
    if ($user['role'] !== 'admin') {
        throw new \Mirage\AppException(403, 'An administrator names the school.');
    }
    $name = trim($name);
    if (_text_length($name) < 2) {
        throw new \Mirage\AppException(400, 'Give the school a name.');
    }
    $db = \Mirage\db();
    \Mirage\run(
        $db,
        'UPDATE schools SET name = ? WHERE id = (SELECT id FROM schools ORDER BY id LIMIT 1)',
        [$name]
    );
    return \Mirage\one($db, 'SELECT * FROM schools ORDER BY id LIMIT 1');
}

function _stats(\PDO $db, int $courseId): array
{
    $learners = \Mirage\one(
        $db,
        <<<'SQL'
        SELECT COUNT(*) AS n FROM enrollments e
        JOIN users u ON u.id = e.user_id
        WHERE e.course_id = ? AND u.role = 'student' AND COALESCE(e.active, 1) = 1
        SQL,
        [$courseId]
    )['n'];
    $deactivated = \Mirage\one(
        $db,
        'SELECT COUNT(*) AS n FROM enrollments WHERE course_id = ? AND COALESCE(active, 1) = 0',
        [$courseId]
    )['n'];
    $toScore = \Mirage\one(
        $db,
        <<<'SQL'
        SELECT COUNT(*) AS n FROM submissions s
        JOIN assignments a ON a.id = s.assignment_id
        WHERE a.course_id = ? AND s.status != 'graded'
        SQL,
        [$courseId]
    )['n'];
    $enrolled = \Mirage\many(
        $db,
        'SELECT user_id FROM enrollments WHERE course_id = ? AND COALESCE(active, 1) = 1',
        [$courseId]
    );
    $completed = 0;
    $progressTotal = 0;
    foreach ($enrolled as $row) {
        $progress = \Mirage\Store\progress_percent($db, $row['user_id'], $courseId);
        $progressTotal += $progress;
        if ($progress >= 100) {
            $completed++;
        }
    }
    $average = $enrolled !== [] ? (int) round($progressTotal / count($enrolled)) : 0;
    return [
        'learners' => $learners,
        'completed' => $completed,
        'deactivated' => $deactivated,
        'to_score' => $toScore,
        'average_progress' => $average,
    ];
}

function _course_card(\PDO $db, array $row, array $user): array
{
    return array_merge([
        'id' => $row['id'],
        'title' => $row['title'],
        'slug' => $row['slug'],
        'category' => _or($row['category'] ?? null, ''),
        'status' => _or($row['status'] ?? null, ''),
        'instructor_name' => _or($row['instructor_name'] ?? null, ''),
        'created_at' => _or($row['created_at'] ?? null, ''),
        'starts_on' => _or($row['starts_on'] ?? null, ''),
        'ends_on' => _or($row['ends_on'] ?? null, ''),
        'product_code' => _or($row['product_code'] ?? null, ''),
        'parent_id' => $row['parent_id'] ?? null,
        'can_edit' => (bool) \Mirage\Store\can_edit_course($user, $row),
    ], _stats($db, (int) $row['id']));
}

function desk(array $user): array
{
    $db = \Mirage\db();
    $schoolRow = \Mirage\one($db, 'SELECT * FROM schools ORDER BY id LIMIT 1');
    if ($user['role'] === 'admin') {
        $clause = 'c.deleted_at IS NULL';
        $args = [];
    } elseif ($user['role'] === 'instructor') {
        $clause = 'c.deleted_at IS NULL AND c.instructor_id = ?';
        $args = [$user['id']];
    } else {
        $clause = '0 = 1';
        $args = [];
    }
    $teachingRows = \Mirage\many(
        $db,
        "
            SELECT c.*, u.full_name AS instructor_name
            FROM courses c JOIN users u ON u.id = c.instructor_id
            WHERE {$clause}
            ORDER BY c.created_at DESC
            ",
        $args
    );
    $teaching = [];
    foreach ($teachingRows as $row) {
        $teaching[] = _course_card($db, $row, $user);
    }
    $enrolledRows = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT c.*, u.full_name AS instructor_name
            FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            JOIN users u ON u.id = c.instructor_id
            WHERE e.user_id = ? AND c.deleted_at IS NULL AND COALESCE(e.active, 1) = 1
            ORDER BY e.enrolled_at DESC
            SQL,
        [$user['id']]
    );
    $enrolled = [];
    foreach ($enrolledRows as $row) {
        $card = _course_card($db, $row, $user);
        $card['progress'] = \Mirage\Store\progress_percent($db, $user['id'], $row['id']);
        $enrolled[] = $card;
    }
    $news = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT p.*, u.full_name AS author_name, g.name AS group_name
            FROM feed_posts p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN study_groups g ON g.id = p.group_id
            ORDER BY p.created_at DESC
            LIMIT 8
            SQL
    );
    $unread = \Mirage\one(
        $db,
        'SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND read = 0',
        [$user['id']]
    )['n'];
    $groups = _my_groups($db, (int) $user['id']);
    return [
        'school' => $schoolRow,
        'teaching' => $teaching,
        'enrolled' => $enrolled,
        'groups' => $groups,
        'news' => $news,
        'unread' => $unread,
    ];
}

function _my_groups(\PDO $db, int $userId): array
{
    return \Mirage\many(
        $db,
        <<<'SQL'
        SELECT g.*, u.full_name AS owner_name,
               (SELECT COUNT(*) FROM study_group_members m WHERE m.group_id = g.id) AS members
        FROM study_groups g
        JOIN users u ON u.id = g.owner_id
        WHERE g.owner_id = ? OR g.id IN (SELECT group_id FROM study_group_members WHERE user_id = ?)
        ORDER BY g.created_at DESC
        SQL,
        [$userId, $userId]
    );
}

function list_groups(array $user): array
{
    $db = \Mirage\db();
    if (in_array($user['role'], ['admin', 'instructor'], true)) {
        return \Mirage\many(
            $db,
            <<<'SQL'
                SELECT g.*, u.full_name AS owner_name,
                       (SELECT COUNT(*) FROM study_group_members m WHERE m.group_id = g.id) AS members
                FROM study_groups g JOIN users u ON u.id = g.owner_id
                ORDER BY g.created_at DESC
                SQL
        );
    }
    return _my_groups($db, (int) $user['id']);
}

function create_group(array $user, string $name, string $description): ?array
{
    _staff($user);
    $name = trim($name);
    if (_text_length($name) < 2) {
        throw new \Mirage\AppException(400, 'Name the group.');
    }
    $db = \Mirage\db();
    \Mirage\run(
        $db,
        'INSERT INTO study_groups (name, description, owner_id, created_at) VALUES (?, ?, ?, ?)',
        [$name, trim($description), $user['id'], \Mirage\app_now()]
    );
    $groupId = \Mirage\insert_id($db);
    \Mirage\run(
        $db,
        'INSERT INTO study_group_members (group_id, user_id) VALUES (?, ?)',
        [$groupId, $user['id']]
    );
    return \Mirage\one(
        $db,
        <<<'SQL'
            SELECT g.*, u.full_name AS owner_name, 1 AS members
            FROM study_groups g JOIN users u ON u.id = g.owner_id WHERE g.id = ?
            SQL,
        [$groupId]
    );
}

function group_detail(array $user, int $groupId): array
{
    $db = \Mirage\db();
    $group = _group_or_404($db, $groupId);
    _can_see_group($db, $user, $group);
    $members = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT u.id, u.full_name, u.email, u.role
            FROM study_group_members m JOIN users u ON u.id = m.user_id
            WHERE m.group_id = ?
            ORDER BY u.full_name
            SQL,
        [$groupId]
    );
    $posts = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT p.*, u.full_name AS author_name
            FROM feed_posts p JOIN users u ON u.id = p.user_id
            WHERE p.group_id = ?
            ORDER BY p.created_at DESC
            SQL,
        [$groupId]
    );
    $group['members_list'] = $members;
    $group['posts'] = $posts;
    return $group;
}

function _group_or_404(\PDO $db, int $groupId): array
{
    $group = \Mirage\one(
        $db,
        <<<'SQL'
        SELECT g.*, u.full_name AS owner_name
        FROM study_groups g JOIN users u ON u.id = g.owner_id WHERE g.id = ?
        SQL,
        [$groupId]
    );
    if (!$group) {
        throw new \Mirage\AppException(404, 'Group not found.');
    }
    return $group;
}

function _can_see_group(\PDO $db, array $user, array $group): void
{
    if (in_array($user['role'], ['admin', 'instructor'], true)) {
        return;
    }
    $member = \Mirage\one(
        $db,
        'SELECT 1 AS ok FROM study_group_members WHERE group_id = ? AND user_id = ?',
        [$group['id'], $user['id']]
    );
    if (!$member) {
        throw new \Mirage\AppException(403, 'You are not in this group.');
    }
}

function add_member(array $user, int $groupId, string $email): array
{
    _staff($user);
    $email = strtolower(trim($email));
    $db = \Mirage\db();
    $group = _group_or_404($db, $groupId);
    if ($user['role'] !== 'admin' && (int) $group['owner_id'] !== (int) $user['id']) {
        throw new \Mirage\AppException(403, 'The group owner adds people.');
    }
    $person = \Mirage\one($db, 'SELECT * FROM users WHERE lower(email) = ?', [$email]);
    if (!$person) {
        throw new \Mirage\AppException(404, 'No account uses that email.');
    }
    \Mirage\run(
        $db,
        'INSERT OR IGNORE INTO study_group_members (group_id, user_id) VALUES (?, ?)',
        [$groupId, $person['id']]
    );
    \Mirage\run(
        $db,
        'INSERT INTO notifications (user_id, body, href, created_at) VALUES (?, ?, ?, ?)',
        [$person['id'], 'You were added to ' . $group['name'] . '.', '/groups/' . $groupId, \Mirage\app_now()]
    );
    return group_detail($user, $groupId);
}

function post_news(array $user, string $body, ?int $groupId): ?array
{
    $body = trim($body);
    if (_text_length($body) < 2) {
        throw new \Mirage\AppException(400, 'Write a message.');
    }
    $db = \Mirage\db();
    if ($groupId) {
        $group = _group_or_404($db, $groupId);
        _can_see_group($db, $user, $group);
    } elseif (!in_array($user['role'], ['admin', 'instructor'], true)) {
        throw new \Mirage\AppException(403, 'Instructors post school news.');
    }
    \Mirage\run(
        $db,
        'INSERT INTO feed_posts (user_id, group_id, body, created_at) VALUES (?, ?, ?, ?)',
        [$user['id'], $groupId, $body, \Mirage\app_now()]
    );
    $id = \Mirage\insert_id($db);
    return \Mirage\one(
        $db,
        <<<'SQL'
            SELECT p.*, u.full_name AS author_name, g.name AS group_name
            FROM feed_posts p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN study_groups g ON g.id = p.group_id
            WHERE p.id = ?
            SQL,
        [$id]
    );
}

function copy_course(array $user, int $courseId): array
{
    _staff($user);
    $db = \Mirage\db();
    $course = \Mirage\Store\course_row($db, $courseId);
    if (!$course || _or($course['deleted_at'] ?? null, null) !== null) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    if (!\Mirage\Store\can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot copy this course.');
    }
    $title = str_starts_with(strtolower((string) $course['title']), 'copy of ')
        ? $course['title']
        : 'Copy of ' . $course['title'];
    \Mirage\run(
        $db,
        <<<'SQL'
            INSERT INTO courses
              (title, slug, summary, description, category, level, status, instructor_id, created_at,
               parent_id, starts_on, ends_on, product_code)
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?)
            SQL,
        [
            $title,
            \Mirage\Store\unique_slug($db, $title),
            _or($course['summary'] ?? null, ''),
            _or($course['description'] ?? null, ''),
            _or($course['category'] ?? null, 'General'),
            _or($course['level'] ?? null, 'Foundation'),
            $user['id'],
            \Mirage\app_now(),
            $course['id'],
            _or($course['starts_on'] ?? null, ''),
            _or($course['ends_on'] ?? null, ''),
            _or($course['product_code'] ?? null, ''),
        ]
    );
    $newId = \Mirage\insert_id($db);
    $assignmentMap = [];
    foreach (\Mirage\many($db, 'SELECT * FROM assignments WHERE course_id = ?', [$course['id']]) as $item) {
        \Mirage\run(
            $db,
            'INSERT INTO assignments (course_id, title, instructions, max_score) VALUES (?, ?, ?, ?)',
            [$newId, $item['title'], $item['instructions'], $item['max_score']]
        );
        $assignmentMap[$item['id']] = \Mirage\insert_id($db);
    }
    $quizMap = [];
    foreach (\Mirage\many($db, 'SELECT * FROM quizzes WHERE course_id = ?', [$course['id']]) as $item) {
        \Mirage\run(
            $db,
            <<<'SQL'
                INSERT INTO quizzes (course_id, title, passing_score, max_attempts, duration_minutes, show_answers)
                VALUES (?, ?, ?, ?, ?, ?)
                SQL,
            [$newId, $item['title'], $item['passing_score'], $item['max_attempts'], $item['duration_minutes'], $item['show_answers']]
        );
        $copiedQuizId = \Mirage\insert_id($db);
        $quizMap[$item['id']] = $copiedQuizId;
        foreach (\Mirage\many($db, 'SELECT * FROM questions WHERE quiz_id = ? ORDER BY position', [$item['id']]) as $question) {
            \Mirage\run(
                $db,
                <<<'SQL'
                    INSERT INTO questions (quiz_id, prompt, options_json, answer_index, explanation, position)
                    VALUES (?, ?, ?, ?, ?, ?)
                    SQL,
                [
                    $copiedQuizId,
                    $question['prompt'],
                    $question['options_json'],
                    $question['answer_index'],
                    $question['explanation'],
                    $question['position'],
                ]
            );
        }
    }
    $chapterMap = [];
    foreach (\Mirage\many($db, 'SELECT * FROM chapters WHERE course_id = ? ORDER BY position', [$course['id']]) as $chapter) {
        \Mirage\run(
            $db,
            'INSERT INTO chapters (course_id, title, position) VALUES (?, ?, ?)',
            [$newId, $chapter['title'], $chapter['position']]
        );
        $chapterMap[$chapter['id']] = \Mirage\insert_id($db);
    }
    $lessons = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT l.* FROM lessons l
            JOIN chapters c ON c.id = l.chapter_id
            WHERE c.course_id = ?
            ORDER BY l.position
            SQL,
        [$course['id']]
    );
    foreach ($lessons as $lesson) {
        $oldQuiz = $lesson['quiz_id'] ?? null;
        $oldAssignment = $lesson['assignment_id'] ?? null;
        \Mirage\run(
            $db,
            <<<'SQL'
                INSERT INTO lessons
                  (chapter_id, title, kind, body, video_url, minutes, position, quiz_id, assignment_id,
                   include_in_preview, instructor_notes, content_json, library_item_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                SQL,
            [
                $chapterMap[$lesson['chapter_id']],
                $lesson['title'],
                $lesson['kind'],
                _or($lesson['body'] ?? null, ''),
                _or($lesson['video_url'] ?? null, ''),
                _or($lesson['minutes'] ?? null, 8),
                $lesson['position'],
                $oldQuiz === null ? null : ($quizMap[$oldQuiz] ?? null),
                $oldAssignment === null ? null : ($assignmentMap[$oldAssignment] ?? null),
                _or($lesson['include_in_preview'] ?? null, 0),
                _or($lesson['instructor_notes'] ?? null, ''),
                _or($lesson['content_json'] ?? null, ''),
                $lesson['library_item_id'] ?? null,
            ]
        );
    }
    $copiedRow = \Mirage\Store\course_row($db, $newId);
    return ['course' => ['id' => $copiedRow['id'], 'title' => $copiedRow['title'], 'slug' => $copiedRow['slug']]];
}

function trash_course(array $user, int $courseId): array
{
    $db = \Mirage\db();
    $course = \Mirage\Store\course_row($db, $courseId);
    if (!$course) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    if (!\Mirage\Store\can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot move this course to trash.');
    }
    \Mirage\run($db, 'UPDATE courses SET deleted_at = ? WHERE id = ?', [\Mirage\app_now(), $courseId]);
    return ['trashed' => true];
}

function trash_bin(array $user): array
{
    _staff($user);
    $db = \Mirage\db();
    if ($user['role'] === 'admin') {
        $courseClause = 'c.deleted_at IS NOT NULL';
        $args = [];
        $itemClause = 'i.deleted_at IS NOT NULL';
        $itemArgs = [];
    } else {
        $courseClause = 'c.deleted_at IS NOT NULL AND c.instructor_id = ?';
        $args = [$user['id']];
        $itemClause = 'i.deleted_at IS NOT NULL AND i.owner_id = ?';
        $itemArgs = [$user['id']];
    }
    $courses = \Mirage\many(
        $db,
        "
            SELECT c.id, c.title, c.slug, c.deleted_at, u.full_name AS instructor_name
            FROM courses c JOIN users u ON u.id = c.instructor_id
            WHERE {$courseClause}
            ORDER BY c.deleted_at DESC
            ",
        $args
    );
    $items = \Mirage\many(
        $db,
        "
            SELECT i.id, i.title, i.kind, i.deleted_at, u.full_name AS owner_name
            FROM library_items i JOIN users u ON u.id = i.owner_id
            WHERE {$itemClause}
            ORDER BY i.deleted_at DESC
            ",
        $itemArgs
    );
    return ['courses' => $courses, 'resources' => $items];
}

function restore_course(array $user, int $courseId): array
{
    $db = \Mirage\db();
    $course = \Mirage\Store\course_row($db, $courseId);
    if (!$course || _or($course['deleted_at'] ?? null, null) === null) {
        throw new \Mirage\AppException(404, 'That course is not in the trash.');
    }
    if (!\Mirage\Store\can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot restore this course.');
    }
    \Mirage\run($db, 'UPDATE courses SET deleted_at = NULL WHERE id = ?', [$courseId]);
    return ['restored' => true];
}

function purge_course(array $user, int $courseId): array
{
    $db = \Mirage\db();
    $course = \Mirage\Store\course_row($db, $courseId);
    if (!$course || _or($course['deleted_at'] ?? null, null) === null) {
        throw new \Mirage\AppException(404, 'That course is not in the trash.');
    }
    if (!\Mirage\Store\can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot remove this course.');
    }
    \Mirage\run($db, 'DELETE FROM courses WHERE id = ?', [$courseId]);
    return ['purged' => true];
}

function restore_resource(array $user, int $itemId): array
{
    $db = \Mirage\db();
    $item = \Mirage\one($db, 'SELECT * FROM library_items WHERE id = ?', [$itemId]);
    if (!$item || _or($item['deleted_at'] ?? null, null) === null) {
        throw new \Mirage\AppException(404, 'That resource is not in the trash.');
    }
    if ($user['role'] !== 'admin' && (int) $item['owner_id'] !== (int) $user['id']) {
        throw new \Mirage\AppException(403, 'You cannot restore this resource.');
    }
    \Mirage\run($db, 'UPDATE library_items SET deleted_at = NULL WHERE id = ?', [$itemId]);
    return ['restored' => true];
}

function purge_resource(array $user, int $itemId): array
{
    $db = \Mirage\db();
    $item = \Mirage\one($db, 'SELECT * FROM library_items WHERE id = ?', [$itemId]);
    if (!$item || _or($item['deleted_at'] ?? null, null) === null) {
        throw new \Mirage\AppException(404, 'That resource is not in the trash.');
    }
    if ($user['role'] !== 'admin' && (int) $item['owner_id'] !== (int) $user['id']) {
        throw new \Mirage\AppException(403, 'You cannot remove this resource.');
    }
    \Mirage\run($db, 'DELETE FROM library_items WHERE id = ?', [$itemId]);
    if (_or($item['stored_name'] ?? null, null) !== null) {
        $path = \Mirage\uploads_dir() . DIRECTORY_SEPARATOR . $item['stored_name'];
        if (is_file($path)) {
            unlink($path);
        }
    }
    return ['purged' => true];
}

function reports(array $user): array
{
    $data = desk($user);
    $rows = in_array($user['role'], ['admin', 'instructor'], true) ? $data['teaching'] : $data['enrolled'];
    $learners = 0;
    $completed = 0;
    $toScore = 0;
    foreach ($rows as $row) {
        $learners += $row['learners'];
        $completed += $row['completed'];
        $toScore += $row['to_score'];
    }
    return [
        'school' => $data['school'],
        'courses' => $rows,
        'totals' => [
            'courses' => count($rows),
            'learners' => $learners,
            'completed' => $completed,
            'to_score' => $toScore,
        ],
    ];
}

function calendar(array $user): array
{
    $db = \Mirage\db();
    $live = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT id, title, starts_at, minutes, status, course_id
            FROM live_classes
            ORDER BY starts_at
            SQL
    );
    $batches = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT b.id, b.title, b.start_date, b.end_date, c.title AS course_title
            FROM batches b JOIN courses c ON c.id = b.course_id
            WHERE c.deleted_at IS NULL
            ORDER BY b.start_date
            SQL
    );
    $slots = \Mirage\many(
        $db,
        'SELECT id, starts_at, minutes, status FROM eval_slots ORDER BY starts_at'
    );
    return ['live' => $live, 'batches' => $batches, 'evaluations' => $slots];
}

function search(array $user, string $query): array
{
    $query = trim($query);
    if (_text_length($query) < 2) {
        return ['courses' => [], 'people' => [], 'resources' => [], 'groups' => []];
    }
    $like = '%' . $query . '%';
    $db = \Mirage\db();
    $courses = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT id, title, slug, category FROM courses
            WHERE deleted_at IS NULL AND (title LIKE ? OR category LIKE ?)
            ORDER BY title LIMIT 12
            SQL,
        [$like, $like]
    );
    $people = [];
    if (in_array($user['role'], ['admin', 'instructor'], true)) {
        $people = \Mirage\many(
            $db,
            <<<'SQL'
                SELECT id, full_name, email, role, username FROM users
                WHERE full_name LIKE ? OR email LIKE ?
                ORDER BY full_name LIMIT 12
                SQL,
            [$like, $like]
        );
    }
    $resources = [];
    if (in_array($user['role'], ['admin', 'instructor'], true)) {
        $resources = \Mirage\many(
            $db,
            <<<'SQL'
                SELECT id, title, kind FROM library_items
                WHERE deleted_at IS NULL AND title LIKE ?
                ORDER BY title LIMIT 12
                SQL,
            [$like]
        );
    }
    $groups = \Mirage\many(
        $db,
        'SELECT id, name FROM study_groups WHERE name LIKE ? ORDER BY name LIMIT 12',
        [$like]
    );
    return ['courses' => $courses, 'people' => $people, 'resources' => $resources, 'groups' => $groups];
}

function mark_notifications(array $user): array
{
    $db = \Mirage\db();
    \Mirage\run($db, 'UPDATE notifications SET read = 1 WHERE user_id = ? AND read = 0', [$user['id']]);
    return ['read' => true];
}

function roster(array $user, int $courseId): array
{
    $db = \Mirage\db();
    $course = \Mirage\Store\course_row($db, $courseId);
    if (!$course) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    if (!\Mirage\Store\can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot see this roster.');
    }
    return \Mirage\many(
        $db,
        <<<'SQL'
            SELECT u.id, u.full_name, u.email, COALESCE(e.active, 1) AS active, e.enrolled_at
            FROM enrollments e JOIN users u ON u.id = e.user_id
            WHERE e.course_id = ?
            ORDER BY u.full_name
            SQL,
        [$courseId]
    );
}

function set_enrollment(array $user, int $courseId, int $learnerId, bool $active): array
{
    $db = \Mirage\db();
    $course = \Mirage\Store\course_row($db, $courseId);
    if (!$course) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    if (!\Mirage\Store\can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot change this class.');
    }
    $row = \Mirage\one(
        $db,
        'SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?',
        [$courseId, $learnerId]
    );
    if (!$row) {
        throw new \Mirage\AppException(404, 'That learner is not in this class.');
    }
    \Mirage\run($db, 'UPDATE enrollments SET active = ? WHERE id = ?', [$active ? 1 : 0, $row['id']]);
    return ['active' => $active];
}
