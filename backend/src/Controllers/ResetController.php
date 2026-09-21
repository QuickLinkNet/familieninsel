<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequireParent;
use App\Services\ResetService;
use App\Support\JsonResponse;
use App\Support\Session;

final class ResetController
{
    public function __construct(private readonly ResetService $resetService)
    {
    }

    /**
     * @param array{id: string} $params
     */
    public function resetIntro(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $this->respondPlayerAction(
            $this->resetService->resetIntroForPlayer((int) Session::familyId(), (int) $params['id']),
        );
    }

    /**
     * @param array{id: string} $params
     */
    public function resetRewards(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $this->respondPlayerAction(
            $this->resetService->resetRewardsCursorForPlayer((int) Session::familyId(), (int) $params['id']),
        );
    }

    /**
     * @param array{id: string} $params
     */
    public function resetTasks(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $this->respondPlayerAction(
            $this->resetService->resetTasksForPlayer((int) Session::familyId(), (int) $params['id']),
        );
    }

    public function resetFamilyProgress(): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $this->resetService->resetFamilyProgress((int) Session::familyId());

        JsonResponse::success([]);
    }

    /**
     * @param array{success: bool, code?: string, message?: string} $result
     */
    private function respondPlayerAction(array $result): void
    {
        if (!$result['success']) {
            $statusCode = $result['code'] === 'PLAYER_NOT_FOUND' ? 404 : 422;
            JsonResponse::error($statusCode, $result['code'], $result['message']);

            return;
        }

        JsonResponse::success([]);
    }
}
