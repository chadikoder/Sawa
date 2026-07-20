<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_role('admin');

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
if ($campaignId <= 0) {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'campaigns']);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, title, status FROM campaigns WHERE id = ? LIMIT 1');
$stmt->execute([$campaignId]);
$campaign = $stmt->fetch();
if (!$campaign) {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'campaigns']);
}

$donationCountStmt = $pdo->prepare('SELECT COUNT(*) FROM donations WHERE campaign_id = ?');
$donationCountStmt->execute([$campaignId]);
$donationCount = (int) $donationCountStmt->fetchColumn();

$pdo->beginTransaction();
try {
    if ($donationCount === 0) {
        $pdo->prepare('DELETE FROM reports WHERE target_type = \'campaign\' AND target_id = ?')
            ->execute([$campaignId]);
        $pdo->prepare('DELETE FROM campaigns WHERE id = ?')->execute([$campaignId]);

        AuditLog::write(Auth::id() ?? 0, 'campaign_deleted', 'campaign', $campaignId, [
            'title' => $campaign['title'],
            'mode' => 'hard_delete',
        ]);
    } else {
        $pdo->prepare(
            'UPDATE campaigns
             SET status = \'rejected\', rejection_reason = ?
             WHERE id = ?'
        )->execute(['Removed from public view by admin. Donation records are preserved.', $campaignId]);

        AuditLog::write(Auth::id() ?? 0, 'campaign_removed_public', 'campaign', $campaignId, [
            'title' => $campaign['title'],
            'mode' => 'soft_delete',
            'donations' => $donationCount,
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'campaigns']);
}

Response::redirectStatus('pages/admin.php', 'success', ['view' => 'campaigns']);
