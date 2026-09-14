<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use App\Database\Migrator;
use App\Database\Seeder;
use PDO;
use PHPUnit\Framework\TestCase;

final class SeederTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-seeder-' . uniqid() . '.sqlite';
        $pdo = Connection::make($this->databasePath);
        (new Migrator($pdo, dirname(__DIR__) . '/database/migrations'))->run();
    }

    protected function tearDown(): void
    {
        Connection::reset();
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testSeedCreatesDemoFamilyWithFivePlayers(): void
    {
        $pdo = Connection::make($this->databasePath);
        (new Seeder($pdo))->seedDemoFamilyIfEmpty();

        $familyCount = (int) $pdo->query('SELECT COUNT(*) FROM families')->fetchColumn();
        $playerCount = (int) $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn();

        self::assertSame(1, $familyCount);
        self::assertSame(5, $playerCount);
    }

    public function testSeedIsIdempotent(): void
    {
        $pdo = Connection::make($this->databasePath);
        $seeder = new Seeder($pdo);

        $seeder->seedDemoFamilyIfEmpty();
        $seeder->seedDemoFamilyIfEmpty();

        $familyCount = (int) $pdo->query('SELECT COUNT(*) FROM families')->fetchColumn();
        self::assertSame(1, $familyCount);
    }

    public function testDemoCredentialsVerifyCorrectly(): void
    {
        $pdo = Connection::make($this->databasePath);
        (new Seeder($pdo))->seedDemoFamilyIfEmpty();

        $familyCodeHash = $pdo->query('SELECT family_code_hash FROM families LIMIT 1')->fetchColumn();
        self::assertTrue(password_verify('INSEL2026', (string) $familyCodeHash));
        self::assertFalse(password_verify('falsch', (string) $familyCodeHash));

        $manuelPinHash = $pdo->query(
            "SELECT password_hash FROM players WHERE name = 'Manuel' LIMIT 1",
        )->fetchColumn();
        self::assertTrue(password_verify('2026', (string) $manuelPinHash));

        $kathrinPinHash = $pdo->query(
            "SELECT password_hash FROM players WHERE name = 'Kathrin' LIMIT 1",
        )->fetchColumn();
        self::assertTrue(password_verify('2580', (string) $kathrinPinHash));
    }

    public function testSeedResourceCatalogCreatesFiveResources(): void
    {
        $pdo = Connection::make($this->databasePath);
        (new Seeder($pdo))->seedResourceCatalogIfEmpty();

        $count = (int) $pdo->query('SELECT COUNT(*) FROM resources')->fetchColumn();
        self::assertSame(5, $count);
    }

    public function testSeedDemoTasksCreatesFiveTasksWithRewards(): void
    {
        $pdo = Connection::make($this->databasePath);
        $seeder = new Seeder($pdo);
        $seeder->seedDemoFamilyIfEmpty();
        $seeder->seedResourceCatalogIfEmpty();
        $seeder->seedDemoTasksIfEmpty();

        $taskCount = (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
        $rewardCount = (int) $pdo->query('SELECT COUNT(*) FROM task_rewards')->fetchColumn();

        self::assertSame(5, $taskCount);
        self::assertSame(10, $rewardCount);

        $openCount = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'open'")->fetchColumn();
        self::assertSame(5, $openCount);
    }

    public function testSeedDemoTasksIsIdempotent(): void
    {
        $pdo = Connection::make($this->databasePath);
        $seeder = new Seeder($pdo);
        $seeder->seedDemoFamilyIfEmpty();
        $seeder->seedResourceCatalogIfEmpty();
        $seeder->seedDemoTasksIfEmpty();
        $seeder->seedDemoTasksIfEmpty();

        $taskCount = (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
        self::assertSame(5, $taskCount);
    }

    public function testSeedBuildingCatalogCreatesBeachHutWithFourCosts(): void
    {
        $pdo = Connection::make($this->databasePath);
        $seeder = new Seeder($pdo);
        $seeder->seedResourceCatalogIfEmpty();
        $seeder->seedBuildingCatalogIfEmpty();

        $buildingCount = (int) $pdo->query('SELECT COUNT(*) FROM buildings')->fetchColumn();
        $costCount = (int) $pdo->query('SELECT COUNT(*) FROM building_costs')->fetchColumn();

        self::assertSame(1, $buildingCount);
        self::assertSame(4, $costCount);
    }

    public function testSeedActiveFamilyBuildingStartsInProgressAtStageOne(): void
    {
        $pdo = Connection::make($this->databasePath);
        $seeder = new Seeder($pdo);
        $seeder->seedDemoFamilyIfEmpty();
        $seeder->seedResourceCatalogIfEmpty();
        $seeder->seedBuildingCatalogIfEmpty();
        $seeder->seedActiveFamilyBuildingIfEmpty();

        $row = $pdo->query('SELECT status, stage FROM family_buildings LIMIT 1')->fetch(PDO::FETCH_ASSOC);

        self::assertSame('in_progress', $row['status']);
        self::assertSame(1, (int) $row['stage']);
    }

    public function testSeedActiveFamilyBuildingIsIdempotent(): void
    {
        $pdo = Connection::make($this->databasePath);
        $seeder = new Seeder($pdo);
        $seeder->seedDemoFamilyIfEmpty();
        $seeder->seedResourceCatalogIfEmpty();
        $seeder->seedBuildingCatalogIfEmpty();
        $seeder->seedActiveFamilyBuildingIfEmpty();
        $seeder->seedActiveFamilyBuildingIfEmpty();

        $count = (int) $pdo->query('SELECT COUNT(*) FROM family_buildings')->fetchColumn();
        self::assertSame(1, $count);
    }
}
