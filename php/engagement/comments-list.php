<?php
declare(strict_types=1);

/**
 * Comments for one campaign, as JSON.
 *
 * The campaign modal is populated client-side from the card's data attributes,
 * and comments could not travel that way — a busy campaign would put every
 * comment into an HTML attribute on every card in the grid. So the modal
 * fetches them here when it opens.
 *
 * Public on purpose: campaign pages are readable by guests, and the comment
 * feed is part of that page. Only fields meant to be shown are selected —
 * never the commenter's email or id.
 */

require_once __DIR__ . '/../bootstrap.php';

$campaignId = (int) ($_GET['campaign_id'] ?? 0);
if ($campaignId < 1) {
    json_error('invalid_campaign', 422);
}

$stmt = db()->prepare(
    'SELECT c.id, c.body, c.created_at, u.full_name AS author,
            p.avatar_path AS author_avatar
       FROM comments c
       INNER JOIN users u ON u.id = c.user_id
       LEFT  JOIN user_profiles p ON p.user_id = u.id
      WHERE c.campaign_id = ? AND c.deleted_at IS NULL
      ORDER BY c.created_at DESC
      LIMIT 100'
);
$stmt->execute([$campaignId]);

$comments = [];
foreach ($stmt->fetchAll() as $row) {
    $comments[] = [
        'id'      => (int) $row['id'],
        'body'    => (string) $row['body'],
        'author'  => (string) $row['author'],
        'avatar'  => !empty($row['author_avatar'])
            ? Upload::publicUrl((string) $row['author_avatar'])
            : null,
        // Pre-formatted so the client does not have to parse a SQL datetime,
        // and so "2h" style relative times match the rest of the app.
        'ago'     => Format::timeAgo((string) $row['created_at']),
        'created' => (string) $row['created_at'],
    ];
}

json_response(['ok' => true, 'count' => count($comments), 'comments' => $comments]);
