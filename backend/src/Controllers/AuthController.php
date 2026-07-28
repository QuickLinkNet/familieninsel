<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\JsonResponse;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Session;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly int $parentUnlockSeconds,
        private readonly int $pinMaxAttempts,
        private readonly int $pinLockoutSeconds,
        private readonly string $logDirectory,
    ) {
    }

    public function familyLogin(): void
    {
        $code = trim((string) (Request::jsonBody()['familyCode'] ?? ''));

        if ($code === '' || strlen($code) > 64) {
            JsonResponse::error(422, 'VALIDATION_ERROR', 'Bitte einen gueltigen Familiencode eingeben.');

            return;
        }

        $family = $this->authService->verifyFamilyCode($code);
        if ($family === null) {
            JsonResponse::error(401, 'INVALID_FAMILY_CODE', 'Der Familiencode ist nicht korrekt.');

            return;
        }

        Session::setFamily($family['id']);
        Session::regenerate();

        JsonResponse::success(['family' => $family]);
    }

    public function selectProfile(): void
    {
        $familyId = Session::familyId();
        if ($familyId === null) {
            JsonResponse::error(401, 'UNAUTHENTICATED', 'Bitte zuerst mit dem Familiencode anmelden.');

            return;
        }

        $playerId = (int) (Request::jsonBody()['playerId'] ?? 0);
        $profile = $this->authService->findSelectableProfile($playerId, $familyId);

        if ($profile === null) {
            JsonResponse::error(404, 'PLAYER_NOT_FOUND', 'Dieses Profil wurde nicht gefunden.');

            return;
        }

        Session::selectProfile((int) $profile['id'], $profile['role']);

        JsonResponse::success(['player' => $this->formatPlayer($profile)]);
    }

    public function parentUnlock(): void
    {
        $familyId = Session::familyId();
        $playerId = Session::playerId();

        if ($familyId === null || $playerId === null) {
            JsonResponse::error(401, 'UNAUTHENTICATED', 'Bitte zuerst ein Profil waehlen.');

            return;
        }

        if (Session::isPinLocked()) {
            JsonResponse::error(429, 'PIN_LOCKED', 'Zu viele Fehlversuche. Bitte kurz warten.');

            return;
        }

        $pin = (string) (Request::jsonBody()['pin'] ?? '');

        if ($pin === '' || strlen($pin) > 16) {
            JsonResponse::error(422, 'VALIDATION_ERROR', 'Bitte eine gueltige PIN eingeben.');

            return;
        }

        if (!$this->authService->verifyParentPin($playerId, $familyId, $pin)) {
            Session::registerFailedPinAttempt($this->pinMaxAttempts, $this->pinLockoutSeconds);
            Logger::security($this->logDirectory, "Fehlgeschlagener Eltern-PIN-Versuch fuer player_id={$playerId}");
            JsonResponse::error(401, 'INVALID_PIN', 'Die PIN ist nicht korrekt.');

            return;
        }

        Session::resetPinAttempts();
        Session::unlockParent($this->parentUnlockSeconds);

        JsonResponse::success(['parentUnlocked' => true]);
    }

    public function logout(): void
    {
        Session::destroy();
        JsonResponse::success([]);
    }

    public function session(): void
    {
        JsonResponse::success([
            'authenticated' => Session::familyId() !== null,
            'familyId' => Session::familyId(),
            'playerId' => Session::playerId(),
            'playerRole' => Session::playerRole(),
            'parentUnlocked' => Session::isParentUnlocked(),
            'csrfToken' => Session::csrfToken(),
        ]);
    }

    /**
     * @param array{id: int, name: string, age: int|null, role: string, avatar_key: string} $player
     * @return array{id: int, name: string, age: int|null, role: string, avatarKey: string}
     */
    private function formatPlayer(array $player): array
    {
        return [
            'id' => (int) $player['id'],
            'name' => $player['name'],
            'age' => $player['age'] !== null ? (int) $player['age'] : null,
            'role' => $player['role'],
            'avatarKey' => $player['avatar_key'],
        ];
    }
}
