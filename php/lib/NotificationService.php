<?php
declare(strict_types=1);

final class NotificationService
{
    public static function send(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        db()->prepare(
            'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)'
        )->execute([$userId, $type, $title, $body, $link]);
    }

    /** @return list<array<string, mixed>> */
    public static function listForUser(int $userId, int $limit = 20): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Mark a single notification read.
     *
     * Scoped by user_id as well as id on purpose: without it any signed-in
     * user could clear someone else's notification by posting its id.
     */
    public static function markRead(int $notificationId, int $userId): void
    {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
            ->execute([$notificationId, $userId]);
    }

    /**
     * The full list grouped into Today / This week / Earlier.
     *
     * Grouping is done here rather than in the view so the buckets are decided
     * once, against the server's clock — a client-side split would disagree
     * with created_at across timezones.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function groupedForUser(int $userId, int $limit = 100): array
    {
        $rows = self::listForUser($userId, $limit);
        $today = new DateTimeImmutable('today');
        $weekAgo = $today->modify('-7 days');

        $buckets = ['Today' => [], 'This week' => [], 'Earlier' => []];
        foreach ($rows as $row) {
            $when = new DateTimeImmutable((string) $row['created_at']);
            if ($when >= $today) {
                $buckets['Today'][] = $row;
            } elseif ($when >= $weekAgo) {
                $buckets['This week'][] = $row;
            } else {
                $buckets['Earlier'][] = $row;
            }
        }
        return array_filter($buckets, static fn (array $b): bool => $b !== []);
    }

    public static function markAllRead(int $userId): void
    {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
            ->execute([$userId]);
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
