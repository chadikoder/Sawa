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

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // Keep a failed candidate from stalling the page while we try the next.
        PDO::ATTR_TIMEOUT            => 2,
    ];

    $isProd = defined('APP_ENV') && APP_ENV === 'production';

    // Whatever is configured always wins.
    $candidates = [[DB_HOST, (int) DB_PORT, DB_USER, DB_PASS, DB_NAME]];

    // A stale .env can also name a database that does not exist on this
    // machine, so 'sawa' — the name the README and the SQL dump use — is
    // retried alongside the configured one.
    $names = DB_NAME === 'sawa' ? ['sawa'] : [DB_NAME, 'sawa'];

    // Outside production, fall back to the usual local setups. The project is
    // handed over as a folder and opened on someone else's machine, where the
    // bundled .env may name a port that only existed on the author's box, and
    // a default XAMPP install has root with no password on 3306 rather than a
    // dedicated 'sawa' user. Trying the common combinations means the marker
    // only has to create the database and import the SQL.
    if (!$isProd) {
        foreach ($names as $name) {
            foreach ([3306, 3307, 3305, 3310] as $port) {
                $candidates[] = ['127.0.0.1', $port, 'root', '', $name];
                $candidates[] = ['127.0.0.1', $port, 'sawa', 'sawa', $name];
                $candidates[] = ['127.0.0.1', $port, 'sawa', '', $name];
            }
        }
    }

    $seen = [];
    $lastError = null;
    foreach ($candidates as [$host, $port, $user, $pass, $name]) {
        $key = "$host|$port|$user|$pass|$name";
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            if ($host !== DB_HOST || $port !== (int) DB_PORT || $user !== DB_USER || $name !== DB_NAME) {
                error_log("[Sawa] DB: configured connection failed; using fallback {$user}@{$host}:{$port}/{$name}");
            }
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e;
        }
    }

    $message = $lastError instanceof PDOException ? $lastError->getMessage() : 'unknown error';
    error_log('[Sawa] DB connection failed: ' . $message);
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');

    if ($isProd) {
        $detail = '<p>Please try again shortly.</p>';
    } else {
        $detail =
            '<p>The app could not reach a MySQL server holding a <code>' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8')
            . '</code> database.</p>'
            . '<h2>To run this project</h2>'
            . '<ol>'
            . '<li>Start MySQL (in XAMPP, press <b>Start</b> next to MySQL).</li>'
            . '<li>Create the database:<br><code>CREATE DATABASE sawa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code></li>'
            . '<li>Import <code>database_complete.sql</code> into it — in phpMyAdmin: select <b>sawa</b> → <b>Import</b> → choose the file → <b>Go</b>.</li>'
            . '<li>Reload this page.</li>'
            . '</ol>'
            . '<p>Ports 3306, 3307, 3305 and 3310 were each tried with the usual '
            . '<code>root</code> (no password) and <code>sawa</code> logins. If your MySQL uses a '
            . 'different port or password, put it in <code>.env</code> (see <code>.env.example</code>).</p>'
            . '<p class="err">Last error: ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    echo '<!doctype html><meta charset="utf-8"><title>Database not connected — Sawa</title>'
        . '<style>body{font-family:system-ui,sans-serif;max-width:680px;margin:8vh auto;padding:0 1.2rem;color:#222;line-height:1.55}'
        . 'h1{font-size:1.5rem}h2{font-size:1.1rem;margin-top:1.6rem}code{background:#f2f2f2;padding:.1rem .35rem;border-radius:3px}'
        . 'li{margin-bottom:.5rem}.err{color:#777;font-size:.85rem;margin-top:1.6rem}</style>'
        . '<h1>Database not connected</h1>' . $detail;
    exit;
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
