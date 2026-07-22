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

// Straight back to the campaign that was commented on, opened on the comments
// tab, so the new comment is visible where it was written. This is the
// opposite of the payment flow on purpose: a payment returns to the dashboard
// because reopening the campaign there buried the payment status modal, but a
// comment belongs to the campaign and losing your place is the wrong outcome.
Response::redirect('pages/userhome.php', [
    'status'   => 'comment_posted',
    'campaign' => $campaignId,
    'tab'      => 'comments',
]);
