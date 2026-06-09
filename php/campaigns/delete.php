<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_auth();

$id = (int) ($_POST['campaign_id'] ?? 0);
$camp = CampaignService::find($id);
if (!$camp || !CampaignService::canManage(Auth::id(), Auth::role() ?? '', $camp)) {
    json_error('forbidden', 403);
}

db()->prepare('UPDATE campaigns SET status = \'paused\' WHERE id = ?')->execute([$id]);

if (Auth::role() === 'admin') {
    AuditLog::write(Auth::id(), 'pause_campaign', 'campaign', $id);
}

Response::redirectStatus('pages/userhome.php', 'success', ['section' => 'campaigns']);
