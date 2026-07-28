<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Connection;
use App\Database\Migrator;
use App\Database\Seeder;
use App\Repositories\ActivityLogRepository;
use App\Repositories\FamilyRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTransactionRepository;
use App\Repositories\TaskRepository;
use App\Services\AuthService;
use App\Services\TaskService;
use PDO;
use PHPUnit\Framework\TestCase;

final class TaskServiceTest extends TestCase
{
    private string $databasePath;
    private PDO $pdo;
    private TaskService $taskService;
    private int $familyId;
    private int $emilId;
    private int $manuelId;
    private int $kathrinId;
    private int $woodResourceId;

    protected function setUp(): void
    {
        Connection::reset();
        $this->databasePath = sys_get_temp_dir() . '/familieninsel-tasks-' . uniqid() . '.sqlite';
        $this->pdo = Connection::make($this->databasePath);
        (new Migrator($this->pdo, dirname(__DIR__) . '/database/migrations'))->run();

        $seeder = new Seeder($this->pdo);
        $seeder->seedDemoFamilyIfEmpty();
        $seeder->seedResourceCatalogIfEmpty();

        $authService = new AuthService(new FamilyRepository($this->pdo), new PlayerRepository($this->pdo));
        $this->familyId = (int) $this->pdo->query('SELECT id FROM families LIMIT 1')->fetchColumn();
        $players = $authService->listActivePlayers($this->familyId);
        $byName = [];
        foreach ($players as $player) {
            $byName[$player['name']] = (int) $player['id'];
        }
        $this->emilId = $byName['Emil'];
        $this->manuelId = $byName['Manuel'];
        $this->kathrinId = $byName['Kathrin'];

        $resources = new ResourceRepository($this->pdo);
        $this->woodResourceId = (int) $resources->findIdByKey('wood');

        $this->taskService = new TaskService(
            $this->pdo,
            new TaskRepository($this->pdo),
            $resources,
            new ResourceTransactionRepository($this->pdo),
            new ActivityLogRepository($this->pdo),
            new PlayerRepository($this->pdo),
        );
    }

    protected function tearDown(): void
    {
        Connection::reset();
        gc_collect_cycles();
        if (file_exists($this->databasePath)) {
            @unlink($this->databasePath);
        }
    }

    private function createTask(int $assignedTo, array $rewards = ['wood' => 3]): int
    {
        $result = $this->taskService->createTask(
            $this->familyId,
            $this->kathrinId,
            $assignedTo,
            'Zimmer aufraeumen',
            null,
            null,
            $rewards,
        );

        self::assertTrue($result['success']);

        return $result['taskId'];
    }

    public function testCreateTaskRejectsAllZeroRewards(): void
    {
        $result = $this->taskService->createTask(
            $this->familyId,
            $this->kathrinId,
            $this->emilId,
            'Nichts tun',
            null,
            null,
            ['wood' => 0],
        );

        self::assertFalse($result['success']);
        self::assertSame('VALIDATION_ERROR', $result['code']);
    }

    public function testCreateTaskRejectsUnknownResource(): void
    {
        $result = $this->taskService->createTask(
            $this->familyId,
            $this->kathrinId,
            $this->emilId,
            'Titel',
            null,
            null,
            ['diamanten' => 5],
        );

        self::assertFalse($result['success']);
        self::assertSame('VALIDATION_ERROR', $result['code']);
    }

    public function testCreateTaskRejectsUnknownAssignee(): void
    {
        $result = $this->taskService->createTask(
            $this->familyId,
            $this->kathrinId,
            999,
            'Titel',
            null,
            null,
            ['wood' => 3],
        );

        self::assertFalse($result['success']);
        self::assertSame('PLAYER_NOT_FOUND', $result['code']);
    }

    public function testFullLifecycleCreditsRewardExactlyOnce(): void
    {
        $taskId = $this->createTask($this->emilId, ['wood' => 3, 'fabric' => 1]);

        $complete = $this->taskService->completeTask($taskId, $this->familyId, $this->emilId);
        self::assertTrue($complete['success']);

        $approve = $this->taskService->approveTask($taskId, $this->familyId, $this->manuelId);
        self::assertTrue($approve['success']);

        $woodBalance = (int) $this->pdo->query(
            "SELECT amount FROM family_resources WHERE family_id = {$this->familyId} AND resource_id = {$this->woodResourceId}",
        )->fetchColumn();
        self::assertSame(3, $woodBalance);

        $transactionCount = (int) $this->pdo->query('SELECT COUNT(*) FROM resource_transactions')->fetchColumn();
        self::assertSame(2, $transactionCount);
    }

    public function testCannotCompleteTaskAssignedToSomeoneElse(): void
    {
        $taskId = $this->createTask($this->emilId);

        $result = $this->taskService->completeTask($taskId, $this->familyId, $this->manuelId);

        self::assertFalse($result['success']);
        self::assertSame('FORBIDDEN', $result['code']);
    }

    public function testDoubleApprovalIsRejectedAndDoesNotDoublePay(): void
    {
        $taskId = $this->createTask($this->emilId, ['wood' => 5]);
        $this->taskService->completeTask($taskId, $this->familyId, $this->emilId);

        $firstApproval = $this->taskService->approveTask($taskId, $this->familyId, $this->manuelId);
        $secondApproval = $this->taskService->approveTask($taskId, $this->familyId, $this->manuelId);

        self::assertTrue($firstApproval['success']);
        self::assertFalse($secondApproval['success']);
        self::assertSame('TASK_ALREADY_REWARDED', $secondApproval['code']);

        $woodBalance = (int) $this->pdo->query(
            "SELECT amount FROM family_resources WHERE family_id = {$this->familyId} AND resource_id = {$this->woodResourceId}",
        )->fetchColumn();
        self::assertSame(5, $woodBalance);
    }

    public function testApprovingWithoutPriorCompletionFails(): void
    {
        $taskId = $this->createTask($this->emilId);

        $result = $this->taskService->approveTask($taskId, $this->familyId, $this->manuelId);

        self::assertFalse($result['success']);
        self::assertSame('TASK_ALREADY_REWARDED', $result['code']);
    }

    public function testRejectDoesNotPayOutAndAllowsReopen(): void
    {
        $taskId = $this->createTask($this->emilId, ['wood' => 4]);
        $this->taskService->completeTask($taskId, $this->familyId, $this->emilId);

        $reject = $this->taskService->rejectTask($taskId, $this->familyId, 'Bitte nochmal.');
        self::assertTrue($reject['success']);

        $balance = $this->pdo->query(
            "SELECT COUNT(*) FROM family_resources WHERE family_id = {$this->familyId} AND resource_id = {$this->woodResourceId}",
        )->fetchColumn();
        self::assertSame(0, (int) $balance);

        $reopen = $this->taskService->reopenTask($taskId, $this->familyId);
        self::assertTrue($reopen['success']);

        $task = $this->taskService->findTaskForFamily($taskId, $this->familyId);
        self::assertSame('open', $task['status']);
    }

    public function testChildCannotSeeOtherChildrensTasks(): void
    {
        $this->createTask($this->emilId);

        $tasksForEmil = $this->taskService->listTasksForPlayer($this->familyId, 'child', $this->emilId);
        self::assertCount(1, $tasksForEmil);

        $players = (new PlayerRepository($this->pdo))->findActiveByFamily($this->familyId);
        $novaId = null;
        foreach ($players as $player) {
            if ($player['name'] === 'Nova') {
                $novaId = (int) $player['id'];
            }
        }

        $tasksForNova = $this->taskService->listTasksForPlayer($this->familyId, 'child', $novaId);
        self::assertCount(0, $tasksForNova);
    }

    public function testParentSeesAllFamilyTasks(): void
    {
        $this->createTask($this->emilId);
        $this->createTask($this->manuelId);

        $tasksForParent = $this->taskService->listTasksForPlayer($this->familyId, 'parent', $this->manuelId);
        self::assertCount(2, $tasksForParent);
    }

    public function testCreateTaskRejectsTooLongDescription(): void
    {
        $result = $this->taskService->createTask(
            $this->familyId,
            $this->kathrinId,
            $this->emilId,
            'Titel',
            str_repeat('a', 2001),
            null,
            ['wood' => 1],
        );

        self::assertFalse($result['success']);
        self::assertSame('VALIDATION_ERROR', $result['code']);
    }

    public function testRejectTaskRejectsTooLongParentNote(): void
    {
        $taskId = $this->createTask($this->emilId);
        $this->taskService->completeTask($taskId, $this->familyId, $this->emilId);

        $result = $this->taskService->rejectTask($taskId, $this->familyId, str_repeat('a', 501));

        self::assertFalse($result['success']);
        self::assertSame('VALIDATION_ERROR', $result['code']);
    }
}
