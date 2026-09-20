<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use App\Repositories\BuildingRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTransactionRepository;

final class RewardRevealService
{
    public function __construct(
        private readonly PlayerRepository $players,
        private readonly ResourceTransactionRepository $transactions,
        private readonly ActivityLogRepository $activityLog,
        private readonly ResourceRepository $resources,
        private readonly BuildingRepository $buildings,
    ) {
    }

    /**
     * Alles, was ein Kind seit seinem letzten Besuch an bestaetigten
     * Aufgaben-Belohnungen (und dadurch ausgeloestem Baufortschritt) noch
     * nicht gesehen hat - eine Zeile pro Aufgabe, nicht pro Rohstoff, damit
     * das Frontend bei einer Aufgabe eine individuelle und bei mehreren eine
     * kompakte Sammel-Reaktion zeigen kann.
     *
     * @return array{hasUpdates: bool, events: array<int, array<string, mixed>>}
     */
    public function getUpdatesForPlayer(int $familyId, int $playerId): array
    {
        $cursor = $this->players->findRewardCursor($playerId);
        $rewardRows = $this->transactions->findTaskRewardsForPlayerSince($playerId, $cursor);

        if ($rewardRows === []) {
            return ['hasUpdates' => false, 'events' => []];
        }

        $resourceKeysById = [];
        foreach ($this->resources->findAllActive() as $resource) {
            $resourceKeysById[(int) $resource['id']] = $resource['key'];
        }

        $buildingMetaByTaskId = [];
        foreach ($this->activityLog->findByFamilyAndTypeSince($familyId, 'task_auto_contribution', $cursor) as $row) {
            $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
            if (!is_array($metadata) || !isset($metadata['taskId'])) {
                continue;
            }
            $buildingMetaByTaskId[(int) $metadata['taskId']] = $metadata;
        }

        $orderedTaskIds = [];
        $taskTitles = [];
        $rewardsByTask = [];

        foreach ($rewardRows as $row) {
            $taskId = (int) $row['task_id'];
            if (!isset($rewardsByTask[$taskId])) {
                $orderedTaskIds[] = $taskId;
                $rewardsByTask[$taskId] = [];
                $taskTitles[$taskId] = $row['task_title'];
            }
            $resourceId = (int) $row['resource_id'];
            $rewardsByTask[$taskId][$resourceId] = ($rewardsByTask[$taskId][$resourceId] ?? 0) + (int) $row['amount'];
        }

        $buildingCache = [];
        $events = [];

        foreach ($orderedTaskIds as $taskId) {
            $rewards = [];
            foreach ($rewardsByTask[$taskId] as $resourceId => $amount) {
                $key = $resourceKeysById[$resourceId] ?? null;
                if ($key === null) {
                    continue;
                }
                $rewards[] = ['resourceKey' => $key, 'amount' => $amount];
            }

            $building = null;
            $meta = $buildingMetaByTaskId[$taskId] ?? null;
            if ($meta !== null && ($meta['buildingId'] ?? null) !== null) {
                $buildingId = (int) $meta['buildingId'];
                if (!array_key_exists($buildingId, $buildingCache)) {
                    $buildingCache[$buildingId] = $this->buildings->findById($buildingId);
                }
                $buildingRow = $buildingCache[$buildingId];

                $building = [
                    'key' => $buildingRow['key'] ?? null,
                    'name' => $buildingRow['name'] ?? null,
                    'beforePercent' => $meta['beforePercent'] ?? null,
                    'afterPercent' => $meta['afterPercent'] ?? null,
                    'beforeStage' => $meta['beforeStage'] ?? null,
                    'afterStage' => $meta['afterStage'] ?? null,
                    'justCompleted' => $meta['justCompleted'] ?? false,
                    'unlockedMinigameName' => $meta['unlockedMinigameName'] ?? null,
                    'unlockedBuildingName' => $meta['unlockedBuildingName'] ?? null,
                ];
            }

            $events[] = [
                'taskId' => $taskId,
                'taskTitle' => $taskTitles[$taskId],
                'rewards' => $rewards,
                'building' => $building,
            ];
        }

        return ['hasUpdates' => true, 'events' => $events];
    }
}
