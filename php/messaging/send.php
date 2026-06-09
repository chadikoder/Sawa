<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}

Csrf::validate();
require_auth();

$threadId = (int) ($_POST['thread_id'] ?? 0);
$message = (string) ($_POST['message'] ?? '');

try {
    MessageService::send($threadId, Auth::id(), $message);
} catch (Throwable) {
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'messages']);
}

Response::redirect('pages/userhome.php', ['section' => 'messages', 'thread' => $threadId]);
