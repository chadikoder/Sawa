<?php
declare(strict_types=1);
/** @var array<string, mixed> $row */
$billId = htmlspecialchars((string) ($row['bill_id'] ?? 'PENDING'), ENT_QUOTES, 'UTF-8');
$total = '$' . number_format((float) ($row['total_charged'] ?? $row['total_paid'] ?? 0), 2);
$method = htmlspecialchars((string) ($row['method_label'] ?? 'Payment'), ENT_QUOTES, 'UTF-8');
$date = date('F j, Y \a\t g:i A', strtotime((string) ($row['created_at']));
$ref = htmlspecialchars((string) ($row['payment_ref'] ?? $row['provider_ref'] ?? ''), ENT_QUOTES, 'UTF-8');
$recipient = htmlspecialchars((string) ($row['campaign_title'] ?? $row['recipient_label'] ?? 'Sawa'), ENT_QUOTES, 'UTF-8');
$icon = ($row['payment_method'] ?? '') === 'wallet' ? 'wallet' : (($row['payment_method'] ?? '') === 'hosted_checkout' ? 'card-pay' : 'paid');
$isFirst = !empty($row['_first']);
?>
<button type="button" class="activity-ledger-row<?= $isFirst ? ' active' : '' ?>"
  data-bill-id="<?= $billId ?>" data-bill-total="<?= $total ?>"
  data-bill-method="<?= $method ?>" data-bill-date="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>"
  data-bill-ref="<?= $ref ?>" data-bill-recipient="<?= $recipient ?>">
  <span class="activity-ledger-icon <?= $icon ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 000-7.78z"/></svg>
  </span>
  <span class="activity-ledger-main">
    <strong>Donation paid</strong>
    <small><?= $recipient ?></small>
  </span>
  <span class="activity-ledger-side">
    <strong><?= $total ?></strong>
    <small>Receipt</small>
  </span>
</button>
