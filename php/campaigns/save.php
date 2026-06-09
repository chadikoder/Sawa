<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_auth();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$userId = Auth::id();

$exists = db()->prepare('SELECT id FROM saved_campaigns WHERE user_id = ? AND campaign_id = ?');
$exists->execute([$userId, $campaignId]);
if ($exists->fetch()) {
    db()->prepare('DELETE FROM saved_campaigns WHERE user_id = ? AND campaign_id = ?')
        ->execute([$userId, $campaignId]);
    json_response(['saved' => false]);
}

db()->prepare('INSERT INTO saved_campaigns (user_id, campaign_id) VALUES (?, ?)')
    ->execute([$userId, $campaignId]);
json_response(['saved' => true]);
