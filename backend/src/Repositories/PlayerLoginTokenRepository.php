<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PlayerLoginTokenRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function revokeAllForPlayer(int $playerId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE player_login_tokens
             SET revoked_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now')
             WHERE player_id = :player_id AND revoked_at IS NULL",
        );
        $statement->execute(['player_id' => $playerId]);
    }

    public function create(int $playerId, string $tokenHash): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO player_login_tokens (player_id, token_hash) VALUES (:player_id, :token_hash)',
        );
        $statement->execute(['player_id' => $playerId, 'token_hash' => $tokenHash]);
    }

    /**
     * @return array{id: int, player_id: int}|null
     */
    public function findActiveByTokenHash(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, player_id FROM player_login_tokens WHERE token_hash = :hash AND revoked_at IS NULL LIMIT 1',
        );
        $statement->execute(['hash' => $tokenHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function markUsed(int $id): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE player_login_tokens SET last_used_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now') WHERE id = :id",
        );
        $statement->execute(['id' => $id]);
    }

    /**
     * @return array{created_at: string, last_used_at: string|null}|null
     */
    public function findActiveStatusForPlayer(int $playerId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT created_at, last_used_at
             FROM player_login_tokens
             WHERE player_id = :player_id AND revoked_at IS NULL
             ORDER BY id DESC
             LIMIT 1',
        );
        $statement->execute(['player_id' => $playerId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
