<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/login.php');
}

Csrf::validate();

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');

if (!Validator::email($email) || $password === '') {
    Response::redirectStatus('pages/login.php', 'invalid');
}

if (BruteForce::isLocked($email)) {
    Response::redirectStatus('pages/login.php', 'error');
}

$stmt = db()->prepare(
    'SELECT id, password_hash, role, email_verified, active FROM users WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

$ok = $user
    && (int) $user['active'] === 1
    && password_verify($password, $user['password_hash']);

BruteForce::record($email, $ok);

if (!$ok) {
    Response::redirectStatus('pages/login.php', 'invalid');
}

if ((int) $user['email_verified'] !== 1 && $user['role'] !== 'admin') {
    Response::redirectStatus('pages/login.php', 'error');
}

$addMode = !empty($_POST['add']) && Auth::check();
Auth::login((int) $user['id'], $user['role'], $addMode);

// "Remember me" — re-issue the session cookie with an expiry instead of
// leaving it a browser-session cookie that dies when Chrome closes.
//
// The old ini_set('session.cookie_lifetime') here did nothing at all: the
// session had already been started by bootstrap.php and the Set-Cookie header
// was already sent, so changing the ini value afterwards could not affect it.
// This has to run after Auth::login(), which regenerates the id.
if (!empty($_POST['remember_me'])) {
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires'  => time() + (60 * 60 * 24 * 30),
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$dest = match ($user['role']) {
    'admin' => 'admin',
    'organisation' => Auth::isOrganisationVerified()
        ? 'pages/userhome.php'
        : 'pages/org-pending.html',
    default => 'pages/userhome.php',
};

Response::redirectStatus($dest, 'success');
