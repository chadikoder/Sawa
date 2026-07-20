<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_role('admin');

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$status = (string) ($_POST['action'] ?? '');
$validStatuses = ['active', 'paused', 'completed', 'rejected'];

if ($campaignId <= 0 || !in_array($status, $validStatuses, true)) {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'campaigns']);
}

$campaign = CampaignService::find($campaignId);
if (!$campaign) {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'campaigns']);
}

$reason = Validator::sanitizeString((string) ($_POST['reason'] ?? ''), 500);
if ($status === 'rejected') {
    db()->prepare('UPDATE campaigns SET status = ?, rejection_reason = ? WHERE id = ?')
        ->execute([$status, $reason ?: 'Rejected by admin review.', $campaignId]);
} else {
    db()->prepare('UPDATE campaigns SET status = ?, rejection_reason = NULL WHERE id = ?')
        ->execute([$status, $campaignId]);
}

AuditLog::write(Auth::id() ?? 0, 'campaign_' . $status, 'campaign', $campaignId);
Response::redirectStatus('pages/admin.php', 'success', ['view' => 'campaigns']);
