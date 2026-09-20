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
