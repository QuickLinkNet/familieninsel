<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use App\Database\Migrator;
use App\Database\Seeder;
use App\Repositories\ActivityLogRepository;
use App\Repositories\MinigameRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTransactionRepository;
use App\Services\MinigameService;
use PDO;
use PHPUnit\Framework\TestCase;

final class MinigameServiceTest extends TestCase
{
    private string $databasePath;
    private PDO $pdo;
    private MinigameService $minigameService;
    private MinigameRepository $minigameRepository;
    private ResourceRepository $resourceRepository;
    private int $familyId;
    private int $emilId;
    private int $minigameId;
    private int $starsResourceId;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-minigames-' . uniqid() . '.sqlite';
        $this->pdo = Connection::make($this->databasePath);
        (new Migrator($this->pdo, dirname(__DIR__) . '/database/migrations'))->run();

        $seeder = new Seeder($this->pdo);
        $seeder->seedDemoFamilyIfEmpty();
        $seeder->seedResourceCatalogIfEmpty();
        $seeder->seedMinigameCatalogIfEmpty();

        $this->familyId = (int) $this->pdo->query('SELECT id FROM families LIMIT 1')->fetchColumn();
        $this->emilId = (int) $this->pdo->query("SELECT id FROM players WHERE name = 'Emil'")->fetchColumn();
        $this->minigameId = (int) $this->pdo->query("SELECT id FROM minigames WHERE key = 'schatzsuche'")->fetchColumn();

        $this->minigameRepository = new MinigameRepository($this->pdo);
        $this->resourceRepository = new ResourceRepository($this->pdo);
        $this->starsResourceId = (int) $this->resourceRepository->findIdByKey('stars');

        $this->minigameService = new MinigameService(
            $this->pdo,
            $this->minigameRepository,
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

    public function testCompletingLockedMinigameFails(): void
    {
        $result = $this->minigameService->complete($this->familyId, $this->emilId, 'schatzsuche');

        self::assertFalse($result['success']);
        self::assertSame('MINIGAME_LOCKED', $result['code']);
    }

    public function testCompletingUnknownMinigameFails(): void
    {
        $result = $this->minigameService->complete($this->familyId, $this->emilId, 'nicht-vorhanden');

        self::assertFalse($result['success']);
        self::assertSame('MINIGAME_NOT_FOUND', $result['code']);
    }

    public function testFirstCompletionAfterUnlockAwardsTwoStars(): void
    {
        $this->minigameRepository->unlockForFamily($this->familyId, $this->minigameId);

        $result = $this->minigameService->complete($this->familyId, $this->emilId, 'schatzsuche');

        self::assertTrue($result['success']);
        self::assertSame(2, $result['starsAwarded']);

        $balances = $this->resourceRepository->findFamilyBalances($this->familyId);
        self::assertSame(2, $balances[$this->starsResourceId] ?? 0);
    }

    public function testRepeatedCompletionDoesNotAwardStarsAgain(): void
    {
        $this->minigameRepository->unlockForFamily($this->familyId, $this->minigameId);

        $this->minigameService->complete($this->familyId, $this->emilId, 'schatzsuche');
        $second = $this->minigameService->complete($this->familyId, $this->emilId, 'schatzsuche');

        self::assertTrue($second['success']);
        self::assertSame(0, $second['starsAwarded']);

        $balances = $this->resourceRepository->findFamilyBalances($this->familyId);
        self::assertSame(2, $balances[$this->starsResourceId] ?? 0);
    }

    public function testListForFamilyReflectsUnlockStatus(): void
    {
        $before = $this->minigameService->listForFamily($this->familyId);
        self::assertFalse($before[0]['unlocked']);

        $this->minigameRepository->unlockForFamily($this->familyId, $this->minigameId);

        $after = $this->minigameService->listForFamily($this->familyId);
        self::assertTrue($after[0]['unlocked']);
    }
}
