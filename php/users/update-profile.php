<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}

Csrf::validate();
require_auth();

$userId = Auth::id();
$fullName = Validator::sanitizeString((string) ($_POST['full_name'] ?? ''), 120);
$bio = Validator::sanitizeString((string) ($_POST['bio'] ?? ''), 250);

if ($fullName === '' || mb_strlen($fullName) < 2) {
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'profile']);
}

$pdo = db();
$pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$fullName, $userId]);

$avatarPath = null;
if (!empty($_FILES['profile_image']['tmp_name'])) {
    try {
        $avatarPath = Upload::store($_FILES['profile_image'], 'profiles/' . $userId);
    } catch (RuntimeException) {
        Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'profile']);
    }
}

if ($avatarPath !== null) {
    $pdo->prepare(
        'INSERT INTO user_profiles (user_id, bio, avatar_path)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE bio = VALUES(bio), avatar_path = VALUES(avatar_path)'
    )->execute([$userId, $bio !== '' ? $bio : null, $avatarPath]);
} else {
    $pdo->prepare(
        'INSERT INTO user_profiles (user_id, bio)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE bio = VALUES(bio)'
    )->execute([$userId, $bio !== '' ? $bio : null]);
}

Response::redirectStatus('pages/userhome.php', 'success', ['section' => 'profile']);
