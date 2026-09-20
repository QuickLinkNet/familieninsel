<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use App\Database\Migrator;
use App\Database\Seeder;
use App\Repositories\PlayerLoginTokenRepository;
use App\Repositories\PlayerRepository;
use App\Services\PlayerService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PlayerServiceTest extends TestCase
{
    private string $databasePath;
    private PDO $pdo;
    private PlayerService $playerService;
    private int $familyId;
    private int $emilId;
    private int $manuelId;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-players-' . uniqid() . '.sqlite';
        $this->pdo = Connection::make($this->databasePath);
        (new Migrator($this->pdo, dirname(__DIR__) . '/database/migrations'))->run();

        $seeder = new Seeder($this->pdo);
        $seeder->seedDemoFamilyIfEmpty();

        $this->familyId = (int) $this->pdo->query('SELECT id FROM families LIMIT 1')->fetchColumn();
        $this->emilId = (int) $this->pdo->query("SELECT id FROM players WHERE name = 'Emil'")->fetchColumn();
        $this->manuelId = (int) $this->pdo->query("SELECT id FROM players WHERE name = 'Manuel'")->fetchColumn();

        $this->playerService = new PlayerService(
            $this->pdo,
            new PlayerRepository($this->pdo),
            new PlayerLoginTokenRepository($this->pdo),
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

    public function testChildCanMarkOwnIntroSeen(): void
    {
        $result = $this->playerService->markIntroSeen($this->familyId, $this->emilId, $this->emilId, 'child');

        self::assertTrue($result['success']);
        $introSeenAt = $this->pdo
            ->query("SELECT intro_seen_at FROM players WHERE id = {$this->emilId}")
            ->fetchColumn();
        self::assertNotNull($introSeenAt);
    }

    public function testChildCannotMarkIntroSeenForSomeoneElse(): void
    {
        $result = $this->playerService->markIntroSeen($this->familyId, $this->manuelId, $this->emilId, 'child');

        self::assertFalse($result['success']);
        self::assertSame('FORBIDDEN', $result['code']);
    }

    public function testParentCanMarkIntroSeenForChild(): void
    {
        $result = $this->playerService->markIntroSeen($this->familyId, $this->emilId, $this->manuelId, 'parent');

        self::assertTrue($result['success']);
    }

    public function testUnknownPlayerFails(): void
    {
        $result = $this->playerService->markIntroSeen($this->familyId, 999999, $this->manuelId, 'parent');

        self::assertFalse($result['success']);
        self::assertSame('PLAYER_NOT_FOUND', $result['code']);
    }

    public function testMarkingIntroSeenAgainIsIdempotent(): void
    {
        $first = $this->playerService->markIntroSeen($this->familyId, $this->emilId, $this->emilId, 'child');
        $second = $this->playerService->markIntroSeen($this->familyId, $this->emilId, $this->emilId, 'child');

        self::assertTrue($first['success']);
        self::assertTrue($second['success']);
    }

    public function testChildCanMarkOwnRewardsSeen(): void
    {
        $result = $this->playerService->markRewardsSeen($this->familyId, $this->emilId, $this->emilId);

        self::assertTrue($result['success']);
        $seenAt = $this->pdo
            ->query("SELECT last_reward_seen_at FROM players WHERE id = {$this->emilId}")
            ->fetchColumn();
        self::assertNotNull($seenAt);
    }

    public function testChildCannotMarkRewardsSeenForSomeoneElse(): void
    {
        $result = $this->playerService->markRewardsSeen($this->familyId, $this->manuelId, $this->emilId);

        self::assertFalse($result['success']);
        self::assertSame('FORBIDDEN', $result['code']);
    }

    public function testParentCannotMarkRewardsSeenForChildEither(): void
    {
        // Anders als beim Intro gibt es hier bewusst keine Eltern-Ausnahme.
        $result = $this->playerService->markRewardsSeen($this->familyId, $this->emilId, $this->manuelId);

        self::assertFalse($result['success']);
        self::assertSame('FORBIDDEN', $result['code']);
    }
}
