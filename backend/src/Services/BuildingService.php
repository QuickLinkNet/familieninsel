<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use App\Repositories\BuildingRepository;
use App\Repositories\FamilyBuildingRepository;
use App\Repositories\MinigameRepository;
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
        private readonly MinigameRepository $minigames,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBuildingsForFamily(int $familyId): array
    {
        $familyBuildings = $this->familyBuildings->findAllForFamily($familyId);

        return array_map(fn (array $familyBuilding): array => $this->formatFamilyBuilding($familyBuilding), $familyBuildings);
    }

    /**
     * @param array<string, mixed> $requestedAmounts resource_key => amount
     * @return array{success: true, justCompleted: bool}|array{success: false, code: string, message: string}
     */
    public function contribute(int $familyId, int $playerId, int $buildingId, array $requestedAmounts): array
    {
        $familyBuilding = $this->familyBuildings->findByFamilyAndBuilding($familyId, $buildingId);
        if ($familyBuilding === null) {
            return $this->error('BUILDING_NOT_FOUND', 'Kein passendes Bauprojekt gefunden.');
        }
        if ($familyBuilding['status'] !== 'in_progress') {
            return $this->error('BUILDING_ALREADY_COMPLETED', 'Dieses Bauprojekt ist bereits abgeschlossen.');
        }

        $familyBuildingId = (int) $familyBuilding['id'];

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

        $this->pdo->beginTransaction();
        try {
            foreach ($toApply as $resourceId => $amount) {
                $this->resources->decrementBalance($familyId, $resourceId, $amount);
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

            $applied = $this->applyContribution(
                $familyId,
                $familyBuildingId,
                $buildingId,
                (int) $familyBuilding['stage'],
                $toApply,
                $playerId,
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['success' => true, 'justCompleted' => $applied['justCompleted']];
    }

    /**
     * Investiert frisch aus einer Aufgaben-Bestaetigung verdiente Rohstoffe
     * automatisch in das aktive Bauprojekt der Familie - gedeckelt auf den
     * tatsaechlichen Restbedarf pro Rohstoff, exakt wie bei einer manuellen
     * Einzahlung ueber contribute(). Was das Gebaeude nicht braucht, bleibt
     * unangetastet im Familienlager (dort bereits vom Aufrufer gutgeschrieben).
     *
     * Anders als contribute() gibt es hier keinen Fehlerfall: eine
     * Aufgaben-Bestaetigung darf nie daran scheitern, dass gerade kein
     * passendes Bauprojekt existiert - dann bleibt einfach alles im Lager.
     *
     * @param array<int, int> $earnedAmountsByResourceId resource_id => Menge
     * @return array{
     *     invested: array<int, int>,
     *     overflow: array<int, int>,
     *     beforePercent: int|null,
     *     afterPercent: int|null,
     *     beforeStage: int|null,
     *     afterStage: int|null,
     *     justCompleted: bool,
     *     familyBuildingId: int|null,
     *     buildingId: int|null,
     * }
     */
    public function investEarnedResourcesFromTask(
        int $familyId,
        int $playerId,
        int $taskId,
        array $earnedAmountsByResourceId,
    ): array {
        $familyBuilding = $this->familyBuildings->findActiveInProgress($familyId);
        if ($familyBuilding === null) {
            return [
                'invested' => [],
                'overflow' => $earnedAmountsByResourceId,
                'beforePercent' => null,
                'afterPercent' => null,
                'beforeStage' => null,
                'afterStage' => null,
                'justCompleted' => false,
                'familyBuildingId' => null,
                'buildingId' => null,
            ];
        }

        $familyBuildingId = (int) $familyBuilding['id'];
        $buildingId = (int) $familyBuilding['building_id'];
        $beforeStage = (int) $familyBuilding['stage'];

        $costsByResource = [];
        foreach ($this->buildings->findCosts($buildingId) as $cost) {
            $costsByResource[(int) $cost['resource_id']] = (int) $cost['required_amount'];
        }

        $contributed = $this->familyBuildings->findContributedTotals($familyBuildingId);
        $beforeTotals = $this->sumCappedTotals($costsByResource, $contributed);
        $beforePercent = $this->percentFor($beforeTotals['contributedTotal'], $beforeTotals['requiredTotal']);

        $toInvest = [];
        foreach ($earnedAmountsByResourceId as $resourceId => $amount) {
            $amount = (int) $amount;
            if ($amount <= 0) {
                continue;
            }
            $stillNeeded = max(0, ($costsByResource[$resourceId] ?? 0) - ($contributed[$resourceId] ?? 0));
            $invested = min($amount, $stillNeeded);
            if ($invested > 0) {
                $toInvest[$resourceId] = $invested;
            }
        }

        $overflow = [];
        foreach ($earnedAmountsByResourceId as $resourceId => $amount) {
            $remainder = (int) $amount - ($toInvest[$resourceId] ?? 0);
            if ($remainder > 0) {
                $overflow[$resourceId] = $remainder;
            }
        }

        if ($toInvest === []) {
            return [
                'invested' => [],
                'overflow' => $overflow,
                'beforePercent' => $beforePercent,
                'afterPercent' => $beforePercent,
                'beforeStage' => $beforeStage,
                'afterStage' => $beforeStage,
                'justCompleted' => false,
                'familyBuildingId' => $familyBuildingId,
                'buildingId' => $buildingId,
            ];
        }

        foreach ($toInvest as $resourceId => $amount) {
            $this->resources->decrementBalance($familyId, $resourceId, $amount);
            $this->transactions->record(
                $familyId,
                $playerId,
                $resourceId,
                -$amount,
                'task_auto_contribution',
                'task',
                $taskId,
                'Aufgaben-Belohnung automatisch investiert',
            );
        }

        $applied = $this->applyContribution($familyId, $familyBuildingId, $buildingId, $beforeStage, $toInvest, $playerId);

        return [
            'invested' => $toInvest,
            'overflow' => $overflow,
            'beforePercent' => $beforePercent,
            'afterPercent' => $this->percentFor($applied['contributedTotal'], $applied['requiredTotal']),
            'beforeStage' => $beforeStage,
            'afterStage' => $applied['newStage'],
            'justCompleted' => $applied['justCompleted'],
            'familyBuildingId' => $familyBuildingId,
            'buildingId' => $buildingId,
        ];
    }

    /**
     * Gemeinsamer Kern fuer manuelle (contribute()) und automatische
     * (investEarnedResourcesFromTask()) Einzahlungen: bucht die Kontributionen,
     * berechnet die neue Baustufe, erkennt Fertigstellung und loest die
     * Freischalt-Kette (Minispiel/naechstes Gebaeude) aus. Traegt selbst KEINE
     * Transaktion - der Aufrufer haelt die Transaktionsklammer.
     *
     * @param array<int, int> $amountsByResourceId resource_id => bereits gedeckelte Menge
     * @return array{newStage: int, justCompleted: bool, contributedTotal: int, requiredTotal: int}
     */
    private function applyContribution(
        int $familyId,
        int $familyBuildingId,
        int $buildingId,
        int $oldStage,
        array $amountsByResourceId,
        int $playerId,
    ): array {
        foreach ($amountsByResourceId as $resourceId => $amount) {
            $this->familyBuildings->recordContribution($familyBuildingId, $resourceId, $amount, $playerId);
        }

        $costsByResource = [];
        foreach ($this->buildings->findCosts($buildingId) as $cost) {
            $costsByResource[(int) $cost['resource_id']] = (int) $cost['required_amount'];
        }

        $newContributed = $this->familyBuildings->findContributedTotals($familyBuildingId);
        $totals = $this->sumCappedTotals($costsByResource, $newContributed);
        $contributedTotal = $totals['contributedTotal'];
        $requiredTotal = $totals['requiredTotal'];

        $newStage = $this->calculateStage($contributedTotal, $requiredTotal);
        $justCompleted = false;

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

                $unlockMinigameKey = $building['unlock_minigame_key'] ?? null;
                if ($unlockMinigameKey !== null) {
                    $minigame = $this->minigames->findByKey($unlockMinigameKey);
                    if ($minigame !== null) {
                        $this->minigames->unlockForFamily($familyId, (int) $minigame['id']);
                        $this->activityLog->record(
                            $familyId,
                            $playerId,
                            'minigame_unlocked',
                            sprintf('%s wurde freigeschaltet!', $minigame['name']),
                        );
                    }
                }

                $unlocksBuildingKey = $building['unlocks_building_key'] ?? null;
                if ($unlocksBuildingKey !== null) {
                    $nextBuilding = $this->buildings->findByKey($unlocksBuildingKey);
                    if ($nextBuilding !== null) {
                        $this->familyBuildings->unlockForFamilyIfMissing($familyId, (int) $nextBuilding['id']);
                        $this->activityLog->record(
                            $familyId,
                            $playerId,
                            'building_unlocked',
                            sprintf('Neues Bauprojekt freigeschaltet: %s!', $nextBuilding['name']),
                        );
                    }
                }
            }
        }

        return [
            'newStage' => $newStage,
            'justCompleted' => $justCompleted,
            'contributedTotal' => $contributedTotal,
            'requiredTotal' => $requiredTotal,
        ];
    }

    /**
     * @param array<int, int> $costsByResource
     * @param array<int, int> $contributedByResource
     * @return array{contributedTotal: int, requiredTotal: int}
     */
    private function sumCappedTotals(array $costsByResource, array $contributedByResource): array
    {
        $contributedTotal = 0;
        $requiredTotal = 0;
        foreach ($costsByResource as $resourceId => $required) {
            $contributedTotal += min($contributedByResource[$resourceId] ?? 0, $required);
            $requiredTotal += $required;
        }

        return ['contributedTotal' => $contributedTotal, 'requiredTotal' => $requiredTotal];
    }

    private function percentFor(int $contributedTotal, int $requiredTotal): int
    {
        return $requiredTotal > 0 ? (int) round($contributedTotal / $requiredTotal * 100) : 100;
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
            'progressPercent' => $this->percentFor($contributedTotal, $requiredTotal),
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
