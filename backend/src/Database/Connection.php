<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class Connection
{
    private static ?PDO $instance = null;

    public static function make(string $databasePath): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $directory = dirname($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON;');

        self::$instance = $pdo;

        return $pdo;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
