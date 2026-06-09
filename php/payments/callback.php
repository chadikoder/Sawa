<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::abort(405);
}

Csrf::validate();

$token = trim((string) ($_POST['token'] ?? ''));
$action = (string) ($_POST['action'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    Response::redirectStatus('pages/userhome.php', 'payment_failed');
}

if ($action === 'confirm') {
    try {
        PaymentService::confirm($token, 'DEV-' . strtoupper(substr($token, 0, 8)));
        Response::redirectStatus('pages/userhome.php', 'payment_confirmed');
    } catch (Throwable) {
        Response::redirectStatus('pages/userhome.php', 'payment_failed');
    }
}

PaymentService::fail($token);
Response::redirectStatus('pages/userhome.php', 'payment_cancelled');
