<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use App\Repositories\BuildingRepository;
use App\Repositories\FamilyBuildingRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTransactionRepository;
use PDO;
use Throwable;

final class BuildingService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly BuildingRepository $buildings,
        private readonly FamilyBuildingRepository $familyBuildings,
        private readonly ResourceRepository $resources,
        private readonly ResourceTransactionRepository $transactions,
        private readonly ActivityLogRepository $activityLog,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActiveBuildingForFamily(int $familyId): ?array
    {
        $familyBuilding = $this->familyBuildings->findForFamily($familyId);
        if ($familyBuilding === null) {
            return null;
        }

        return $this->formatFamilyBuilding($familyBuilding);
    }

    /**
     * @param array<string, mixed> $requestedAmounts resource_key => amount
     * @return array{success: true, justCompleted: bool}|array{success: false, code: string, message: string}
     */
    public function contribute(int $familyId, int $playerId, array $requestedAmounts): array
    {
        $familyBuilding = $this->familyBuildings->findForFamily($familyId);
        if ($familyBuilding === null) {
            return $this->error('BUILDING_NOT_FOUND', 'Kein aktives Bauprojekt gefunden.');
        }
        if ($familyBuilding['status'] !== 'in_progress') {
            return $this->error('BUILDING_ALREADY_COMPLETED', 'Dieses Bauprojekt ist bereits abgeschlossen.');
        }

        $familyBuildingId = (int) $familyBuilding['id'];
        $buildingId = (int) $familyBuilding['building_id'];

        $costsByResource = [];
        foreach ($this->buildings->findCosts($buildingId) as $cost) {
            $costsByResource[(int) $cost['resource_id']] = (int) $cost['required_amount'];
        }

        $contributed = $this->familyBuildings->findContributedTotals($familyBuildingId);
        $balances = $this->resources->findFamilyBalances($familyId);

        $toApply = [];
        foreach ($requestedAmounts as $resourceKey => $requestedAmount) {
            $requestedAmount = (int) $requestedAmount;
            if ($requestedAmount < 0) {
                return $this->error('VALIDATION_ERROR', 'Rohstoffmengen duerfen nicht negativ sein.');
            }
            if ($requestedAmount === 0) {
                continue;
            }

            $resourceId = $this->resources->findIdByKey((string) $resourceKey);
            if ($resourceId === null) {
                return $this->error('VALIDATION_ERROR', "Unbekannter Rohstoff: {$resourceKey}");
            }

            $required = $costsByResource[$resourceId] ?? 0;
            $alreadyContributed = $contributed[$resourceId] ?? 0;
            $stillNeeded = max(0, $required - $alreadyContributed);
            if ($stillNeeded === 0) {
                continue;
            }

            $available = $balances[$resourceId] ?? 0;
            if ($requestedAmount > $available) {
                return $this->error('INSUFFICIENT_RESOURCES', 'Nicht genug Rohstoffe fuer diese Einzahlung vorhanden.');
            }

            $toApply[$resourceId] = min($requestedAmount, $stillNeeded);
        }

        if ($toApply === []) {
            return $this->error('VALIDATION_ERROR', 'Es wurde keine gueltige Rohstoffmenge zum Einzahlen angegeben.');
        }

        $justCompleted = false;

        $this->pdo->beginTransaction();
        try {
            foreach ($toApply as $resourceId => $amount) {
                $this->resources->decrementBalance($familyId, $resourceId, $amount);
                $this->familyBuildings->recordContribution($familyBuildingId, $resourceId, $amount, $playerId);
                $this->transactions->record(
                    $familyId,
                    $playerId,
                    $resourceId,
                    -$amount,
                    'building_contribution',
                    'family_building',
                    $familyBuildingId,
                    'Investiert in Bauprojekt',
                );
            }

            $newContributed = $this->familyBuildings->findContributedTotals($familyBuildingId);
            $contributedTotal = 0;
            $requiredTotal = 0;
            foreach ($costsByResource as $resourceId => $required) {
                $contributedTotal += min($newContributed[$resourceId] ?? 0, $required);
                $requiredTotal += $required;
            }

            $oldStage = (int) $familyBuilding['stage'];
            $newStage = $this->calculateStage($contributedTotal, $requiredTotal);

            if ($newStage > $oldStage) {
                $this->familyBuildings->updateStage($familyBuildingId, $newStage);
                if ($newStage < 5) {
                    $this->activityLog->record(
                        $familyId,
                        $playerId,
                        'building_stage_reached',
                        "Baustufe {$newStage} erreicht.",
                    );
                }
            }

            if ($requiredTotal > 0 && $contributedTotal >= $requiredTotal) {
                $justCompleted = $this->familyBuildings->markCompleted($familyBuildingId);
                if ($justCompleted) {
                    $building = $this->buildings->findById($buildingId);
                    $this->activityLog->record(
                        $familyId,
                        $playerId,
                        'building_completed',
                        sprintf('%s wurde fertiggestellt!', $building['name'] ?? 'Das Gebaeude'),
                    );
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['success' => true, 'justCompleted' => $justCompleted];
    }

    private function calculateStage(int $contributedTotal, int $requiredTotal): int
    {
        if ($requiredTotal <= 0 || $contributedTotal >= $requiredTotal) {
            return 5;
        }
        if ($contributedTotal <= 0) {
            return 1;
        }

        $percentage = ($contributedTotal / $requiredTotal) * 100;
        if ($percentage < 34) {
            return 2;
        }
        if ($percentage < 67) {
            return 3;
        }

        return 4;
    }

    /**
     * @param array<string, mixed> $familyBuilding
     * @return array<string, mixed>
     */
    private function formatFamilyBuilding(array $familyBuilding): array
    {
        $familyBuildingId = (int) $familyBuilding['id'];
        $buildingId = (int) $familyBuilding['building_id'];

        $building = $this->buildings->findById($buildingId);
        $contributed = $this->familyBuildings->findContributedTotals($familyBuildingId);

        $requiredTotal = 0;
        $contributedTotal = 0;
        $costs = [];

        foreach ($this->buildings->findCosts($buildingId) as $cost) {
            $resourceId = (int) $cost['resource_id'];
            $required = (int) $cost['required_amount'];
            $have = min($contributed[$resourceId] ?? 0, $required);

            $requiredTotal += $required;
            $contributedTotal += $have;
            $costs[] = ['resourceId' => $resourceId, 'required' => $required, 'contributed' => $have];
        }

        return [
            'id' => $buildingId,
            'familyBuildingId' => $familyBuildingId,
            'key' => $building['key'] ?? null,
            'name' => $building['name'] ?? null,
            'description' => $building['description'] ?? null,
            'status' => $familyBuilding['status'],
            'stage' => (int) $familyBuilding['stage'],
            'progressPercent' => $requiredTotal > 0 ? (int) round($contributedTotal / $requiredTotal * 100) : 100,
            'completedAt' => $familyBuilding['completed_at'],
            'unlockMinigameKey' => $building['unlock_minigame_key'] ?? null,
            'costs' => $costs,
        ];
    }

    /**
     * @return array{success: false, code: string, message: string}
     */
    private function error(string $code, string $message): array
    {
        return ['success' => false, 'code' => $code, 'message' => $message];
    }
}
