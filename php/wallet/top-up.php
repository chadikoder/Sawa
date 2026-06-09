<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}

Csrf::validate();
require_auth();

$amount = (float) ($_POST['amount'] ?? 0);
if ($amount < 1) {
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'wallet']);
}

$session = PaymentService::createSession('wallet_topup', $amount, 0, 'hosted_checkout', Auth::id(), null);
Response::redirect('php/payments/checkout.php', ['token' => $session['token']]);
