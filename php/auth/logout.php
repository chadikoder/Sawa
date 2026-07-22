<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Logging out changes server state, so it cannot be a bare GET link: any page
// on the internet could embed <img src=".../logout.php"> and sign the user
// out. POST + CSRF means only Sawa's own forms can trigger it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}
Csrf::validate();

// Returns true if another signed-in account on this device became active.
if (Auth::logout()) {
    Response::redirectStatus('pages/userhome.php', 'success');
}
Response::redirectStatus('pages/login.php', 'logged_out');
