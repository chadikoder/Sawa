<?php
declare(strict_types=1);
/** @var array<string, mixed> $tx */
$type = (string) $tx['type'];
$label = match ($type) {
    'topup' => 'Wallet top-up',
    'donation' => 'Donation',
    'cashout' => 'Cash-out',
    'refund' => 'Refund',
    default => ucfirst($type),
};
$sign = in_array($type, ['donation', 'cashout'], true) ? '-' : '+';
$amt = number_format((float) $tx['amount'], 2);
?>
<li>
  <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
  <strong><?= $sign ?>$<?= $amt ?></strong>
  <small><?= date('M j, Y', strtotime((string) $tx['created_at'])) ?></small>
</li>
