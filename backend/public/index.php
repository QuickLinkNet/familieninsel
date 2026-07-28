<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\HealthController;
use App\Middleware\Cors;
use App\Support\Router;

$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set($config['app']['timezone']);

Cors::handle($config['cors']['allowed_origins']);

$router = new Router();

$healthController = new HealthController($config['database']['path']);
$router->get('/health', [$healthController, 'show']);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$scriptDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

$path = $scriptDirectory !== '' && str_starts_with($requestPath, $scriptDirectory)
    ? substr($requestPath, strlen($scriptDirectory))
    : $requestPath;

$path = '/' . ltrim($path, '/');

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
