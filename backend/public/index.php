<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ActivityController;
use App\Controllers\AuthController;
use App\Controllers\BuildingsController;
use App\Controllers\HealthController;
use App\Controllers\MinigamesController;
use App\Controllers\PlayersController;
use App\Controllers\ResourcesController;
use App\Controllers\TasksController;
use App\Database\Connection;
use App\Database\Migrator;
use App\Database\Seeder;
use App\Middleware\Cors;
use App\Middleware\Csrf;
use App\Repositories\ActivityLogRepository;
use App\Repositories\BuildingRepository;
use App\Repositories\FamilyBuildingRepository;
use App\Repositories\FamilyRepository;
use App\Repositories\MinigameRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTransactionRepository;
use App\Repositories\TaskRepository;
use App\Services\AuthService;
use App\Services\BuildingService;
use App\Services\MinigameService;
use App\Services\TaskService;
use App\Support\JsonResponse;
use App\Support\Logger;
use App\Support\Router;
use App\Support\Session;

$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set($config['app']['timezone']);

// Sicherheitsnetz: Eine unerwartete Ausnahme darf niemals einen rohen
// PHP-Fehler (Stacktrace, Dateipfade) an den Client durchreichen. Wird
// zusaetzlich zur serverseitigen display_errors=off-Konfiguration gehalten,
// damit die API auch bei falscher Serverkonfiguration ein sicheres JSON liefert.
set_exception_handler(static function (\Throwable $exception) use ($config): void {
    Logger::security(
        dirname($config['database']['path']) . '/../logs',
        'Unbehandelte Ausnahme: ' . get_class($exception) . ': ' . $exception->getMessage(),
    );
    JsonResponse::error(500, 'INTERNAL_ERROR', 'Es ist ein unerwarteter Fehler aufgetreten.');
});

Cors::handle($config['cors']['allowed_origins']);
Session::start($config['session']);

$pdo = Connection::make($config['database']['path']);
(new Migrator($pdo, $config['database']['migrations_path']))->run();
$seeder = new Seeder($pdo);
$seeder->seedDemoFamilyIfEmpty();
$seeder->seedResourceCatalogIfEmpty();
$seeder->seedDemoTasksIfEmpty();
$seeder->seedBuildingCatalogIfEmpty();
$seeder->seedActiveFamilyBuildingIfEmpty();
$seeder->seedMinigameCatalogIfEmpty();

$playerRepository = new PlayerRepository($pdo);
$resourceRepository = new ResourceRepository($pdo);
$activityLogRepository = new ActivityLogRepository($pdo);
$minigameRepository = new MinigameRepository($pdo);

$authService = new AuthService(new FamilyRepository($pdo), $playerRepository);
$authController = new AuthController(
    $authService,
    (int) $config['session']['parent_unlock_seconds'],
    (int) $config['security']['pin_max_attempts'],
    (int) $config['security']['pin_lockout_seconds'],
    dirname($config['database']['path']) . '/../logs',
);
$playersController = new PlayersController($authService);
$healthController = new HealthController($config['database']['path']);

$taskService = new TaskService(
    $pdo,
    new TaskRepository($pdo),
    $resourceRepository,
    new ResourceTransactionRepository($pdo),
    $activityLogRepository,
    $playerRepository,
);
$tasksController = new TasksController($taskService);
$resourcesController = new ResourcesController($resourceRepository);

$buildingService = new BuildingService(
    $pdo,
    new BuildingRepository($pdo),
    new FamilyBuildingRepository($pdo),
    $resourceRepository,
    new ResourceTransactionRepository($pdo),
    $activityLogRepository,
    $minigameRepository,
);
$buildingsController = new BuildingsController($buildingService);
$activityController = new ActivityController($activityLogRepository);

$minigameService = new MinigameService(
    $pdo,
    $minigameRepository,
    $resourceRepository,
    new ResourceTransactionRepository($pdo),
    $activityLogRepository,
);
$minigamesController = new MinigamesController($minigameService);

$router = new Router();
$router->get('/health', [$healthController, 'show']);
$router->get('/auth/session', [$authController, 'session']);
$router->post('/auth/family-login', [$authController, 'familyLogin']);
$router->post('/auth/select-profile', [$authController, 'selectProfile']);
$router->post('/auth/parent-unlock', [$authController, 'parentUnlock']);
$router->post('/auth/logout', [$authController, 'logout']);
$router->get('/players', [$playersController, 'index']);

$router->get('/tasks', [$tasksController, 'index']);
$router->get('/tasks/{id}', [$tasksController, 'show']);
$router->post('/tasks', [$tasksController, 'store']);
$router->post('/tasks/{id}/complete', [$tasksController, 'complete']);
$router->post('/tasks/{id}/approve', [$tasksController, 'approve']);
$router->post('/tasks/{id}/reject', [$tasksController, 'reject']);
$router->post('/tasks/{id}/reopen', [$tasksController, 'reopen']);
$router->delete('/tasks/{id}', [$tasksController, 'destroy']);

$router->get('/resources', [$resourcesController, 'index']);

$router->get('/buildings/active', [$buildingsController, 'active']);
$router->post('/buildings/{id}/contribute', [$buildingsController, 'contribute']);

$router->get('/activity', [$activityController, 'index']);

$router->get('/minigames', [$minigamesController, 'index']);
$router->get('/minigames/{key}', [$minigamesController, 'show']);
$router->post('/minigames/{key}/complete', [$minigamesController, 'complete']);

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
