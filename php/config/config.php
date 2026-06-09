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

define('DB_HOST', env('SAWA_DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env('SAWA_DB_PORT', '3306'));
define('DB_NAME', env('SAWA_DB_NAME', 'sawa'));
define('DB_USER', env('SAWA_DB_USER', 'sawa'));
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
