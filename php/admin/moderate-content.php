<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_role('admin');

$reportId = (int) ($_POST['report_id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'dismiss');
$resolution = Validator::sanitizeString((string) ($_POST['resolution'] ?? ''), 1000);

$stmt = db()->prepare('SELECT * FROM reports WHERE id = ?');
$stmt->execute([$reportId]);
$report = $stmt->fetch();
if (!$report) {
    json_error('not_found', 404);
}

$status = $action === 'resolve' ? 'resolved' : 'dismissed';
db()->prepare(
    'UPDATE reports SET status = ?, resolved_by = ?, resolved_at = NOW(), resolution = ? WHERE id = ?'
)->execute([$status, Auth::id(), $resolution ?: null, $reportId]);

if ($action === 'resolve' && $report['target_type'] === 'comment') {
    db()->prepare('UPDATE comments SET deleted_at = NOW(), deleted_by = ? WHERE id = ?')
        ->execute([Auth::id(), (int) $report['target_id']]);
}

AuditLog::write(Auth::id(), 'moderate_' . $status, $report['target_type'], (int) $report['target_id']);

if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
    json_response(['ok' => true]);
}
Response::redirectStatus('pages/admin.php', 'success', ['view' => 'reports']);
