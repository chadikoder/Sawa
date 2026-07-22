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
      <?php
      /* Owner controls. Compared against the row we already selected rather
         than calling CampaignService::canManage(), which runs a query per
         card — this partial renders inside a grid loop. owner_user_id covers
         a campaign raised by an individual, org_user_id one raised by an
         organisation the viewer represents. */
      $viewerId = Auth::check() ? (int) Auth::id() : 0;
      $ownsCampaign = $viewerId > 0 && (
          (int) ($camp['owner_user_id'] ?? 0) === $viewerId
          || (int) ($camp['org_user_id'] ?? 0) === $viewerId
      );
      ?>
      <?php if ($ownsCampaign): ?>
        <form action="<?= url('php/campaigns/delete.php') ?>" method="POST" class="camp-delete-form"
              onsubmit="return confirm('Delete this campaign? If it has donations it will be withdrawn from public view instead, so the donation records are kept.');">
          <?= Csrf::field() ?>
          <input type="hidden" name="campaign_id" value="<?= $id ?>">
          <button type="submit" class="camp-delete" aria-label="Delete this campaign">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Delete
          </button>
        </form>
      <?php else: ?>
        <button class="camp-donate" data-camp-id="<?= $id ?>" data-camp-title="<?= $title ?>">Donate</button>
      <?php endif; ?>
    </div>
  </div>
</article>
