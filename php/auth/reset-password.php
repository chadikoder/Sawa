<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim((string) ($_GET['token'] ?? ''));
    if ($token === '') {
        Response::redirect('pages/forgot-password.php');
    }
    Response::redirect('pages/reset-password.php', ['token' => $token]);
}

Csrf::validate();

$token = trim((string) ($_POST['token'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirm = (string) ($_POST['password_confirm'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/', $token) || !Validator::password($password) || $password !== $confirm) {
    Response::redirectStatus('pages/reset-password.php', 'error', ['token' => $token]);
}

$stmt = db()->prepare(
    'SELECT pr.user_id, pr.expires_at, pr.used
     FROM password_resets pr
     WHERE pr.token = ?
     LIMIT 1'
);
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row || (int) $row['used'] === 1 || new DateTimeImmutable($row['expires_at']) < new DateTimeImmutable()) {
    Response::redirectStatus('pages/forgot-password.php', 'error');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, (int) $row['user_id']]);
    $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')->execute([$token]);
    $pdo->commit();
} catch (Throwable) {
    $pdo->rollBack();
    Response::redirectStatus('pages/reset-password.php', 'error', ['token' => $token]);
}

Response::redirectStatus('pages/login.php', 'success');
