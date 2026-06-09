<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}

Csrf::validate();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$amount = (float) ($_POST['amount'] ?? 0);
$method = (string) ($_POST['payment_method'] ?? 'whish');
$isGuest = !Auth::check();
$donorId = $isGuest ? null : Auth::id();

if (!in_array($method, ['whish', 'hosted_checkout', 'wallet'], true)) {
    $method = 'whish';
}
if ($method === 'wallet' && $isGuest) {
    Response::redirectStatus('pages/userhome.php', 'error');
}

$camp = CampaignService::find($campaignId);
if (!$camp || $camp['status'] !== 'active' || $amount < 1) {
    Response::redirectStatus('pages/userhome.php', 'error');
}

$breakdown = DonationService::breakdown($amount, $isGuest, $method);
$guestName = Validator::sanitizeString((string) ($_POST['donor_name'] ?? ''), 120);
$guestEmail = trim((string) ($_POST['donor_email'] ?? ''));
$guestPhone = trim((string) ($_POST['donor_phone'] ?? ''));

if ($isGuest && $guestName === '') {
    Response::redirectStatus('pages/userhome.php', 'error');
}

$pdo = db();
try {
    $pdo->prepare(
        'INSERT INTO donations (campaign_id, donor_id, guest_name, guest_email, guest_phone,
         amount, fee_amount, total_charged, payment_method, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')'
    )->execute([
        $campaignId,
        $donorId,
        $isGuest ? $guestName : null,
        $guestEmail !== '' ? $guestEmail : null,
        $guestPhone !== '' ? $guestPhone : null,
        $breakdown['donation'],
        $breakdown['fee'],
        $breakdown['total'],
        $method,
    ]);
    $donationId = (int) $pdo->lastInsertId();
    DonationService::recordStatus($donationId, null, 'pending', $donorId);

    if ($method === 'wallet') {
        WalletService::debit($donorId, $breakdown['total'], 'donation', 'Donation to campaign #' . $campaignId, $donationId);
        DonationService::completeDonation($donationId, 'WALLET-' . $donationId);
        ReceiptService::createForDonation($donationId);
        if ($donorId) {
            NotificationService::send($donorId, 'donation_complete', 'Donation confirmed', 'Thank you for your support.');
        }
        Response::redirectStatus('pages/userhome.php', 'payment_confirmed');
    }

    $session = PaymentService::createSession(
        'donation',
        $breakdown['donation'],
        $breakdown['fee'],
        $method,
        $donorId,
        $donationId
    );
} catch (Throwable) {
    Response::redirectStatus('pages/userhome.php', 'payment_failed');
}

Response::redirect('php/payments/checkout.php', ['token' => $session['token']]);
