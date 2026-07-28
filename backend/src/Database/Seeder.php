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

    /**
     * Legt den Rohstoff-Katalog an, aber nur, wenn er noch leer ist.
     */
    public function seedResourceCatalogIfEmpty(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM resources')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $resources = [
            ['key' => 'wood', 'name' => 'Holz', 'icon_key' => 'wood'],
            ['key' => 'metal', 'name' => 'Metall', 'icon_key' => 'metal'],
            ['key' => 'fabric', 'name' => 'Stoff', 'icon_key' => 'fabric'],
            ['key' => 'rope', 'name' => 'Seil', 'icon_key' => 'rope'],
            ['key' => 'stars', 'name' => 'Sterne', 'icon_key' => 'stars'],
        ];

        $statement = $this->pdo->prepare(
            'INSERT INTO resources (key, name, icon_key) VALUES (:key, :name, :icon_key)',
        );

        foreach ($resources as $resource) {
            $statement->execute($resource);
        }
    }

    /**
     * Legt die fuenf Demo-Aufgaben aus der Produktspezifikation an, aber nur,
     * wenn noch keine Aufgaben existieren. Setzt eine vorhandene Demo-Familie
     * und einen befuellten Ressourcen-Katalog voraus.
     */
    public function seedDemoTasksIfEmpty(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $familyId = $this->pdo->query('SELECT id FROM families LIMIT 1')->fetchColumn();
        if ($familyId === false) {
            return;
        }
        $familyId = (int) $familyId;

        $playersByName = [];
        $playerStatement = $this->pdo->prepare('SELECT id, name FROM players WHERE family_id = :family_id');
        $playerStatement->execute(['family_id' => $familyId]);
        foreach ($playerStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $playersByName[$row['name']] = (int) $row['id'];
        }

        $resourcesByKey = [];
        foreach ($this->pdo->query('SELECT id, key FROM resources')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $resourcesByKey[$row['key']] = (int) $row['id'];
        }

        $demoTasks = [
            ['assignee' => 'Emil', 'title' => 'Spielzeug einsammeln', 'rewards' => ['wood' => 3, 'fabric' => 1]],
            ['assignee' => 'Thea', 'title' => 'Tisch decken', 'rewards' => ['metal' => 2, 'rope' => 1]],
            ['assignee' => 'Nova', 'title' => 'Eigenes Zimmer aufraeumen', 'rewards' => ['wood' => 5, 'fabric' => 2]],
            ['assignee' => 'Manuel', 'title' => 'Werkzeug und Materialien sortieren', 'rewards' => ['metal' => 4, 'rope' => 1]],
            ['assignee' => 'Kathrin', 'title' => 'Vorraete fuer den naechsten Tag vorbereiten', 'rewards' => ['wood' => 3, 'fabric' => 2]],
        ];

        $this->pdo->beginTransaction();
        try {
            $taskStatement = $this->pdo->prepare(
                "INSERT INTO tasks (family_id, assigned_player_id, created_by_player_id, title, status)
                 VALUES (:family_id, :assigned_player_id, :created_by_player_id, :title, 'open')",
            );
            $rewardStatement = $this->pdo->prepare(
                'INSERT INTO task_rewards (task_id, resource_id, amount) VALUES (:task_id, :resource_id, :amount)',
            );

            $manuelId = $playersByName['Manuel'] ?? null;
            $kathrinId = $playersByName['Kathrin'] ?? null;

            foreach ($demoTasks as $demoTask) {
                $assignedPlayerId = $playersByName[$demoTask['assignee']] ?? null;
                if ($assignedPlayerId === null) {
                    continue;
                }

                $createdBy = $assignedPlayerId === $kathrinId ? $manuelId : $kathrinId;
                $createdBy ??= $assignedPlayerId;

                $taskStatement->execute([
                    'family_id' => $familyId,
                    'assigned_player_id' => $assignedPlayerId,
                    'created_by_player_id' => $createdBy,
                    'title' => $demoTask['title'],
                ]);
                $taskId = (int) $this->pdo->lastInsertId();

                foreach ($demoTask['rewards'] as $resourceKey => $amount) {
                    $resourceId = $resourcesByKey[$resourceKey] ?? null;
                    if ($resourceId === null) {
                        continue;
                    }

                    $rewardStatement->execute([
                        'task_id' => $taskId,
                        'resource_id' => $resourceId,
                        'amount' => $amount,
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
