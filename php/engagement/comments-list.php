<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$id = (int) ($_GET['campaign_id'] ?? 0);
$stmt = db()->prepare(
    'SELECT c.id, c.body, c.created_at, u.full_name
     FROM comments c
     INNER JOIN users u ON u.id = c.user_id
     WHERE c.campaign_id = ? AND c.deleted_at IS NULL
     ORDER BY c.created_at DESC'
);
$stmt->execute([$id]);
json_response(['comments' => $stmt->fetchAll()]);
