<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

require_auth();
$id = (int) ($_GET['donation_id'] ?? 0);
$stmt = db()->prepare(
    'SELECT * FROM donation_status_history WHERE donation_id = ? ORDER BY created_at ASC'
);
$stmt->execute([$id]);
json_response(['history' => $stmt->fetchAll()]);
