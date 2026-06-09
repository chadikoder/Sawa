<?php
declare(strict_types=1);
/** @var array<string, mixed> $thread */
/** @var int $activeThreadId */
$tid = (int) $thread['id'];
$otherName = htmlspecialchars((string) ($thread['other_name'] ?? 'User'), ENT_QUOTES, 'UTF-8');
$initials = Format::initials((string) ($thread['other_name'] ?? 'U'));
$preview = htmlspecialchars(mb_substr((string) ($thread['last_body'] ?? 'No messages yet'), 0, 80), ENT_QUOTES, 'UTF-8');
$time = Format::timeAgo((string) ($thread['last_message_at'] ?? $thread['created_at'] ?? 'now'));
$role = (string) ($thread['other_role'] ?? 'user');
$avatarClass = match ($role) {
    'organisation' => '',
    'beneficiary'  => ' recipient',
    default        => ' donor',
};
$active = $tid === $activeThreadId ? ' active' : '';
?>
<a href="userhome.php?thread=<?= $tid ?>" class="message-thread<?= $active ?>">
  <span class="message-avatar<?= $avatarClass ?>"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
  <span class="message-thread-body">
    <strong><?= $otherName ?></strong>
    <small><?= $preview ?></small>
  </span>
  <span class="message-time"><?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?></span>
</a>
