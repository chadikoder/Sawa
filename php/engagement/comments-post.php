<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::redirect('pages/userhome.php');
}

Csrf::validate();
require_auth();

// The campaign modal posts this with fetch so the comment appears in place.
// The redirect path below is kept as the no-JavaScript fallback — the form
// still works on its own if the request did not come from that code.
$wantsJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$body = Validator::sanitizeString((string) ($_POST['body'] ?? ''), 500);

if ($campaignId < 1 || mb_strlen($body) < 2) {
    if ($wantsJson) { json_error('invalid_comment', 422); }
    Response::redirectStatus('pages/userhome.php', 'error');
}

$camp = CampaignService::find($campaignId);
if (!$camp || $camp['status'] !== 'active') {
    if ($wantsJson) { json_error('campaign_unavailable', 422); }
    Response::redirectStatus('pages/userhome.php', 'error');
}

db()->prepare('INSERT INTO comments (campaign_id, user_id, body) VALUES (?, ?, ?)')
    ->execute([$campaignId, Auth::id(), $body]);

if ($wantsJson) {
    // Echo the stored comment back in the same shape comments-list.php uses,
    // so the client can render it with the identical code path instead of
    // guessing at the author name or avatar it just posted under.
    $me = Auth::user();
    json_response([
        'ok' => true,
        'comment' => [
            'id'     => (int) db()->lastInsertId(),
            'body'   => $body,
            'author' => (string) ($me['full_name'] ?? 'You'),
            'avatar' => !empty($me['avatar_path']) ? Upload::publicUrl((string) $me['avatar_path']) : null,
            'ago'    => 'now',
        ],
    ]);
}

// Straight back to the campaign that was commented on, opened on the comments
// tab, so the new comment is visible where it was written. This is the
// opposite of the payment flow on purpose: a payment returns to the dashboard
// because reopening the campaign there buried the payment status modal, but a
// comment belongs to the campaign and losing your place is the wrong outcome.
Response::redirect('pages/userhome.php', [
    'status'   => 'comment_posted',
    'campaign' => $campaignId,
    'tab'      => 'comments',
]);
