<?php
declare(strict_types=1);

final class MessageService
{
    /** Normalize pair so user_a < user_b for unique thread */
    private static function pair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    public static function findOrCreateThread(int $userId, int $otherUserId): int
    {
        if ($userId === $otherUserId) {
            throw new RuntimeException('invalid_thread');
        }
        [$a, $b] = self::pair($userId, $otherUserId);
        $stmt = db()->prepare(
            'SELECT id FROM message_threads WHERE user_a_id = ? AND user_b_id = ? LIMIT 1'
        );
        $stmt->execute([$a, $b]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }
        db()->prepare(
            'INSERT INTO message_threads (user_a_id, user_b_id) VALUES (?, ?)'
        )->execute([$a, $b]);
        return (int) db()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public static function inbox(int $userId): array
    {
        $sql = 'SELECT t.*, 
                       CASE WHEN t.user_a_id = ? THEN ub.full_name ELSE ua.full_name END AS other_name,
                       CASE WHEN t.user_a_id = ? THEN ub.role ELSE ua.role END AS other_role,
                       CASE WHEN t.user_a_id = ? THEN t.user_b_id ELSE t.user_a_id END AS other_id,
                       (SELECT body FROM messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_body
                FROM message_threads t
                INNER JOIN users ua ON ua.id = t.user_a_id
                INNER JOIN users ub ON ub.id = t.user_b_id
                WHERE t.user_a_id = ? OR t.user_b_id = ?
                ORDER BY COALESCE(t.last_message_at, t.created_at) DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function messages(int $threadId, int $userId): array
    {
        if (!self::isMember($threadId, $userId)) {
            throw new RuntimeException('forbidden');
        }
        $stmt = db()->prepare(
            'SELECT m.*, u.full_name AS sender_name
             FROM messages m
             INNER JOIN users u ON u.id = m.sender_id
             WHERE m.thread_id = ?
             ORDER BY m.created_at ASC'
        );
        $stmt->execute([$threadId]);
        return $stmt->fetchAll();
    }

    public static function isMember(int $threadId, int $userId): bool
    {
        $stmt = db()->prepare(
            'SELECT id FROM message_threads WHERE id = ? AND (user_a_id = ? OR user_b_id = ?)'
        );
        $stmt->execute([$threadId, $userId, $userId]);
        return (bool) $stmt->fetch();
    }

    public static function send(int $threadId, int $senderId, string $body): void
    {
        if (!self::isMember($threadId, $senderId)) {
            throw new RuntimeException('forbidden');
        }
        $body = Validator::sanitizeString($body, 2000);
        if ($body === '') {
            throw new RuntimeException('empty_message');
        }
        db()->prepare(
            'INSERT INTO messages (thread_id, sender_id, body) VALUES (?, ?, ?)'
        )->execute([$threadId, $senderId, $body]);
        db()->prepare(
            'UPDATE message_threads SET last_message_at = NOW() WHERE id = ?'
        )->execute([$threadId]);
    }

    public static function resolveUserId(string $with): ?int
    {
        if (ctype_digit($with)) {
            return (int) $with;
        }
        $stmt = db()->prepare('SELECT user_id FROM organisations WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $with]);
        $row = $stmt->fetch();
        return $row ? (int) $row['user_id'] : null;
    }
}
