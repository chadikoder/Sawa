<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/php/bootstrap.php';
// "Add an account" flow: when ?add=1 and someone is already signed in, the new
// login is added alongside the current session instead of replacing it.
$addMode = isset($_GET['add']) && Auth::check();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/login.css') ?>">
    <link rel="icon" href="../images/sawa.svg" type="image/svg+xml">
    <script src="<?= asset('js/theme.js') ?>"></script>
    <title>Sawa — Log In</title>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <?php /* Adding an account is reached from Settings while already signed
             in, so back has to return to the dashboard. Sending that user to
             the public landing page looked like being signed out. */ ?>
    <a href="<?= $addMode ? 'userhome.php' : '../index.html' ?>" class="back-home">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
      <span><?= $addMode ? 'Back to dashboard' : 'Back to home' ?></span>
    </a>
    <div class="login-page" id="main">

        <div class="login-brand">
            <img src="../images/sawa_v2.svg" alt="Sawa" class="brand-logo">
            <p class="brand-name">SAWA</p>
            <p class="brand-tagline">Connecting Lebanese donors<br>with families in need</p>

            <ul class="brand-trust-list" aria-label="Why Sawa">
                <li class="brand-trust-item">
                    <span class="brand-trust-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                    </span>
                    <div>
                        <strong>Verified NGOs only</strong>
                        <span>Every organisation is reviewed by our team before joining.</span>
                    </div>
                </li>
                <li class="brand-trust-item">
                    <span class="brand-trust-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <div>
                        <strong>Transparent fees</strong>
                        <span>5% for members, 10% for guests — shown before you pay.</span>
                    </div>
                </li>
                <li class="brand-trust-item">
                    <span class="brand-trust-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </span>
                    <div>
                        <strong>Track every donation</strong>
                        <span>Real-time updates, receipts, and a full activity trail.</span>
                    </div>
                </li>
            </ul>

            <p class="brand-quote">
                <span aria-hidden="true">“</span>Built in response to the Lebanese crisis — for families, by neighbours.<span aria-hidden="true">”</span>
            </p>
        </div>

        <div class="login-card">
            <img src="../images/sawa_v2.svg" alt="Sawa" class="card-logo">
            <h1><?= $addMode ? 'Add an account' : 'Welcome Back' ?></h1>
            <p class="auth-sub"><?= $addMode ? 'Your current account stays signed in — switch anytime.' : "We're glad to see you again" ?></p>

            <form class="login_form" action="../php/auth/login.php" method="POST">
                <?= Csrf::field() ?>
                <?php if ($addMode): ?><input type="hidden" name="add" value="1"><?php endif; ?>

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

                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password" required>
                        <button type="button" class="pwd-toggle" aria-label="Show password">
                            <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="login-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>

                <input type="submit" value="Log In" data-busy="Logging in...">

            </form>

            <?php /* In add-account mode this used to read "Don't have an
                     account? Sign up", which invites creating a second account
                     when the intent was to sign in to an existing one — the
                     opposite of what this screen is for. */ ?>
            <?php if ($addMode): ?>
              <p class="auth-switch">Adding an account keeps your current one signed in. No account yet? <a href="signup.php?add=1">Create one</a> &middot; <a href="userhome.php">Cancel</a></p>
            <?php else: ?>
              <p class="auth-switch">Don't have an account? <a href="signup.php">Sign up</a></p>
            <?php endif; ?>
        </div>

    </div>

    <script src="<?= asset('js/login.js') ?>"></script>
</body>
</html>
