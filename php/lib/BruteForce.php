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

    /**
     * Generic per-IP rate limiter for public endpoints that don't have a
     * user-account context yet (password-reset requests, guest signups, etc).
     * Piggy-backs on login_attempts using a synthetic "email" marker so we
     * don't need a schema migration.
     */
    public static function isIpRateLimited(string $key, int $max = 5, int $windowMinutes = 15): bool
    {
        $since = (new DateTimeImmutable('-' . $windowMinutes . ' minutes'))->format('Y-m-d H:i:s');
        $marker = '__rate:' . $key;
        try {
            $stmt = db()->prepare(
                'SELECT COUNT(*) AS c FROM login_attempts
                 WHERE email = ? AND ip_address = ? AND attempted_at >= ?'
            );
            $stmt->execute([$marker, client_ip(), $since]);
            $row = $stmt->fetch();
        } catch (Throwable) {
            // If the DB is unreachable, fail open — the endpoint will still
            // enforce its own validation. Better than a hard 500 for a legit
            // user hitting a transient DB blip.
            return false;
        }
        return $row && (int) $row['c'] >= $max;
    }

    public static function recordIpHit(string $key): void
    {
        $marker = '__rate:' . $key;
        try {
            $stmt = db()->prepare(
                'INSERT INTO login_attempts (email, ip_address, success, user_agent)
                 VALUES (?, ?, 0, ?)'
            );
            $stmt->execute([
                $marker,
                client_ip(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (Throwable) {
            // Same failure mode as isIpRateLimited — don't 500 on tracking.
        }
    }
}
