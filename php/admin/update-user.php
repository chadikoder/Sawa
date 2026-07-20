<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_role('admin');

$userId = (int) ($_POST['user_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

if ($userId <= 0 || !in_array($action, ['activate', 'suspend'], true)) {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'people']);
}

if ($userId === Auth::id() && $action === 'suspend') {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'people']);
}

$stmt = db()->prepare('SELECT id, active FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'people']);
}

$active = $action === 'activate' ? 1 : 0;
db()->prepare('UPDATE users SET active = ? WHERE id = ?')->execute([$active, $userId]);
AuditLog::write(Auth::id() ?? 0, 'user_' . $action, 'user', $userId);

Response::redirectStatus('pages/admin.php', 'success', ['view' => 'people']);
