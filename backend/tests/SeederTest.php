<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use App\Database\Migrator;
use App\Database\Seeder;
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

        $parentPinHash = $pdo->query(
            "SELECT parent_pin_hash FROM players WHERE role = 'parent' LIMIT 1",
        )->fetchColumn();
        self::assertTrue(password_verify('2580', (string) $parentPinHash));
    }
}
