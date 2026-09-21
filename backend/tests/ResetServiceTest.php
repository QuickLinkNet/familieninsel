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
use App\Services\ResetService;
use App\Services\TaskService;
use PDO;
use PHPUnit\Framework\TestCase;

final class ResetServiceTest extends TestCase
{
    private string $databasePath;
    private PDO $pdo;
    private TaskService $taskService;
    private ResetService $resetService;
    private PlayerRepository $playerRepository;
    private int $familyId;
    private int $emilId;
    private int $manuelId;
    private int $woodResourceId;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-reset-' . uniqid() . '.sqlite';
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
        $this->woodResourceId = (int) $resources->findIdByKey('wood');
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

        $this->resetService = new ResetService(
            $this->pdo,
            $this->playerRepository,
            new TaskRepository($this->pdo),
            $resources,
            new ResourceTransactionRepository($this->pdo),
            new FamilyBuildingRepository($this->pdo),
            new BuildingRepository($this->pdo),
            new MinigameRepository($this->pdo),
            $activityLog,
        );
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
        $result = $this->taskService->createTask($this->familyId, $this->manuelId, $this->emilId, $title, null, null, $rewards);
        $taskId = $result['taskId'];
        $this->taskService->completeTask($taskId, $this->familyId, $this->emilId);
        $this->taskService->approveTask($taskId, $this->familyId, $this->manuelId);

        return $taskId;
    }

    public function testResetIntroClearsIntroSeenAt(): void
    {
        $this->playerRepository->markIntroSeen($this->emilId);

        $result = $this->resetService->resetIntroForPlayer($this->familyId, $this->emilId);

        self::assertTrue($result['success']);
        $player = $this->playerRepository->findByIdAndFamily($this->emilId, $this->familyId);
        self::assertArrayHasKey('intro_seen_at', $player);
        self::assertNull($player['intro_seen_at']);
    }

    public function testResetIntroFailsForUnknownPlayer(): void
    {
        $result = $this->resetService->resetIntroForPlayer($this->familyId, 999999);

        self::assertFalse($result['success']);
        self::assertSame('PLAYER_NOT_FOUND', $result['code']);
    }

    public function testResetRewardsClearsCursor(): void
    {
        $this->playerRepository->markRewardsSeen($this->emilId);
        self::assertNotNull($this->playerRepository->findRewardCursor($this->emilId));

        $result = $this->resetService->resetRewardsCursorForPlayer($this->familyId, $this->emilId);

        self::assertTrue($result['success']);
        self::assertNull($this->playerRepository->findRewardCursor($this->emilId));
    }

    public function testResetTasksReopensTaskAndCleansTaskLedgerWithoutTouchingBuildingProgress(): void
    {
        // Strandhuette braucht 20 Holz - die Belohnung von 3 fliesst komplett
        // automatisch ins Bauprojekt (siehe TaskService::approveTask), nicht
        // ins Familienlager. Der Reset darf diesen bereits erzielten
        // Baufortschritt nicht ruecknehmen - dafuer gibt es den separaten
        // Familien-Reset.
        $taskId = $this->createAndApproveTask('Zimmer aufraeumen', ['wood' => 3]);

        $contributedBefore = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM building_contributions WHERE resource_id = {$this->woodResourceId}",
        )->fetchColumn();
        self::assertSame(3, $contributedBefore);

        $result = $this->resetService->resetTasksForPlayer($this->familyId, $this->emilId);
        self::assertTrue($result['success']);

        $task = $this->pdo->query("SELECT * FROM tasks WHERE id = {$taskId}")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('open', $task['status']);
        self::assertNull($task['completed_at']);
        self::assertNull($task['approved_at']);
        self::assertNull($task['rewarded_at']);

        $transactionCount = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM resource_transactions WHERE reference_type = 'task' AND reference_id = {$taskId}",
        )->fetchColumn();
        self::assertSame(0, $transactionCount);

        // Der bereits erzielte Baufortschritt bleibt unangetastet.
        $contributedAfter = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM building_contributions WHERE resource_id = {$this->woodResourceId}",
        )->fetchColumn();
        self::assertSame(3, $contributedAfter);
    }

    public function testResetTasksAllowsRedoingTheSameTaskFromScratch(): void
    {
        $taskId = $this->createAndApproveTask('Zimmer aufraeumen', ['wood' => 3]);
        $this->resetService->resetTasksForPlayer($this->familyId, $this->emilId);

        $complete = $this->taskService->completeTask($taskId, $this->familyId, $this->emilId);
        self::assertTrue($complete['success']);

        $approve = $this->taskService->approveTask($taskId, $this->familyId, $this->manuelId);
        self::assertTrue($approve['success']);

        // Zweite Runde investiert erneut 3 Holz ins Bauprojekt (insgesamt 6
        // von 20 benoetigten) - die Aufgabe war wirklich sauber wiederholbar.
        $contributedTotal = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM building_contributions WHERE resource_id = {$this->woodResourceId}",
        )->fetchColumn();
        self::assertSame(6, $contributedTotal);
    }

    public function testResetFamilyProgressWipesEverythingAndRestartsBeachHut(): void
    {
        $this->createAndApproveTask('Zimmer aufraeumen', ['wood' => 25]);

        $this->resetService->resetFamilyProgress($this->familyId);

        $woodBalance = (int) $this->pdo->query(
            "SELECT amount FROM family_resources WHERE family_id = {$this->familyId} AND resource_id = {$this->woodResourceId}",
        )->fetchColumn();
        self::assertSame(0, $woodBalance);

        $buildingCount = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM family_buildings WHERE family_id = {$this->familyId}",
        )->fetchColumn();
        self::assertSame(1, $buildingCount);

        $beachHut = $this->pdo->query(
            "SELECT fb.* FROM family_buildings fb JOIN buildings b ON b.id = fb.building_id
             WHERE fb.family_id = {$this->familyId} AND b.key = 'beach_hut'",
        )->fetch(PDO::FETCH_ASSOC);
        self::assertSame('in_progress', $beachHut['status']);
        self::assertSame(1, (int) $beachHut['stage']);

        $activityCount = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM activity_log WHERE family_id = {$this->familyId}",
        )->fetchColumn();
        self::assertSame(0, $activityCount);

        $tasksOpen = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM tasks WHERE family_id = {$this->familyId} AND status = 'open'",
        )->fetchColumn();
        self::assertGreaterThan(0, $tasksOpen);
    }

    public function testResetFamilyProgressDoesNotTouchIntroOrRewardCursor(): void
    {
        $this->playerRepository->markIntroSeen($this->emilId);
        $this->playerRepository->markRewardsSeen($this->emilId);

        $this->resetService->resetFamilyProgress($this->familyId);

        self::assertNotNull($this->playerRepository->findByIdAndFamily($this->emilId, $this->familyId)['intro_seen_at']);
        self::assertNotNull($this->playerRepository->findRewardCursor($this->emilId));
    }
}
