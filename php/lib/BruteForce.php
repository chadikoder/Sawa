<?php
declare(strict_types=1);

final class BruteForce
{
    public static function isLocked(string $email): bool
    {
        $since = (new DateTimeImmutable('-' . LOGIN_LOCKOUT_MINUTES . ' minutes'))->format('Y-m-d H:i:s');
        $stmt = db()->prepare(
            'SELECT COUNT(*) AS c FROM login_attempts
             WHERE email = ? AND success = 0 AND attempted_at >= ?'
        );
        $stmt->execute([strtolower($email), $since]);
        $row = $stmt->fetch();
        return $row && (int) $row['c'] >= LOGIN_MAX_ATTEMPTS;
    }

    public static function record(string $email, bool $success): void
    {
        $stmt = db()->prepare(
            'INSERT INTO login_attempts (email, ip_address, success, user_agent)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            strtolower($email),
            client_ip(),
            $success ? 1 : 0,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }
}
