<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_auth();

$threadId = (int) ($_GET['thread_id'] ?? 0);
$after = (int) ($_GET['after'] ?? 0);

try {
    $messages = MessageService::messagesSince($threadId, Auth::id(), $after);
} catch (Throwable) {
    json_error('forbidden', 403);
}

json_response([
    'me'       => Auth::id(),
    'messages' => $messages,
]);
