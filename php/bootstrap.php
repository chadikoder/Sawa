<?php
declare(strict_types=1);

/**
 * Single entry bootstrap for every PHP endpoint.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

require_once __DIR__ . '/lib/Session.php';
require_once __DIR__ . '/lib/Csrf.php';
require_once __DIR__ . '/lib/Response.php';
require_once __DIR__ . '/lib/Validator.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/BruteForce.php';
require_once __DIR__ . '/lib/Upload.php';
require_once __DIR__ . '/lib/Mailer.php';
require_once __DIR__ . '/lib/AuditLog.php';
require_once __DIR__ . '/lib/CampaignService.php';
require_once __DIR__ . '/lib/WalletService.php';
require_once __DIR__ . '/lib/DonationService.php';
require_once __DIR__ . '/lib/PaymentService.php';
require_once __DIR__ . '/lib/NotificationService.php';
require_once __DIR__ . '/lib/MessageService.php';
require_once __DIR__ . '/lib/ReceiptService.php';
require_once __DIR__ . '/lib/Format.php';

// Security headers on every response
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 0'); // modern browsers rely on CSP; legacy header disabled
if (APP_ENV === 'production') {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('session.cookie_secure', '1');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

Session::start();

// Ensure upload root exists (lazy)
if (!is_dir(UPLOAD_ROOT)) {
    mkdir(UPLOAD_ROOT, 0750, true);
}
