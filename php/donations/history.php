<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_auth();
$items = DonationService::activityForUser(Auth::id());
json_response(['donations' => $items]);
