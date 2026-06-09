<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * PDO singleton for MySQL.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function json_error(string $code, int $status = 400, array $extra = []): never
{
    json_response(array_merge(['error' => $code], $extra), $status);
}

function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        json_error('method_not_allowed', 405);
    }
}

/** @return array<string, mixed> */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** @param array<int, string> $fields */
function require_fields(array $source, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($source[$field]) || (is_string($source[$field]) && trim($source[$field]) === '')) {
            json_error('missing_field', 422, ['field' => $field]);
        }
    }
}

/** @return array{page: int, per_page: int, offset: int} */
function pagination_params(int $defaultPerPage = 20, int $maxPerPage = 100): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min($maxPerPage, max(1, (int) ($_GET['per_page'] ?? $defaultPerPage)));
    return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return is_string($ip) && strlen($ip) <= 45 ? $ip : '0.0.0.0';
}

function gen_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function send_mail(string $to, string $subject, string $body): bool
{
    $headers = [
        'From: ' . MAIL_FROM,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}
