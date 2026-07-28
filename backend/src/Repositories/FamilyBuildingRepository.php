<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Clock;
use PDO;

final class FamilyBuildingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * MVP: hoechstens ein Bauprojekt pro Familie insgesamt - das jeweils neueste.
     *
     * @return array<string, mixed>|null
     */
    public function findForFamily(int $familyId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM family_buildings WHERE family_id = :family_id ORDER BY id DESC LIMIT 1',
        );
        $statement->execute(['family_id' => $familyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, int> resource_id => Summe der bisherigen Einzahlungen
     */
    public function findContributedTotals(int $familyBuildingId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT resource_id, SUM(amount) AS total
             FROM building_contributions WHERE family_building_id = :id GROUP BY resource_id',
        );
        $statement->execute(['id' => $familyBuildingId]);

        $totals = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $totals[(int) $row['resource_id']] = (int) $row['total'];
        }

        return $totals;
    }

    public function recordContribution(int $familyBuildingId, int $resourceId, int $amount, int $playerId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO building_contributions (family_building_id, resource_id, amount, contributed_by_player_id)
             VALUES (:family_building_id, :resource_id, :amount, :player_id)',
        );
        $statement->execute([
            'family_building_id' => $familyBuildingId,
            'resource_id' => $resourceId,
            'amount' => $amount,
            'player_id' => $playerId,
        ]);
    }

    public function updateStage(int $id, int $stage): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE family_buildings SET stage = :stage, updated_at = :now WHERE id = :id',
        );
        $statement->execute(['id' => $id, 'stage' => $stage, 'now' => Clock::nowIso()]);
    }

    /**
     * Markiert das Bauprojekt als fertiggestellt, aber nur, wenn es noch "in_progress" ist.
     * Verhindert doppelte Fertigstellung (Rueckgabe false, wenn schon abgeschlossen).
     */
    public function markCompleted(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE family_buildings SET status = 'completed', stage = 5, completed_at = :now, updated_at = :now
             WHERE id = :id AND status = 'in_progress'",
        );
        $statement->execute(['id' => $id, 'now' => Clock::nowIso()]);

        return $statement->rowCount() > 0;
    }
}
