<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

/**
 * Exactly two ways to be authorised for a receipt:
 *
 *   ?token=<64 hex>  the capability link issued to a guest donor by email.
 *                    No session — the token IS the authorisation.
 *   ?id=<bill id>    a signed-in member fetching their own receipt.
 *
 * Anything else is a 404. Previously a signed-out visitor fell through both
 * checks and got whatever bill id they asked for, and bill ids run in
 * sequence, so the entire receipts table could be walked by a stranger.
 *
 * 404 (not 403) on both failure modes on purpose: 403 would confirm that a
 * given bill id exists, which is exactly the signal an enumerator wants.
 */
$token = trim((string) ($_GET['token'] ?? ''));

if ($token !== '') {
    $receipt = ReceiptService::findByAccessToken($token);
} else {
    require_auth();
    $billId = trim((string) ($_GET['id'] ?? ''));
    $receipt = $billId === '' ? null : ReceiptService::findByBillId($billId, (int) Auth::id());
}

if (!$receipt) {
    Response::abort(404);
}

// Build the filename from the stored row, never from user input — $_GET could
// otherwise steer the Content-Disposition header.
$filename = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $receipt['bill_id']) ?: 'receipt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.txt"');

echo "SAWA PAYMENT RECEIPT\n";
echo "===================\n";
echo "Bill ID:      {$receipt['bill_id']}\n";
echo "Date:         {$receipt['created_at']}\n";
echo "Recipient:    {$receipt['recipient_label']}\n";
echo "Method:       {$receipt['method_label']}\n";
echo "Donation:     $" . number_format((float) $receipt['subtotal'], 2) . "\n";
echo "Sawa fee:     $" . number_format((float) $receipt['fee_amount'], 2) . "\n";
echo "Total paid:   $" . number_format((float) $receipt['total_paid'], 2) . "\n";
echo "Provider ref: " . ($receipt['provider_ref'] ?? 'N/A') . "\n";
echo "Checksum:     {$receipt['checksum']}\n";
exit;
