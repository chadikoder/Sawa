<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_role('admin');

$pdo = db();
json_response([
    'users'          => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'organisations'  => (int) $pdo->query('SELECT COUNT(*) FROM organisations')->fetchColumn(),
    'pending_orgs'   => (int) $pdo->query('SELECT COUNT(*) FROM organisations WHERE verified = 0 AND rejected = 0')->fetchColumn(),
    'active_campaigns' => (int) $pdo->query('SELECT COUNT(*) FROM campaigns WHERE status = \'active\'')->fetchColumn(),
    'donations'      => (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM donations WHERE status IN (\'verified\',\'completed\')')->fetchColumn(),
    'open_reports'   => (int) $pdo->query('SELECT COUNT(*) FROM reports WHERE status = \'open\'')->fetchColumn(),
]);
