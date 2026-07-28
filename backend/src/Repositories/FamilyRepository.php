<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class FamilyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * MVP: Es existiert genau eine Familie pro Installation.
     *
     * @return array{id: int, name: string, family_code_hash: string}|null
     */
    public function findSoleFamily(): ?array
    {
        $row = $this->pdo->query('SELECT id, name, family_code_hash FROM families LIMIT 1')->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name FROM families WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
