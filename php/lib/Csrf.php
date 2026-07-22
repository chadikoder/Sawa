<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get(CSRF_TOKEN_KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::set(CSRF_TOKEN_KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        $t = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $t . '">';
    }

    public static function validate(?string $token = null): void
    {
        $submitted = $token ?? ($_POST['_csrf'] ?? '');
        $expected = Session::get(CSRF_TOKEN_KEY);

        if (!is_string($submitted) || !is_string($expected)) {
            Response::abort(403, 'pages/403.php');
        }

        if (!hash_equals($expected, $submitted)) {
            Response::abort(403, 'pages/403.php');
        }
    }

    /** For forms that may be opened before session token exists */
    public static function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(CSRF_TOKEN_KEY, $token);
        return $token;
    }
}
