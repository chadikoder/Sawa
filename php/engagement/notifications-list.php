<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_auth();
json_response([
    'notifications' => NotificationService::listForUser(Auth::id()),
    'unread'        => NotificationService::unreadCount(Auth::id()),
]);
