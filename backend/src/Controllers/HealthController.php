<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Support\JsonResponse;
use PDOException;

final class HealthController
{
    public function __construct(private readonly string $databasePath)
    {
    }

    public function show(): void
    {
        try {
            $pdo = Connection::make($this->databasePath);
            $pdo->query('SELECT 1');
        } catch (PDOException) {
            JsonResponse::error(500, 'DATABASE_UNAVAILABLE', 'Datenbankverbindung fehlgeschlagen.');
            return;
        }

        JsonResponse::success([
            'status' => 'ok',
            'database' => 'connected',
            'time' => gmdate('c'),
        ]);
    }
}
