<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use App\Repositories\MinigameRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTransactionRepository;
use PDO;
use Throwable;

final class MinigameService
{
    private const FIRST_COMPLETION_STAR_REWARD = 2;

    public function __construct(
        private readonly PDO $pdo,
        private readonly MinigameRepository $minigames,
        private readonly ResourceRepository $resources,
        private readonly ResourceTransactionRepository $transactions,
        private readonly ActivityLogRepository $activityLog,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForFamily(int $familyId): array
    {
        return array_map(
            fn (array $minigame): array => $this->formatMinigame($familyId, $minigame),
            $this->minigames->findAllActive(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStatusForFamily(int $familyId, string $key): ?array
    {
        $minigame = $this->minigames->findByKey($key);

        return $minigame === null ? null : $this->formatMinigame($familyId, $minigame);
    }

    /**
     * @return array{success: true, starsAwarded: int}|array{success: false, code: string, message: string}
     */
    public function complete(int $familyId, int $playerId, string $key): array
    {
        $minigame = $this->minigames->findByKey($key);
        if ($minigame === null) {
            return ['success' => false, 'code' => 'MINIGAME_NOT_FOUND', 'message' => 'Dieses Minispiel wurde nicht gefunden.'];
        }

        $minigameId = (int) $minigame['id'];
        $status = $this->minigames->findFamilyStatus($familyId, $minigameId);
        if ($status === null) {
            return ['success' => false, 'code' => 'MINIGAME_LOCKED', 'message' => 'Dieses Minispiel ist noch nicht freigeschaltet.'];
        }

        $starsAwarded = 0;

        $this->pdo->beginTransaction();
        try {
            $isFirstCompletion = $this->minigames->markFirstCompletion($familyId, $minigameId);

            if ($isFirstCompletion) {
                $starResourceId = $this->resources->findIdByKey('stars');
                if ($starResourceId !== null) {
                    $this->resources->incrementBalance($familyId, $starResourceId, self::FIRST_COMPLETION_STAR_REWARD);
                    $this->transactions->record(
                        $familyId,
                        $playerId,
                        $starResourceId,
                        self::FIRST_COMPLETION_STAR_REWARD,
                        'minigame_reward',
                        'minigame',
                        $minigameId,
                        sprintf('Erstbelohnung fuer %s', $minigame['name']),
                    );
                    $starsAwarded = self::FIRST_COMPLETION_STAR_REWARD;
                }

                $this->activityLog->record(
                    $familyId,
                    $playerId,
                    'minigame_completed',
                    sprintf('%s zum ersten Mal abgeschlossen - %d Sterne erhalten!', $minigame['name'], $starsAwarded),
                );
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['success' => true, 'starsAwarded' => $starsAwarded];
    }

    /**
     * @param array<string, mixed> $minigame
     * @return array<string, mixed>
     */
    private function formatMinigame(int $familyId, array $minigame): array
    {
        $minigameId = (int) $minigame['id'];
        $status = $this->minigames->findFamilyStatus($familyId, $minigameId);

        return [
            'id' => $minigameId,
            'key' => $minigame['key'],
            'name' => $minigame['name'],
            'description' => $minigame['description'],
            'unlocked' => $status !== null,
            'firstCompletedAt' => $status['first_completion_at'] ?? null,
        ];
    }
}
