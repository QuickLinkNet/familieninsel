<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Clock;
use PDO;

final class MinigameRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllActive(): array
    {
        return $this->pdo->query(
            'SELECT id, key, name, description FROM minigames WHERE is_active = 1 ORDER BY id ASC',
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM minigames WHERE key = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Schaltet ein Minispiel fuer eine Familie frei. Idempotent (dank UNIQUE-
     * Constraint auf family_id+minigame_id und "INSERT OR IGNORE").
     */
    public function unlockForFamily(int $familyId, int $minigameId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO family_minigames (family_id, minigame_id, unlocked_at)
             VALUES (:family_id, :minigame_id, :now)',
        );
        $statement->execute(['family_id' => $familyId, 'minigame_id' => $minigameId, 'now' => Clock::nowIso()]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findFamilyStatus(int $familyId, int $minigameId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM family_minigames WHERE family_id = :family_id AND minigame_id = :minigame_id LIMIT 1',
        );
        $statement->execute(['family_id' => $familyId, 'minigame_id' => $minigameId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Markiert die erste erfolgreiche Runde. Gibt true zurueck, wenn dies
     * tatsaechlich die erste war (Belohnung faellig), sonst false.
     */
    public function markFirstCompletion(int $familyId, int $minigameId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE family_minigames SET first_completion_at = :now, reward_claimed_at = :now
             WHERE family_id = :family_id AND minigame_id = :minigame_id AND first_completion_at IS NULL',
        );
        $statement->execute(['family_id' => $familyId, 'minigame_id' => $minigameId, 'now' => Clock::nowIso()]);

        return $statement->rowCount() > 0;
    }
}
