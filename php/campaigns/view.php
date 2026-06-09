<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$camp = CampaignService::find($id);
if (!$camp || $camp['status'] !== 'active') {
    json_error('not_found', 404);
}

$images = db()->prepare('SELECT image_path FROM campaign_images WHERE campaign_id = ? ORDER BY sort_order');
$images->execute([$id]);
$donors = DonationService::forCampaign($id);
$comments = db()->prepare(
    'SELECT c.*, u.full_name FROM comments c INNER JOIN users u ON u.id = c.user_id
     WHERE c.campaign_id = ? AND c.deleted_at IS NULL ORDER BY c.created_at DESC LIMIT 50'
);
$comments->execute([$id]);

json_response([
    'campaign' => $camp,
    'images'   => $images->fetchAll(),
    'donors'   => $donors,
    'comments' => $comments->fetchAll(),
]);
