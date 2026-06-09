<?php
declare(strict_types=1);
/** @var array<string, mixed> $camp */
$id = (int) $camp['id'];
$title = htmlspecialchars((string) $camp['title'], ENT_QUOTES, 'UTF-8');
$summary = htmlspecialchars((string) ($camp['summary'] ?? mb_substr((string) $camp['description'], 0, 80)), ENT_QUOTES, 'UTF-8');
$raised = (float) $camp['raised_amount'];
$goal = (float) $camp['goal_amount'];
$pct = $goal > 0 ? min(100, (int) round(($raised / $goal) * 100)) : 0;
$catCss = CampaignService::categoryCss((string) ($camp['category_name'] ?? 'other'));
$urgent = CampaignService::isUrgent($camp);
?>
<article class="featured-camp" data-camp-id="<?= $id ?>" data-camp-title="<?= $title ?>">
  <div class="featured-camp-thumb <?= $catCss ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
    <?php if ($urgent): ?><span class="camp-urgent-badge">Urgent</span><?php endif; ?>
  </div>
  <div class="featured-camp-body">
    <strong><?= $title ?></strong>
    <small><?= $summary ?></small>
    <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
    <div class="featured-camp-meta">
      <span><strong>$<?= number_format($raised) ?></strong> of $<?= number_format($goal) ?></span>
      <button class="featured-camp-donate" data-camp-id="<?= $id ?>" data-camp-title="<?= $title ?>">Donate</button>
    </div>
  </div>
</article>
