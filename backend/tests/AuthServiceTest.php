<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use App\Database\Migrator;
use App\Database\Seeder;
use App\Repositories\FamilyRepository;
use App\Repositories\PlayerRepository;
use App\Services\AuthService;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private string $databasePath;
    private AuthService $authService;
    private int $familyId;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-auth-' . uniqid() . '.sqlite';
        $pdo = Connection::make($this->databasePath);
        (new Migrator($pdo, dirname(__DIR__) . '/database/migrations'))->run();
        (new Seeder($pdo))->seedDemoFamilyIfEmpty();

        $this->authService = new AuthService(new FamilyRepository($pdo), new PlayerRepository($pdo));
        $this->familyId = (int) $pdo->query('SELECT id FROM families LIMIT 1')->fetchColumn();
    }

    protected function tearDown(): void
    {
        Connection::reset();
        gc_collect_cycles();
        if (file_exists($this->databasePath)) {
            @unlink($this->databasePath);
        }
    }

    public function testVerifyFamilyCodeAcceptsCorrectCode(): void
    {
        $family = $this->authService->verifyFamilyCode('INSEL2026');

        self::assertNotNull($family);
        self::assertSame($this->familyId, $family['id']);
    }

    public function testVerifyFamilyCodeRejectsWrongCode(): void
    {
        self::assertNull($this->authService->verifyFamilyCode('falscher-code'));
    }

    public function testListActivePlayersReturnsAllFiveDemoPlayers(): void
    {
        $players = $this->authService->listActivePlayers($this->familyId);

        self::assertCount(5, $players);
        $names = array_column($players, 'name');
        self::assertEqualsCanonicalizing(['Manuel', 'Kathrin', 'Emil', 'Thea', 'Nova'], $names);
    }

    public function testFindSelectableProfileNeverExposesPinHash(): void
    {
        $players = $this->authService->listActivePlayers($this->familyId);
        $manuel = array_values(array_filter($players, static fn (array $p) => $p['name'] === 'Manuel'))[0];

        $profile = $this->authService->findSelectableProfile((int) $manuel['id'], $this->familyId);

        self::assertNotNull($profile);
        self::assertArrayNotHasKey('parent_pin_hash', $profile);
    }

    public function testVerifyParentPinAcceptsCorrectPin(): void
    {
        $players = $this->authService->listActivePlayers($this->familyId);
        $manuel = array_values(array_filter($players, static fn (array $p) => $p['name'] === 'Manuel'))[0];

        self::assertTrue($this->authService->verifyParentPin((int) $manuel['id'], $this->familyId, '2580'));
        self::assertFalse($this->authService->verifyParentPin((int) $manuel['id'], $this->familyId, '0000'));
    }

    public function testVerifyParentPinRejectsChildProfile(): void
    {
        $players = $this->authService->listActivePlayers($this->familyId);
        $emil = array_values(array_filter($players, static fn (array $p) => $p['name'] === 'Emil'))[0];

        self::assertFalse($this->authService->verifyParentPin((int) $emil['id'], $this->familyId, '2580'));
    }
}
