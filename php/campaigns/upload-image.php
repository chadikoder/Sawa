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

if (empty($_FILES['image']['tmp_name'])) {
    json_error('no_file', 422);
}

$path = Upload::store($_FILES['image'], 'campaigns/' . $campaignId);
$s = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM campaign_images WHERE campaign_id = ?');
$s->execute([$campaignId]);
$sort = (int) $s->fetchColumn();
db()->prepare('INSERT INTO campaign_images (campaign_id, image_path, sort_order) VALUES (?, ?, ?)')
    ->execute([$campaignId, $path, $sort]);

json_response(['path' => Upload::publicUrl($path)]);
