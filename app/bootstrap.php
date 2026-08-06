<?php

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/Core/Lang.php';

use App\Core\Env;

Env::load(BASE_PATH . '/.env');

date_default_timezone_set('Asia/Riyadh');
