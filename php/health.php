<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    db()->query('SELECT 1');
    $dbOk = true;
} catch (Throwable) {
    $dbOk = false;
}

echo json_encode([
    'ok'      => $dbOk,
    'env'     => APP_ENV,
    'session' => session_status() === PHP_SESSION_ACTIVE,
    'csrf'    => strlen(Csrf::token()) >= 32,
], JSON_THROW_ON_ERROR);
