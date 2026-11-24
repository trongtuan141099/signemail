<?php
declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^|/)\.|^/(?:storage|data|bin)(?:/|$)|^/(?:app-server\.php|router\.php|database\.sql|README\.md)$~i', $path)) {
    http_response_code(404);
    exit;
}
if (preg_match('~^/uploads/brand(?:/|$)~i', $path) && !preg_match('~^/uploads/brand/[a-f0-9]{32}\.(?:png|gif)$~D', $path)) {
    http_response_code(404);
    exit;
}
if (preg_match('~^/blog/([a-z0-9]+(?:-[a-z0-9]+)*)/?$~D', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/post.php';
    return true;
}
return false;
