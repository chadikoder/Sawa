<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}

Csrf::validate();
require_auth();

$accountId = (int) ($_POST['account_id'] ?? 0);

if ($accountId > 0 && Auth::switchTo($accountId)) {
    Response::redirectStatus('pages/userhome.php', 'success');
}

Response::redirectStatus('pages/userhome.php', 'error');
