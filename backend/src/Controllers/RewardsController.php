<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequireAuth;
use App\Services\PlayerService;
use App\Services\RewardRevealService;
use App\Support\JsonResponse;
use App\Support\Session;

final class RewardsController
{
    public function __construct(
        private readonly RewardRevealService $rewardRevealService,
        private readonly PlayerService $playerService,
    ) {
    }

    /**
     * @param array{id: string} $params
     */
    public function index(array $params): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        if ((int) $params['id'] !== Session::playerId()) {
            JsonResponse::error(403, 'FORBIDDEN', 'Belohnungen koennen nur fuer das eigene Profil abgerufen werden.');

            return;
        }

        $result = $this->rewardRevealService->getUpdatesForPlayer((int) Session::familyId(), (int) $params['id']);

        JsonResponse::success($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function acknowledge(array $params): void
    {
        if (!RequireAuth::check()) {
            return;
        }

        $result = $this->playerService->markRewardsSeen(
            (int) Session::familyId(),
            (int) $params['id'],
            (int) Session::playerId(),
        );

        if (!$result['success']) {
            $statusCode = match ($result['code']) {
                'PLAYER_NOT_FOUND' => 404,
                'FORBIDDEN' => 403,
                default => 422,
            };
            JsonResponse::error($statusCode, $result['code'], $result['message']);

            return;
        }

        JsonResponse::success([]);
    }
}
