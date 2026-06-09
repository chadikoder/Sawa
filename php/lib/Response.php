<?php
declare(strict_types=1);

final class Response
{
    public static function redirect(string $path, array $query = []): never
    {
        if (!str_starts_with($path, 'http')) {
            $base = APP_URL;
            if (str_starts_with($path, '/')) {
                $url = $base . $path;
            } elseif (str_starts_with($path, 'pages/') || str_starts_with($path, '../')) {
                $url = $base . '/' . ltrim(str_replace('../', '', $path), '/');
            } else {
                $url = $base . '/' . ltrim($path, '/');
            }
        } else {
            $url = $path;
        }

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        header('Location: ' . $url, true, 302);
        exit;
    }

    public static function redirectStatus(string $path, string $status, array $extra = []): never
    {
        self::redirect($path, array_merge(['status' => $status], $extra));
    }

    public static function abort(int $code, ?string $page = null): never
    {
        http_response_code($code);
        $map = [
            403 => 'pages/403.html',
            404 => 'pages/404.html',
            500 => 'pages/500.html',
        ];
        $file = $page ?? ($map[$code] ?? null);
        if ($file !== null) {
            $full = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            if (is_readable($full)) {
                readfile($full);
                exit;
            }
        }
        echo (string) $code;
        exit;
    }
}
