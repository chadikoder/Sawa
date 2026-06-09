<?php
declare(strict_types=1);

final class AuditLog
{
    /** @param array<string, mixed> $details */
    public static function write(
        int $adminId,
        string $action,
        string $targetType,
        ?int $targetId = null,
        array $details = []
    ): void {
        $stmt = db()->prepare(
            'INSERT INTO audit_log (admin_id, action, target_type, target_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $adminId,
            $action,
            $targetType,
            $targetId,
            json_encode($details, JSON_THROW_ON_ERROR),
            client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }
}
