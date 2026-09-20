<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlayerLoginTokenRepository;
use App\Repositories\PlayerRepository;
use PDO;
use Throwable;

final class PlayerService
{
    private const MAX_NAME_LENGTH = 40;
    private const PIN_LENGTH = 4;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PlayerRepository $players,
        private readonly PlayerLoginTokenRepository $loginTokens,
    ) {
    }

    /**
     * @return array{success: true, playerId: int}|array{success: false, code: string, message: string}
     */
    public function createChild(int $familyId, string $name, ?int $age): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            return $this->error('VALIDATION_ERROR', 'Bitte einen gueltigen Namen (1-40 Zeichen) angeben.');
        }

        if ($age !== null && ($age < 0 || $age > 17)) {
            return $this->error('VALIDATION_ERROR', 'Das Alter muss zwischen 0 und 17 liegen.');
        }

        $avatarKey = $this->slugify($name);
        $playerId = $this->players->createChild($familyId, $name, $age, $avatarKey);

        return ['success' => true, 'playerId' => $playerId];
    }

    /**
     * Erzeugt einen neuen QR-Login-Token fuer ein Kind und macht damit einen
     * evtl. vorher ausgegebenen Token sofort ungueltig (nur ein aktiver Code
     * pro Kind). Der Rohwert wird nur hier einmalig zurueckgegeben - in der
     * Datenbank steht ausschliesslich der SHA-256-Hash.
     *
     * @return array{success: true, token: string}|array{success: false, code: string, message: string}
     */
    public function generateLoginToken(int $familyId, int $playerId): array
    {
        $player = $this->players->findActiveByIdAndFamily($playerId, $familyId);
        if ($player === null) {
            return $this->error('PLAYER_NOT_FOUND', 'Dieses Profil wurde nicht gefunden.');
        }

        if ($player['role'] !== 'child') {
            return $this->error('VALIDATION_ERROR', 'QR-Codes sind nur fuer Kinder-Profile gedacht.');
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $this->pdo->beginTransaction();
        try {
            $this->loginTokens->revokeAllForPlayer($playerId);
            $this->loginTokens->create($playerId, $tokenHash);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return ['success' => true, 'token' => $rawToken];
    }

    /**
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function revokeLoginToken(int $familyId, int $playerId): array
    {
        $player = $this->players->findActiveByIdAndFamily($playerId, $familyId);
        if ($player === null) {
            return $this->error('PLAYER_NOT_FOUND', 'Dieses Profil wurde nicht gefunden.');
        }

        $this->loginTokens->revokeAllForPlayer($playerId);

        return ['success' => true];
    }

    /**
     * @return array{success: true, active: bool, createdAt: string|null, lastUsedAt: string|null}|array{success: false, code: string, message: string}
     */
    public function loginTokenStatus(int $familyId, int $playerId): array
    {
        $player = $this->players->findActiveByIdAndFamily($playerId, $familyId);
        if ($player === null) {
            return $this->error('PLAYER_NOT_FOUND', 'Dieses Profil wurde nicht gefunden.');
        }

        $status = $this->loginTokens->findActiveStatusForPlayer($playerId);

        return [
            'success' => true,
            'active' => $status !== null,
            'createdAt' => $status['created_at'] ?? null,
            'lastUsedAt' => $status['last_used_at'] ?? null,
        ];
    }

    /**
     * Alle Familienmitglieder fuer die Benutzerverwaltung, inklusive
     * deaktivierter Profile (im Gegensatz zu AuthService::listActivePlayers,
     * das nur fuer aktive Profile im normalen Spielbetrieb gedacht ist).
     *
     * @return array<int, array{id: int, name: string, age: int|null, role: string, avatar_key: string, is_active: bool, intro_seen_at: string|null}>
     */
    public function listAll(int $familyId): array
    {
        return array_map(
            static fn (array $player): array => [
                'id' => (int) $player['id'],
                'name' => $player['name'],
                'age' => $player['age'] !== null ? (int) $player['age'] : null,
                'role' => $player['role'],
                'avatar_key' => $player['avatar_key'],
                'is_active' => ((int) $player['is_active']) === 1,
                'intro_seen_at' => $player['intro_seen_at'],
            ],
            $this->players->findAllByFamily($familyId),
        );
    }

    /**
     * Name/Alter bearbeiten - fuer Eltern- wie Kinderprofile gleichermassen.
     *
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function updatePlayer(int $familyId, int $playerId, string $name, ?int $age): array
    {
        $player = $this->players->findByIdAndFamily($playerId, $familyId);
        if ($player === null) {
            return $this->error('PLAYER_NOT_FOUND', 'Dieses Profil wurde nicht gefunden.');
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            return $this->error('VALIDATION_ERROR', 'Bitte einen gueltigen Namen (1-40 Zeichen) angeben.');
        }

        if ($age !== null && ($age < 0 || $age > 120)) {
            return $this->error('VALIDATION_ERROR', 'Bitte ein gueltiges Alter angeben.');
        }

        $this->players->updateNameAndAge($playerId, $name, $age);

        return ['success' => true];
    }

    /**
     * Setzt die PIN eines Elternprofils neu. Bewusst ohne Pruefung der
     * bisherigen PIN - wer hier ankommt, ist bereits als Elternteil
     * eingeloggt (RequireParent), beide Eltern verwalten die ganze Familie
     * gleichberechtigt, auch sich gegenseitig.
     *
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function setParentPin(int $familyId, int $playerId, string $newPin): array
    {
        $player = $this->players->findByIdAndFamily($playerId, $familyId);
        if ($player === null || $player['role'] !== 'parent') {
            return $this->error('VALIDATION_ERROR', 'PINs gibt es nur fuer Elternprofile.');
        }

        if (!ctype_digit($newPin) || strlen($newPin) !== self::PIN_LENGTH) {
            return $this->error('VALIDATION_ERROR', 'Die PIN muss genau 4 Ziffern haben.');
        }

        $this->players->updatePasswordHash($playerId, password_hash($newPin, PASSWORD_DEFAULT));

        return ['success' => true];
    }

    /**
     * Aktiviert/deaktiviert ein Profil (soft delete, Daten bleiben erhalten).
     * Schutz analog zu Admin-Selbstloeschung in anderen Projekten: niemand
     * deaktiviert sich selbst, und der letzte aktive Elternteil bleibt
     * unantastbar - sonst kann sich niemand mehr als Elternteil einloggen,
     * um irgendetwas zu reaktivieren.
     *
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function setActive(int $familyId, int $playerId, bool $active, int $actingPlayerId): array
    {
        $player = $this->players->findByIdAndFamily($playerId, $familyId);
        if ($player === null) {
            return $this->error('PLAYER_NOT_FOUND', 'Dieses Profil wurde nicht gefunden.');
        }

        if (!$active) {
            if ($playerId === $actingPlayerId) {
                return $this->error('FORBIDDEN', 'Du kannst dich nicht selbst deaktivieren.');
            }

            if ($player['role'] === 'parent' && $this->players->countActiveParents($familyId) <= 1) {
                return $this->error('FORBIDDEN', 'Der letzte aktive Elternteil kann nicht deaktiviert werden.');
            }
        }

        $this->players->setActive($playerId, $active);

        return ['success' => true];
    }

    /**
     * Markiert das Story-Intro als gesehen. Selbstbedienung: das Kind selbst
     * darf es fuer sich setzen (nach Durchlaufen von IntroStory), Eltern
     * duerfen es zusaetzlich fuer jedes Familienmitglied setzen (z. B. um es
     * bei Bedarf ueber die Familienverwaltung erneut zu triggern - siehe
     * Plan). Jeder andere authentifizierte Nutzer darf es nur fuer sich
     * selbst setzen.
     *
     * @return array{success: true}|array{success: false, code: string, message: string}
     */
    public function markIntroSeen(int $familyId, int $playerId, int $actingPlayerId, string $actingPlayerRole): array
    {
        $player = $this->players->findByIdAndFamily($playerId, $familyId);
        if ($player === null) {
            return $this->error('PLAYER_NOT_FOUND', 'Dieses Profil wurde nicht gefunden.');
        }

        if ($playerId !== $actingPlayerId && $actingPlayerRole !== 'parent') {
            return $this->error('FORBIDDEN', 'Das Intro kann nur fuer das eigene Profil markiert werden.');
        }

        $this->players->markIntroSeen($playerId);

        return ['success' => true];
    }

    private function slugify(string $name): string
    {
        $ascii = strtr($name, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue']);
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $ascii));

        return $slug !== '' ? $slug : 'kind';
    }

    /**
     * @return array{success: false, code: string, message: string}
     */
    private function error(string $code, string $message): array
    {
        return ['success' => false, 'code' => $code, 'message' => $message];
    }
}
