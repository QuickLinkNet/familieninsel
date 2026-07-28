<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\JsonResponse;
use App\Support\Session;

final class PlayersController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function index(): void
    {
        $familyId = Session::familyId();
        if ($familyId === null) {
            JsonResponse::error(401, 'UNAUTHENTICATED', 'Bitte zuerst mit dem Familiencode anmelden.');

            return;
        }

        $players = array_map(
            static fn (array $player): array => [
                'id' => (int) $player['id'],
                'name' => $player['name'],
                'age' => $player['age'] !== null ? (int) $player['age'] : null,
                'role' => $player['role'],
                'avatarKey' => $player['avatar_key'],
            ],
            $this->authService->listActivePlayers($familyId),
        );

        JsonResponse::success(['players' => $players]);
    }
}
