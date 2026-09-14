<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use App\Database\Migrator;
use App\Database\Seeder;
use App\Repositories\FamilyRepository;
use App\Repositories\PlayerLoginTokenRepository;
use App\Repositories\PlayerRepository;
use App\Services\AuthService;
use App\Services\PlayerService;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private string $databasePath;
    private PDO $pdo;
    private AuthService $authService;
    private PlayerService $playerService;
    private int $familyId;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-auth-' . uniqid() . '.sqlite';
        $this->pdo = Connection::make($this->databasePath);
        (new Migrator($this->pdo, dirname(__DIR__) . '/database/migrations'))->run();
        (new Seeder($this->pdo))->seedDemoFamilyIfEmpty();

        $playerRepository = new PlayerRepository($this->pdo);
        $loginTokens = new PlayerLoginTokenRepository($this->pdo);
        $this->authService = new AuthService(new FamilyRepository($this->pdo), $playerRepository, $loginTokens);
        $this->playerService = new PlayerService($this->pdo, $playerRepository, $loginTokens);
        $this->familyId = (int) $this->pdo->query('SELECT id FROM families LIMIT 1')->fetchColumn();
    }

    protected function tearDown(): void
    {
        Connection::reset();
        gc_collect_cycles();
        if (file_exists($this->databasePath)) {
            @unlink($this->databasePath);
        }
    }

    public function testListActivePlayersReturnsAllFiveDemoPlayers(): void
    {
        $players = $this->authService->listActivePlayers($this->familyId);

        self::assertCount(5, $players);
        $names = array_column($players, 'name');
        self::assertEqualsCanonicalizing(['Manuel', 'Kathrin', 'Emil', 'Thea', 'Nova'], $names);
    }

    public function testListParentCandidatesReturnsOnlyParents(): void
    {
        $candidates = $this->authService->listParentCandidates();

        self::assertCount(2, $candidates);
        self::assertEqualsCanonicalizing(['Manuel', 'Kathrin'], array_column($candidates, 'name'));
    }

    public function testVerifyParentPinAcceptsCorrectPin(): void
    {
        $manuel = $this->findPlayerByName('Manuel');

        $result = $this->authService->verifyParentPin((int) $manuel['id'], '2026');

        self::assertNotNull($result);
        self::assertSame((int) $manuel['id'], $result['id']);
        self::assertSame($this->familyId, $result['familyId']);
    }

    public function testVerifyParentPinRejectsWrongPin(): void
    {
        $manuel = $this->findPlayerByName('Manuel');

        self::assertNull($this->authService->verifyParentPin((int) $manuel['id'], '0000'));
    }

    public function testVerifyParentPinRejectsChildProfile(): void
    {
        $emil = $this->findPlayerByName('Emil');

        self::assertNull($this->authService->verifyParentPin((int) $emil['id'], '2026'));
    }

    public function testVerifyLoginTokenAcceptsFreshlyGeneratedToken(): void
    {
        $emil = $this->findPlayerByName('Emil');
        $generated = $this->playerService->generateLoginToken($this->familyId, (int) $emil['id']);
        self::assertTrue($generated['success']);

        $player = $this->authService->verifyLoginToken($generated['token']);

        self::assertNotNull($player);
        self::assertSame((int) $emil['id'], $player['id']);
        self::assertSame($this->familyId, $player['family_id']);
    }

    public function testVerifyLoginTokenRejectsUnknownToken(): void
    {
        self::assertNull($this->authService->verifyLoginToken('nicht-vergebener-token'));
    }

    public function testRegeneratingLoginTokenInvalidatesThePreviousOne(): void
    {
        $emil = $this->findPlayerByName('Emil');
        $first = $this->playerService->generateLoginToken($this->familyId, (int) $emil['id']);
        $second = $this->playerService->generateLoginToken($this->familyId, (int) $emil['id']);

        self::assertNull($this->authService->verifyLoginToken($first['token']));
        self::assertNotNull($this->authService->verifyLoginToken($second['token']));
    }

    public function testRevokedLoginTokenNoLongerWorks(): void
    {
        $emil = $this->findPlayerByName('Emil');
        $generated = $this->playerService->generateLoginToken($this->familyId, (int) $emil['id']);

        $revoked = $this->playerService->revokeLoginToken($this->familyId, (int) $emil['id']);

        self::assertTrue($revoked['success']);
        self::assertNull($this->authService->verifyLoginToken($generated['token']));
    }

    public function testGenerateLoginTokenRejectsParentProfile(): void
    {
        $manuel = $this->findPlayerByName('Manuel');

        $result = $this->playerService->generateLoginToken($this->familyId, (int) $manuel['id']);

        self::assertFalse($result['success']);
        self::assertSame('VALIDATION_ERROR', $result['code']);
    }

    public function testCreateChildAddsANewSelectablePlayer(): void
    {
        $result = $this->playerService->createChild($this->familyId, 'Lotta', 6);
        self::assertTrue($result['success']);

        $players = $this->authService->listActivePlayers($this->familyId);
        $names = array_column($players, 'name');

        self::assertContains('Lotta', $names);
    }

    public function testCreateChildRejectsEmptyName(): void
    {
        $result = $this->playerService->createChild($this->familyId, '   ', null);

        self::assertFalse($result['success']);
        self::assertSame('VALIDATION_ERROR', $result['code']);
    }

    public function testSetParentPinChangesPinEvenWithoutKnowingTheOldOne(): void
    {
        $kathrin = $this->findPlayerByName('Kathrin');

        $result = $this->playerService->setParentPin($this->familyId, (int) $kathrin['id'], '4321');

        self::assertTrue($result['success']);
        self::assertNull($this->authService->verifyParentPin((int) $kathrin['id'], '2580'));
        self::assertNotNull($this->authService->verifyParentPin((int) $kathrin['id'], '4321'));
    }

    public function testSetParentPinRejectsWrongLength(): void
    {
        $manuel = $this->findPlayerByName('Manuel');

        $result = $this->playerService->setParentPin($this->familyId, (int) $manuel['id'], '123');

        self::assertFalse($result['success']);
        self::assertSame('VALIDATION_ERROR', $result['code']);
    }

    public function testSetParentPinRejectsNonNumeric(): void
    {
        $manuel = $this->findPlayerByName('Manuel');

        $result = $this->playerService->setParentPin($this->familyId, (int) $manuel['id'], 'abcd');

        self::assertFalse($result['success']);
        self::assertSame('VALIDATION_ERROR', $result['code']);
    }

    public function testSetParentPinRejectsChildProfile(): void
    {
        $emil = $this->findPlayerByName('Emil');

        $result = $this->playerService->setParentPin($this->familyId, (int) $emil['id'], '4321');

        self::assertFalse($result['success']);
        self::assertSame('VALIDATION_ERROR', $result['code']);
    }

    public function testUpdatePlayerChangesNameAndAge(): void
    {
        $emil = $this->findPlayerByName('Emil');

        $result = $this->playerService->updatePlayer($this->familyId, (int) $emil['id'], 'Emil Neu', 6);

        self::assertTrue($result['success']);
        $updated = array_values(array_filter(
            $this->authService->listActivePlayers($this->familyId),
            static fn (array $p) => $p['id'] === $emil['id'],
        ))[0];
        self::assertSame('Emil Neu', $updated['name']);
        self::assertSame(6, $updated['age']);
    }

    public function testUpdatePlayerRejectsEmptyName(): void
    {
        $emil = $this->findPlayerByName('Emil');

        $result = $this->playerService->updatePlayer($this->familyId, (int) $emil['id'], '   ', null);

        self::assertFalse($result['success']);
        self::assertSame('VALIDATION_ERROR', $result['code']);
    }

    public function testListAllIncludesInactivePlayers(): void
    {
        $emil = $this->findPlayerByName('Emil');
        $manuel = $this->findPlayerByName('Manuel');
        $this->playerService->setActive($this->familyId, (int) $emil['id'], false, (int) $manuel['id']);

        $all = $this->playerService->listAll($this->familyId);
        $emilRow = array_values(array_filter($all, static fn (array $p) => $p['id'] === (int) $emil['id']))[0];

        self::assertCount(5, $all);
        self::assertFalse($emilRow['is_active']);
        self::assertCount(4, $this->authService->listActivePlayers($this->familyId));
    }

    public function testCannotDeactivateOwnProfile(): void
    {
        $manuel = $this->findPlayerByName('Manuel');

        $result = $this->playerService->setActive($this->familyId, (int) $manuel['id'], false, (int) $manuel['id']);

        self::assertFalse($result['success']);
        self::assertSame('FORBIDDEN', $result['code']);
    }

    public function testCannotDeactivateTheLastActiveParent(): void
    {
        $manuel = $this->findPlayerByName('Manuel');
        $kathrin = $this->findPlayerByName('Kathrin');

        $this->playerService->setActive($this->familyId, (int) $kathrin['id'], false, (int) $manuel['id']);
        $result = $this->playerService->setActive($this->familyId, (int) $manuel['id'], false, (int) $kathrin['id']);

        self::assertFalse($result['success']);
        self::assertSame('FORBIDDEN', $result['code']);
    }

    public function testReactivatingAPlayerMakesThemSelectableAgain(): void
    {
        $emil = $this->findPlayerByName('Emil');
        $manuel = $this->findPlayerByName('Manuel');
        $this->playerService->setActive($this->familyId, (int) $emil['id'], false, (int) $manuel['id']);

        $result = $this->playerService->setActive($this->familyId, (int) $emil['id'], true, (int) $manuel['id']);

        self::assertTrue($result['success']);
        self::assertCount(5, $this->authService->listActivePlayers($this->familyId));
    }

    /**
     * @return array{id: int, name: string}
     */
    private function findPlayerByName(string $name): array
    {
        $players = $this->authService->listActivePlayers($this->familyId);

        return array_values(array_filter($players, static fn (array $p) => $p['name'] === $name))[0];
    }
}
