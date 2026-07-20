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

$wantsJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

try {
    $saved = MessageService::send($threadId, Auth::id(), $message);
} catch (Throwable) {
    if ($wantsJson) {
        json_error('send_failed', 422);
    }
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'messages']);
}

if ($wantsJson) {
    json_response([
        'ok' => true,
        'message' => [
            'id'         => $saved['id'],
            'sender_id'  => $saved['sender_id'],
            'body'       => $saved['body'],
            'created_at' => $saved['created_at'],
            'outgoing'   => true,
        ],
    ]);
}

Response::redirect('pages/userhome.php', ['section' => 'messages', 'thread' => $threadId]);
