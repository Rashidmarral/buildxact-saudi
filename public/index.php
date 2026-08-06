<?php

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Lang;
use App\Core\Router;

session_start();
Lang::boot();

$router = new Router();
require __DIR__ . '/../app/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
