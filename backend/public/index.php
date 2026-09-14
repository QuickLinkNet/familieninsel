<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ActivityController;
use App\Controllers\AuthController;
use App\Controllers\BuildingsController;
use App\Controllers\HealthController;
use App\Controllers\MinigamesController;
use App\Controllers\PlayerPhotoController;
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
use App\Repositories\PlayerLoginTokenRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTransactionRepository;
use App\Repositories\TaskRepository;
use App\Services\AuthService;
use App\Services\BuildingService;
use App\Services\MinigameService;
use App\Services\PlayerPhotoService;
use App\Services\PlayerService;
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
$seeder->seedWatchtowerBuildingIfMissing();
$seeder->seedWatchtowerUnlockForCompletedBeachHuts();
$seeder->seedParentPinsIfMissing();
$seeder->seedParentPinsFromLegacyPasswords();

$playerRepository = new PlayerRepository($pdo);
$playerLoginTokenRepository = new PlayerLoginTokenRepository($pdo);
$resourceRepository = new ResourceRepository($pdo);
$activityLogRepository = new ActivityLogRepository($pdo);
$minigameRepository = new MinigameRepository($pdo);

$authService = new AuthService(new FamilyRepository($pdo), $playerRepository, $playerLoginTokenRepository);
$authController = new AuthController(
    $authService,
    (int) $config['security']['login_max_attempts'],
    (int) $config['security']['login_lockout_seconds'],
    (int) $config['session']['child_session_lifetime_seconds'],
    dirname($config['database']['path']) . '/../logs',
);
$photoService = new PlayerPhotoService(dirname($config['database']['path']) . '/../photos');
$playerService = new PlayerService($pdo, $playerRepository, $playerLoginTokenRepository);
$playersController = new PlayersController($authService, $photoService, $playerService);
$playerPhotoController = new PlayerPhotoController($photoService, $playerRepository);
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
$router->get('/auth/parents', [$authController, 'parents']);
$router->post('/auth/parent-login', [$authController, 'parentLogin']);
$router->post('/auth/qr-login', [$authController, 'qrLogin']);
$router->post('/auth/logout', [$authController, 'logout']);
$router->get('/players', [$playersController, 'index']);
$router->post('/players', [$playersController, 'store']);
$router->get('/players/manage', [$playersController, 'manage']);
$router->put('/players/{id}', [$playersController, 'update']);
$router->post('/players/{id}/pin', [$playersController, 'setPin']);
$router->post('/players/{id}/activate', [$playersController, 'activate']);
$router->post('/players/{id}/deactivate', [$playersController, 'deactivate']);
$router->get('/players/{id}/login-token', [$playersController, 'loginTokenStatus']);
$router->post('/players/{id}/login-token', [$playersController, 'regenerateLoginToken']);
$router->delete('/players/{id}/login-token', [$playersController, 'revokeLoginToken']);
$router->get('/players/{id}/photo', [$playerPhotoController, 'show']);
$router->post('/players/{id}/photo', [$playerPhotoController, 'upload']);

$router->get('/tasks', [$tasksController, 'index']);
$router->get('/tasks/{id}', [$tasksController, 'show']);
$router->post('/tasks', [$tasksController, 'store']);
$router->post('/tasks/{id}/complete', [$tasksController, 'complete']);
$router->post('/tasks/{id}/approve', [$tasksController, 'approve']);
$router->post('/tasks/{id}/reject', [$tasksController, 'reject']);
$router->post('/tasks/{id}/reopen', [$tasksController, 'reopen']);
$router->delete('/tasks/{id}', [$tasksController, 'destroy']);

$router->get('/resources', [$resourcesController, 'index']);

$router->get('/buildings', [$buildingsController, 'index']);
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
