<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::redirect('pages/admin.php');
}

Csrf::validate();
require_role('admin');

$orgId = (int) ($_POST['organisation_id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'approve');
$reason = Validator::sanitizeString((string) ($_POST['reason'] ?? ''), 1000);

$stmt = db()->prepare(
    'SELECT o.*, u.email FROM organisations o INNER JOIN users u ON u.id = o.user_id WHERE o.id = ?'
);
$stmt->execute([$orgId]);
$org = $stmt->fetch();
if (!$org) {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'organisations']);
}

if ($action === 'approve') {
    db()->prepare(
        'UPDATE organisations SET verified = 1, verified_at = NOW(), verified_by = ?, rejected = 0, rejection_reason = NULL WHERE id = ?'
    )->execute([Auth::id(), $orgId]);
    if ($org['email']) {
        Mailer::sendOrgDecision($org['email'], true);
    }
    AuditLog::write(Auth::id(), 'verify_organisation', 'organisation', $orgId);
    NotificationService::send((int) $org['user_id'], 'org_verified', 'Organisation approved', 'You can now publish campaigns.');
} else {
    db()->prepare(
        'UPDATE organisations SET verified = 0, rejected = 1, rejection_reason = ? WHERE id = ?'
    )->execute([$reason ?: 'Application not approved.', $orgId]);
    if ($org['email']) {
        Mailer::sendOrgDecision($org['email'], false, $reason);
    }
    AuditLog::write(Auth::id(), 'reject_organisation', 'organisation', $orgId, ['reason' => $reason]);
}

Response::redirectStatus('pages/admin.php', 'success', ['view' => 'organisations']);
