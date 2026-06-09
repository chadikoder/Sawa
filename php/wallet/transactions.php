<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_auth();
json_response(['transactions' => WalletService::transactions(Auth::id())]);
