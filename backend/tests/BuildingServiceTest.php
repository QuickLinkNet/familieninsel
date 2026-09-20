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
    private int $beachHutBuildingId;
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
        $seeder->seedMinigameCatalogIfEmpty();
        $seeder->seedWatchtowerBuildingIfMissing();

        $this->resourceRepository = new ResourceRepository($this->pdo);
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
        $this->manuelId = $byName['Manuel'];

        $this->beachHutBuildingId = (int) $this->pdo->query("SELECT id FROM buildings WHERE key = 'beach_hut'")->fetchColumn();

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
            new MinigameRepository($this->pdo),
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

    /**
     * @return array<string, mixed>|null
     */
    private function findBuildingByKey(string $key): ?array
    {
        foreach ($this->buildingService->getBuildingsForFamily($this->familyId) as $building) {
            if ($building['key'] === $key) {
                return $building;
            }
        }

        return null;
    }

    private function contributeToBeachHut(array $amounts): array
    {
        return $this->buildingService->contribute($this->familyId, $this->manuelId, $this->beachHutBuildingId, $amounts);
    }

    public function testInitialBuildingIsInProgressAtStageOneWithZeroProgress(): void
    {
        $building = $this->findBuildingByKey('beach_hut');

        self::assertNotNull($building);
        self::assertSame('in_progress', $building['status']);
        self::assertSame(1, $building['stage']);
        self::assertSame(0, $building['progressPercent']);
    }

    public function testWatchtowerIsNotYetUnlockedForNewFamily(): void
    {
        self::assertNull($this->findBuildingByKey('watchtower'));
    }

    public function testContributeFailsWithoutSufficientBalance(): void
    {
        $result = $this->contributeToBeachHut(['wood' => 5]);

        self::assertFalse($result['success']);
        self::assertSame('INSUFFICIENT_RESOURCES', $result['code']);
    }

    public function testContributingToUnknownBuildingIsRejected(): void
    {
        $this->grantResources($this->woodResourceId, 5);

        $result = $this->buildingService->contribute($this->familyId, $this->manuelId, 999999, ['wood' => 5]);

        self::assertFalse($result['success']);
        self::assertSame('BUILDING_NOT_FOUND', $result['code']);
    }

    public function testPartialContributionUpdatesProgressAndDeductsBalance(): void
    {
        $this->grantResources($this->woodResourceId, 10);

        $result = $this->contributeToBeachHut(['wood' => 10]);
        self::assertTrue($result['success']);
        self::assertFalse($result['justCompleted']);

        $building = $this->findBuildingByKey('beach_hut');
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

        $this->contributeToBeachHut(['wood' => 100]);

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

        $result = $this->contributeToBeachHut([
            'wood' => 20,
            'metal' => 10,
            'fabric' => 8,
            'rope' => 5,
        ]);

        self::assertTrue($result['success']);
        self::assertTrue($result['justCompleted']);

        $building = $this->findBuildingByKey('beach_hut');
        self::assertSame('completed', $building['status']);
        self::assertSame(5, $building['stage']);
        self::assertSame(100, $building['progressPercent']);
        self::assertNotNull($building['completedAt']);
    }

    public function testCompletingBuildingUnlocksLinkedMinigame(): void
    {
        $this->grantResources($this->woodResourceId, 20);
        $this->grantResources($this->metalResourceId, 10);
        $this->grantResources($this->fabricResourceId, 8);
        $this->grantResources($this->ropeResourceId, 5);

        $this->contributeToBeachHut(['wood' => 20, 'metal' => 10, 'fabric' => 8, 'rope' => 5]);

        $minigameId = (int) $this->pdo->query("SELECT id FROM minigames WHERE key = 'schatzsuche'")->fetchColumn();
        $status = (new MinigameRepository($this->pdo))->findFamilyStatus($this->familyId, $minigameId);

        self::assertNotNull($status);
        self::assertNotNull($status['unlocked_at']);
        self::assertNull($status['first_completion_at']);
    }

    public function testCompletingBeachHutUnlocksWatchtower(): void
    {
        $this->grantResources($this->woodResourceId, 20);
        $this->grantResources($this->metalResourceId, 10);
        $this->grantResources($this->fabricResourceId, 8);
        $this->grantResources($this->ropeResourceId, 5);

        $this->contributeToBeachHut(['wood' => 20, 'metal' => 10, 'fabric' => 8, 'rope' => 5]);

        $watchtower = $this->findBuildingByKey('watchtower');
        self::assertNotNull($watchtower);
        self::assertSame('in_progress', $watchtower['status']);
        self::assertSame(1, $watchtower['stage']);
        self::assertSame(0, $watchtower['progressPercent']);
    }

    public function testWatchtowerCanBeContributedToIndependentlyOfBeachHut(): void
    {
        $this->grantResources($this->woodResourceId, 35);
        $this->grantResources($this->metalResourceId, 10);
        $this->grantResources($this->fabricResourceId, 8);
        $this->grantResources($this->ropeResourceId, 5);
        $this->contributeToBeachHut(['wood' => 20, 'metal' => 10, 'fabric' => 8, 'rope' => 5]);

        $watchtower = $this->findBuildingByKey('watchtower');
        self::assertNotNull($watchtower);

        $result = $this->buildingService->contribute(
            $this->familyId,
            $this->manuelId,
            (int) $watchtower['id'],
            ['wood' => 15],
        );

        self::assertTrue($result['success']);

        $updatedWatchtower = $this->findBuildingByKey('watchtower');
        self::assertGreaterThan(0, $updatedWatchtower['progressPercent']);

        // Die Strandhuette bleibt davon unberuehrt (bereits fertig).
        $beachHut = $this->findBuildingByKey('beach_hut');
        self::assertSame('completed', $beachHut['status']);
    }

    public function testContributingAfterCompletionIsRejected(): void
    {
        $this->grantResources($this->woodResourceId, 20);
        $this->grantResources($this->metalResourceId, 10);
        $this->grantResources($this->fabricResourceId, 8);
        $this->grantResources($this->ropeResourceId, 5);
        $this->contributeToBeachHut(['wood' => 20, 'metal' => 10, 'fabric' => 8, 'rope' => 5]);

        $this->grantResources($this->woodResourceId, 5);
        $result = $this->contributeToBeachHut(['wood' => 5]);

        self::assertFalse($result['success']);
        self::assertSame('BUILDING_ALREADY_COMPLETED', $result['code']);

        // Die zusaetzlichen 5 Holz duerfen nicht abgezogen worden sein.
        $balances = $this->resourceRepository->findFamilyBalances($this->familyId);
        self::assertSame(5, $balances[$this->woodResourceId] ?? 0);
    }

    public function testResourceBalanceNeverGoesNegative(): void
    {
        $this->grantResources($this->woodResourceId, 3);

        $result = $this->contributeToBeachHut(['wood' => 3, 'metal' => 1]);

        self::assertFalse($result['success']);
        self::assertSame('INSUFFICIENT_RESOURCES', $result['code']);

        $balances = $this->resourceRepository->findFamilyBalances($this->familyId);
        self::assertSame(3, $balances[$this->woodResourceId] ?? 0);
        self::assertGreaterThanOrEqual(0, $balances[$this->metalResourceId] ?? 0);
    }

    public function testInvestEarnedResourcesFromTaskFillsBuildingAndReturnsOverflow(): void
    {
        // Nachbildung dessen, was TaskService::approveTask vorher schon getan hat:
        // die verdiente Menge ist bereits im Familienlager gutgeschrieben.
        $this->grantResources($this->woodResourceId, 25);

        $result = $this->buildingService->investEarnedResourcesFromTask(
            $this->familyId,
            $this->manuelId,
            1,
            [$this->woodResourceId => 25],
        );

        self::assertSame(20, $result['invested'][$this->woodResourceId] ?? 0);
        self::assertSame(5, $result['overflow'][$this->woodResourceId] ?? 0);
        self::assertSame(0, $result['beforePercent']);
        self::assertGreaterThan(0, $result['afterPercent']);
        self::assertFalse($result['justCompleted']);

        // Nur der Ueberschuss bleibt im Lager, der Rest wurde ins Gebaeude gebucht.
        $balances = $this->resourceRepository->findFamilyBalances($this->familyId);
        self::assertSame(5, $balances[$this->woodResourceId] ?? 0);

        $contributedWood = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM building_contributions WHERE resource_id = {$this->woodResourceId}",
        )->fetchColumn();
        self::assertSame(20, $contributedWood);
    }

    public function testInvestEarnedResourcesFromTaskWithoutActiveBuildingLeavesEverythingAsOverflow(): void
    {
        $this->pdo->exec('UPDATE family_buildings SET status = \'completed\'');

        $result = $this->buildingService->investEarnedResourcesFromTask(
            $this->familyId,
            $this->manuelId,
            1,
            [$this->woodResourceId => 10],
        );

        self::assertSame([], $result['invested']);
        self::assertSame(10, $result['overflow'][$this->woodResourceId] ?? 0);
        self::assertNull($result['beforePercent']);
        self::assertNull($result['afterPercent']);
        self::assertFalse($result['justCompleted']);
    }

    public function testInvestEarnedResourcesFromTaskCanCompleteBuildingAndUnlockChain(): void
    {
        $this->grantResources($this->woodResourceId, 20);
        $this->grantResources($this->metalResourceId, 10);
        $this->grantResources($this->fabricResourceId, 8);
        $this->grantResources($this->ropeResourceId, 5);

        $result = $this->buildingService->investEarnedResourcesFromTask($this->familyId, $this->manuelId, 1, [
            $this->woodResourceId => 20,
            $this->metalResourceId => 10,
            $this->fabricResourceId => 8,
            $this->ropeResourceId => 5,
        ]);

        self::assertTrue($result['justCompleted']);
        self::assertSame(100, $result['afterPercent']);

        $building = $this->findBuildingByKey('beach_hut');
        self::assertSame('completed', $building['status']);

        self::assertNotNull($this->findBuildingByKey('watchtower'));
    }

    public function testInvestEarnedResourcesFromTaskIgnoresResourceNotNeededByBuilding(): void
    {
        $starsResourceId = (int) $this->resourceRepository->findIdByKey('stars');
        $this->resourceRepository->incrementBalance($this->familyId, $starsResourceId, 5);

        $result = $this->buildingService->investEarnedResourcesFromTask(
            $this->familyId,
            $this->manuelId,
            1,
            [$starsResourceId => 5],
        );

        self::assertSame([], $result['invested']);
        self::assertSame(5, $result['overflow'][$starsResourceId] ?? 0);

        $balances = $this->resourceRepository->findFamilyBalances($this->familyId);
        self::assertSame(5, $balances[$starsResourceId] ?? 0);
    }
}
