<?php
declare(strict_types=1);
/** @var array<string, mixed> $camp */
$id = (int) $camp['id'];
$title = htmlspecialchars((string) $camp['title'], ENT_QUOTES, 'UTF-8');
$creator = htmlspecialchars((string) ($camp['creator_name'] ?? 'Sawa member'), ENT_QUOTES, 'UTF-8');
$raised = (float) $camp['raised_amount'];
$goal = (float) $camp['goal_amount'];
$pct = $goal > 0 ? min(100, (int) round(($raised / $goal) * 100)) : 0;
$priorityClass = Format::urgentPriority($camp);
$priorityLabel = Format::urgentPriorityLabel($camp);
$showDonate = empty($camp['_hide_donate']);
$badgeLabel = $priorityLabel;
if (!empty($camp['_pct_badge'])) {
    $badgeLabel = $pct . '%';
    $priorityClass = 'priority-medium';
}
?>
<article class="urgent-item">
  <div class="urgent-row">
    <div class="urgent-meta">
      <strong><?= $title ?></strong>
      <small><?= $creator ?></small>
    </div>
    <span class="urgent-priority <?= $priorityClass ?>"><?= htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <div class="urgent-progress-row">
    <small>Raised: <strong>$<?= number_format($raised) ?></strong></small>
    <small>$<?= number_format($goal) ?></small>
  </div>
  <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
  <?php if ($showDonate): ?>
  <button class="urgent-donate" data-camp-id="<?= $id ?>" data-camp-title="<?= $title ?>">Donate Now</button>
  <?php endif; ?>
</article>
