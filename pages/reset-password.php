<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/php/bootstrap.php';
$token = trim((string) ($_GET['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    Response::redirect('pages/forgot-password.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/tokens.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="icon" href="../images/sawa.svg" type="image/svg+xml">
    <script src="../js/theme.js"></script>
    <title>Sawa — New Password</title>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <div class="login-page" id="main">
        <div class="login-card" style="margin: 4rem auto;">
            <h1>Choose a new password</h1>
            <p class="auth-sub">At least 6 characters with letters and numbers.</p>
            <form class="login_form" action="../php/auth/reset-password.php" method="POST">
                <?= Csrf::field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="field-group">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                </div>
                <div class="field-group">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                </div>
                <input type="submit" value="Update password">
            </form>
        </div>
    </div>
</body>
</html>
