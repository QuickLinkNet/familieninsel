<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Clock;
use PDO;

final class TaskRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(
        int $familyId,
        int $assignedPlayerId,
        int $createdByPlayerId,
        string $title,
        ?string $description,
        ?string $dueDate,
    ): int {
        $statement = $this->pdo->prepare(
            "INSERT INTO tasks (family_id, assigned_player_id, created_by_player_id, title, description, due_date, status)
             VALUES (:family_id, :assigned_player_id, :created_by_player_id, :title, :description, :due_date, 'open')",
        );
        $statement->execute([
            'family_id' => $familyId,
            'assigned_player_id' => $assignedPlayerId,
            'created_by_player_id' => $createdByPlayerId,
            'title' => $title,
            'description' => $description,
            'due_date' => $dueDate,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function addReward(int $taskId, int $resourceId, int $amount): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO task_rewards (task_id, resource_id, amount) VALUES (:task_id, :resource_id, :amount)',
        );
        $statement->execute(['task_id' => $taskId, 'resource_id' => $resourceId, 'amount' => $amount]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdAndFamily(int $id, int $familyId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id AND family_id = :family_id LIMIT 1');
        $statement->execute(['id' => $id, 'family_id' => $familyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array{id: int, resource_id: int, amount: int}>
     */
    public function findRewardsForTask(int $taskId): array
    {
        $statement = $this->pdo->prepare('SELECT id, resource_id, amount FROM task_rewards WHERE task_id = :task_id');
        $statement->execute(['task_id' => $taskId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllForFamily(int $familyId, ?int $assignedPlayerId): array
    {
        if ($assignedPlayerId !== null) {
            $statement = $this->pdo->prepare(
                "SELECT * FROM tasks WHERE family_id = :family_id AND assigned_player_id = :assigned_player_id
                 AND status != 'cancelled' ORDER BY created_at DESC",
            );
            $statement->execute(['family_id' => $familyId, 'assigned_player_id' => $assignedPlayerId]);
        } else {
            $statement = $this->pdo->prepare(
                "SELECT * FROM tasks WHERE family_id = :family_id AND status != 'cancelled' ORDER BY created_at DESC",
            );
            $statement->execute(['family_id' => $familyId]);
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markCompletedPending(int $id, int $familyId): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE tasks SET status = 'completed_pending', completed_at = :now1, updated_at = :now2
             WHERE id = :id AND family_id = :family_id AND status = 'open'",
        );
        $statement->execute(['id' => $id, 'family_id' => $familyId, 'now1' => Clock::nowIso(), 'now2' => Clock::nowIso()]);

        return $statement->rowCount() > 0;
    }

    public function markApproved(int $id, int $familyId, int $approvedByPlayerId): bool
    {
        $now = Clock::nowIso();
        $statement = $this->pdo->prepare(
            "UPDATE tasks SET status = 'approved', approved_at = :now1, approved_by_player_id = :approved_by,
                    rewarded_at = :now2, updated_at = :now3
             WHERE id = :id AND family_id = :family_id AND status = 'completed_pending'",
        );
        $statement->execute([
            'id' => $id,
            'family_id' => $familyId,
            'approved_by' => $approvedByPlayerId,
            'now1' => $now,
            'now2' => $now,
            'now3' => $now,
        ]);

        return $statement->rowCount() > 0;
    }

    public function markRejected(int $id, int $familyId, ?string $parentNote): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE tasks SET status = 'rejected', parent_note = :note, updated_at = :now
             WHERE id = :id AND family_id = :family_id AND status = 'completed_pending'",
        );
        $statement->execute(['id' => $id, 'family_id' => $familyId, 'note' => $parentNote, 'now' => Clock::nowIso()]);

        return $statement->rowCount() > 0;
    }

    public function reopen(int $id, int $familyId): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE tasks SET status = 'open', updated_at = :now
             WHERE id = :id AND family_id = :family_id AND status = 'rejected'",
        );
        $statement->execute(['id' => $id, 'family_id' => $familyId, 'now' => Clock::nowIso()]);

        return $statement->rowCount() > 0;
    }

    public function cancel(int $id, int $familyId): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE tasks SET status = 'cancelled', updated_at = :now
             WHERE id = :id AND family_id = :family_id AND status IN ('open', 'rejected')",
        );
        $statement->execute(['id' => $id, 'family_id' => $familyId, 'now' => Clock::nowIso()]);

        return $statement->rowCount() > 0;
    }

    /**
     * Alle (nicht bereits geloeschten) Aufgaben-IDs eines Spielers - fuer den
     * Test-/Admin-Reset, um zugehoerige Ledger-Eintraege (resource_transactions,
     * activity_log) mit aufzuraeumen, bevor die Aufgabe selbst zurueckgesetzt wird.
     *
     * @return array<int, int>
     */
    public function findIdsForPlayer(int $familyId, int $playerId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM tasks WHERE family_id = :family_id AND assigned_player_id = :player_id
             AND status != 'cancelled'",
        );
        $statement->execute(['family_id' => $familyId, 'player_id' => $playerId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Test-/Admin-Reset: setzt alle Aufgaben eines Spielers zurueck auf
     * "offen", als waere noch nichts gemeldet/bestaetigt worden. Ruehrt die
     * Rohstoff-Belohnungen selbst nicht an - das ist bewusst Aufgabe des
     * aufrufenden ResetService (Ledger-Bereinigung), damit diese Methode ein
     * einfacher, vorhersehbarer Baustein bleibt.
     */
    public function resetForPlayer(int $familyId, int $playerId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE tasks SET status = 'open', completed_at = NULL, approved_at = NULL,
                    approved_by_player_id = NULL, rewarded_at = NULL, parent_note = NULL, updated_at = :now
             WHERE family_id = :family_id AND assigned_player_id = :player_id AND status != 'cancelled'",
        );
        $statement->execute(['family_id' => $familyId, 'player_id' => $playerId, 'now' => Clock::nowIso()]);
    }

    /**
     * Wie resetForPlayer(), aber fuer die gesamte Familie auf einmal (Teil des
     * grossen "Fortschritt komplett zuruecksetzen"-Knopfs).
     */
    public function resetAllForFamily(int $familyId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE tasks SET status = 'open', completed_at = NULL, approved_at = NULL,
                    approved_by_player_id = NULL, rewarded_at = NULL, parent_note = NULL, updated_at = :now
             WHERE family_id = :family_id AND status != 'cancelled'",
        );
        $statement->execute(['family_id' => $familyId, 'now' => Clock::nowIso()]);
    }
}
