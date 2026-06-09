<?php
declare(strict_types=1);
/** @var array<string, mixed> $don */
$name = (string) ($don['donor_name'] ?? '');
$label = $name !== '' ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : 'Anonymous';
$initials = $name !== '' ? Format::initials($name) : '?';
$anon = $name === '';
$time = Format::timeAgo((string) ($don['created_at'] ?? 'now'));
$amount = '$' . number_format((float) ($don['amount'] ?? 0), 0);
?>
<li class="donor-row">
  <span class="donor-avatar<?= $anon ? ' donor-anon' : '' ?>"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
  <div class="donor-meta"><strong><?= $label ?></strong><small><?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?></small></div>
  <span class="donor-amount"><?= $amount ?></span>
</li>
