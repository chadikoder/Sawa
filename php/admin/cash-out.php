<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_role('admin');

$requestId = (int) ($_POST['request_id'] ?? 0);
$status = (string) ($_POST['action'] ?? '');
$validStatuses = ['processing', 'completed', 'failed', 'cancelled'];

if ($requestId <= 0 || !in_array($status, $validStatuses, true)) {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'money']);
}

$stmt = db()->prepare('SELECT * FROM cash_out_requests WHERE id = ? LIMIT 1');
$stmt->execute([$requestId]);
$request = $stmt->fetch();
if (!$request) {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'money']);
}

$currentStatus = (string) $request['status'];
if (in_array($currentStatus, ['completed', 'failed', 'cancelled'], true)) {
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'money']);
}

$refundStatuses = ['failed', 'cancelled'];
// Refund exactly what was debited, which is `amount` — the fee is taken out of
// that amount, not added to it (see php/wallet/cash-out.php). Adding the fee
// back on top here would credit the user more than the payout ever removed.
$refundAmount = round((float) $request['amount'], 2);
$pdo = db();
$processedAtSql = in_array($status, ['completed', 'failed', 'cancelled'], true) ? ', processed_at = NOW()' : '';
try {
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE cash_out_requests SET status = ?' . $processedAtSql . ' WHERE id = ?')
        ->execute([$status, $requestId]);
    if (in_array($status, $refundStatuses, true)) {
        WalletService::credit(
            (int) $request['user_id'],
            $refundAmount,
            'refund',
            'Cash-out request #' . $requestId . ' refunded'
        );
    }
    $pdo->commit();
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::redirectStatus('pages/admin.php', 'error', ['view' => 'money']);
}

NotificationService::send(
    (int) $request['user_id'],
    'cashout_' . $status,
    'Cash-out ' . $status,
    'Your cash-out request is now marked as ' . $status . '.'
);
AuditLog::write(Auth::id() ?? 0, 'cashout_' . $status, 'cash_out_request', $requestId);

Response::redirectStatus('pages/admin.php', 'success', ['view' => 'money']);
