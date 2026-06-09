<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_auth();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$camp = CampaignService::find($campaignId);
if (!$camp || !CampaignService::canManage(Auth::id(), Auth::role() ?? '', $camp)) {
    json_error('forbidden', 403);
}

$title = Validator::sanitizeString((string) ($_POST['title'] ?? 'Update'), 200);
$body = Validator::sanitizeString((string) ($_POST['body'] ?? ''), 5000);
if ($body === '') {
    json_error('empty_body', 422);
}

db()->prepare(
    'INSERT INTO campaign_updates (campaign_id, posted_by, title, body) VALUES (?, ?, ?, ?)'
)->execute([$campaignId, Auth::id(), $title, $body]);

json_response(['ok' => true]);
