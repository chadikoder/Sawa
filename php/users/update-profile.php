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

$bannerPath = null;
if (!empty($_FILES['banner_image']['tmp_name'])) {
    try {
        $bannerPath = Upload::store($_FILES['banner_image'], 'banners/' . $userId);
    } catch (RuntimeException) {
        Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'profile']);
    }
}

// Built from a fixed whitelist rather than branching per combination: with an
// avatar and a banner both optional there are four cases, and the previous
// if/else pair already had to repeat the whole statement for two. Only columns
// the user actually submitted are touched, so uploading a banner does not wipe
// an existing avatar and vice versa. The names here are literals, never input.
$columns = ['bio'];
$values  = [$bio !== '' ? $bio : null];
if ($avatarPath !== null) { $columns[] = 'avatar_path'; $values[] = $avatarPath; }
if ($bannerPath !== null) { $columns[] = 'banner_path'; $values[] = $bannerPath; }

$assignments = implode(', ', array_map(static fn (string $c): string => "$c = VALUES($c)", $columns));
$pdo->prepare(
    'INSERT INTO user_profiles (user_id, ' . implode(', ', $columns) . ')
     VALUES (?, ' . implode(', ', array_fill(0, count($columns), '?')) . ')
     ON DUPLICATE KEY UPDATE ' . $assignments
)->execute(array_merge([$userId], $values));

Response::redirectStatus('pages/userhome.php', 'success', ['section' => 'profile']);
