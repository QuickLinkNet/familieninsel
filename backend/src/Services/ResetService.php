<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use App\Repositories\BuildingRepository;
use App\Repositories\FamilyBuildingRepository;
use App\Repositories\MinigameRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTransactionRepository;
use App\Repositories\TaskRepository;
use PDO;
use Throwable;

/**
 * Test-/Admin-Werkzeuge fuer Eltern, um Fortschritt gezielt zurueckzusetzen -
 * getrennt von den regulaeren Spiel-Services, damit destruktive Operationen
 * an einer Stelle gebuendelt und klar als solche erkennbar sind.
 */
final class ResetService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PlayerRepository $players,
        private readonly TaskRepository $tasks,
        private readonly ResourceRepository $resources,
        private readonly ResourceTransactionRepository $transactions,
        private readonly FamilyBuildingRepository $familyBuildings,
        private readonly BuildingRepository $buildings,
        private readonly MinigameRepository $minigames,
        private readonly ActivityLogRepository $activityLog,
    ) {
    }

    /**
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function resetIntroForPlayer(int $familyId, int $playerId): array
    {
        $player = $this->players->findByIdAndFamily($playerId, $familyId);
        if ($player === null) {
            return $this->error('PLAYER_NOT_FOUND', 'Dieses Profil wurde nicht gefunden.');
        }

        $this->players->clearIntroSeen($playerId);

        return ['success' => true];
    }

    /**
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function resetRewardsCursorForPlayer(int $familyId, int $playerId): array
    {
        $player = $this->players->findByIdAndFamily($playerId, $familyId);
        if ($player === null) {
            return $this->error('PLAYER_NOT_FOUND', 'Dieses Profil wurde nicht gefunden.');
        }

        $this->players->clearRewardCursor($playerId);

        return ['success' => true];
    }

    /**
     * Setzt alle Aufgaben eines Spielers zurueck auf "offen" und raeumt die
     * zugehoerigen Ledger-Eintraege auf, damit dieselbe Aufgabe sauber neu
     * durchgespielt werden kann (z. B. um RewardReveal erneut zu testen).
     * Ruehrt bereits gutgeschriebene Rohstoffe im Familienlager/Bauprojekt
     * bewusst NICHT an - dafuer gibt es den separaten Familien-Reset.
     *
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function resetTasksForPlayer(int $familyId, int $playerId): array
    {
        $player = $this->players->findByIdAndFamily($playerId, $familyId);
        if ($player === null) {
            return $this->error('PLAYER_NOT_FOUND', 'Dieses Profil wurde nicht gefunden.');
        }

        $taskIds = $this->tasks->findIdsForPlayer($familyId, $playerId);

        $this->pdo->beginTransaction();
        try {
            $this->tasks->resetForPlayer($familyId, $playerId);
            if ($taskIds !== []) {
                $this->transactions->deleteForTaskIds($taskIds);
                $this->activityLog->deleteTaskAutoContributionsForTaskIds($familyId, $taskIds);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['success' => true];
    }

    /**
     * Der grosse Knopf: setzt den gesamten Familien-Fortschritt zurueck auf
     * den Zustand kurz nach dem Start (alle Aufgaben offen, Rohstoffe leer,
     * kein Baufortschritt/Minispiel-Freischaltung, leeres Tagebuch) - aber
     * OHNE Profile, PINs, QR-Codes oder den Intro-/Belohnungs-Status
     * einzelner Kinder anzufassen (dafuer gibt es die gezielten Aktionen
     * oben). Legt danach die Strandhuette frisch auf Stufe 1 an, damit die
     * Familie sofort weiterspielen kann.
     */
    public function resetFamilyProgress(int $familyId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->tasks->resetAllForFamily($familyId);
            $this->transactions->deleteAllForFamily($familyId);
            $this->familyBuildings->deleteAllForFamily($familyId);
            $this->minigames->deleteAllForFamily($familyId);
            $this->resources->resetFamilyBalances($familyId);
            $this->activityLog->deleteAllForFamily($familyId);

            $beachHut = $this->buildings->findByKey('beach_hut');
            if ($beachHut !== null) {
                $this->familyBuildings->unlockForFamilyIfMissing($familyId, (int) $beachHut['id']);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array{success: false, code: string, message: string}
     */
    private function error(string $code, string $message): array
    {
        return ['success' => false, 'code' => $code, 'message' => $message];
    }
}
