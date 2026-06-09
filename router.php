<?php
declare(strict_types=1);

/**
 * PHP built-in server router:
 *   php -S localhost:8000 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$root = __DIR__;

if (str_starts_with($uri, '/storage/uploads/')) {
    $file = $root . str_replace('/', DIRECTORY_SEPARATOR, $uri);
    if (is_file($file)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        return true;
    }
    http_response_code(404);
    return true;
}

$file = $root . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && is_file($file)) {
    return false;
}

if ($uri === '/' || $uri === '') {
    require $root . DIRECTORY_SEPARATOR . 'index.html';
    return true;
}

http_response_code(404);
require $root . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . '404.html';
return true;
