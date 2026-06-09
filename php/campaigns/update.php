<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_method('POST');
Csrf::validate();
require_auth();

$id = (int) ($_POST['campaign_id'] ?? 0);
$camp = CampaignService::find($id);
if (!$camp || !CampaignService::canManage(Auth::id(), Auth::role() ?? '', $camp)) {
    json_error('forbidden', 403);
}

$fields = [];
$params = [];
if (isset($_POST['title'])) {
    $fields[] = 'title = ?';
    $params[] = Validator::sanitizeString((string) $_POST['title'], 200);
}
if (isset($_POST['description'])) {
    $fields[] = 'description = ?';
    $params[] = Validator::sanitizeString((string) $_POST['description'], 5000);
}
if (isset($_POST['goal'])) {
    $fields[] = 'goal_amount = ?';
    $params[] = max(1, (float) $_POST['goal']);
}
if ($fields === []) {
    json_error('nothing_to_update', 422);
}

$params[] = $id;
db()->prepare('UPDATE campaigns SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
    json_response(['ok' => true]);
}
Response::redirectStatus('pages/userhome.php', 'success', ['section' => 'campaigns']);
