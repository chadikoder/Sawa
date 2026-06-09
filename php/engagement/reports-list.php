<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_role('admin');
$stmt = db()->query(
    'SELECT * FROM reports WHERE status = \'open\' ORDER BY created_at DESC LIMIT 100'
);
json_response(['reports' => $stmt->fetchAll()]);
