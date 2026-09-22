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
        $costsByResource = $this->costsByResourceFor($buildingId);

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

            $capped = $this->capToRemainingNeed($costsByResource, $contributed, [$resourceId => $requestedAmount]);
            if (!isset($capped[$resourceId])) {
                continue;
            }

            $available = $balances[$resourceId] ?? 0;
            if ($requestedAmount > $available) {
                return $this->error('INSUFFICIENT_RESOURCES', 'Nicht genug Rohstoffe fuer diese Einzahlung vorhanden.');
            }

            $toApply[$resourceId] = $capped[$resourceId];
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
                $costsByResource,
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
     *     unlockedMinigameName: string|null,
     *     unlockedBuildingName: string|null,
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
                'unlockedMinigameName' => null,
                'unlockedBuildingName' => null,
                'familyBuildingId' => null,
                'buildingId' => null,
            ];
        }

        $familyBuildingId = (int) $familyBuilding['id'];
        $buildingId = (int) $familyBuilding['building_id'];
        $beforeStage = (int) $familyBuilding['stage'];

        $costsByResource = $this->costsByResourceFor($buildingId);

        $contributed = $this->familyBuildings->findContributedTotals($familyBuildingId);
        $beforeTotals = $this->sumCappedTotals($costsByResource, $contributed);
        $beforePercent = $this->percentFor($beforeTotals['contributedTotal'], $beforeTotals['requiredTotal']);

        $toInvest = $this->capToRemainingNeed($costsByResource, $contributed, $earnedAmountsByResourceId);

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
                'unlockedMinigameName' => null,
                'unlockedBuildingName' => null,
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

        $applied = $this->applyContribution($familyId, $familyBuildingId, $buildingId, $costsByResource, $beforeStage, $toInvest, $playerId);

        return [
            'invested' => $toInvest,
            'overflow' => $overflow,
            'beforePercent' => $beforePercent,
            'afterPercent' => $this->percentFor($applied['contributedTotal'], $applied['requiredTotal']),
            'beforeStage' => $beforeStage,
            'afterStage' => $applied['newStage'],
            'justCompleted' => $applied['justCompleted'],
            'unlockedMinigameName' => $applied['unlockedMinigameName'],
            'unlockedBuildingName' => $applied['unlockedBuildingName'],
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
     * @param array<int, int> $costsByResource bereits vom Aufrufer ermittelt, wird hier nicht erneut abgefragt
     * @param array<int, int> $amountsByResourceId resource_id => bereits gedeckelte Menge
     * @return array{
     *     newStage: int,
     *     justCompleted: bool,
     *     contributedTotal: int,
     *     requiredTotal: int,
     *     unlockedMinigameName: string|null,
     *     unlockedBuildingName: string|null,
     * }
     */
    private function applyContribution(
        int $familyId,
        int $familyBuildingId,
        int $buildingId,
        array $costsByResource,
        int $oldStage,
        array $amountsByResourceId,
        int $playerId,
    ): array {
        foreach ($amountsByResourceId as $resourceId => $amount) {
            $this->familyBuildings->recordContribution($familyBuildingId, $resourceId, $amount, $playerId);
        }

        $newContributed = $this->familyBuildings->findContributedTotals($familyBuildingId);
        $totals = $this->sumCappedTotals($costsByResource, $newContributed);
        $contributedTotal = $totals['contributedTotal'];
        $requiredTotal = $totals['requiredTotal'];

        $newStage = $this->calculateStage($contributedTotal, $requiredTotal);
        $justCompleted = false;
        $unlockedMinigameName = null;
        $unlockedBuildingName = null;

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
                        $unlockedMinigameName = $minigame['name'];
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
                        $unlockedBuildingName = $nextBuilding['name'];
                    }
                }
            }
        }

        return [
            'newStage' => $newStage,
            'justCompleted' => $justCompleted,
            'contributedTotal' => $contributedTotal,
            'requiredTotal' => $requiredTotal,
            'unlockedMinigameName' => $unlockedMinigameName,
            'unlockedBuildingName' => $unlockedBuildingName,
        ];
    }

    /**
     * @return array<int, int> resource_id => benoetigte Menge
     */
    private function costsByResourceFor(int $buildingId): array
    {
        $costsByResource = [];
        foreach ($this->buildings->findCosts($buildingId) as $cost) {
            $costsByResource[(int) $cost['resource_id']] = (int) $cost['required_amount'];
        }

        return $costsByResource;
    }

    /**
     * Deckelt angebotene Mengen auf den tatsaechlichen Restbedarf pro
     * Rohstoff (benoetigt minus bereits eingezahlt) - die eine gemeinsame
     * Definition von "wie viel einer Einzahlung zaehlt tatsaechlich", die
     * sich sowohl contribute() (manuelle Einzahlung) als auch
     * investEarnedResourcesFromTask() (automatische Einzahlung) teilen.
     * Ressourcen, die der Restbedarf-Pruefung nicht standhalten (Bedarf
     * bereits gedeckt, oder Menge <= 0), tauchen im Ergebnis gar nicht auf.
     *
     * @param array<int, int> $costsByResource
     * @param array<int, int> $contributedByResource
     * @param array<int|string, int> $amounts resource_id => angebotene Menge
     * @return array<int, int> resource_id => tatsaechlich anrechenbare Menge (> 0)
     */
    private function capToRemainingNeed(array $costsByResource, array $contributedByResource, array $amounts): array
    {
        $capped = [];
        foreach ($amounts as $resourceId => $amount) {
            $resourceId = (int) $resourceId;
            $stillNeeded = max(0, ($costsByResource[$resourceId] ?? 0) - ($contributedByResource[$resourceId] ?? 0));
            $applied = min((int) $amount, $stillNeeded);
            if ($applied > 0) {
                $capped[$resourceId] = $applied;
            }
        }

        return $capped;
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
