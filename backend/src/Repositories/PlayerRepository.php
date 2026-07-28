<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PlayerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array{id: int, name: string, age: int|null, role: string, avatar_key: string}>
     */
    public function findActiveByFamily(int $familyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, age, role, avatar_key
             FROM players
             WHERE family_id = :family_id AND is_active = 1
             ORDER BY id ASC',
        );
        $statement->execute(['family_id' => $familyId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{id: int, family_id: int, name: string, age: int|null, role: string, avatar_key: string, parent_pin_hash: string|null}|null
     */
    public function findActiveByIdAndFamily(int $id, int $familyId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, family_id, name, age, role, avatar_key, parent_pin_hash
             FROM players
             WHERE id = :id AND family_id = :family_id AND is_active = 1
             LIMIT 1',
        );
        $statement->execute(['id' => $id, 'family_id' => $familyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
