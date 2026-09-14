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
        private readonly int $loginMaxAttempts,
        private readonly int $loginLockoutSeconds,
        private readonly int $childSessionLifetimeSeconds,
        private readonly string $logDirectory,
    ) {
    }

    /**
     * Elternprofile fuer den Login-Bildschirm, bevor ueberhaupt eine Session
     * existiert (kein RequireAuth noetig - siehe AuthService::listParentCandidates).
     */
    public function parents(): void
    {
        $players = array_map(
            fn (array $player): array => $this->formatPlayer($player),
            $this->authService->listParentCandidates(),
        );

        JsonResponse::success(['players' => $players]);
    }

    public function parentLogin(): void
    {
        if (Session::isLoginLocked()) {
            JsonResponse::error(429, 'LOGIN_LOCKED', 'Zu viele Fehlversuche. Bitte kurz warten.');

            return;
        }

        $playerId = (int) (Request::jsonBody()['playerId'] ?? 0);
        $pin = (string) (Request::jsonBody()['pin'] ?? '');

        if ($playerId <= 0 || $pin === '' || strlen($pin) > 128) {
            JsonResponse::error(422, 'VALIDATION_ERROR', 'Bitte ein Profil waehlen und die PIN eingeben.');

            return;
        }

        $result = $this->authService->verifyParentPin($playerId, $pin);
        if ($result === null) {
            Session::registerFailedLoginAttempt($this->loginMaxAttempts, $this->loginLockoutSeconds);
            Logger::security($this->logDirectory, "Fehlgeschlagener Eltern-Login-Versuch fuer player_id={$playerId}");
            JsonResponse::error(401, 'INVALID_CREDENTIALS', 'Die PIN ist nicht korrekt.');

            return;
        }

        Session::resetLoginAttempts();
        Session::setFamily($result['familyId']);
        Session::selectProfile($result['id'], 'parent');
        Session::regenerate();

        JsonResponse::success(['player' => $this->formatPlayer($result)]);
    }

    public function qrLogin(): void
    {
        $token = trim((string) (Request::jsonBody()['token'] ?? ''));

        if ($token === '' || strlen($token) > 128) {
            JsonResponse::error(422, 'VALIDATION_ERROR', 'Ungueltiger Code.');

            return;
        }

        $player = $this->authService->verifyLoginToken($token);
        if ($player === null) {
            JsonResponse::error(401, 'INVALID_LOGIN_TOKEN', 'Dieser Code ist ungueltig oder wurde ersetzt.');

            return;
        }

        Session::setFamily((int) $player['family_id']);
        Session::selectProfile((int) $player['id'], $player['role']);
        Session::regenerate();
        Session::extendCookieLifetime($this->childSessionLifetimeSeconds);

        JsonResponse::success(['player' => $this->formatPlayer($player)]);
    }

    public function logout(): void
    {
        Session::destroy();
        JsonResponse::success([]);
    }

    public function session(): void
    {
        JsonResponse::success([
            'authenticated' => Session::familyId() !== null && Session::playerId() !== null,
            'familyId' => Session::familyId(),
            'playerId' => Session::playerId(),
            'playerRole' => Session::playerRole(),
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
