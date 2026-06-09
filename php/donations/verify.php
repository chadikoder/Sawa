<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_role('admin');

$id = (int) ($_POST['donation_id'] ?? 0);
DonationService::completeDonation($id, 'ADMIN-VERIFY-' . $id);
ReceiptService::createForDonation($id);
AuditLog::write(Auth::id(), 'verify_donation', 'donation', $id);
json_response(['ok' => true]);
