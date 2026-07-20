<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}

Csrf::validate();
require_auth();

$wantsJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

$type      = (string) ($_POST['type'] ?? 'campaign');
$targetId  = (int) ($_POST['target_id'] ?? 0);
$reasonRaw = trim((string) ($_POST['reason'] ?? ''));
$message   = Validator::sanitizeString((string) ($_POST['message'] ?? ''), 500);

$targetType = match ($type) {
    'campaign' => 'campaign',
    'user'     => 'user',
    default    => null,
};

$reason = mb_substr($reasonRaw, 0, 80);

if ($targetType === null || $targetId < 1 || $reason === '') {
    if ($wantsJson) {
        json_error('invalid_report', 422);
    }
    Response::redirectStatus('pages/userhome.php', 'report_invalid');
}

if ($targetType === 'campaign') {
    $exists = db()->prepare('SELECT id FROM campaigns WHERE id = ? LIMIT 1');
} else {
    $exists = db()->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
}
$exists->execute([$targetId]);
if (!$exists->fetchColumn()) {
    if ($wantsJson) {
        json_error('target_not_found', 404);
    }
    Response::redirectStatus('pages/userhome.php', 'report_invalid');
}

$dup = db()->prepare(
    'SELECT id FROM reports
     WHERE reporter_id = ? AND target_type = ? AND target_id = ? AND status = "open"
     LIMIT 1'
);
$dup->execute([Auth::id(), $targetType, $targetId]);
if ($dup->fetchColumn()) {
    if ($wantsJson) {
        json_response(['ok' => true, 'duplicate' => true]);
    }
    Response::redirectStatus('pages/userhome.php', 'report_submitted');
}

$details = $message === '' ? null : $message;

db()->prepare(
    'INSERT INTO reports (reporter_id, target_type, target_id, reason, details, status)
     VALUES (?, ?, ?, ?, ?, "open")'
)->execute([Auth::id(), $targetType, $targetId, $reason, $details]);

$reportId = (int) db()->lastInsertId();

if ($wantsJson) {
    json_response(['ok' => true, 'report_id' => $reportId]);
}

Response::redirectStatus('pages/userhome.php', 'report_submitted');
