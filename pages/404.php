<?php
declare(strict_types=1);
/**
 * 404 page.
 *
 * PHP rather than HTML so every asset and link goes through url(). This file
 * is rendered from three different places — Response::abort() from any depth
 * under /php/, router.php on the built-in server, and the mod_rewrite
 * fallback in .htaccess for a URL that matches no file — so the browser's
 * base for a relative path is whatever the *request* was, not this file's
 * location. The old '../css/tokens.css' resolved correctly only when the URL
 * happened to be one level deep; from /php/payments/checkout.php it asked for
 * /php/css/tokens.css and the page rendered completely unstyled.
 */
require_once dirname(__DIR__) . '/php/config/config.php';

// Set here as well as at the call site: reached through the rewrite fallback
// there is no call site, and a 404 body served as 200 is worse than useless.
if (!headers_sent()) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Sawa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/status.css') ?>">
    <link rel="icon" href="<?= url('images/sawa.svg') ?>" type="image/svg+xml">
    <script src="<?= asset('js/theme.js') ?>"></script>
</head>
<body>
    <main class="status-shell" aria-labelledby="status-title">
        <section class="status-copy">
            <img class="status-logo" src="<?= url('images/sawa_v2.svg') ?>" alt="Sawa">
            <p class="status-eyebrow">404</p>
            <h1 id="status-title">Page not found.</h1>
            <p>The page may have moved, or the campaign link may be incorrect.</p>
            <div class="status-actions">
                <a href="<?= url('pages/userhome.php') ?>" class="status-btn">Browse Campaigns</a>
                <a href="<?= url('index.html') ?>" class="status-btn secondary">Home</a>
            </div>
        </section>
        <div class="status-art">
            <svg class="status-illustration" viewBox="0 0 240 240" role="img" aria-labelledby="notfound-svg-title">
                <title id="notfound-svg-title">Not found illustration</title>
                <circle class="status-bg" cx="120" cy="120" r="104"/>
                <rect class="status-panel" x="56" y="70" width="128" height="92" rx="18"/>
                <path class="status-muted" d="M82 100h76v10H82zM82 124h44v10H82z" opacity=".72"/>
                <circle cx="102" cy="178" r="28" fill="none" stroke="var(--color-primary)" stroke-width="9"/>
                <path d="M122 198l28 28" fill="none" stroke="var(--color-primary)" stroke-width="9" stroke-linecap="round"/>
            </svg>
        </div>
    </main>
</body>
</html>
