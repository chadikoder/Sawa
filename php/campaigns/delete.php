<?php
declare(strict_types=1);

/**
 * Let a creator remove their own campaign.
 *
 * Until now only admins could delete anything (php/admin/delete-campaign.php),
 * so a user who published a campaign by mistake had no way to take it down.
 *
 * The same two-mode rule as the admin path, and for the same reason: a
 * campaign with donations against it cannot be hard-deleted, because those
 * donation rows are financial records that reference it. Those are withdrawn
 * from public view instead. A campaign nobody has given to is deleted outright.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_auth();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
if ($campaignId <= 0) {
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'campaigns']);
}

$campaign = CampaignService::find($campaignId);
if (!$campaign) {
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'campaigns']);
}

// Ownership is decided by CampaignService::canManage, which already handles
// both shapes a campaign can have — owned directly by a user, or owned by an
// organisation the user represents. Without this any signed-in user could
// delete any campaign by posting an id.
$userId = (int) Auth::id();
$role = (string) (Auth::role() ?? '');
if (!CampaignService::canManage($userId, $role, $campaign)) {
    Response::abort(403);
}

$pdo = db();
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM donations WHERE campaign_id = ?');
$countStmt->execute([$campaignId]);
$donationCount = (int) $countStmt->fetchColumn();

$pdo->beginTransaction();
try {
    if ($donationCount === 0) {
        $pdo->prepare('DELETE FROM reports WHERE target_type = \'campaign\' AND target_id = ?')
            ->execute([$campaignId]);
        $pdo->prepare('DELETE FROM campaigns WHERE id = ?')->execute([$campaignId]);
        $outcome = 'campaign_deleted';
    } else {
        $pdo->prepare(
            'UPDATE campaigns
                SET status = \'rejected\',
                    rejection_reason = ?
              WHERE id = ?'
        )->execute(['Withdrawn by the creator. Donation records are preserved.', $campaignId]);
        $outcome = 'campaign_withdrawn';
    }
    $pdo->commit();
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'campaigns']);
}

Response::redirectStatus('pages/userhome.php', $outcome, ['section' => 'campaigns']);
