<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_auth();

// A specific id marks just that one — used when a notification is opened, so
// its unread dot clears without wiping the rest of the list. No id keeps the
// original "mark all read" behaviour the panel button relies on.
$one = (int) ($_POST['notification_id'] ?? 0);
if ($one > 0) {
    NotificationService::markRead($one, (int) Auth::id());
} else {
    NotificationService::markAllRead((int) Auth::id());
}

if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
    json_response(['ok' => true]);
}
Response::redirect('pages/userhome.php');
