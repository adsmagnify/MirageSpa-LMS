<?php

namespace Mirage\Materials;

const MIME = [
    '.mp4' => 'video/mp4',
    '.webm' => 'video/webm',
    '.mov' => 'video/quicktime',
    '.m4v' => 'video/mp4',
    '.pdf' => 'application/pdf',
    '.txt' => 'text/plain',
    '.doc' => 'application/msword',
    '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    '.ppt' => 'application/vnd.ms-powerpoint',
    '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];

const KINDS = [
    'video' => ['.mp4', '.webm', '.mov', '.m4v'],
    'document' => ['.pdf', '.txt', '.doc', '.docx', '.ppt', '.pptx'],
];

function _text_length(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function _basename(string $filename): string
{
    if ($filename === '') {
        return 'file';
    }
    $name = basename(str_replace('\\', '/', $filename));
    return $name === '' ? 'file' : $name;
}

function _suffix(string $basename): string
{
    if ($basename === '' || $basename === '.' || $basename === '..') {
        return '';
    }
    if ($basename[0] === '.' && substr_count($basename, '.') === 1) {
        return '';
    }
    $dot = strrpos($basename, '.');
    if ($dot === false) {
        return '';
    }
    return strtolower(substr($basename, $dot));
}

function _stem(string $basename): string
{
    $suffix = _suffix($basename);
    if ($suffix === '') {
        return $basename;
    }
    return substr($basename, 0, -strlen($suffix));
}

function _json(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function _discard(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }
}

function _paths_equal(string $left, string $right): bool
{
    $left = rtrim(str_replace('\\', '/', $left), '/');
    $right = rtrim(str_replace('\\', '/', $right), '/');
    if (DIRECTORY_SEPARATOR === '\\') {
        return strcasecmp($left, $right) === 0;
    }
    return $left === $right;
}

function _write_stream(string $source, string $dest): int
{
    $in = fopen($source, 'rb');
    if ($in === false) {
        throw new \Mirage\AppException(400, 'The file is empty.');
    }
    $out = fopen($dest, 'wb');
    if ($out === false) {
        fclose($in);
        throw new \RuntimeException('Could not store the upload.');
    }
    $size = 0;
    try {
        while (!feof($in)) {
            $chunk = fread($in, 1024 * 1024);
            if ($chunk === false) {
                throw new \RuntimeException('Could not read the upload.');
            }
            if ($chunk === '') {
                break;
            }
            $written = fwrite($out, $chunk);
            if ($written !== strlen($chunk)) {
                throw new \RuntimeException('Could not store the upload.');
            }
            $size += $written;
        }
    } finally {
        fclose($in);
        fclose($out);
    }
    return $size;
}

function _public(array $row): array
{
    $item = [
        'id' => $row['id'],
        'owner_id' => $row['owner_id'],
        'owner_name' => (string) ($row['owner_name'] ?? ''),
        'kind' => $row['kind'],
        'title' => $row['title'],
        'description' => $row['description'],
        'original_name' => $row['original_name'],
        'mime' => $row['mime'],
        'size' => $row['size'],
        'created_at' => $row['created_at'],
        'question_count' => 0,
    ];
    if ($row['kind'] === 'quiz' && !empty($row['quiz_json'])) {
        try {
            $decoded = json_decode((string) $row['quiz_json'], true, 512, JSON_THROW_ON_ERROR);
            $questions = is_array($decoded) ? ($decoded['questions'] ?? []) : [];
            $item['question_count'] = is_array($questions) ? count($questions) : 0;
        } catch (\JsonException) {
            $item['question_count'] = 0;
        }
    }
    return $item;
}

function _require_teacher(array $user): void
{
    if (!in_array($user['role'], ['admin', 'instructor'], true)) {
        throw new \Mirage\AppException(403, 'The library is for instructors.');
    }
}

function list_items(array $user): array
{
    _require_teacher($user);
    $rows = \Mirage\many(
        \Mirage\db(),
        <<<'SQL'
            SELECT i.*, u.full_name AS owner_name
            FROM library_items i JOIN users u ON u.id = i.owner_id
            WHERE i.deleted_at IS NULL
            ORDER BY i.created_at DESC
            SQL,
    );
    $items = [];
    foreach ($rows as $row) {
        $items[] = _public($row);
    }
    return $items;
}

function save_file(array $user, string $title, string $kind, array $upload): array
{
    _require_teacher($user);
    if (!isset(KINDS[$kind])) {
        throw new \Mirage\AppException(400, 'Upload a video or a document. Quizzes are written in the library.');
    }
    $title = trim($title);
    $original = _basename((string) ($upload['name'] ?? ''));
    $suffix = _suffix($original);
    if (!in_array($suffix, KINDS[$kind], true)) {
        $allowed = KINDS[$kind];
        sort($allowed, SORT_STRING);
        throw new \Mirage\AppException(400, "That file is not a {$kind}. Use " . implode(', ', $allowed) . '.');
    }
    if (_text_length($title) < 2) {
        $stem = trim(str_replace('_', ' ', _stem($original)));
        $title = $stem !== '' ? $stem : 'Untitled';
    }
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    $tmp = (string) ($upload['tmp_name'] ?? '');
    if ($error !== UPLOAD_ERR_OK || $tmp === '') {
        throw new \Mirage\AppException(400, 'The file is empty.');
    }
    $uploads = \Mirage\uploads_dir();
    if (!is_dir($uploads) && !mkdir($uploads, 0777, true) && !is_dir($uploads)) {
        throw new \RuntimeException('Could not create the uploads directory.');
    }
    $stored = bin2hex(random_bytes(16)) . $suffix;
    $dest = $uploads . DIRECTORY_SEPARATOR . $stored;
    try {
        $size = _write_stream($tmp, $dest);
    } catch (\Throwable $e) {
        _discard($dest);
        throw $e;
    }
    if ($size === 0) {
        _discard($dest);
        throw new \Mirage\AppException(400, 'The file is empty.');
    }
    try {
        $db = \Mirage\db();
        \Mirage\run(
            $db,
            <<<'SQL'
                INSERT INTO library_items
                  (owner_id, kind, title, stored_name, original_name, mime, size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                SQL,
            [$user['id'], $kind, $title, $stored, $original, MIME[$suffix] ?? 'application/octet-stream', $size, \Mirage\app_now()],
        );
        $id = \Mirage\insert_id($db);
        $row = \Mirage\one(
            $db,
            'SELECT i.*, u.full_name AS owner_name FROM library_items i JOIN users u ON u.id = i.owner_id WHERE i.id = ?',
            [$id],
        );
    } catch (\Throwable $e) {
        _discard($dest);
        throw $e;
    }
    return _public($row);
}

function save_quiz(array $user, string $title, array $questions, int $passingScore): array
{
    _require_teacher($user);
    $title = trim($title);
    if (_text_length($title) < 2) {
        throw new \Mirage\AppException(400, 'Name the quiz.');
    }
    if (count($questions) < 1) {
        throw new \Mirage\AppException(400, 'A quiz needs at least one question.');
    }
    $cleaned = [];
    foreach ($questions as $question) {
        if (!is_array($question)) {
            throw new \Mirage\AppException(400, 'Write the question.');
        }
        $options = [];
        $rawOptions = $question['options'] ?? [];
        if (!is_array($rawOptions)) {
            $rawOptions = [];
        }
        foreach ($rawOptions as $option) {
            $text = trim((string) $option);
            if ($text !== '') {
                $options[] = $text;
            }
        }
        if (count($options) < 2) {
            throw new \Mirage\AppException(400, 'Each question needs at least two choices.');
        }
        $answer = (int) ($question['answer_index'] ?? 0);
        if ($answer < 0 || $answer >= count($options)) {
            throw new \Mirage\AppException(400, 'Mark a correct choice for every question.');
        }
        $prompt = trim((string) ($question['prompt'] ?? ''));
        if (_text_length($prompt) < 2) {
            throw new \Mirage\AppException(400, 'Write the question.');
        }
        $cleaned[] = [
            'prompt' => $prompt,
            'options' => $options,
            'answer_index' => $answer,
            'explanation' => trim((string) ($question['explanation'] ?? '')),
        ];
    }
    $score = $passingScore ?: 70;
    $payload = [
        'passing_score' => max(1, min((int) $score, 100)),
        'questions' => $cleaned,
    ];
    $db = \Mirage\db();
    \Mirage\run(
        $db,
        "INSERT INTO library_items (owner_id, kind, title, quiz_json, created_at) VALUES (?, 'quiz', ?, ?, ?)",
        [$user['id'], $title, _json($payload), \Mirage\app_now()],
    );
    $row = \Mirage\one(
        $db,
        'SELECT i.*, u.full_name AS owner_name FROM library_items i JOIN users u ON u.id = i.owner_id WHERE i.id = ?',
        [\Mirage\insert_id($db)],
    );
    return _public($row);
}

function delete_item(array $user, int $itemId): array
{
    $db = \Mirage\db();
    $item = \Mirage\one($db, 'SELECT * FROM library_items WHERE id = ?', [$itemId]);
    if (!$item) {
        throw new \Mirage\AppException(404, 'That material is not in the library.');
    }
    if ($user['role'] !== 'admin' && (int) $item['owner_id'] !== (int) $user['id']) {
        throw new \Mirage\AppException(403, 'You can remove materials you uploaded.');
    }
    if ($item['deleted_at'] !== null && $item['deleted_at'] !== '') {
        return ['deleted' => true];
    }
    \Mirage\run($db, 'UPDATE library_items SET deleted_at = ? WHERE id = ?', [\Mirage\app_now(), $itemId]);
    return ['deleted' => true];
}

function file_for(array $user, int $itemId): array
{
    if (!$user) {
        throw new \Mirage\AppException(403, 'Sign in to open this file.');
    }
    $db = \Mirage\db();
    $item = \Mirage\one($db, 'SELECT * FROM library_items WHERE id = ?', [$itemId]);
    if (!$item || empty($item['stored_name'])) {
        throw new \Mirage\AppException(404, 'File not found.');
    }
    if (!in_array($user['role'], ['admin', 'instructor'], true)) {
        $enrolled = \Mirage\one(
            $db,
            <<<'SQL'
                SELECT e.id FROM enrollments e
                JOIN chapters c ON c.course_id = e.course_id
                JOIN lessons l ON l.chapter_id = c.id
                WHERE e.user_id = ? AND l.library_item_id = ?
                SQL,
            [$user['id'], $itemId],
        );
        if (!$enrolled && (int) $item['owner_id'] !== (int) $user['id']) {
            throw new \Mirage\AppException(403, 'This file is not in a class assigned to you.');
        }
    }
    $uploads = \Mirage\uploads_dir();
    $path = realpath($uploads . DIRECTORY_SEPARATOR . $item['stored_name']);
    $uploadsReal = realpath($uploads);
    if ($path === false || $uploadsReal === false || !is_file($path) || !_paths_equal(dirname($path), $uploadsReal)) {
        throw new \Mirage\AppException(404, 'File not found.');
    }
    $downloadName = ($item['original_name'] ?? '') !== '' && $item['original_name'] !== null
        ? (string) $item['original_name']
        : basename($path);
    $mime = ($item['mime'] ?? '') !== '' && $item['mime'] !== null
        ? (string) $item['mime']
        : 'application/octet-stream';
    return [$path, $downloadName, $mime];
}

function place_in_course(array $user, int $itemId, int $chapterId): array
{
    _require_teacher($user);
    $db = \Mirage\db();
    $item = \Mirage\one($db, 'SELECT * FROM library_items WHERE id = ?', [$itemId]);
    if (!$item) {
        throw new \Mirage\AppException(404, 'That material is not in the library.');
    }
    $chapter = \Mirage\one($db, 'SELECT * FROM chapters WHERE id = ?', [$chapterId]);
    if (!$chapter) {
        throw new \Mirage\AppException(404, 'Chapter not found.');
    }
    $course = \Mirage\Store\course_row($db, (int) $chapter['course_id']);
    if (!\Mirage\Store\can_edit_course($user, $course)) {
        throw new \Mirage\AppException(403, 'You cannot edit this course.');
    }
    $position = \Mirage\one(
        $db,
        'SELECT COALESCE(MAX(position), 0) + 1 AS n FROM lessons WHERE chapter_id = ?',
        [$chapter['id']],
    )['n'];
    $quizId = null;
    $content = '';
    $videoUrl = '';
    $kind = $item['kind'];
    if ($kind === 'video') {
        $videoUrl = '/api/library/' . $item['id'] . '/file';
        $content = _json([['type' => 'video', 'url' => $videoUrl]]);
    } elseif ($kind === 'document') {
        $original = $item['original_name'] ?? '';
        $content = _json([[
            'type' => 'document',
            'url' => '/api/library/' . $item['id'] . '/file',
            'name' => ($original !== '' && $original !== null) ? $original : $item['title'],
            'mime' => $item['mime'],
        ]]);
    } elseif ($kind === 'quiz') {
        $rawQuiz = $item['quiz_json'] ?? '';
        if ($rawQuiz === null || $rawQuiz === '') {
            $rawQuiz = '{}';
        }
        try {
            $spec = json_decode((string) $rawQuiz, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \Mirage\AppException(400, $e->getMessage());
        }
        if (!is_array($spec)) {
            $spec = [];
        }
        $passing = $spec['passing_score'] ?? 0;
        if (!$passing) {
            $passing = 70;
        }
        \Mirage\run(
            $db,
            'INSERT INTO quizzes (course_id, title, passing_score) VALUES (?, ?, ?)',
            [$course['id'], $item['title'], (int) $passing],
        );
        $quizId = \Mirage\insert_id($db);
        $index = 1;
        foreach (($spec['questions'] ?? []) as $question) {
            \Mirage\run(
                $db,
                <<<'SQL'
                    INSERT INTO questions (quiz_id, prompt, options_json, answer_index, explanation, position)
                    VALUES (?, ?, ?, ?, ?, ?)
                    SQL,
                [
                    $quizId,
                    $question['prompt'],
                    _json($question['options']),
                    (int) $question['answer_index'],
                    ($question['explanation'] ?? '') ?: '',
                    $index,
                ],
            );
            $index++;
        }
    } else {
        throw new \Mirage\AppException(400, 'This library item cannot be placed in a course.');
    }
    \Mirage\run(
        $db,
        <<<'SQL'
            INSERT INTO lessons
              (chapter_id, title, kind, body, video_url, minutes, position, quiz_id, content_json, library_item_id)
            VALUES (?, ?, ?, '', ?, 8, ?, ?, ?, ?)
            SQL,
        [$chapter['id'], $item['title'], $kind, $videoUrl, $position, $quizId, $content, $item['id']],
    );
    $lesson = \Mirage\one($db, 'SELECT id, title, kind FROM lessons WHERE id = ?', [\Mirage\insert_id($db)]);
    return [
        'lesson' => $lesson,
        'course' => [
            'id' => $course['id'],
            'title' => $course['title'],
            'slug' => $course['slug'],
        ],
    ];
}
