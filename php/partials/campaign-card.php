<?php
declare(strict_types=1);
/** @var array<string, mixed> $camp */
$raised = (float) $camp['raised_amount'];
$goal = (float) $camp['goal_amount'];
$pct = $goal > 0 ? min(100, (int) round(($raised / $goal) * 100)) : 0;
$catName = (string) ($camp['category_name'] ?? 'Other');
$catCss = CampaignService::categoryCss($catName);
$locSlug = (string) ($camp['location_slug'] ?? '');
$locLabel = $locSlug !== '' ? CampaignService::locationLabel($locSlug) : '';
$urgent = CampaignService::isUrgent($camp) ? 'true' : 'false';
$donors = (int) ($camp['donor_count'] ?? 0);
$id = (int) $camp['id'];
$title = htmlspecialchars((string) $camp['title'], ENT_QUOTES, 'UTF-8');
$desc = htmlspecialchars(mb_substr((string) $camp['description'], 0, 160), ENT_QUOTES, 'UTF-8');
$images = !empty($camp['image_paths'])
    ? implode('|', array_map(fn ($p) => Upload::publicUrl($p), explode('|', (string) $camp['image_paths'])))
    : '';
$cover = !empty($camp['cover_image']) ? Upload::publicUrl((string) $camp['cover_image']) : '';
$dataImage = $cover !== '' ? ' data-image="' . htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') . '"' : '';
$dataImages = $images !== '' ? ' data-images="' . htmlspecialchars($images, ENT_QUOTES, 'UTF-8') . '"' : '';
$orgId = !empty($camp['org_user_id']) ? (int) $camp['org_user_id'] : '';
$creator = htmlspecialchars((string) ($camp['creator_name'] ?? 'Sawa member'), ENT_QUOTES, 'UTF-8');
?>
<article class="camp-card" data-category="<?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>"
  data-camp-id="<?= $id ?>" data-camp-title="<?= $title ?>" data-urgent="<?= $urgent ?>"
  data-location="<?= htmlspecialchars($locLabel, ENT_QUOTES, 'UTF-8') ?>"
  data-raised="<?= (int) $raised ?>" data-goal="<?= (int) $goal ?>"
  data-description="<?= $desc ?>" data-creator="<?= $creator ?>"
  <?= $orgId ? 'data-org-id="' . $orgId . '"' : '' ?>
  <?= $dataImage ?><?= $dataImages ?>>
  <div class="camp-card-media <?= $catCss ?>"<?= $dataImage ?>>
    <span class="camp-cat-tag"><?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?></span>
    <?php if ($urgent === 'true'): ?><span class="camp-urgent-badge">Urgent</span><?php endif; ?>
  </div>
  <div class="camp-card-content">
    <h3 class="camp-card-title"><?= $title ?></h3>
    <p class="camp-card-desc"><?= $desc ?></p>
    <div class="camp-progress-info">
      <span class="camp-raised"><strong>$<?= number_format($raised) ?></strong> raised</span>
      <span class="camp-pct"><?= $pct ?>%</span>
    </div>
    <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
    <div class="camp-card-footer">
      <span class="camp-donor-count"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <?= $donors ?> donor<?= $donors === 1 ? '' : 's' ?></span>
      <button class="camp-donate" data-camp-id="<?= $id ?>" data-camp-title="<?= $title ?>">Donate</button>
    </div>
  </div>
</article>
