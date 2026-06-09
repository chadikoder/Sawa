<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    Response::redirectStatus('pages/login.php', 'error');
}

$stmt = db()->prepare(
    'SELECT ev.user_id, ev.expires_at, ev.used
     FROM email_verifications ev
     WHERE ev.token = ?
     LIMIT 1'
);
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row || (int) $row['used'] === 1) {
    Response::redirectStatus('pages/login.php', 'error');
}

if (new DateTimeImmutable($row['expires_at']) < new DateTimeImmutable()) {
    Response::redirectStatus('pages/login.php', 'expired');
}

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = ?')->execute([(int) $row['user_id']]);
    $pdo->prepare('UPDATE email_verifications SET used = 1 WHERE token = ?')->execute([$token]);
    $pdo->commit();
} catch (Throwable) {
    $pdo->rollBack();
    Response::redirectStatus('pages/login.php', 'error');
}

Response::redirectStatus('pages/login.php', 'success');
