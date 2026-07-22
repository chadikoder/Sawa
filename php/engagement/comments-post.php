<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}

Csrf::validate();
require_auth();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$body = Validator::sanitizeString((string) ($_POST['body'] ?? ''), 500);

if ($campaignId < 1 || mb_strlen($body) < 2) {
    Response::redirectStatus('pages/userhome.php', 'error');
}

$camp = CampaignService::find($campaignId);
if (!$camp || $camp['status'] !== 'active') {
    Response::redirectStatus('pages/userhome.php', 'error');
}

db()->prepare('INSERT INTO comments (campaign_id, user_id, body) VALUES (?, ?, ?)')
    ->execute([$campaignId, Auth::id(), $body]);

// Deliberately no 'campaign' parameter: js/userhome.js opens the campaign
// modal whenever one is present, so returning with it made the whole campaign
// screen spring open again the moment a comment was posted.
Response::redirect('pages/userhome.php', ['status' => 'comment_posted']);
