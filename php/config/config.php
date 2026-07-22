<?php
declare(strict_types=1);

/**
 * App-wide configuration. Loads from environment / .env at repo root.
 */

function env(string $key, ?string $default = null): ?string
{
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $hostingFile = __DIR__ . DIRECTORY_SEPARATOR . 'hosting.php';
        if (is_readable($hostingFile)) {
            $hostingConfig = require $hostingFile;
            if (is_array($hostingConfig)) {
                foreach ($hostingConfig as $name => $value) {
                    $name = (string) $name;
                    if ($name !== '' && getenv($name) === false) {
                        $stringValue = (string) $value;
                        putenv("$name=$stringValue");
                        $_ENV[$name] = $stringValue;
                    }
                }
            }
        }

        $envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (is_readable($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (!str_contains($line, '=')) {
                        continue;
                    }
                    [$name, $value] = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value, " \t\"'");
                    if ($name !== '' && getenv($name) === false) {
                        putenv("$name=$value");
                        $_ENV[$name] = $value;
                    }
                }
            }
        }
    }

    $val = getenv($key);
    if ($val === false) {
        return $default;
    }
    return $val;
}

define('APP_ENV', env('SAWA_APP_ENV', 'development'));
define('APP_URL', rtrim(env('SAWA_APP_URL', 'http://localhost:8000'), '/'));

/**
 * URL prefix the app is served under, with no trailing slash ('' at a domain
 * root, '/sawa' when the folder is dropped into XAMPP's htdocs).
 *
 * Detected from the running script rather than read from .env: the project is
 * handed over as a folder and unzipped into whatever directory the marker
 * picks, so the bundled .env cannot know the prefix. SCRIPT_NAME is the URL
 * path of the file PHP is executing and SCRIPT_FILENAME is its location on
 * disk; the part of SCRIPT_NAME left after removing the file's path relative
 * to the project root is the prefix. Falls back to the path in SAWA_APP_URL
 * (and then to '') when there is no request, e.g. on the CLI.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $root = realpath(dirname(__DIR__, 2));
    $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    $name = $_SERVER['SCRIPT_NAME'] ?? '';

    if ($root !== false && $script !== false && $name !== '' && str_starts_with($script, $root . DIRECTORY_SEPARATOR)) {
        // e.g. 'php/auth/login.php' — the tail SCRIPT_NAME must end with.
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($script, strlen($root) + 1));
        if (str_ends_with($name, '/' . $relative)) {
            $base = rtrim(substr($name, 0, -strlen('/' . $relative)), '/');
            return $base;
        }
    }

    $fromUrl = parse_url(APP_URL, PHP_URL_PATH);
    $base = is_string($fromUrl) ? rtrim($fromUrl, '/') : '';
    return $base;
}

define('BASE_PATH', base_path());

/** Absolute-from-host URL for an app-relative path: url('css/admin.css'). */
function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/**
 * URL for a static asset, with a cache-busting version stamp taken from the
 * file's own modification time: css/userhome.css?v=1753207412
 *
 * Cache-Control alone was not enough. A browser that had already cached these
 * files before that header existed kept serving its stale copy under heuristic
 * freshness, so edits appeared not to apply — repeatedly, for hours, across
 * CSS and JS both. A URL that changes whenever the file changes cannot be
 * served from cache for the wrong version, and it costs nothing: the stamp
 * only moves when the file does, so unchanged assets still cache normally.
 *
 * Falls back to a plain url() when the file is missing rather than failing.
 */
function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $stamp = is_file($file) ? filemtime($file) : false;
    return url($relative) . ($stamp !== false ? '?v=' . $stamp : '');
}

/**
 * Fully-qualified URL (scheme + host + BASE_PATH + path), for email and
 * anywhere else a host-relative path would be meaningless.
 *
 * Built from the live request for the same reason BASE_PATH is detected
 * rather than configured: the project is handed over as a folder and unzipped
 * into an unknown directory, so a bundled .env cannot know the real address.
 * SAWA_APP_URL is usually stale on someone else's machine, which is what made
 * emailed links and uploaded-image URLs point at the author's old setup.
 *
 * In production the Host header is deliberately NOT trusted — it is
 * attacker-controlled, and a forged one would rewrite a password-reset link to
 * point at the attacker's server. There APP_URL is authoritative, matching how
 * php/config/db.php disables its convenience fallbacks in production.
 */
function absolute_url(string $path = ''): string
{
    if (APP_ENV !== 'production') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        // Reject anything that is not a bare host[:port] before echoing it.
        if ($host !== '' && preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
            $secure = (($_SERVER['HTTPS'] ?? 'off') !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') === '443');
            return ($secure ? 'https' : 'http') . '://' . $host . url($path);
        }
    }

    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

define('DB_HOST', env('SAWA_DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env('SAWA_DB_PORT', '3306'));
define('DB_NAME', env('SAWA_DB_NAME', 'sawa'));
define('DB_USER', env('SAWA_DB_USER', 'root'));
define('DB_PASS', env('SAWA_DB_PASS', ''));

define('MAIL_FROM', env('SAWA_MAIL_FROM', 'noreply@sawa-together.com'));
define('SESSION_SECRET', env('SAWA_SESSION_SECRET', 'dev-only-change-in-production-use-64-plus-random-bytes'));

define('UPLOAD_ROOT', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);
define('MAX_CAMPAIGN_IMAGES', 6);

define('FEE_RATE_GUEST', 0.10);
define('FEE_RATE_MEMBER_WALLET', 0.05);
define('FEE_RATE_DIRECT', 0.10);
define('CASHOUT_FEE_RATE', 0.05);

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

define('CSRF_TOKEN_KEY', '_csrf_token');
