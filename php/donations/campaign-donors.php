<?php
declare(strict_types=1);

/**
 * Confirmed donors for one campaign, as JSON.
 *
 * The Donors tab in the campaign modal was a hardcoded placeholder reading
 * "Donor details rendered by PHP after donations are loaded", so a donation
 * never appeared anywhere the donor could see it.
 *
 * Privacy is the whole difficulty here. A campaign page is public, so this
 * must never publish an identity the donor did not agree to publish:
 *
 *   - donations.anonymous = 1  ->  "Anonymous", no name at all
 *   - a guest donation (donor_id NULL) -> "Guest donor"; guest_name is
 *     collected for the receipt, not for display
 *   - otherwise the first name only, matching
 *     DonationService::recentPlatformActivity(), which made the same choice
 *     for the public activity feed
 *
 * Only 'verified' and 'completed' donations count — a pending or failed one is
 * not money that arrived, and listing it would overstate the campaign.
 */

require_once __DIR__ . '/../bootstrap.php';

$campaignId = (int) ($_GET['campaign_id'] ?? 0);
if ($campaignId < 1) {
    json_error('invalid_campaign', 422);
}

$stmt = db()->prepare(
    "SELECT d.amount, d.anonymous, d.donor_id, d.created_at,
            u.full_name AS donor_name, p.avatar_path
       FROM donations d
       LEFT JOIN users u ON u.id = d.donor_id
       LEFT JOIN user_profiles p ON p.user_id = d.donor_id
      WHERE d.campaign_id = ? AND d.status IN ('verified','completed')
      ORDER BY d.created_at DESC
      LIMIT 50"
);
$stmt->execute([$campaignId]);

$viewerId = Auth::check() ? (int) Auth::id() : 0;
$donors = [];
foreach ($stmt->fetchAll() as $row) {
    $anon = (int) $row['anonymous'] === 1;
    $isGuest = $row['donor_id'] === null;

    if ($anon) {
        $label = 'Anonymous';
    } elseif ($isGuest) {
        $label = 'Guest donor';
    } else {
        $label = explode(' ', trim((string) $row['donor_name']))[0] ?: 'Sawa donor';
    }

    $donors[] = [
        'label'  => $label,
        'amount' => (float) $row['amount'],
        'ago'    => Format::timeAgo((string) $row['created_at']),
        // Avatars only for named donors — showing one beside "Anonymous" would
        // undo the anonymity the donor asked for.
        'avatar' => (!$anon && !$isGuest && !empty($row['avatar_path']))
            ? Upload::publicUrl((string) $row['avatar_path'])
            : null,
        // Lets the viewer spot their own contribution in the list, without
        // exposing donor ids to anyone else.
        'isYou'  => !$anon && $viewerId > 0 && (int) $row['donor_id'] === $viewerId,
    ];
}

json_response(['ok' => true, 'count' => count($donors), 'donors' => $donors]);
