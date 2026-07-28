<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use App\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    private string $databasePath;
    private string $migrationsPath;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-migrator-' . uniqid() . '.sqlite';
        $this->migrationsPath = dirname(__DIR__) . '/database/migrations';
    }

    protected function tearDown(): void
    {
        Connection::reset();
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testRunCreatesFamiliesAndPlayersTables(): void
    {
        $pdo = Connection::make($this->databasePath);
        $migrator = new Migrator($pdo, $this->migrationsPath);

        $migrator->run();

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);

        self::assertContains('families', $tables);
        self::assertContains('players', $tables);
        self::assertContains('tasks', $tables);
        self::assertContains('resources', $tables);
    }

    public function testRunTwiceDoesNotFail(): void
    {
        $pdo = Connection::make($this->databasePath);
        $migrator = new Migrator($pdo, $this->migrationsPath);

        $migrator->run();
        $countAfterFirstRun = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();

        $migrator->run();
        $countAfterSecondRun = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();

        self::assertGreaterThan(0, $countAfterFirstRun);
        self::assertSame($countAfterFirstRun, $countAfterSecondRun);
    }
}
