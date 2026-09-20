<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ResourceTransactionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(
        int $familyId,
        ?int $playerId,
        int $resourceId,
        int $amount,
        string $transactionType,
        ?string $referenceType,
        ?int $referenceId,
        ?string $description,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO resource_transactions
                (family_id, player_id, resource_id, amount, transaction_type, reference_type, reference_id, description)
             VALUES (:family_id, :player_id, :resource_id, :amount, :transaction_type, :reference_type, :reference_id, :description)',
        );
        $statement->execute([
            'family_id' => $familyId,
            'player_id' => $playerId,
            'resource_id' => $resourceId,
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
        ]);
    }

    /**
     * Aufgaben-Belohnungen fuer einen Spieler seit einem Zeitpunkt, samt
     * Aufgabentitel - Grundlage fuer RewardReveal (welche Aufgabe hat wie
     * viel gebracht, seit das Kind zuletzt reingeschaut hat).
     *
     * @return array<int, array{resource_id: int, amount: int, task_id: int, task_title: string, created_at: string}>
     */
    public function findTaskRewardsForPlayerSince(int $playerId, ?string $since): array
    {
        $statement = $this->pdo->prepare(
            "SELECT rt.resource_id, rt.amount, rt.reference_id AS task_id, rt.created_at, t.title AS task_title
             FROM resource_transactions rt
             JOIN tasks t ON t.id = rt.reference_id AND rt.reference_type = 'task'
             WHERE rt.player_id = :player_id
               AND rt.transaction_type = 'task_reward'
               AND (:since IS NULL OR rt.created_at > :since)
             ORDER BY rt.created_at ASC",
        );
        $statement->execute(['player_id' => $playerId, 'since' => $since]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
