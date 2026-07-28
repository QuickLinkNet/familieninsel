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
}
