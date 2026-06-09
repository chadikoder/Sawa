<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_role('admin');
$p = pagination_params();
$stmt = db()->prepare(
    'SELECT * FROM audit_log ORDER BY created_at DESC LIMIT ? OFFSET ?'
);
$stmt->bindValue(1, $p['per_page'], PDO::PARAM_INT);
$stmt->bindValue(2, $p['offset'], PDO::PARAM_INT);
$stmt->execute();
json_response(['items' => $stmt->fetchAll(), 'page' => $p['page']]);
