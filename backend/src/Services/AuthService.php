<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FamilyRepository;
use App\Repositories\PlayerLoginTokenRepository;
use App\Repositories\PlayerRepository;

final class AuthService
{
    public function __construct(
        private readonly FamilyRepository $families,
        private readonly PlayerRepository $players,
        private readonly PlayerLoginTokenRepository $loginTokens,
    ) {
    }

    /**
     * @return array<int, array{id: int, name: string, age: int|null, role: string, avatar_key: string}>
     */
    public function listActivePlayers(int $familyId): array
    {
        return $this->players->findActiveByFamily($familyId);
    }

    /**
     * Fuer den Eltern-Login-Bildschirm: welche Eltern-Profile stehen zur
     * Auswahl, bevor ueberhaupt eine Session existiert. MVP hat genau eine
     * Familie pro Installation, daher ohne Familiencode auflösbar.
     *
     * @return array<int, array{id: int, name: string, age: int|null, role: string, avatar_key: string}>
     */
    public function listParentCandidates(): array
    {
        $family = $this->families->findSoleFamily();
        if ($family === null) {
            return [];
        }

        return $this->players->findActiveParentsByFamily((int) $family['id']);
    }

    /**
     * @return array{id: int, familyId: int, role: string, name: string, age: int|null, avatar_key: string}|null
     */
    public function verifyParentPin(int $playerId, string $pin): ?array
    {
        $family = $this->families->findSoleFamily();
        if ($family === null) {
            return null;
        }

        $player = $this->players->findActiveByIdAndFamily($playerId, (int) $family['id']);
        if ($player === null || $player['role'] !== 'parent' || $player['password_hash'] === null) {
            return null;
        }

        if (!password_verify($pin, $player['password_hash'])) {
            return null;
        }

        return [
            'id' => (int) $player['id'],
            'familyId' => (int) $family['id'],
            'role' => $player['role'],
            'name' => $player['name'],
            'age' => $player['age'] !== null ? (int) $player['age'] : null,
            'avatar_key' => $player['avatar_key'],
        ];
    }

    /**
     * Prueft einen QR-Login-Token und liefert bei Erfolg das zugehoerige
     * Spielerprofil. Der Token selbst wird nie im Klartext gespeichert -
     * lediglich sein SHA-256-Hash steht in der Datenbank (siehe
     * PlayerService::generateLoginToken).
     *
     * @return array{id: int, family_id: int, name: string, age: int|null, role: string, avatar_key: string}|null
     */
    public function verifyLoginToken(string $rawToken): ?array
    {
        $tokenRecord = $this->loginTokens->findActiveByTokenHash(hash('sha256', $rawToken));
        if ($tokenRecord === null) {
            return null;
        }

        $player = $this->players->findActiveById((int) $tokenRecord['player_id']);
        if ($player === null) {
            return null;
        }

        $this->loginTokens->markUsed((int) $tokenRecord['id']);

        return $player;
    }
}
