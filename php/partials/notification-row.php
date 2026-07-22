<?php
declare(strict_types=1);
/** @var array<string, mixed> $n */
$unread = !(int) $n['is_read'];
$title = htmlspecialchars((string) $n['title'], ENT_QUOTES, 'UTF-8');
$body  = htmlspecialchars((string) ($n['body'] ?? ''), ENT_QUOTES, 'UTF-8');

/* Rows used to be inert <li>s, so tapping a notification did nothing at all.
   notifications.link already exists in the schema and some notifications set
   it; when it does the row navigates there, and when it does not the row opens
   Activity & Bills, which is where notification history lives. Only in-app
   paths are followed — a link from the database is never treated as an
   absolute URL, so a stored value cannot send the user off-site. */
$rawLink = trim((string) ($n['link'] ?? ''));
$safeLink = ($rawLink !== '' && !preg_match('#^[a-z]+:|^//#i', $rawLink))
    ? url(ltrim($rawLink, '/'))
    : '';
?>
<li class="dash-notif-row<?= $unread ? ' unread' : '' ?>" data-notif-id="<?= (int) $n['id'] ?>">
  <?php if ($safeLink !== ''): ?>
  <a class="dash-notif-hit" href="<?= htmlspecialchars($safeLink, ENT_QUOTES, 'UTF-8') ?>">
  <?php else: ?>
  <button type="button" class="dash-notif-hit" data-section="activity">
  <?php endif; ?>
    <span class="dash-notif-icon icon-heart"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></span>
    <span class="dash-notif-text">
      <strong><?= $title ?></strong>
      <small><?= $body ?></small>
    </span>
    <svg class="dash-notif-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
  <?= $safeLink !== '' ? '</a>' : '</button>' ?>
</li>
