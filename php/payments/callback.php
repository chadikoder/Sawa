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

$session = PaymentService::findByToken($token);
$returnExtra = ['section' => 'discover'];
if ($session && !empty($session['donation_id'])) {
    $stmt = db()->prepare('SELECT campaign_id FROM donations WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $session['donation_id']]);
    $campaignId = (int) $stmt->fetchColumn();
    if ($campaignId > 0) {
        $returnExtra['campaign'] = $campaignId;
    }
}

if ($action === 'confirm') {
    try {
        PaymentService::confirm($token, 'DEV-' . strtoupper(substr($token, 0, 8)));
    } catch (Throwable) {
        Response::redirectStatus('pages/userhome.php', 'payment_failed', $returnExtra);
    }

    // Deliberately outside the try above and after confirm() has committed: a
    // guest has no account, so this link is their only route to the receipt,
    // but failing to send it must not report the payment as failed.
    if ($session && !empty($session['donation_id'])) {
        ReceiptService::emailGuestCopy((int) $session['donation_id']);
    }

    Response::redirectStatus('pages/userhome.php', 'payment_confirmed', $returnExtra);
}

PaymentService::fail($token);
Response::redirectStatus('pages/userhome.php', 'payment_cancelled', $returnExtra);
