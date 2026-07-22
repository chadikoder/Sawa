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
    <a href="login.php" class="back-home">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
      <span>Back to log in</span>
    </a>
    <div class="login-page" id="main">
        <div class="login-card">
            <img src="../images/sawa_v2.svg" alt="Sawa" class="card-logo">
            <h1>Choose a new password</h1>
            <p class="auth-sub">At least 6 characters with letters and numbers.</p>
            <form class="login_form" action="../php/auth/reset-password.php" method="POST">
                <?= Csrf::field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                <?php /* Fields must sit inside .input-wrap: the 4.8rem height,
                         1.2rem radius and control border all come from the
                         `.input-wrap input` rule. Without the wrapper these
                         rendered as raw browser default boxes — 21px tall with
                         a grey 1px outline — next to a login page styled the
                         other way entirely. */ ?>
                <div class="field-group">
                    <label for="password">New password</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" id="password" name="password" placeholder="Min. 6 chars, letters &amp; numbers" required autocomplete="new-password">
                        <button type="button" class="pwd-toggle" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="field-group">
                    <label for="password_confirm">Confirm password</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" id="password_confirm" name="password_confirm" placeholder="Repeat your password" required autocomplete="new-password">
                        <button type="button" class="pwd-toggle" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <input type="submit" value="Update password" data-busy="Updating...">
            </form>
            <p class="auth-switch"><a href="login.php">Back to log in</a></p>
        </div>
    </div>

    <?php /* Also gives this page the ?status= toast — a failed reset used to
             bounce back here showing nothing at all. */ ?>
    <script src="../js/login.js"></script>
</body>
</html>
