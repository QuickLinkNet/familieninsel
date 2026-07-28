<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use Throwable;

final class Seeder
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Legt die Demo-Familie an, aber nur, wenn noch keine Familie existiert.
     * Sicher fuer wiederholten Aufruf (z. B. bei jedem Request).
     */
    public function seedDemoFamilyIfEmpty(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM families')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $familyStatement = $this->pdo->prepare(
                'INSERT INTO families (name, family_code_hash) VALUES (:name, :hash)',
            );
            $familyStatement->execute([
                'name' => 'Familie Manuel & Kathrin',
                'hash' => password_hash('INSEL2026', PASSWORD_DEFAULT),
            ]);
            $familyId = (int) $this->pdo->lastInsertId();

            $parentPinHash = password_hash('2580', PASSWORD_DEFAULT);

            $players = [
                ['name' => 'Manuel', 'age' => null, 'role' => 'parent', 'avatar_key' => 'manuel', 'pin' => $parentPinHash],
                ['name' => 'Kathrin', 'age' => null, 'role' => 'parent', 'avatar_key' => 'kathrin', 'pin' => $parentPinHash],
                ['name' => 'Emil', 'age' => 5, 'role' => 'child', 'avatar_key' => 'emil', 'pin' => null],
                ['name' => 'Thea', 'age' => 7, 'role' => 'child', 'avatar_key' => 'thea', 'pin' => null],
                ['name' => 'Nova', 'age' => 8, 'role' => 'child', 'avatar_key' => 'nova', 'pin' => null],
            ];

            $playerStatement = $this->pdo->prepare(
                'INSERT INTO players (family_id, name, age, role, avatar_key, parent_pin_hash)
                 VALUES (:family_id, :name, :age, :role, :avatar_key, :pin)',
            );

            foreach ($players as $player) {
                $playerStatement->execute([
                    'family_id' => $familyId,
                    'name' => $player['name'],
                    'age' => $player['age'],
                    'role' => $player['role'],
                    'avatar_key' => $player['avatar_key'],
                    'pin' => $player['pin'],
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
