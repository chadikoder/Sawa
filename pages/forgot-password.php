<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/php/bootstrap.php';
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
    <title>Sawa — Forgot Password</title>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <a href="login.php" class="back-home" aria-label="Go back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <div class="login-page" id="main">
        <div class="login-card">
            <img src="../images/sawa_v2.svg" alt="Sawa" class="card-logo">
            <h1>Reset password</h1>
            <p class="auth-sub">Enter your email and we will send reset instructions if the account exists.</p>
            <form class="login_form" action="../php/auth/request-password-reset.php" method="POST">
                <?= Csrf::field() ?>
                <?php /* .input-wrap is what carries the field styling — see the
                         note in reset-password.php. */ ?>
                <div class="field-group">
                    <label for="email">Email</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email" required>
                    </div>
                </div>
                <input type="submit" value="Send reset link" data-busy="Sending...">
            </form>
            <p class="auth-switch"><a href="login.php">Back to log in</a></p>
        </div>
    </div>
    <script src="../js/login.js"></script>
</body>
</html>
