<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/php/bootstrap.php';
require_once dirname(__DIR__) . '/php/auth/middleware.php';
require_role('admin');

$pending = db()->query(
    'SELECT o.*, u.email, u.full_name FROM organisations o
     INNER JOIN users u ON u.id = o.user_id
     WHERE o.verified = 0 AND o.rejected = 0
     ORDER BY o.created_at ASC'
)->fetchAll();

$reports = db()->query(
    'SELECT * FROM reports WHERE status = \'open\' ORDER BY created_at DESC LIMIT 20'
)->fetchAll();

$stats = [
    'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'campaigns' => (int) db()->query('SELECT COUNT(*) FROM campaigns WHERE status = \'active\'')->fetchColumn(),
    'donations' => (float) db()->query('SELECT COALESCE(SUM(amount),0) FROM donations WHERE status IN (\'verified\',\'completed\')')->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/tokens.css">
    <link rel="stylesheet" href="../css/status.css">
    <title>Admin — Sawa</title>
</head>
<body>
<main class="status-shell" style="display:block;max-width:900px;margin:2rem auto;padding:1rem;">
    <h1>Sawa Admin</h1>
    <p>Users: <?= $stats['users'] ?> · Active campaigns: <?= $stats['campaigns'] ?> · Verified donations: $<?= number_format($stats['donations'], 2) ?></p>
    <p><a href="userhome.php">← Dashboard</a></p>

    <h2>Pending organisations (<?= count($pending) ?>)</h2>
    <?php if (!$pending): ?>
        <p>No pending applications.</p>
    <?php else: ?>
        <?php foreach ($pending as $org): ?>
        <div class="status-detail" style="margin-bottom:1rem;">
            <strong><?= htmlspecialchars($org['name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
            <?= htmlspecialchars($org['full_name'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) $org['email'], ENT_QUOTES, 'UTF-8') ?><br>
            <form action="../php/admin/verify-organisation.php" method="POST" style="display:inline-flex;gap:0.5rem;margin-top:0.5rem;">
                <?= Csrf::field() ?>
                <input type="hidden" name="organisation_id" value="<?= (int) $org['id'] ?>">
                <button type="submit" name="action" value="approve" class="status-btn">Approve</button>
                <button type="submit" name="action" value="reject" class="status-btn secondary">Reject</button>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2>Open reports (<?= count($reports) ?>)</h2>
    <?php foreach ($reports as $r): ?>
        <div class="status-detail" style="margin-bottom:0.75rem;">
            #<?= (int) $r['id'] ?> — <?= htmlspecialchars($r['target_type'], ENT_QUOTES, 'UTF-8') ?> #<?= (int) $r['target_id'] ?> — <?= htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8') ?>
            <form action="../php/admin/moderate-content.php" method="POST" style="margin-top:0.35rem;">
                <?= Csrf::field() ?>
                <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
                <button type="submit" name="action" value="resolve" class="status-btn">Resolve</button>
                <button type="submit" name="action" value="dismiss" class="status-btn secondary">Dismiss</button>
            </form>
        </div>
    <?php endforeach; ?>
</main>
</body>
</html>
