<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequireAuth;
use App\Middleware\RequireParent;
use App\Services\TaskService;
use App\Support\JsonResponse;
use App\Support\Request;
use App\Support\Session;

final class TasksController
{
    public function __construct(private readonly TaskService $taskService)
    {
    }

    public function index(): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $tasks = $this->taskService->listTasksForPlayer(
            (int) Session::familyId(),
            (string) Session::playerRole(),
            (int) Session::playerId(),
        );

        JsonResponse::success(['tasks' => $tasks]);
    }

    /**
     * @param array{id: string} $params
     */
    public function show(array $params): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $task = $this->taskService->findTaskForFamily((int) $params['id'], (int) Session::familyId());
        if ($task === null) {
            JsonResponse::error(404, 'TASK_NOT_FOUND', 'Aufgabe wurde nicht gefunden.');

            return;
        }

        if (Session::playerRole() !== 'parent' && $task['assignedPlayerId'] !== Session::playerId()) {
            JsonResponse::error(403, 'FORBIDDEN', 'Diese Aufgabe gehoert nicht zu deinem Profil.');

            return;
        }

        JsonResponse::success(['task' => $task]);
    }

    public function store(): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $body = Request::jsonBody();
        $rewards = is_array($body['rewards'] ?? null) ? $body['rewards'] : [];

        $result = $this->taskService->createTask(
            (int) Session::familyId(),
            (int) Session::playerId(),
            (int) ($body['assignedPlayerId'] ?? 0),
            (string) ($body['title'] ?? ''),
            isset($body['description']) ? (string) $body['description'] : null,
            isset($body['dueDate']) ? (string) $body['dueDate'] : null,
            $rewards,
        );

        $this->respond($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function complete(array $params): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $result = $this->taskService->completeTask(
            (int) $params['id'],
            (int) Session::familyId(),
            (int) Session::playerId(),
        );

        $this->respond($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function approve(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $result = $this->taskService->approveTask(
            (int) $params['id'],
            (int) Session::familyId(),
            (int) Session::playerId(),
        );

        $this->respond($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function reject(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $body = Request::jsonBody();
        $result = $this->taskService->rejectTask(
            (int) $params['id'],
            (int) Session::familyId(),
            isset($body['parentNote']) ? (string) $body['parentNote'] : null,
        );

        $this->respond($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function reopen(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $result = $this->taskService->reopenTask((int) $params['id'], (int) Session::familyId());

        $this->respond($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function destroy(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $result = $this->taskService->cancelTask((int) $params['id'], (int) Session::familyId());

        $this->respond($result);
    }

    /**
     * @param array{success: bool, code?: string, message?: string, taskId?: int} $result
     */
    private function respond(array $result): void
    {
        if (!$result['success']) {
            $statusCode = match ($result['code']) {
                'TASK_NOT_FOUND', 'PLAYER_NOT_FOUND' => 404,
                'FORBIDDEN' => 403,
                'VALIDATION_ERROR', 'INVALID_STATUS', 'TASK_ALREADY_REWARDED' => 422,
                default => 400,
            };
            JsonResponse::error($statusCode, $result['code'], $result['message']);

            return;
        }

        $data = isset($result['taskId']) ? ['taskId' => $result['taskId']] : [];
        JsonResponse::success($data);
    }
}
