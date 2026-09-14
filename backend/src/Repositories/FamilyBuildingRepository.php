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
     * Alle Bauprojekte (jeder Status), die fuer eine Familie bereits freigeschaltet sind.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllForFamily(int $familyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM family_buildings WHERE family_id = :family_id ORDER BY id ASC',
        );
        $statement->execute(['family_id' => $familyId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByFamilyAndBuilding(int $familyId, int $buildingId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM family_buildings WHERE family_id = :family_id AND building_id = :building_id LIMIT 1',
        );
        $statement->execute(['family_id' => $familyId, 'building_id' => $buildingId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Schaltet ein Gebaeude fuer eine Familie frei (legt die Fortschritts-Zeile an),
     * aber nur, wenn noch keine existiert. Sicher fuer wiederholten Aufruf.
     */
    public function unlockForFamilyIfMissing(int $familyId, int $buildingId): void
    {
        $statement = $this->pdo->prepare(
            "INSERT OR IGNORE INTO family_buildings (family_id, building_id, status, stage)
             VALUES (:family_id, :building_id, 'in_progress', 1)",
        );
        $statement->execute(['family_id' => $familyId, 'building_id' => $buildingId]);
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
