<?php

namespace Mirage\Learning;

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

function blocks_for(\PDO $db, array $lesson, ?array $user, bool $editor): array
{
    $raw = [];
    $content = $lesson['content_json'] ?? null;
    if (_or($content, null) !== null) {
        $decoded = json_decode((string) $content, true);
        if (is_array($decoded) && ($decoded === [] || array_keys($decoded) === range(0, count($decoded) - 1))) {
            $raw = $decoded;
        }
    }
    if ($raw === []) {
        if (_or($lesson['body'] ?? null, null) !== null) {
            $raw[] = ['type' => 'markdown', 'text' => $lesson['body']];
        }
        if (_or($lesson['video_url'] ?? null, null) !== null) {
            $raw[] = ['type' => 'video', 'url' => $lesson['video_url']];
        }
        if (_or($lesson['quiz_id'] ?? null, null) !== null) {
            $raw[] = ['type' => 'quiz', 'quiz_id' => $lesson['quiz_id']];
        }
        if (_or($lesson['assignment_id'] ?? null, null) !== null) {
            $raw[] = ['type' => 'assignment', 'assignment_id' => $lesson['assignment_id']];
        }
    }
    $userId = $user ? $user['id'] : null;
    $rendered = [];
    foreach ($raw as $block) {
        if (!is_array($block)) {
            continue;
        }
        $kind = $block['type'] ?? null;
        if ($kind === 'markdown') {
            $rendered[] = ['type' => 'markdown', 'text' => _or($block['text'] ?? null, '')];
        } elseif ($kind === 'video') {
            $rendered[] = [
                'type' => 'video',
                'url' => _or($block['url'] ?? null, _or($lesson['video_url'] ?? null, '')),
            ];
        } elseif ($kind === 'document') {
            $rendered[] = [
                'type' => 'document',
                'url' => _or($block['url'] ?? null, ''),
                'name' => _or($block['name'] ?? null, 'Document'),
                'mime' => _or($block['mime'] ?? null, ''),
            ];
        } elseif ($kind === 'quiz' && _or($block['quiz_id'] ?? null, null) !== null) {
            $rendered[] = [
                'type' => 'quiz',
                'quiz' => \Mirage\Store\quiz_payload($db, $block['quiz_id'], $editor, $userId),
            ];
        } elseif ($kind === 'assignment' && _or($block['assignment_id'] ?? null, null) !== null) {
            $rendered[] = [
                'type' => 'assignment',
                'assignment' => \Mirage\Store\assignment_payload($db, $block['assignment_id'], $userId),
            ];
        }
    }
    return $rendered;
}

function numbered_outline(\PDO $db, int $courseId, ?int $userId): array
{
    $done = [];
    if ($userId) {
        foreach (\Mirage\many($db, 'SELECT lesson_id FROM lesson_progress WHERE user_id = ? AND completed = 1', [$userId]) as $row) {
            $done[$row['lesson_id']] = true;
        }
    }
    $chapters = [];
    $chapterNumber = 0;
    foreach (\Mirage\many($db, 'SELECT * FROM chapters WHERE course_id = ? ORDER BY position, id', [$courseId]) as $chapter) {
        $chapterNumber++;
        $lessons = [];
        $lessonNumber = 0;
        foreach (\Mirage\many($db, 'SELECT * FROM lessons WHERE chapter_id = ? ORDER BY position, id', [$chapter['id']]) as $lesson) {
            $lessonNumber++;
            $lessons[] = [
                'id' => $lesson['id'],
                'number' => $lessonNumber,
                'title' => $lesson['title'],
                'kind' => $lesson['kind'],
                'minutes' => $lesson['minutes'],
                'completed' => isset($done[$lesson['id']]),
                'include_in_preview' => (bool) $lesson['include_in_preview'],
            ];
        }
        $chapters[] = [
            'id' => $chapter['id'],
            'number' => $chapterNumber,
            'title' => $chapter['title'],
            'lessons' => $lessons,
        ];
    }
    return $chapters;
}

function lesson_at(\PDO $db, int $courseId, int $chapterNumber, int $lessonNumber): array
{
    $chapters = \Mirage\many($db, 'SELECT * FROM chapters WHERE course_id = ? ORDER BY position, id', [$courseId]);
    if ($chapterNumber < 1 || $chapterNumber > count($chapters)) {
        throw new \Mirage\AppException(404, 'Chapter not found.');
    }
    $chapter = $chapters[$chapterNumber - 1];
    $lessons = \Mirage\many($db, 'SELECT * FROM lessons WHERE chapter_id = ? ORDER BY position, id', [$chapter['id']]);
    if ($lessonNumber < 1 || $lessonNumber > count($lessons)) {
        throw new \Mirage\AppException(404, 'Lesson not found.');
    }
    return [$chapter, $lessons[$lessonNumber - 1]];
}

function flat_positions(array $outline): array
{
    $rows = [];
    foreach ($outline as $chapter) {
        foreach ($chapter['lessons'] as $lesson) {
            $rows[] = [
                'chapter' => $chapter['number'],
                'lesson' => $lesson['number'],
                'id' => $lesson['id'],
            ];
        }
    }
    return $rows;
}

function course_page(string $slug, ?array $user): array
{
    $payload = \Mirage\Store\get_public_course($slug, $user);
    $course = $payload['course'];
    $db = \Mirage\db();
    $payload['outline'] = numbered_outline($db, (int) $course['id'], $user ? (int) $user['id'] : null);
    $payload['reviews'] = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT r.rating, r.body, r.created_at, u.full_name, u.username
            FROM reviews r JOIN users u ON u.id = r.user_id
            WHERE r.course_id = ? ORDER BY r.created_at DESC
            SQL,
        [$course['id']]
    );
    $ratings = [];
    foreach ($payload['reviews'] as $row) {
        $ratings[] = $row['rating'];
    }
    $payload['rating'] = $ratings !== [] ? round(array_sum($ratings) / count($ratings), 1) : null;
    $payload['certificate'] = null;
    if ($user) {
        $payload['certificate'] = \Mirage\one(
            $db,
            'SELECT id, issue_date FROM certificates WHERE course_id = ? AND user_id = ?',
            [$course['id'], $user['id']]
        );
    }
    return $payload;
}

function lesson_page(?array $user, string $slug, int $chapterNumber, int $lessonNumber): array
{
    $db = \Mirage\db();
    $course = \Mirage\Store\course_by_slug($db, $slug);
    $editor = (bool) ($user && \Mirage\Store\can_edit_course($user, $course));
    $enrolled = (bool) ($user && \Mirage\one(
        $db,
        'SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?',
        [$course['id'], $user['id']]
    ));
    [$chapter, $lesson] = lesson_at($db, (int) $course['id'], $chapterNumber, $lessonNumber);
    if ($course['status'] !== 'published' && !$editor) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    if (!$enrolled && !$editor) {
        throw new \Mirage\AppException(403, 'This class has not been assigned to you.');
    }
    $outline = numbered_outline($db, (int) $course['id'], $user ? (int) $user['id'] : null);
    $positions = flat_positions($outline);
    $index = 0;
    foreach ($positions as $i => $row) {
        if ($row['chapter'] === $chapterNumber && $row['lesson'] === $lessonNumber) {
            $index = $i;
            break;
        }
    }
    $notes = [];
    if ($user) {
        $note = \Mirage\one(
            $db,
            'SELECT body FROM lesson_notes WHERE lesson_id = ? AND user_id = ?',
            [$lesson['id'], $user['id']]
        );
        $notes = $note ? $note['body'] : '';
    }
    $discussions = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT d.*, u.full_name, u.username FROM discussions d
            JOIN users u ON u.id = d.user_id
            WHERE d.lesson_id = ? ORDER BY d.created_at
            SQL,
        [$lesson['id']]
    );
    return [
        'course' => $course,
        'enrolled' => $enrolled || $editor,
        'progress' => $user && ($enrolled || $editor)
            ? \Mirage\Store\progress_percent($db, $user['id'], $course['id'])
            : 0,
        'outline' => $outline,
        'chapter_number' => $chapterNumber,
        'lesson_number' => $lessonNumber,
        'chapter_title' => $chapter['title'],
        'lesson' => [
            'id' => $lesson['id'],
            'title' => $lesson['title'],
            'kind' => $lesson['kind'],
            'minutes' => $lesson['minutes'],
            'completed' => (bool) ($user && \Mirage\one(
                $db,
                'SELECT 1 FROM lesson_progress WHERE user_id = ? AND lesson_id = ? AND completed = 1',
                [$user['id'], $lesson['id']]
            )),
            'include_in_preview' => (bool) $lesson['include_in_preview'],
            'instructor_notes' => $editor ? $lesson['instructor_notes'] : '',
            'blocks' => blocks_for($db, $lesson, $user, $editor),
        ],
        'prev' => $index > 0 ? $positions[$index - 1] : null,
        'next' => $index + 1 < count($positions) ? $positions[$index + 1] : null,
        'notes' => $notes,
        'discussions' => $discussions,
    ];
}

function save_note(array $user, int $lessonId, string $body): array
{
    $db = \Mirage\db();
    \Mirage\run(
        $db,
        <<<'SQL'
            INSERT INTO lesson_notes (lesson_id, user_id, body, updated_at) VALUES (?, ?, ?, ?)
            ON CONFLICT(lesson_id, user_id) DO UPDATE SET body = excluded.body, updated_at = excluded.updated_at
            SQL,
        [$lessonId, $user['id'], trim($body), \Mirage\app_now()]
    );
    return ['saved' => true];
}

function post_discussion(array $user, string $body, ?int $lessonId = null, ?int $batchId = null, ?int $parentId = null): ?array
{
    $body = trim($body);
    if (_text_length($body) < 2) {
        throw new \Mirage\AppException(400, 'Write a comment first.');
    }
    if (!$lessonId && !$batchId) {
        throw new \Mirage\AppException(400, 'A comment needs a lesson or a batch.');
    }
    $db = \Mirage\db();
    $courseId = null;
    if ($lessonId) {
        $row = \Mirage\one(
            $db,
            'SELECT c.course_id FROM lessons l JOIN chapters c ON c.id = l.chapter_id WHERE l.id = ?',
            [$lessonId]
        );
        if (!$row) {
            throw new \Mirage\AppException(404, 'Lesson not found.');
        }
        $courseId = $row['course_id'];
    }
    if ($batchId && !\Mirage\one($db, 'SELECT id FROM batches WHERE id = ?', [$batchId])) {
        throw new \Mirage\AppException(404, 'Batch not found.');
    }
    \Mirage\run(
        $db,
        <<<'SQL'
            INSERT INTO discussions (course_id, lesson_id, batch_id, parent_id, user_id, body, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            SQL,
        [$courseId, $lessonId, $batchId, $parentId, $user['id'], $body, \Mirage\app_now()]
    );
    $id = \Mirage\insert_id($db);
    return \Mirage\one(
        $db,
        'SELECT d.*, u.full_name, u.username FROM discussions d JOIN users u ON u.id = d.user_id WHERE d.id = ?',
        [$id]
    );
}

function list_batch_discussions(int $batchId): array
{
    $db = \Mirage\db();
    return \Mirage\many(
        $db,
        <<<'SQL'
            SELECT d.*, u.full_name, u.username FROM discussions d
            JOIN users u ON u.id = d.user_id
            WHERE d.batch_id = ? ORDER BY d.created_at
            SQL,
        [$batchId]
    );
}

function list_announcements(int $batchId): array
{
    $db = \Mirage\db();
    return \Mirage\many(
        $db,
        <<<'SQL'
            SELECT a.*, u.full_name FROM announcements a JOIN users u ON u.id = a.user_id
            WHERE a.batch_id = ? ORDER BY a.created_at DESC
            SQL,
        [$batchId]
    );
}

function post_announcement(array $user, int $batchId, string $title, string $body): ?array
{
    if (!in_array($user['role'], ['admin', 'instructor', 'moderator'], true)) {
        throw new \Mirage\AppException(403, 'Only the batch team can post an announcement.');
    }
    $title = trim($title);
    $body = trim($body);
    if (_text_length($title) < 2 || _text_length($body) < 2) {
        throw new \Mirage\AppException(400, 'An announcement needs a title and a message.');
    }
    $db = \Mirage\db();
    $batch = \Mirage\one($db, 'SELECT * FROM batches WHERE id = ?', [$batchId]);
    if (!$batch) {
        throw new \Mirage\AppException(404, 'Batch not found.');
    }
    \Mirage\run(
        $db,
        'INSERT INTO announcements (batch_id, user_id, title, body, created_at) VALUES (?, ?, ?, ?, ?)',
        [$batchId, $user['id'], $title, $body, \Mirage\app_now()]
    );
    $id = \Mirage\insert_id($db);
    $members = \Mirage\many($db, 'SELECT user_id FROM batch_students WHERE batch_id = ?', [$batchId]);
    foreach ($members as $member) {
        \Mirage\run(
            $db,
            'INSERT INTO notifications (user_id, body, href, created_at) VALUES (?, ?, ?, ?)',
            [$member['user_id'], $title, '/batches/' . $batchId, \Mirage\app_now()]
        );
    }
    return \Mirage\one($db, 'SELECT * FROM announcements WHERE id = ?', [$id]);
}

function post_review(array $user, int $courseId, int $rating, string $body): array
{
    $rating = (int) $rating;
    if ($rating < 1 || $rating > 5) {
        throw new \Mirage\AppException(400, 'Rate the course from 1 to 5.');
    }
    $db = \Mirage\db();
    if (!\Mirage\one($db, 'SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?', [$courseId, $user['id']])) {
        throw new \Mirage\AppException(403, 'Enroll before reviewing.');
    }
    \Mirage\run(
        $db,
        <<<'SQL'
            INSERT INTO reviews (course_id, user_id, rating, body, created_at) VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(course_id, user_id) DO UPDATE SET rating = excluded.rating, body = excluded.body, created_at = excluded.created_at
            SQL,
        [$courseId, $user['id'], $rating, trim($body), \Mirage\app_now()]
    );
    return ['saved' => true];
}

function course_row_slug(int $courseId): string
{
    $db = \Mirage\db();
    $row = \Mirage\one($db, 'SELECT slug FROM courses WHERE id = ?', [$courseId]);
    if (!$row) {
        throw new \Mirage\AppException(404, 'Course not found.');
    }
    return $row['slug'];
}

function list_programs(?array $user): array
{
    $db = \Mirage\db();
    $programs = \Mirage\many($db, 'SELECT * FROM programs WHERE published = 1 ORDER BY title');
    foreach ($programs as $i => $program) {
        $program['courses'] = \Mirage\many(
            $db,
            <<<'SQL'
                SELECT c.id, c.title, c.slug, pc.position FROM program_courses pc
                JOIN courses c ON c.id = pc.course_id
                WHERE pc.program_id = ? ORDER BY pc.position
                SQL,
            [$program['id']]
        );
        $program['members'] = \Mirage\one(
            $db,
            'SELECT COUNT(*) AS n FROM program_members WHERE program_id = ?',
            [$program['id']]
        )['n'];
        $program['joined'] = (bool) ($user && \Mirage\one(
            $db,
            'SELECT 1 FROM program_members WHERE program_id = ? AND user_id = ?',
            [$program['id'], $user['id']]
        ));
        if ($program['joined']) {
            $progresses = [];
            foreach ($program['courses'] as $course) {
                $progresses[] = \Mirage\Store\progress_percent($db, $user['id'], $course['id']);
            }
            $program['progress'] = $progresses !== []
                ? (int) round(array_sum($progresses) / count($progresses))
                : 0;
        }
        $programs[$i] = $program;
    }
    return $programs;
}

function enroll_program(array $user, int $programId): array
{
    $db = \Mirage\db();
    $program = \Mirage\one($db, 'SELECT * FROM programs WHERE id = ? AND published = 1', [$programId]);
    if (!$program) {
        throw new \Mirage\AppException(404, 'Program not found.');
    }
    if (!\Mirage\one($db, 'SELECT 1 FROM program_members WHERE program_id = ? AND user_id = ?', [$programId, $user['id']])) {
        \Mirage\run(
            $db,
            'INSERT INTO program_members (program_id, user_id, enrolled_at) VALUES (?, ?, ?)',
            [$programId, $user['id'], \Mirage\app_now()]
        );
    }
    $courses = \Mirage\many($db, 'SELECT course_id FROM program_courses WHERE program_id = ?', [$programId]);
    foreach ($courses as $course) {
        \Mirage\Store\enroll_silent($db, $user['id'], $course['course_id']);
    }
    return ['joined' => true];
}

function certification(array $user, string $slug): array
{
    $db = \Mirage\db();
    $course = \Mirage\Store\course_by_slug($db, $slug);
    $progress = \Mirage\Store\progress_percent($db, $user['id'], $course['id']);
    $certificate = \Mirage\one(
        $db,
        <<<'SQL'
            SELECT c.*, u.full_name AS member_name, e.full_name AS evaluator_name
            FROM certificates c
            JOIN users u ON u.id = c.user_id
            LEFT JOIN users e ON e.id = c.evaluator_id
            WHERE c.course_id = ? AND c.user_id = ?
            SQL,
        [$course['id'], $user['id']]
    );
    $request = \Mirage\one(
        $db,
        <<<'SQL'
            SELECT r.*, s.starts_at, s.meeting_url, s.status AS slot_status
            FROM certificate_requests r
            LEFT JOIN eval_slots s ON s.id = r.slot_id
            WHERE r.course_id = ? AND r.user_id = ?
            ORDER BY r.id DESC LIMIT 1
            SQL,
        [$course['id'], $user['id']]
    );
    return [
        'course' => [
            'id' => $course['id'],
            'title' => $course['title'],
            'slug' => $course['slug'],
            'enable_certification' => (bool) $course['enable_certification'],
        ],
        'progress' => $progress,
        'eligible' => (bool) $course['enable_certification'] && $progress >= 100,
        'certificate' => $certificate,
        'request' => $request,
    ];
}

function request_certificate(array $user, int $courseId): ?array
{
    $db = \Mirage\db();
    $course = \Mirage\Store\course_row($db, $courseId);
    if (!$course['enable_certification']) {
        throw new \Mirage\AppException(400, 'This course does not issue a certificate.');
    }
    if (\Mirage\Store\progress_percent($db, $user['id'], $courseId) < 100) {
        throw new \Mirage\AppException(400, 'Finish every lesson before requesting a certificate.');
    }
    if (\Mirage\one($db, 'SELECT id FROM certificates WHERE course_id = ? AND user_id = ?', [$courseId, $user['id']])) {
        throw new \Mirage\AppException(400, 'You already hold this certificate.');
    }
    $existing = \Mirage\one(
        $db,
        "SELECT * FROM certificate_requests WHERE course_id = ? AND user_id = ? AND status IN ('pending', 'scheduled')",
        [$courseId, $user['id']]
    );
    if ($existing) {
        throw new \Mirage\AppException(400, 'An evaluation is already booked for this certificate.');
    }
    $booked = \Mirage\Store\book_evaluation($user, $courseId);
    \Mirage\run(
        $db,
        <<<'SQL'
            INSERT INTO certificate_requests (course_id, user_id, evaluator_id, slot_id, status, created_at)
            VALUES (?, ?, ?, ?, 'scheduled', ?)
            SQL,
        [$courseId, $user['id'], $booked['instructor_id'], $booked['id'], \Mirage\app_now()]
    );
    $id = \Mirage\insert_id($db);
    \Mirage\run(
        $db,
        'INSERT INTO notifications (user_id, body, href, created_at) VALUES (?, ?, ?, ?)',
        [
            $booked['instructor_id'],
            $user['full_name'] . ' requested a certificate evaluation.',
            '/courses/' . $course['slug'] . '/certification',
            \Mirage\app_now(),
        ]
    );
    return \Mirage\one($db, 'SELECT * FROM certificate_requests WHERE id = ?', [$id]);
}

function decide_certificate(array $user, int $requestId, bool $passed): array
{
    $db = \Mirage\db();
    $request = \Mirage\one($db, 'SELECT * FROM certificate_requests WHERE id = ?', [$requestId]);
    if (!$request) {
        throw new \Mirage\AppException(404, 'Certificate request not found.');
    }
    $course = \Mirage\Store\course_row($db, $request['course_id']);
    $allowed = [];
    if ($course['evaluator_id'] !== null && $course['evaluator_id'] !== '') {
        $allowed[] = (int) $course['evaluator_id'];
    }
    if ($course['instructor_id'] !== null && $course['instructor_id'] !== '') {
        $allowed[] = (int) $course['instructor_id'];
    }
    if ($user['role'] !== 'admin' && !in_array((int) $user['id'], $allowed, true)) {
        throw new \Mirage\AppException(403, 'Only the evaluator can issue this certificate.');
    }
    $status = $passed ? 'passed' : 'failed';
    \Mirage\run($db, 'UPDATE certificate_requests SET status = ? WHERE id = ?', [$status, $requestId]);
    if ($passed && !\Mirage\one(
        $db,
        'SELECT id FROM certificates WHERE course_id = ? AND user_id = ?',
        [$course['id'], $request['user_id']]
    )) {
        \Mirage\run(
            $db,
            'INSERT INTO certificates (course_id, user_id, evaluator_id, issue_date) VALUES (?, ?, ?, ?)',
            [$course['id'], $request['user_id'], $user['id'], \Mirage\app_now()]
        );
        \Mirage\run(
            $db,
            'INSERT INTO notifications (user_id, body, href, created_at) VALUES (?, ?, ?, ?)',
            [
                $request['user_id'],
                'Certificate issued for ' . $course['title'] . '.',
                '/certified-participants',
                \Mirage\app_now(),
            ]
        );
    }
    return ['status' => $status];
}

function list_certificates(): array
{
    $db = \Mirage\db();
    return \Mirage\many(
        $db,
        <<<'SQL'
            SELECT c.id, c.issue_date, u.full_name, u.username, k.title AS course_title, k.slug
            FROM certificates c
            JOIN users u ON u.id = c.user_id
            JOIN courses k ON k.id = c.course_id
            ORDER BY c.issue_date DESC
            SQL
    );
}

function profile(string $username, ?array $viewer): array
{
    $db = \Mirage\db();
    $person = \Mirage\one($db, 'SELECT * FROM users WHERE username = ?', [$username]);
    if (!$person) {
        throw new \Mirage\AppException(404, 'Profile not found.');
    }
    $public = \Mirage\public_user($person);
    if (!$viewer || (int) $viewer['id'] !== (int) $person['id']) {
        unset($public['email']);
    }
    $courses = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT c.title, c.slug, e.progress FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            WHERE e.user_id = ? ORDER BY e.enrolled_at DESC
            SQL,
        [$person['id']]
    );
    $certificates = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT c.issue_date, k.title, k.slug FROM certificates c
            JOIN courses k ON k.id = c.course_id WHERE c.user_id = ?
            SQL,
        [$person['id']]
    );
    $slots = [];
    $requests = [];
    if ($viewer && (int) $viewer['id'] === (int) $person['id'] && in_array($person['role'], ['admin', 'instructor'], true)) {
        $slots = \Mirage\many(
            $db,
            'SELECT * FROM eval_slots WHERE instructor_id = ? ORDER BY starts_at',
            [$person['id']]
        );
        $requests = \Mirage\many(
            $db,
            <<<'SQL'
                SELECT r.*, u.full_name AS student_name, k.title AS course_title, k.slug
                FROM certificate_requests r
                JOIN users u ON u.id = r.user_id
                JOIN courses k ON k.id = r.course_id
                WHERE r.evaluator_id = ? OR k.instructor_id = ?
                ORDER BY r.created_at DESC
                SQL,
            [$person['id'], $person['id']]
        );
    }
    return [
        'profile' => $public,
        'courses' => $courses,
        'certificates' => $certificates,
        'slots' => $slots,
        'requests' => $requests,
    ];
}

function list_exercises(): array
{
    $db = \Mirage\db();
    return \Mirage\many(
        $db,
        <<<'SQL'
            SELECT e.*, c.title AS course_title, c.slug,
                   (SELECT COUNT(*) FROM exercise_submissions s WHERE s.exercise_id = e.id) AS submissions
            FROM exercises e JOIN courses c ON c.id = e.course_id ORDER BY e.id
            SQL
    );
}

function submit_exercise(array $user, int $exerciseId, string $code): ?array
{
    $code = trim($code);
    if (_text_length($code) < 8) {
        throw new \Mirage\AppException(400, 'Write an answer before submitting.');
    }
    $db = \Mirage\db();
    if (!\Mirage\one($db, 'SELECT id FROM exercises WHERE id = ?', [$exerciseId])) {
        throw new \Mirage\AppException(404, 'Exercise not found.');
    }
    \Mirage\run(
        $db,
        "INSERT INTO exercise_submissions (exercise_id, user_id, code, status, submitted_at) VALUES (?, ?, ?, 'pending', ?)",
        [$exerciseId, $user['id'], $code, \Mirage\app_now()]
    );
    $id = \Mirage\insert_id($db);
    return \Mirage\one($db, 'SELECT * FROM exercise_submissions WHERE id = ?', [$id]);
}

function exercise_submissions(array $user): array
{
    $db = \Mirage\db();
    if (in_array($user['role'], ['admin', 'instructor', 'moderator'], true)) {
        $clause = '1 = 1';
        $args = [];
    } else {
        $clause = 's.user_id = ?';
        $args = [$user['id']];
    }
    return \Mirage\many(
        $db,
        "
            SELECT s.*, e.title, u.full_name AS student_name FROM exercise_submissions s
            JOIN exercises e ON e.id = s.exercise_id
            JOIN users u ON u.id = s.user_id
            WHERE {$clause} ORDER BY s.submitted_at DESC
            ",
        $args
    );
}

function grade_exercise(array $user, int $submissionId, string $status, string $feedback): ?array
{
    if (!in_array($status, ['pass', 'fail'], true)) {
        throw new \Mirage\AppException(400, 'Mark the exercise pass or fail.');
    }
    if (!in_array($user['role'], ['admin', 'instructor'], true)) {
        throw new \Mirage\AppException(403, 'Only an instructor can mark an exercise.');
    }
    $db = \Mirage\db();
    \Mirage\run(
        $db,
        'UPDATE exercise_submissions SET status = ?, feedback = ? WHERE id = ?',
        [$status, trim($feedback), $submissionId]
    );
    return \Mirage\one($db, 'SELECT * FROM exercise_submissions WHERE id = ?', [$submissionId]);
}

function notifications(array $user): array
{
    $db = \Mirage\db();
    return \Mirage\many(
        $db,
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
        [$user['id']]
    );
}

function list_quizzes(?array $user): array
{
    $db = \Mirage\db();
    $rows = \Mirage\many(
        $db,
        <<<'SQL'
            SELECT q.*, c.title AS course_title, c.slug FROM quizzes q
            JOIN courses c ON c.id = q.course_id ORDER BY c.title, q.title
            SQL
    );
    if ($user) {
        foreach ($rows as $i => $row) {
            $attempt = \Mirage\one(
                $db,
                'SELECT score, passed, submitted_at FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1',
                [$row['id'], $user['id']]
            );
            $row['last_attempt'] = $attempt ?: null;
            if ($row['last_attempt']) {
                $row['last_attempt']['passed'] = (bool) $row['last_attempt']['passed'];
            }
            $row['attempts'] = \Mirage\one(
                $db,
                'SELECT COUNT(*) AS n FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?',
                [$row['id'], $user['id']]
            )['n'];
            $rows[$i] = $row;
        }
    }
    return $rows;
}

function quiz_submissions(array $user): array
{
    if (!in_array($user['role'], ['admin', 'instructor', 'moderator'], true)) {
        throw new \Mirage\AppException(403, 'Submissions are for instructors.');
    }
    $db = \Mirage\db();
    $clause = '';
    $args = [];
    if ($user['role'] === 'instructor') {
        $clause = 'WHERE c.instructor_id = ?';
        $args[] = $user['id'];
    }
    return \Mirage\many(
        $db,
        "
            SELECT a.id, a.score, a.passed, a.submitted_at, q.title AS quiz_title, u.full_name, c.title AS course_title
            FROM quiz_attempts a
            JOIN quizzes q ON q.id = a.quiz_id
            JOIN courses c ON c.id = q.course_id
            JOIN users u ON u.id = a.user_id
            {$clause}
            ORDER BY a.submitted_at DESC
            ",
        $args
    );
}
