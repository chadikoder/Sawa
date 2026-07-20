<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Returns true if another signed-in account on this device became active.
if (Auth::logout()) {
    Response::redirectStatus('pages/userhome.php', 'success');
}
Response::redirectStatus('pages/login.php', 'logged_out');
