<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTransactionRepository;
use App\Repositories\TaskRepository;
use PDO;
use Throwable;

final class TaskService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly TaskRepository $tasks,
        private readonly ResourceRepository $resources,
        private readonly ResourceTransactionRepository $transactions,
        private readonly ActivityLogRepository $activityLog,
        private readonly PlayerRepository $players,
    ) {
    }

    /**
     * @param array<string, mixed> $rewards resource_key => amount
     * @return array{success: true, taskId: int}|array{success: false, code: string, message: string}
     */
    public function createTask(
        int $familyId,
        int $createdByPlayerId,
        int $assignedPlayerId,
        string $title,
        ?string $description,
        ?string $dueDate,
        array $rewards,
    ): array {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 120) {
            return $this->validationError('Bitte einen gueltigen Titel angeben (1-120 Zeichen).');
        }

        if ($description !== null && mb_strlen($description) > 2000) {
            return $this->validationError('Die Beschreibung darf hoechstens 2000 Zeichen lang sein.');
        }

        if ($this->players->findActiveByIdAndFamily($assignedPlayerId, $familyId) === null) {
            return ['success' => false, 'code' => 'PLAYER_NOT_FOUND', 'message' => 'Das zugewiesene Familienmitglied wurde nicht gefunden.'];
        }

        $normalizedRewards = [];
        $hasPositiveReward = false;

        foreach ($rewards as $resourceKey => $amount) {
            $amount = (int) $amount;
            if ($amount < 0 || $amount > 99) {
                return $this->validationError('Rohstoffmengen muessen zwischen 0 und 99 liegen.');
            }

            $resourceId = $this->resources->findIdByKey((string) $resourceKey);
            if ($resourceId === null) {
                return $this->validationError("Unbekannter Rohstoff: {$resourceKey}");
            }

            if ($amount > 0) {
                $hasPositiveReward = true;
            }
            $normalizedRewards[$resourceId] = $amount;
        }

        if (!$hasPositiveReward) {
            return $this->validationError('Mindestens eine Rohstoffbelohnung muss groesser als 0 sein.');
        }

        $this->pdo->beginTransaction();
        try {
            $taskId = $this->tasks->create($familyId, $assignedPlayerId, $createdByPlayerId, $title, $description, $dueDate);
            foreach ($normalizedRewards as $resourceId => $amount) {
                $this->tasks->addReward($taskId, $resourceId, $amount);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['success' => true, 'taskId' => $taskId];
    }

    /**
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function completeTask(int $taskId, int $familyId, int $playerId): array
    {
        $task = $this->tasks->findByIdAndFamily($taskId, $familyId);
        if ($task === null) {
            return $this->notFound();
        }

        if ((int) $task['assigned_player_id'] !== $playerId) {
            return ['success' => false, 'code' => 'FORBIDDEN', 'message' => 'Diese Aufgabe gehoert nicht zu deinem Profil.'];
        }

        if (!$this->tasks->markCompletedPending($taskId, $familyId)) {
            return $this->invalidStatus('Aufgabe kann in diesem Status nicht als erledigt gemeldet werden.');
        }

        $this->activityLog->record(
            $familyId,
            $playerId,
            'task_completed',
            sprintf('Aufgabe "%s" wurde als erledigt gemeldet.', $task['title']),
        );

        return ['success' => true];
    }

    /**
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function approveTask(int $taskId, int $familyId, int $approvedByPlayerId): array
    {
        $task = $this->tasks->findByIdAndFamily($taskId, $familyId);
        if ($task === null) {
            return $this->notFound();
        }

        $this->pdo->beginTransaction();
        try {
            if (!$this->tasks->markApproved($taskId, $familyId, $approvedByPlayerId)) {
                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'code' => 'TASK_ALREADY_REWARDED',
                    'message' => 'Diese Aufgabe wurde bereits bearbeitet oder ist nicht bestaetigungsbereit.',
                ];
            }

            foreach ($this->tasks->findRewardsForTask($taskId) as $reward) {
                $resourceId = (int) $reward['resource_id'];
                $amount = (int) $reward['amount'];
                if ($amount <= 0) {
                    continue;
                }

                $this->resources->incrementBalance($familyId, $resourceId, $amount);
                $this->transactions->record(
                    $familyId,
                    (int) $task['assigned_player_id'],
                    $resourceId,
                    $amount,
                    'task_reward',
                    'task',
                    $taskId,
                    sprintf('Belohnung fuer Aufgabe "%s"', $task['title']),
                );
            }

            $this->activityLog->record(
                $familyId,
                $approvedByPlayerId,
                'task_approved',
                sprintf('Aufgabe "%s" wurde bestaetigt und belohnt.', $task['title']),
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['success' => true];
    }

    /**
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function rejectTask(int $taskId, int $familyId, ?string $parentNote): array
    {
        if ($parentNote !== null && mb_strlen($parentNote) > 500) {
            return $this->validationError('Die Notiz darf hoechstens 500 Zeichen lang sein.');
        }

        $task = $this->tasks->findByIdAndFamily($taskId, $familyId);
        if ($task === null) {
            return $this->notFound();
        }

        if (!$this->tasks->markRejected($taskId, $familyId, $parentNote)) {
            return $this->invalidStatus('Aufgabe kann in diesem Status nicht abgelehnt werden.');
        }

        $this->activityLog->record(
            $familyId,
            null,
            'task_rejected',
            sprintf('Aufgabe "%s" wurde abgelehnt.', $task['title']),
        );

        return ['success' => true];
    }

    /**
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function reopenTask(int $taskId, int $familyId): array
    {
        if ($this->tasks->findByIdAndFamily($taskId, $familyId) === null) {
            return $this->notFound();
        }

        if (!$this->tasks->reopen($taskId, $familyId)) {
            return $this->invalidStatus('Nur abgelehnte Aufgaben koennen wieder geoeffnet werden.');
        }

        return ['success' => true];
    }

    /**
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function cancelTask(int $taskId, int $familyId): array
    {
        if ($this->tasks->findByIdAndFamily($taskId, $familyId) === null) {
            return $this->notFound();
        }

        if (!$this->tasks->cancel($taskId, $familyId)) {
            return $this->invalidStatus('Aufgabe kann in diesem Status nicht geloescht werden.');
        }

        return ['success' => true];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTasksForPlayer(int $familyId, string $role, int $playerId): array
    {
        $assignedFilter = $role === 'parent' ? null : $playerId;

        return array_map(
            fn (array $task) => $this->formatTask($task),
            $this->tasks->findAllForFamily($familyId, $assignedFilter),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findTaskForFamily(int $taskId, int $familyId): ?array
    {
        $task = $this->tasks->findByIdAndFamily($taskId, $familyId);

        return $task === null ? null : $this->formatTask($task);
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function formatTask(array $task): array
    {
        $rewards = $this->tasks->findRewardsForTask((int) $task['id']);

        return [
            'id' => (int) $task['id'],
            'title' => $task['title'],
            'description' => $task['description'],
            'status' => $task['status'],
            'assignedPlayerId' => (int) $task['assigned_player_id'],
            'createdByPlayerId' => (int) $task['created_by_player_id'],
            'dueDate' => $task['due_date'],
            'parentNote' => $task['parent_note'],
            'completedAt' => $task['completed_at'],
            'approvedAt' => $task['approved_at'],
            'createdAt' => $task['created_at'],
            'rewards' => array_map(
                static fn (array $reward) => [
                    'resourceId' => (int) $reward['resource_id'],
                    'amount' => (int) $reward['amount'],
                ],
                $rewards,
            ),
        ];
    }

    /**
     * @return array{success: false, code: string, message: string}
     */
    private function validationError(string $message): array
    {
        return ['success' => false, 'code' => 'VALIDATION_ERROR', 'message' => $message];
    }

    /**
     * @return array{success: false, code: string, message: string}
     */
    private function notFound(): array
    {
        return ['success' => false, 'code' => 'TASK_NOT_FOUND', 'message' => 'Aufgabe wurde nicht gefunden.'];
    }

    /**
     * @return array{success: false, code: string, message: string}
     */
    private function invalidStatus(string $message): array
    {
        return ['success' => false, 'code' => 'INVALID_STATUS', 'message' => $message];
    }
}
