<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ActivityLogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function record(int $familyId, ?int $playerId, string $eventType, string $message, ?array $metadata = null): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO activity_log (family_id, player_id, event_type, message, metadata_json)
             VALUES (:family_id, :player_id, :event_type, :message, :metadata_json)',
        );
        $statement->execute([
            'family_id' => $familyId,
            'player_id' => $playerId,
            'event_type' => $eventType,
            'message' => $message,
            'metadata_json' => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByFamilyAndTypeSince(int $familyId, string $eventType, ?string $since): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM activity_log
             WHERE family_id = :family_id AND event_type = :event_type
               AND (:since IS NULL OR created_at > :since)
             ORDER BY created_at ASC',
        );
        $statement->execute(['family_id' => $familyId, 'event_type' => $eventType, 'since' => $since]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Test-/Admin-Reset: entfernt die 'task_auto_contribution'-Eintraege
     * bestimmter Aufgaben. Da metadata_json auf dem alten Produktions-SQLite
     * (kein JSON1-Modul) nicht per SQL durchsucht werden kann, wird hier in
     * PHP gefiltert - unkritisch, da nur beim seltenen Admin-Reset aufgerufen,
     * nie im normalen Spielbetrieb.
     *
     * @param array<int, int> $taskIds
     */
    public function deleteTaskAutoContributionsForTaskIds(int $familyId, array $taskIds): void
    {
        if ($taskIds === []) {
            return;
        }

        $rows = $this->findByFamilyAndTypeSince($familyId, 'task_auto_contribution', null);
        $idsToDelete = [];
        foreach ($rows as $row) {
            $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
            if (is_array($metadata) && in_array((int) ($metadata['taskId'] ?? 0), $taskIds, true)) {
                $idsToDelete[] = (int) $row['id'];
            }
        }

        if ($idsToDelete === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
        $statement = $this->pdo->prepare("DELETE FROM activity_log WHERE id IN ({$placeholders})");
        $statement->execute($idsToDelete);
    }

    public function deleteAllForFamily(int $familyId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM activity_log WHERE family_id = :family_id');
        $statement->execute(['family_id' => $familyId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRecentForFamily(int $familyId, int $limit = 50): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM activity_log WHERE family_id = :family_id ORDER BY created_at DESC LIMIT :limit',
        );
        $statement->bindValue('family_id', $familyId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
