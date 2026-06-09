<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_auth();

$with = trim((string) ($_GET['with'] ?? ''));
$otherId = MessageService::resolveUserId($with);
if ($otherId === null) {
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'messages']);
}

$threadId = MessageService::findOrCreateThread(Auth::id(), $otherId);
Response::redirect('pages/userhome.php', ['section' => 'messages', 'thread' => $threadId]);
