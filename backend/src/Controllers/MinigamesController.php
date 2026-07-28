<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequireAuth;
use App\Services\MinigameService;
use App\Support\JsonResponse;
use App\Support\Session;

final class MinigamesController
{
    public function __construct(private readonly MinigameService $minigameService)
    {
    }

    public function index(): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        JsonResponse::success(['minigames' => $this->minigameService->listForFamily((int) Session::familyId())]);
    }

    /**
     * @param array{key: string} $params
     */
    public function show(array $params): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $minigame = $this->minigameService->getStatusForFamily((int) Session::familyId(), $params['key']);
        if ($minigame === null) {
            JsonResponse::error(404, 'MINIGAME_NOT_FOUND', 'Dieses Minispiel wurde nicht gefunden.');

            return;
        }

        JsonResponse::success(['minigame' => $minigame]);
    }

    /**
     * @param array{key: string} $params
     */
    public function complete(array $params): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $result = $this->minigameService->complete(
            (int) Session::familyId(),
            (int) Session::playerId(),
            $params['key'],
        );

        if (!$result['success']) {
            $statusCode = match ($result['code']) {
                'MINIGAME_NOT_FOUND' => 404,
                'MINIGAME_LOCKED' => 403,
                default => 400,
            };
            JsonResponse::error($statusCode, $result['code'], $result['message']);

            return;
        }

        JsonResponse::success(['starsAwarded' => $result['starsAwarded']]);
    }
}
