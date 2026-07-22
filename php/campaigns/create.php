<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}

Csrf::validate();
require_auth();
// Donors included: anyone signed in may raise a campaign. Organisations still
// have to be verified first (checked immediately below), and the owner is
// recorded either as the organisation or as the individual user.
require_role('user', 'beneficiary', 'organisation', 'admin');

if (Auth::role() === 'organisation' && !Auth::isOrganisationVerified()) {
    Response::redirect('pages/org-pending.html');
}

$title = Validator::sanitizeString((string) ($_POST['title'] ?? ''), 200);
$description = Validator::sanitizeString((string) ($_POST['description'] ?? ''), 5000);
$goal = (float) ($_POST['goal'] ?? 0);
$category = CampaignService::categorySlug((string) ($_POST['category'] ?? ''));
$location = CampaignService::locationSlug((string) ($_POST['location'] ?? ''));

if (mb_strlen($title) < 5 || $description === '' || $goal < 1 || !$category || !$location) {
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'campaign-new']);
}

$categoryId = CampaignService::lookupId('categories', $category);
$locationId = CampaignService::lookupId('locations', $location);
$userId = Auth::id();
$role = Auth::role();
$orgId = null;
$ownerUserId = null;

if ($role === 'organisation') {
    $stmt = db()->prepare('SELECT id FROM organisations WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $org = $stmt->fetch();
    if (!$org) {
        Response::redirectStatus('pages/userhome.php', 'error');
    }
    $orgId = (int) $org['id'];
} else {
    $ownerUserId = $userId;
}

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO campaigns (organisation_id, owner_user_id, title, description, goal_amount,
         category_id, location_id, status, ends_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, \'active\', DATE_ADD(NOW(), INTERVAL 90 DAY))'
    )->execute([$orgId, $ownerUserId, $title, $description, $goal, $categoryId, $locationId]);
    $campaignId = (int) $pdo->lastInsertId();

    $paths = [];
    $files = $_FILES['camp_images'] ?? null;
    if ($files && is_array($files['name'])) {
        $count = min(count($files['name']), MAX_CAMPAIGN_IMAGES);
        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $file = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
            $paths[] = Upload::store($file, 'campaigns/' . $campaignId);
        }
    }

    $coverIndex = max(0, (int) ($_POST['cover_image_index'] ?? 0));
    if ($paths !== []) {
        CampaignService::attachImages($campaignId, $paths, min($coverIndex, count($paths) - 1));
    }

    $pdo->commit();
} catch (Throwable) {
    $pdo->rollBack();
    Response::redirectStatus('pages/userhome.php', 'error', ['section' => 'campaign-new']);
}

Response::redirectStatus('pages/userhome.php', 'success', ['section' => 'campaigns']);
