<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::redirect('pages/forgot-password.php');
}

Csrf::validate();

$email = strtolower(trim((string) ($_POST['email'] ?? '')));

// IP rate limit — prevent enumeration & mail-flooding. 5 hits / 15 min / IP.
// We record the attempt whether or not the email is valid so probing costs
// the attacker even for malformed inputs.
if (BruteForce::isIpRateLimited('pw_reset', 5, 15)) {
    // Same response as success so we don't leak the throttle state either.
    Response::redirectStatus('pages/forgot-password.php', 'reset_sent');
}
BruteForce::recordIpHit('pw_reset');

// Always same response — prevent enumeration
if (Validator::email($email)) {
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $token = gen_token(32);
        $expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
        db()->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
        )->execute([(int) $user['id'], $token, $expires]);
        Mailer::sendPasswordReset($email, $token);
    }
}

Response::redirectStatus('pages/forgot-password.php', 'reset_sent');
