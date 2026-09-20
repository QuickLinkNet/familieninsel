<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use App\Database\Migrator;
use App\Database\Seeder;
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
use App\Services\RewardRevealService;
use App\Services\TaskService;
use PDO;
use PHPUnit\Framework\TestCase;

final class RewardRevealServiceTest extends TestCase
{
    private string $databasePath;
    private PDO $pdo;
    private TaskService $taskService;
    private RewardRevealService $rewardRevealService;
    private PlayerRepository $playerRepository;
    private int $familyId;
    private int $emilId;
    private int $manuelId;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-rewardreveal-' . uniqid() . '.sqlite';
        $this->pdo = Connection::make($this->databasePath);
        (new Migrator($this->pdo, dirname(__DIR__) . '/database/migrations'))->run();

        $seeder = new Seeder($this->pdo);
        $seeder->seedDemoFamilyIfEmpty();
        $seeder->seedResourceCatalogIfEmpty();
        $seeder->seedBuildingCatalogIfEmpty();
        $seeder->seedActiveFamilyBuildingIfEmpty();
        $seeder->seedMinigameCatalogIfEmpty();

        $authService = new AuthService(
            new FamilyRepository($this->pdo),
            new PlayerRepository($this->pdo),
            new PlayerLoginTokenRepository($this->pdo),
        );
        $this->familyId = (int) $this->pdo->query('SELECT id FROM families LIMIT 1')->fetchColumn();
        $players = $authService->listActivePlayers($this->familyId);
        $byName = [];
        foreach ($players as $player) {
            $byName[$player['name']] = (int) $player['id'];
        }
        $this->emilId = $byName['Emil'];
        $this->manuelId = $byName['Manuel'];

        $this->playerRepository = new PlayerRepository($this->pdo);
        $resources = new ResourceRepository($this->pdo);
        $activityLog = new ActivityLogRepository($this->pdo);

        $buildingService = new BuildingService(
            $this->pdo,
            new BuildingRepository($this->pdo),
            new FamilyBuildingRepository($this->pdo),
            $resources,
            new ResourceTransactionRepository($this->pdo),
            $activityLog,
            new MinigameRepository($this->pdo),
        );

        $this->taskService = new TaskService(
            $this->pdo,
            new TaskRepository($this->pdo),
            $resources,
            new ResourceTransactionRepository($this->pdo),
            $activityLog,
            $this->playerRepository,
            $buildingService,
        );

        $this->rewardRevealService = new RewardRevealService(
            $this->playerRepository,
            new ResourceTransactionRepository($this->pdo),
            $activityLog,
            $resources,
            new BuildingRepository($this->pdo),
        );

        // Der frische Seed-Cursor (Migration 0008) liegt "jetzt" - fuer die
        // Tests soll aber alles seit Spielerstellung als "neu" zaehlen.
        $this->pdo->exec('UPDATE players SET last_reward_seen_at = NULL');
    }

    protected function tearDown(): void
    {
        Connection::reset();
        gc_collect_cycles();
        if (file_exists($this->databasePath)) {
            @unlink($this->databasePath);
        }
    }

    private function createAndApproveTask(string $title, array $rewards): int
    {
        $result = $this->taskService->createTask(
            $this->familyId,
            $this->manuelId,
            $this->emilId,
            $title,
            null,
            null,
            $rewards,
        );
        $taskId = $result['taskId'];
        $this->taskService->completeTask($taskId, $this->familyId, $this->emilId);
        $this->taskService->approveTask($taskId, $this->familyId, $this->manuelId);

        return $taskId;
    }

    public function testNoUpdatesWhenNothingApprovedYet(): void
    {
        $result = $this->rewardRevealService->getUpdatesForPlayer($this->familyId, $this->emilId);

        self::assertFalse($result['hasUpdates']);
        self::assertSame([], $result['events']);
    }

    public function testSingleApprovedTaskProducesOneEventWithBuildingProgress(): void
    {
        $taskId = $this->createAndApproveTask('Zimmer aufraeumen', ['wood' => 10]);

        $result = $this->rewardRevealService->getUpdatesForPlayer($this->familyId, $this->emilId);

        self::assertTrue($result['hasUpdates']);
        self::assertCount(1, $result['events']);

        $event = $result['events'][0];
        self::assertSame($taskId, $event['taskId']);
        self::assertSame('Zimmer aufraeumen', $event['taskTitle']);
        self::assertSame([['resourceKey' => 'wood', 'amount' => 10]], $event['rewards']);
        self::assertNotNull($event['building']);
        self::assertSame('beach_hut', $event['building']['key']);
        self::assertSame(0, $event['building']['beforePercent']);
        self::assertGreaterThan(0, $event['building']['afterPercent']);
        self::assertFalse($event['building']['justCompleted']);
    }

    public function testMultipleApprovedTasksProduceSeparateEventsInOrder(): void
    {
        $firstId = $this->createAndApproveTask('Tisch decken', ['wood' => 3]);
        $secondId = $this->createAndApproveTask('Spielzeug wegraeumen', ['metal' => 2]);

        $result = $this->rewardRevealService->getUpdatesForPlayer($this->familyId, $this->emilId);

        self::assertCount(2, $result['events']);
        self::assertSame($firstId, $result['events'][0]['taskId']);
        self::assertSame($secondId, $result['events'][1]['taskId']);
    }

    public function testTaskWithoutActiveBuildingHasNullBuildingInfo(): void
    {
        $this->pdo->exec("UPDATE family_buildings SET status = 'completed' WHERE family_id = {$this->familyId}");

        $this->createAndApproveTask('Waesche sortieren', ['wood' => 2]);

        $result = $this->rewardRevealService->getUpdatesForPlayer($this->familyId, $this->emilId);

        self::assertCount(1, $result['events']);
        self::assertNull($result['events'][0]['building']);
    }

    public function testAcknowledgingAdvancesCursorAndHidesSeenRewards(): void
    {
        $this->createAndApproveTask('Pflanzen giessen', ['wood' => 1]);

        $before = $this->rewardRevealService->getUpdatesForPlayer($this->familyId, $this->emilId);
        self::assertTrue($before['hasUpdates']);

        $this->playerRepository->markRewardsSeen($this->emilId);

        $after = $this->rewardRevealService->getUpdatesForPlayer($this->familyId, $this->emilId);
        self::assertFalse($after['hasUpdates']);

        $this->createAndApproveTask('Muell rausbringen', ['wood' => 1]);
        $latest = $this->rewardRevealService->getUpdatesForPlayer($this->familyId, $this->emilId);
        self::assertTrue($latest['hasUpdates']);
        self::assertCount(1, $latest['events']);
    }

    public function testOtherChildDoesNotSeeSiblingsRewards(): void
    {
        $players = (new PlayerRepository($this->pdo))->findActiveByFamily($this->familyId);
        $theaId = null;
        foreach ($players as $player) {
            if ($player['name'] === 'Thea') {
                $theaId = (int) $player['id'];
            }
        }
        self::assertNotNull($theaId);

        $this->createAndApproveTask('Emils Aufgabe', ['wood' => 5]);

        $result = $this->rewardRevealService->getUpdatesForPlayer($this->familyId, $theaId);
        self::assertFalse($result['hasUpdates']);
    }
}
