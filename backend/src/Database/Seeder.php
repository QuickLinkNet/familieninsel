<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use Throwable;

final class Seeder
{
    /**
     * Vordefinierte Eltern-PINs (MVP: keine Selbstregistrierung). Bewusst hier
     * zentral statt verstreut, damit seedDemoFamilyIfEmpty (Neuinstallation)
     * und die Backfill-Methoden unten garantiert dieselben Werte verwenden.
     */
    private const PARENT_PINS = [
        'Manuel' => '2026',
        'Kathrin' => '2580',
    ];

    /**
     * Die ursprünglichen, langen Text-Passwörter aus der ersten Login-Version
     * (vor der Umstellung auf 4-stellige PINs) - nur noch gebraucht, um
     * seedParentPinsFromLegacyPassword() idempotent zu machen (siehe dort).
     */
    private const LEGACY_PARENT_PASSWORDS = [
        'Manuel' => 'ManuelInsel2026',
        'Kathrin' => 'KathrinInsel2026',
    ];

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

            $players = [
                ['name' => 'Manuel', 'age' => null, 'role' => 'parent', 'avatar_key' => 'manuel', 'pin' => self::PARENT_PINS['Manuel']],
                ['name' => 'Kathrin', 'age' => null, 'role' => 'parent', 'avatar_key' => 'kathrin', 'pin' => self::PARENT_PINS['Kathrin']],
                ['name' => 'Emil', 'age' => 5, 'role' => 'child', 'avatar_key' => 'emil', 'pin' => null],
                ['name' => 'Thea', 'age' => 7, 'role' => 'child', 'avatar_key' => 'thea', 'pin' => null],
                ['name' => 'Nova', 'age' => 8, 'role' => 'child', 'avatar_key' => 'nova', 'pin' => null],
            ];

            $playerStatement = $this->pdo->prepare(
                'INSERT INTO players (family_id, name, age, role, avatar_key, password_hash)
                 VALUES (:family_id, :name, :age, :role, :avatar_key, :pin)',
            );

            foreach ($players as $player) {
                $playerStatement->execute([
                    'family_id' => $familyId,
                    'name' => $player['name'],
                    'age' => $player['age'],
                    'role' => $player['role'],
                    'avatar_key' => $player['avatar_key'],
                    'pin' => $player['pin'] !== null ? password_hash($player['pin'], PASSWORD_DEFAULT) : null,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Vordefinierte Eltern-PINs (siehe Klassen-Konstante PARENT_PINS).
     * Backfill fuer Installationen, die noch gar keine PIN/Passwort haben
     * (z. B. eine ganz neue Familie ueber einen alten DB-Stand). Idempotent:
     * setzt nur, wo noch nichts hinterlegt ist.
     */
    public function seedParentPinsIfMissing(): void
    {
        $statement = $this->pdo->prepare(
            "SELECT id, name FROM players WHERE role = 'parent' AND password_hash IS NULL",
        );
        $statement->execute();
        $parentsWithoutPin = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($parentsWithoutPin === []) {
            return;
        }

        $updateStatement = $this->pdo->prepare(
            'UPDATE players SET password_hash = :password_hash WHERE id = :id',
        );

        foreach ($parentsWithoutPin as $parent) {
            $pin = self::PARENT_PINS[$parent['name']] ?? null;
            if ($pin === null) {
                continue;
            }

            $updateStatement->execute([
                'password_hash' => password_hash($pin, PASSWORD_DEFAULT),
                'id' => $parent['id'],
            ]);
        }
    }

    /**
     * Migrations-Backfill: die Live-Familie hatte bereits die langen
     * Text-Passwoerter aus der ersten Login-Version (siehe
     * LEGACY_PARENT_PASSWORDS) - die werden hier durch die neuen 4-stelligen
     * PINs ersetzt. Idempotent ueber password_verify() gegen das bekannte
     * alte Passwort: sobald einmal ersetzt, matcht das alte Passwort nicht
     * mehr, ein von Hand geaenderter eigener Wert wird nie angefasst.
     */
    public function seedParentPinsFromLegacyPasswords(): void
    {
        $statement = $this->pdo->prepare(
            "SELECT id, name, password_hash FROM players WHERE role = 'parent' AND password_hash IS NOT NULL",
        );
        $statement->execute();
        $parents = $statement->fetchAll(PDO::FETCH_ASSOC);

        $updateStatement = $this->pdo->prepare(
            'UPDATE players SET password_hash = :password_hash WHERE id = :id',
        );

        foreach ($parents as $parent) {
            $legacyPassword = self::LEGACY_PARENT_PASSWORDS[$parent['name']] ?? null;
            $newPin = self::PARENT_PINS[$parent['name']] ?? null;
            if ($legacyPassword === null || $newPin === null) {
                continue;
            }

            if (password_verify($legacyPassword, (string) $parent['password_hash'])) {
                $updateStatement->execute([
                    'password_hash' => password_hash($newPin, PASSWORD_DEFAULT),
                    'id' => $parent['id'],
                ]);
            }
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

    /**
     * Legt den Gebaeude-Katalog an (aktuell nur die Strandhuette), aber nur,
     * wenn er noch leer ist. Setzt einen befuellten Ressourcen-Katalog voraus.
     */
    public function seedBuildingCatalogIfEmpty(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM buildings')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $resourcesByKey = [];
        foreach ($this->pdo->query('SELECT id, key FROM resources')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $resourcesByKey[$row['key']] = (int) $row['id'];
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec(
                "INSERT INTO buildings (key, name, description, unlock_minigame_key)
                 VALUES ('beach_hut', 'Strandhütte', 'Die erste Unterkunft der Familie auf der Insel.', 'schatzsuche')",
            );
            $buildingId = (int) $this->pdo->lastInsertId();

            $costs = ['wood' => 20, 'metal' => 10, 'fabric' => 8, 'rope' => 5];
            $costStatement = $this->pdo->prepare(
                'INSERT INTO building_costs (building_id, resource_id, required_amount)
                 VALUES (:building_id, :resource_id, :amount)',
            );

            foreach ($costs as $resourceKey => $amount) {
                $resourceId = $resourcesByKey[$resourceKey] ?? null;
                if ($resourceId === null) {
                    continue;
                }

                $costStatement->execute(['building_id' => $buildingId, 'resource_id' => $resourceId, 'amount' => $amount]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Startet das aktive Bauprojekt (Strandhuette) fuer die Demo-Familie,
     * aber nur, wenn noch kein Bauprojekt existiert.
     */
    public function seedActiveFamilyBuildingIfEmpty(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM family_buildings')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $familyId = $this->pdo->query('SELECT id FROM families LIMIT 1')->fetchColumn();
        $buildingId = $this->pdo->query("SELECT id FROM buildings WHERE key = 'beach_hut' LIMIT 1")->fetchColumn();
        if ($familyId === false || $buildingId === false) {
            return;
        }

        $statement = $this->pdo->prepare(
            "INSERT INTO family_buildings (family_id, building_id, status, stage)
             VALUES (:family_id, :building_id, 'in_progress', 1)",
        );
        $statement->execute(['family_id' => (int) $familyId, 'building_id' => (int) $buildingId]);
    }

    /**
     * Legt den Wachturm als zweites Gebaeude an (freigeschaltet nach der
     * Strandhuette), aber nur, wenn er noch nicht im Katalog existiert.
     * Verknuepft die Strandhuette per unlocks_building_key mit dem Wachturm.
     */
    public function seedWatchtowerBuildingIfMissing(): void
    {
        $exists = (bool) $this->pdo->query("SELECT 1 FROM buildings WHERE key = 'watchtower' LIMIT 1")->fetchColumn();
        if ($exists) {
            return;
        }

        $resourcesByKey = [];
        foreach ($this->pdo->query('SELECT id, key FROM resources')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $resourcesByKey[$row['key']] = (int) $row['id'];
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec(
                "INSERT INTO buildings (key, name, description)
                 VALUES ('watchtower', 'Wachturm', 'Ein Ausguck, um die See nach Rettung abzusuchen.')",
            );
            $buildingId = (int) $this->pdo->lastInsertId();

            $costs = ['wood' => 15, 'metal' => 8, 'fabric' => 3, 'rope' => 6];
            $costStatement = $this->pdo->prepare(
                'INSERT INTO building_costs (building_id, resource_id, required_amount)
                 VALUES (:building_id, :resource_id, :amount)',
            );

            foreach ($costs as $resourceKey => $amount) {
                $resourceId = $resourcesByKey[$resourceKey] ?? null;
                if ($resourceId === null) {
                    continue;
                }

                $costStatement->execute(['building_id' => $buildingId, 'resource_id' => $resourceId, 'amount' => $amount]);
            }

            $this->pdo->exec("UPDATE buildings SET unlocks_building_key = 'watchtower' WHERE key = 'beach_hut'");

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Schaltet den Wachturm nachtraeglich fuer Familien frei, deren Strandhuette
     * schon fertiggestellt war, bevor es den Wachturm als Gebaeude gab. Sicher
     * fuer wiederholten Aufruf (unlockForFamilyIfMissing ist idempotent).
     */
    public function seedWatchtowerUnlockForCompletedBeachHuts(): void
    {
        $watchtowerId = $this->pdo->query("SELECT id FROM buildings WHERE key = 'watchtower' LIMIT 1")->fetchColumn();
        if ($watchtowerId === false) {
            return;
        }

        $rows = $this->pdo->query(
            "SELECT fb.family_id
             FROM family_buildings fb
             JOIN buildings b ON b.id = fb.building_id
             WHERE b.key = 'beach_hut' AND fb.status = 'completed'",
        )->fetchAll(PDO::FETCH_ASSOC);

        $statement = $this->pdo->prepare(
            "INSERT OR IGNORE INTO family_buildings (family_id, building_id, status, stage)
             VALUES (:family_id, :building_id, 'in_progress', 1)",
        );

        foreach ($rows as $row) {
            $statement->execute(['family_id' => (int) $row['family_id'], 'building_id' => (int) $watchtowerId]);
        }
    }

    /**
     * Legt den Minispiel-Katalog an (aktuell nur die Schatzsuche), aber nur,
     * wenn er noch leer ist.
     */
    public function seedMinigameCatalogIfEmpty(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM minigames')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO minigames (key, name, description) VALUES (:key, :name, :description)',
        );
        $statement->execute([
            'key' => 'schatzsuche',
            'name' => 'Schatzsuche am Strand',
            'description' => 'Finde die versteckten Gegenstaende am Strand.',
        ]);
    }
}
