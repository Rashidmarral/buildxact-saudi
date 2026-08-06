<?php
// Router for PHP's built-in development server: php -S localhost:8000 -t public public/router.php
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false; // serve the requested static file as-is
}

require __DIR__ . '/index.php';
