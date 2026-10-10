<?php

namespace Mirage;

class AppException extends \RuntimeException
{
    public function __construct(public int $status, string $message)
    {
        parent::__construct($message);
    }
}

class FileDownload
{
    public function __construct(public string $path, public string $name, public string $mime)
    {
    }
}

function project_root(): string
{
    return dirname(__DIR__, 2);
}

function db_path(): string
{
    $fromEnv = getenv('MIRAGE_DB');
    if ($fromEnv) {
        return $fromEnv;
    }
    return project_root() . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'mirage.db';
}

function uploads_dir(): string
{
    $fromEnv = getenv('MIRAGE_UPLOADS');
    if ($fromEnv) {
        return $fromEnv;
    }
    return project_root() . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'uploads';
}

function secret(): string
{
    $value = getenv('MIRAGE_SECRET');
    return $value !== false && $value !== '' ? $value : 'mirage-academy-dev-secret';
}

function db(): \PDO
{
    static $pdo = null;
    if ($pdo instanceof \PDO) {
        return $pdo;
    }
    $path = db_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $uploads = uploads_dir();
    if (!is_dir($uploads)) {
        mkdir($uploads, 0777, true);
    }
    $pdo = new \PDO('sqlite:' . $path, null, null, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function one(\PDO $db, string $sql, array $args = []): ?array
{
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($args));
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function many(\PDO $db, string $sql, array $args = []): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($args));
    return $stmt->fetchAll();
}

function run(\PDO $db, string $sql, array $args = []): void
{
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($args));
}

function insert_id(\PDO $db): int
{
    return (int) $db->lastInsertId();
}

function script(\PDO $db, string $sql): void
{
    $db->exec($sql);
}

function app_now(): string
{
    return gmdate('Y-m-d\TH:i:s+00:00');
}

function hash_password(string $password, ?string $salt = null): string
{
    $salt = $salt ?? bin2hex(random_bytes(16));
    $digest = hash_pbkdf2('sha256', $password, $salt, 120000, 64, false);
    return $salt . '$' . $digest;
}

function verify_password(string $password, string $stored): bool
{
    $salt = explode('$', $stored, 2)[0];
    return hash_equals(hash_password($password, $salt), $stored);
}

function make_token(int $userId): string
{
    $json = json_encode(['sub' => $userId, 'exp' => time() + 7 * 86400], JSON_UNESCAPED_SLASHES);
    $payload = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $payload, secret());
    return $payload . '.' . $sig;
}

function read_token(string $token): ?int
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }
    [$payload, $sig] = $parts;
    $expected = hash_hmac('sha256', $payload, secret());
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    $pad = str_repeat('=', (4 - strlen($payload) % 4) % 4);
    $json = base64_decode(strtr($payload . $pad, '-_', '+/'), true);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['exp'], $data['sub'])) {
        return null;
    }
    if ((int) $data['exp'] < time()) {
        return null;
    }
    return (int) $data['sub'];
}

function slugify(string $title): string
{
    $keep = '';
    $length = strlen($title);
    for ($i = 0; $i < $length; $i++) {
        $ch = strtolower($title[$i]);
        if (ctype_alnum($ch)) {
            $keep .= $ch;
        } elseif ($ch === ' ' || $ch === '-' || $ch === '_') {
            $keep .= '-';
        }
    }
    $slug = trim($keep, '-');
    while (str_contains($slug, '--')) {
        $slug = str_replace('--', '-', $slug);
    }
    return $slug !== '' ? $slug : 'course';
}

function frappe_role(string $role): string
{
    return [
        'admin' => 'Administrator',
        'moderator' => 'Moderator',
        'instructor' => 'Instructor',
        'student' => 'Learner',
    ][$role] ?? $role;
}

function public_user(?array $row): ?array
{
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'email' => $row['email'],
        'username' => $row['username'] ?? '',
        'full_name' => $row['full_name'],
        'role' => $row['role'],
        'frappe_role' => frappe_role($row['role']),
        'headline' => $row['headline'] ?? '',
        'bio' => $row['bio'] ?? '',
        'looking_for_job' => (bool) ($row['looking_for_job'] ?? false),
        'created_at' => $row['created_at'],
    ];
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function authorization_header(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    return is_string($header) ? $header : '';
}

function bearer_token(): string
{
    $header = authorization_header();
    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }
    return '';
}

function send_json(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function send_error(int $status, string $detail): void
{
    send_json(['detail' => $detail], $status);
}
