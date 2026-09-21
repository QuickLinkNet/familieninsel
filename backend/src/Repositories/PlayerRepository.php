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
     * @return array<int, array{id: int, name: string, age: int|null, role: string, avatar_key: string, intro_seen_at: string|null}>
     */
    public function findActiveByFamily(int $familyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, age, role, avatar_key, intro_seen_at
             FROM players
             WHERE family_id = :family_id AND is_active = 1
             ORDER BY id ASC',
        );
        $statement->execute(['family_id' => $familyId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{id: int, family_id: int, name: string, age: int|null, role: string, avatar_key: string, parent_pin_hash: string|null, password_hash: string|null}|null
     */
    public function findActiveByIdAndFamily(int $id, int $familyId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, family_id, name, age, role, avatar_key, parent_pin_hash, password_hash
             FROM players
             WHERE id = :id AND family_id = :family_id AND is_active = 1
             LIMIT 1',
        );
        $statement->execute(['id' => $id, 'family_id' => $familyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Fuer den QR-Login: der Login-Token ist bereits eindeutig einem Spieler
     * zugeordnet, die Familie muss vorher nicht bekannt sein.
     *
     * @return array{id: int, family_id: int, name: string, age: int|null, role: string, avatar_key: string}|null
     */
    public function findActiveById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, family_id, name, age, role, avatar_key
             FROM players
             WHERE id = :id AND is_active = 1
             LIMIT 1',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array{id: int, name: string, age: int|null, role: string, avatar_key: string}>
     */
    public function findActiveParentsByFamily(int $familyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, age, role, avatar_key
             FROM players
             WHERE family_id = :family_id AND role = \'parent\' AND is_active = 1
             ORDER BY id ASC',
        );
        $statement->execute(['family_id' => $familyId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePasswordHash(int $playerId, string $passwordHash): void
    {
        $statement = $this->pdo->prepare('UPDATE players SET password_hash = :password_hash WHERE id = :id');
        $statement->execute(['password_hash' => $passwordHash, 'id' => $playerId]);
    }

    /**
     * Fuer die Benutzerverwaltung: anders als findActiveByFamily auch mit
     * deaktivierten Profilen, damit man sie dort wieder reaktivieren kann.
     * Eltern zuerst, danach Kinder nach Anlagereihenfolge.
     *
     * @return array<int, array{id: int, name: string, age: int|null, role: string, avatar_key: string, is_active: int, intro_seen_at: string|null}>
     */
    public function findAllByFamily(int $familyId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, name, age, role, avatar_key, is_active, intro_seen_at
             FROM players
             WHERE family_id = :family_id
             ORDER BY CASE role WHEN 'parent' THEN 0 ELSE 1 END, id ASC",
        );
        $statement->execute(['family_id' => $familyId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Wie findActiveByIdAndFamily, aber ohne is_active-Filter - fuer
     * Verwaltungsaktionen (Bearbeiten, Passwort setzen, Reaktivieren), die
     * auch auf bereits deaktivierte Profile zugreifen koennen muessen.
     *
     * @return array{id: int, family_id: int, name: string, age: int|null, role: string, avatar_key: string, parent_pin_hash: string|null, password_hash: string|null, is_active: int, intro_seen_at: string|null, last_reward_seen_at: string|null}|null
     */
    public function findByIdAndFamily(int $id, int $familyId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, family_id, name, age, role, avatar_key, parent_pin_hash, password_hash, is_active,
                    intro_seen_at, last_reward_seen_at
             FROM players
             WHERE id = :id AND family_id = :family_id
             LIMIT 1',
        );
        $statement->execute(['id' => $id, 'family_id' => $familyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function updateNameAndAge(int $id, string $name, ?int $age): void
    {
        $statement = $this->pdo->prepare('UPDATE players SET name = :name, age = :age WHERE id = :id');
        $statement->execute(['name' => $name, 'age' => $age, 'id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $statement = $this->pdo->prepare('UPDATE players SET is_active = :is_active WHERE id = :id');
        $statement->execute(['is_active' => $active ? 1 : 0, 'id' => $id]);
    }

    public function countActiveParents(int $familyId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM players WHERE family_id = :family_id AND role = 'parent' AND is_active = 1",
        );
        $statement->execute(['family_id' => $familyId]);

        return (int) $statement->fetchColumn();
    }

    public function markIntroSeen(int $id): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE players SET intro_seen_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now') WHERE id = :id",
        );
        $statement->execute(['id' => $id]);
    }

    public function findRewardCursor(int $id): ?string
    {
        $statement = $this->pdo->prepare('SELECT last_reward_seen_at FROM players WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }

    public function markRewardsSeen(int $id): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE players SET last_reward_seen_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now') WHERE id = :id",
        );
        $statement->execute(['id' => $id]);
    }

    public function clearIntroSeen(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE players SET intro_seen_at = NULL WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function clearRewardCursor(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE players SET last_reward_seen_at = NULL WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function createChild(int $familyId, string $name, ?int $age, string $avatarKey): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO players (family_id, name, age, role, avatar_key)
             VALUES (:family_id, :name, :age, 'child', :avatar_key)",
        );
        $statement->execute([
            'family_id' => $familyId,
            'name' => $name,
            'age' => $age,
            'avatar_key' => $avatarKey,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
