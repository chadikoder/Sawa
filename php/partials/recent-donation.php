<?php
declare(strict_types=1);
/** @var array<string, mixed> $don */
$name = htmlspecialchars((string) ($don['donor_name'] ?? 'Anonymous'), ENT_QUOTES, 'UTF-8');
$campaign = htmlspecialchars((string) ($don['campaign_title'] ?? 'Campaign'), ENT_QUOTES, 'UTF-8');
$amount = '$' . number_format((float) ($don['amount'] ?? 0), 0);
$time = Format::timeAgo((string) ($don['created_at'] ?? 'now'));
$statusClass = Format::donationStatusClass((string) ($don['status'] ?? 'pending'));
$statusLabel = Format::donationStatusLabel((string) ($don['status'] ?? 'pending'));
$showStatus = empty($don['_org_meta']);
$metaSmall = !empty($don['_org_meta']) ? 'to ' . $campaign : $campaign;
?>
<li class="recent-donation">
  <span class="rd-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></span>
  <div class="rd-meta"><strong><?= $name ?></strong><small><?= $metaSmall ?></small></div>
  <div class="rd-right"><span class="rd-amount"><?= $amount ?></span><small class="rd-time"><?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?></small></div>
  <?php if ($showStatus): ?><span class="rd-status <?= $statusClass ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
</li>
