<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FamilyRepository;
use App\Repositories\PlayerRepository;

final class AuthService
{
    public function __construct(
        private readonly FamilyRepository $families,
        private readonly PlayerRepository $players,
    ) {
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public function verifyFamilyCode(string $code): ?array
    {
        $family = $this->families->findSoleFamily();
        if ($family === null || !password_verify($code, $family['family_code_hash'])) {
            return null;
        }

        return ['id' => $family['id'], 'name' => $family['name']];
    }

    /**
     * @return array<int, array{id: int, name: string, age: int|null, role: string, avatar_key: string}>
     */
    public function listActivePlayers(int $familyId): array
    {
        return $this->players->findActiveByFamily($familyId);
    }

    /**
     * @return array{id: int, name: string, age: int|null, role: string, avatar_key: string}|null
     */
    public function findSelectableProfile(int $playerId, int $familyId): ?array
    {
        $player = $this->players->findActiveByIdAndFamily($playerId, $familyId);
        if ($player === null) {
            return null;
        }

        unset($player['parent_pin_hash'], $player['family_id']);

        return $player;
    }

    public function verifyParentPin(int $playerId, int $familyId, string $pin): bool
    {
        $player = $this->players->findActiveByIdAndFamily($playerId, $familyId);
        if ($player === null || $player['role'] !== 'parent' || $player['parent_pin_hash'] === null) {
            return false;
        }

        return password_verify($pin, $player['parent_pin_hash']);
    }
}
