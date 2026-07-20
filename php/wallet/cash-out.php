<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}

Csrf::validate();
require_auth();

$amount = (float) ($_POST['cashout_amount'] ?? 0);
$method = (string) ($_POST['cashout_method'] ?? 'whish');
$destination = Validator::sanitizeString((string) ($_POST['cashout_destination'] ?? ''), 255);

if ($amount < 1 || $destination === '' || !in_array($method, ['whish', 'bank_card'], true)) {
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'wallet']);
}

$fee = round($amount * CASHOUT_FEE_RATE, 2);
$totalDebit = $amount + $fee;

$pdo = db();
try {
    $pdo->beginTransaction();
    WalletService::debit(Auth::id(), $totalDebit, 'cashout', 'Cash-out request');
    $pdo->prepare(
        'INSERT INTO cash_out_requests (user_id, amount, fee_amount, net_amount, method, destination)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([Auth::id(), $amount, $fee, $amount - $fee, $method, $destination]);
    $pdo->commit();
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'wallet']);
}

NotificationService::send(Auth::id(), 'cashout_requested', 'Cash-out requested', 'Your payout is being processed (2–3 business days).');
Response::redirectStatus('pages/userhome.php', 'success', ['section' => 'wallet']);
