<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use PHPUnit\Framework\TestCase;

final class ConnectionTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-test-' . uniqid() . '.sqlite';
    }

    protected function tearDown(): void
    {
        Connection::reset();
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testMakeEnablesForeignKeysAndCreatesDatabaseFile(): void
    {
        $pdo = Connection::make($this->databasePath);

        $result = $pdo->query('PRAGMA foreign_keys')->fetchColumn();

        self::assertSame('1', (string) $result);
        self::assertFileExists($this->databasePath);
    }

    public function testMakeReturnsSameInstanceOnSubsequentCalls(): void
    {
        $first = Connection::make($this->databasePath);
        $second = Connection::make($this->databasePath);

        self::assertSame($first, $second);
    }
}
