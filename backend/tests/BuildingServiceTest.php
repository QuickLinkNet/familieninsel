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
use App\Repositories\PlayerRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTransactionRepository;
use App\Services\AuthService;
use App\Services\BuildingService;
use PDO;
use PHPUnit\Framework\TestCase;

final class BuildingServiceTest extends TestCase
{
    private string $databasePath;
    private PDO $pdo;
    private BuildingService $buildingService;
    private ResourceRepository $resourceRepository;
    private int $familyId;
    private int $manuelId;
    private int $woodResourceId;
    private int $metalResourceId;
    private int $fabricResourceId;
    private int $ropeResourceId;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-buildings-' . uniqid() . '.sqlite';
        $this->pdo = Connection::make($this->databasePath);
        (new Migrator($this->pdo, dirname(__DIR__) . '/database/migrations'))->run();

        $seeder = new Seeder($this->pdo);
        $seeder->seedDemoFamilyIfEmpty();
        $seeder->seedResourceCatalogIfEmpty();
        $seeder->seedBuildingCatalogIfEmpty();
        $seeder->seedActiveFamilyBuildingIfEmpty();

        $this->resourceRepository = new ResourceRepository($this->pdo);
        $authService = new AuthService(new FamilyRepository($this->pdo), new PlayerRepository($this->pdo));
        $this->familyId = (int) $this->pdo->query('SELECT id FROM families LIMIT 1')->fetchColumn();
        $players = $authService->listActivePlayers($this->familyId);
        $byName = [];
        foreach ($players as $player) {
            $byName[$player['name']] = (int) $player['id'];
        }
        $this->manuelId = $byName['Manuel'];

        $this->woodResourceId = (int) $this->resourceRepository->findIdByKey('wood');
        $this->metalResourceId = (int) $this->resourceRepository->findIdByKey('metal');
        $this->fabricResourceId = (int) $this->resourceRepository->findIdByKey('fabric');
        $this->ropeResourceId = (int) $this->resourceRepository->findIdByKey('rope');

        $this->buildingService = new BuildingService(
            $this->pdo,
            new BuildingRepository($this->pdo),
            new FamilyBuildingRepository($this->pdo),
            $this->resourceRepository,
            new ResourceTransactionRepository($this->pdo),
            new ActivityLogRepository($this->pdo),
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

    private function grantResources(int $resourceId, int $amount): void
    {
        $this->resourceRepository->incrementBalance($this->familyId, $resourceId, $amount);
    }

    public function testInitialBuildingIsInProgressAtStageOneWithZeroProgress(): void
    {
        $building = $this->buildingService->getActiveBuildingForFamily($this->familyId);

        self::assertSame('in_progress', $building['status']);
        self::assertSame(1, $building['stage']);
        self::assertSame(0, $building['progressPercent']);
        self::assertSame('beach_hut', $building['key']);
    }

    public function testContributeFailsWithoutSufficientBalance(): void
    {
        $result = $this->buildingService->contribute($this->familyId, $this->manuelId, ['wood' => 5]);

        self::assertFalse($result['success']);
        self::assertSame('INSUFFICIENT_RESOURCES', $result['code']);
    }

    public function testPartialContributionUpdatesProgressAndDeductsBalance(): void
    {
        $this->grantResources($this->woodResourceId, 10);

        $result = $this->buildingService->contribute($this->familyId, $this->manuelId, ['wood' => 10]);
        self::assertTrue($result['success']);
        self::assertFalse($result['justCompleted']);

        $building = $this->buildingService->getActiveBuildingForFamily($this->familyId);
        self::assertSame('in_progress', $building['status']);
        self::assertGreaterThan(0, $building['progressPercent']);
        self::assertLessThan(100, $building['progressPercent']);

        $balances = $this->resourceRepository->findFamilyBalances($this->familyId);
        self::assertSame(0, $balances[$this->woodResourceId] ?? 0);
    }

    public function testOverContributingCapsAtStillNeededAmount(): void
    {
        // Holz-Bedarf der Strandhuette ist 20; wir haben 100 Holz und zahlen alles auf einmal ein.
        $this->grantResources($this->woodResourceId, 100);

        $this->buildingService->contribute($this->familyId, $this->manuelId, ['wood' => 100]);

        $balances = $this->resourceRepository->findFamilyBalances($this->familyId);
        // Nur 20 (der tatsaechliche Bedarf) duerfen abgezogen worden sein, der Rest bleibt im Lager.
        self::assertSame(80, $balances[$this->woodResourceId] ?? 0);
    }

    public function testFullContributionCompletesBuildingExactlyOnce(): void
    {
        $this->grantResources($this->woodResourceId, 20);
        $this->grantResources($this->metalResourceId, 10);
        $this->grantResources($this->fabricResourceId, 8);
        $this->grantResources($this->ropeResourceId, 5);

        $result = $this->buildingService->contribute($this->familyId, $this->manuelId, [
            'wood' => 20,
            'metal' => 10,
            'fabric' => 8,
            'rope' => 5,
        ]);

        self::assertTrue($result['success']);
        self::assertTrue($result['justCompleted']);

        $building = $this->buildingService->getActiveBuildingForFamily($this->familyId);
        self::assertSame('completed', $building['status']);
        self::assertSame(5, $building['stage']);
        self::assertSame(100, $building['progressPercent']);
        self::assertNotNull($building['completedAt']);
    }

    public function testContributingAfterCompletionIsRejected(): void
    {
        $this->grantResources($this->woodResourceId, 20);
        $this->grantResources($this->metalResourceId, 10);
        $this->grantResources($this->fabricResourceId, 8);
        $this->grantResources($this->ropeResourceId, 5);
        $this->buildingService->contribute($this->familyId, $this->manuelId, [
            'wood' => 20, 'metal' => 10, 'fabric' => 8, 'rope' => 5,
        ]);

        $this->grantResources($this->woodResourceId, 5);
        $result = $this->buildingService->contribute($this->familyId, $this->manuelId, ['wood' => 5]);

        self::assertFalse($result['success']);
        self::assertSame('BUILDING_ALREADY_COMPLETED', $result['code']);

        // Die zusaetzlichen 5 Holz duerfen nicht abgezogen worden sein.
        $balances = $this->resourceRepository->findFamilyBalances($this->familyId);
        self::assertSame(5, $balances[$this->woodResourceId] ?? 0);
    }

    public function testResourceBalanceNeverGoesNegative(): void
    {
        $this->grantResources($this->woodResourceId, 3);

        $result = $this->buildingService->contribute($this->familyId, $this->manuelId, ['wood' => 3, 'metal' => 1]);

        self::assertFalse($result['success']);
        self::assertSame('INSUFFICIENT_RESOURCES', $result['code']);

        $balances = $this->resourceRepository->findFamilyBalances($this->familyId);
        self::assertSame(3, $balances[$this->woodResourceId] ?? 0);
        self::assertGreaterThanOrEqual(0, $balances[$this->metalResourceId] ?? 0);
    }
}
