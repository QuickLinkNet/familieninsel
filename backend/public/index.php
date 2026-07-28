<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\PlayersController;
use App\Database\Connection;
use App\Database\Migrator;
use App\Database\Seeder;
use App\Middleware\Cors;
use App\Middleware\Csrf;
use App\Repositories\FamilyRepository;
use App\Repositories\PlayerRepository;
use App\Services\AuthService;
use App\Support\Router;
use App\Support\Session;

$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set($config['app']['timezone']);

Cors::handle($config['cors']['allowed_origins']);
Session::start($config['session']);

$pdo = Connection::make($config['database']['path']);
(new Migrator($pdo, $config['database']['migrations_path']))->run();
(new Seeder($pdo))->seedDemoFamilyIfEmpty();

$authService = new AuthService(new FamilyRepository($pdo), new PlayerRepository($pdo));
$authController = new AuthController(
    $authService,
    (int) $config['session']['parent_unlock_seconds'],
    (int) $config['security']['pin_max_attempts'],
    (int) $config['security']['pin_lockout_seconds'],
    dirname($config['database']['path']) . '/../logs',
);
$playersController = new PlayersController($authService);
$healthController = new HealthController($config['database']['path']);

$router = new Router();
$router->get('/health', [$healthController, 'show']);
$router->get('/auth/session', [$authController, 'session']);
$router->post('/auth/family-login', [$authController, 'familyLogin']);
$router->post('/auth/select-profile', [$authController, 'selectProfile']);
$router->post('/auth/parent-unlock', [$authController, 'parentUnlock']);
$router->post('/auth/logout', [$authController, 'logout']);
$router->get('/players', [$playersController, 'index']);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$scriptDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

$path = $scriptDirectory !== '' && str_starts_with($requestPath, $scriptDirectory)
    ? substr($requestPath, strlen($scriptDirectory))
    : $requestPath;

$path = '/' . ltrim($path, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!Csrf::check($method)) {
    exit;
}

$router->dispatch($method, $path);
