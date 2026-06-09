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

Auth::login((int) $user['id'], $user['role']);

if (!empty($_POST['remember_me'])) {
    ini_set('session.cookie_lifetime', (string) (60 * 60 * 24 * 30));
}

$dest = match ($user['role']) {
    'organisation' => Auth::isOrganisationVerified()
        ? 'pages/userhome.php'
        : 'pages/org-pending.html',
    default => 'pages/userhome.php',
};

Response::redirectStatus($dest, 'success');
