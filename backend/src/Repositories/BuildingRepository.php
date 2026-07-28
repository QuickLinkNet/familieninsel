<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class BuildingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM buildings WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array{resource_id: int, required_amount: int}>
     */
    public function findCosts(int $buildingId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT resource_id, required_amount FROM building_costs WHERE building_id = :building_id',
        );
        $statement->execute(['building_id' => $buildingId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
