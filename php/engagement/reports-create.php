<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_auth();

$type = (string) ($_POST['target_type'] ?? 'campaign');
$targetId = (int) ($_POST['target_id'] ?? 0);
$reason = Validator::sanitizeString((string) ($_POST['reason'] ?? 'abuse'), 80);
$details = Validator::sanitizeString((string) ($_POST['details'] ?? ''), 2000);

if (!in_array($type, ['campaign', 'comment', 'organisation', 'user'], true) || $targetId < 1) {
    json_error('invalid_target', 422);
}

db()->prepare(
    'INSERT INTO reports (reporter_id, target_type, target_id, reason, details) VALUES (?, ?, ?, ?, ?)'
)->execute([Auth::id(), $type, $targetId, $reason, $details ?: null]);

json_response(['ok' => true]);
