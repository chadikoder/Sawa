<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

$billId = trim((string) ($_GET['id'] ?? ''));
if ($billId === '') {
    Response::abort(404);
}

$userId = Auth::check() ? Auth::id() : null;
$receipt = ReceiptService::findByBillId($billId, $userId);
if (!$receipt) {
    Response::abort(403);
}

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $billId . '.txt"');

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
