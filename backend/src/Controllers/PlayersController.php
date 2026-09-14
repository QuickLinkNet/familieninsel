<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequireParent;
use App\Services\AuthService;
use App\Services\PlayerPhotoService;
use App\Services\PlayerService;
use App\Support\JsonResponse;
use App\Support\Request;
use App\Support\Session;

final class PlayersController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly PlayerPhotoService $photoService,
        private readonly PlayerService $playerService,
    ) {
    }

    public function index(): void
    {
        $familyId = Session::familyId();
        if ($familyId === null) {
            JsonResponse::error(401, 'UNAUTHENTICATED', 'Bitte zuerst mit dem Familiencode anmelden.');

            return;
        }

        $players = array_map(
            fn (array $player): array => [
                'id' => (int) $player['id'],
                'name' => $player['name'],
                'age' => $player['age'] !== null ? (int) $player['age'] : null,
                'role' => $player['role'],
                'avatarKey' => $player['avatar_key'],
                'hasPhoto' => $this->photoService->hasPhoto($familyId, (int) $player['id']),
            ],
            $this->authService->listActivePlayers($familyId),
        );

        JsonResponse::success(['players' => $players]);
    }

    public function store(): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $body = Request::jsonBody();
        $ageInput = $body['age'] ?? null;
        $age = ($ageInput !== null && $ageInput !== '') ? (int) $ageInput : null;

        $result = $this->playerService->createChild((int) Session::familyId(), (string) ($body['name'] ?? ''), $age);

        if (!$result['success']) {
            JsonResponse::error(422, $result['code'], $result['message']);

            return;
        }

        JsonResponse::success(['playerId' => $result['playerId']]);
    }

    /**
     * Vollstaendige Mitgliederliste fuer die Benutzerverwaltung, inklusive
     * deaktivierter Profile. Bewusst getrennt von index() (aktive Profile
     * fuer den normalen Spielbetrieb, z. B. Aufgaben-Zuweisung).
     */
    public function manage(): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $familyId = (int) Session::familyId();
        $players = array_map(
            fn (array $player): array => [
                'id' => $player['id'],
                'name' => $player['name'],
                'age' => $player['age'],
                'role' => $player['role'],
                'avatarKey' => $player['avatar_key'],
                'hasPhoto' => $this->photoService->hasPhoto($familyId, $player['id']),
                'isActive' => $player['is_active'],
            ],
            $this->playerService->listAll($familyId),
        );

        JsonResponse::success(['players' => $players]);
    }

    /**
     * @param array{id: string} $params
     */
    public function update(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $body = Request::jsonBody();
        $ageInput = $body['age'] ?? null;
        $age = ($ageInput !== null && $ageInput !== '') ? (int) $ageInput : null;

        $result = $this->playerService->updatePlayer(
            (int) Session::familyId(),
            (int) $params['id'],
            (string) ($body['name'] ?? ''),
            $age,
        );
        $this->respond($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function setPin(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $body = Request::jsonBody();
        $result = $this->playerService->setParentPin(
            (int) Session::familyId(),
            (int) $params['id'],
            (string) ($body['pin'] ?? ''),
        );
        $this->respond($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function activate(array $params): void
    {
        $this->changeActiveState($params, true);
    }

    /**
     * @param array{id: string} $params
     */
    public function deactivate(array $params): void
    {
        $this->changeActiveState($params, false);
    }

    /**
     * @param array{id: string} $params
     */
    private function changeActiveState(array $params, bool $active): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $result = $this->playerService->setActive(
            (int) Session::familyId(),
            (int) $params['id'],
            $active,
            (int) Session::playerId(),
        );
        $this->respond($result);
    }

    /**
     * @param array{success: bool, code?: string, message?: string} $result
     */
    private function respond(array $result): void
    {
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

    /**
     * @param array{id: string} $params
     */
    public function loginTokenStatus(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $result = $this->playerService->loginTokenStatus((int) Session::familyId(), (int) $params['id']);
        $this->respondTokenAction($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function regenerateLoginToken(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $result = $this->playerService->generateLoginToken((int) Session::familyId(), (int) $params['id']);
        $this->respondTokenAction($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function revokeLoginToken(array $params): void
    {
        if (!RequireParent::check()) {
            return;
        }

        $result = $this->playerService->revokeLoginToken((int) Session::familyId(), (int) $params['id']);
        $this->respondTokenAction($result);
    }

    /**
     * @param array{success: bool, code?: string, message?: string} $result
     */
    private function respondTokenAction(array $result): void
    {
        if (!$result['success']) {
            $statusCode = $result['code'] === 'PLAYER_NOT_FOUND' ? 404 : 422;
            JsonResponse::error($statusCode, $result['code'], $result['message']);

            return;
        }

        $data = $result;
        unset($data['success']);
        JsonResponse::success($data);
    }
}
