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
// No 'campaign' here either — see the note in comments-post.php. Returning
// from a payment reopened the full campaign screen behind the status modal.
$returnExtra = ['section' => 'discover'];

$breakdown = DonationService::breakdown($amount, $isGuest, $method);
$guestName = Validator::sanitizeString((string) ($_POST['donor_name'] ?? ''), 120);
$guestEmail = trim((string) ($_POST['donor_email'] ?? ''));
$guestPhone = trim((string) ($_POST['donor_phone'] ?? ''));

if ($isGuest && $guestName === '') {
    Response::redirectStatus('pages/userhome.php', 'error');
}

$pdo = db();
// One transaction around the whole write. WalletService::debit() and
// DonationService::completeDonation() both branch on $pdo->inTransaction():
// with no outer transaction debit() committed on its own, so a failure in
// completeDonation() (e.g. a deadlock on the organisations row when two
// wallet donations to the same campaign overlap) left the donor debited,
// raised_amount unchanged and the donation stranded in 'pending'.
$pdo->beginTransaction();
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
        $pdo->commit();
        // Best-effort side effect, deliberately after the commit: a failed
        // notification must not roll back a completed donation.
        if ($donorId) {
            try {
                NotificationService::send($donorId, 'donation_complete', 'Donation confirmed', 'Thank you for your support.');
            } catch (Throwable) {
                // ignored — the donation is already durable
            }
        }
        Response::redirectStatus('pages/userhome.php', 'payment_confirmed', $returnExtra);
    }

    $session = PaymentService::createSession(
        'donation',
        $breakdown['donation'],
        $breakdown['fee'],
        $method,
        $donorId,
        $donationId
    );
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // An empty wallet is the one failure here the donor can actually do
    // something about, so it gets its own status rather than being flattened
    // into "payment failed" with no reason given. WalletService::debit()
    // throws this before writing anything, so nothing needs undoing beyond the
    // rollback above.
    $status = $e->getMessage() === 'insufficient_balance' ? 'wallet_short' : 'payment_failed';
    Response::redirectStatus('pages/userhome.php', $status, $returnExtra ?? []);
}

Response::redirect('php/payments/checkout.php', ['token' => $session['token']]);
