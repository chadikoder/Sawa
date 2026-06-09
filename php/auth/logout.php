<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::logout();
Response::redirectStatus('pages/login.php', 'logged_out');
