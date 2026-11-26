<?php

namespace Matecat\Core\Workers\Analysis;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\FakeLockRedisClient;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\FastAnalysis;
use Utils\AsyncTasks\Workers\Analysis\MtQueue\MtQueueLoadReader;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Runs the SQL of the MT queue hold and of the live project read on the real test database.
 *
 * The unit tests check the query text; these check what MySQL does with it. Fixtures, in id order:
 * X NEW with a DeepL job on another instance, A NEW with a Lara job, B NEW with a DeepL job, C NEW with a DeepL job and a Lara job, D NEW with a job
 * whose engine row does not exist, then E BUSY, F FAST_OK, G NOT_TO_ANALYZE and H DONE.
 * Every row is created here and deleted in tearDown; the residue gate checks that none is left.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class FastAnalysisMtQueueDbTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const string OWNER = 'mt-queue-db-test@example.org';

    /** @var array<string, int> project id by fixture letter */
    private array $pids = [];

    protected function realSqlTableDeps(): array
    {
        return ['engines', 'projects', 'jobs'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();

        // The pick takes the lowest NEW project of this instance, so a NEW row left by someone else would come
        // first. INSTANCE_ID stays as it is: it also selects the Redis DB of the DAO cache, which tearDown flushes.
        $this->assertSame(0, $this->countUnfinishedProjectsOfThisInstance(), 'the test database must start with no unfinished project of this instance');

        // Both forms of the Lara class_load, so the hold is checked against each of them
        $laraShort = $this->insertEngine('Lara');
        $laraFull  = $this->insertEngine('Utils\Engines\Lara');
        $deepL     = $this->insertEngine('Utils\Engines\DeepL');
        $noEngine  = $this->unusedEngineId();

        // Lowest id of all and never held: a pick that ignored instance_id would take it first
        $this->pids['X'] = $this->insertProject(ProjectStatus::STATUS_NEW, [$deepL], AppConfig::$INSTANCE_ID + 1);
        $this->pids['A'] = $this->insertProject(ProjectStatus::STATUS_NEW, [$laraShort]);
        $this->pids['B'] = $this->insertProject(ProjectStatus::STATUS_NEW, [$deepL]);
        $this->pids['C'] = $this->insertProject(ProjectStatus::STATUS_NEW, [$deepL, $laraFull]);
        $this->pids['D'] = $this->insertProject(ProjectStatus::STATUS_NEW, [$noEngine]);
        $this->pids['E'] = $this->insertProject(ProjectStatus::STATUS_BUSY, [$laraFull]);
        $this->pids['F'] = $this->insertProject(ProjectStatus::STATUS_FAST_OK, [$laraFull]);
        $this->pids['G'] = $this->insertProject(ProjectStatus::STATUS_NOT_TO_ANALYZE, [$laraFull]);
        $this->pids['H'] = $this->insertProject(ProjectStatus::STATUS_DONE, [$laraFull]);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown();
        parent::tearDown();
    }

    #[Test]
    public function aHeldPickSkipsEveryProjectWithAnMtJob(): void
    {
        $this->assertSame(['B', 'D'], $this->pickUntilEmpty(hold: true));
        $this->assertSame(ProjectStatus::STATUS_NEW, $this->statusOf('A'), 'a held Lara project waits in NEW');
        $this->assertSame(ProjectStatus::STATUS_NEW, $this->statusOf('C'), 'one Lara job is enough to hold the project');
    }

    #[Test]
    public function aPickNotHeldTakesEveryNewProjectInIdOrder(): void
    {
        $this->assertSame(['A', 'B', 'C', 'D'], $this->pickUntilEmpty(hold: false));
        $this->assertSame(ProjectStatus::STATUS_BUSY, $this->statusOf('A'), 'a picked project is BUSY');
    }

    #[Test]
    public function theLiveProjectReadKeepsOnlyTheUnfinishedGivenProjects(): void
    {
        $this->assertSame(['A', 'E', 'F'], $this->unfinishedAmong(['A', 'E', 'F', 'G', 'H']));
        // F is unfinished too: only the projects asked about come back
        $this->assertSame(['E'], $this->unfinishedAmong(['E', 'H']));
    }

    /**
     * Picks one project at a time, as main() does, until none is left; each pick turns its project BUSY.
     *
     * Example: with the hold on, it gives ['B', 'D'].
     *
     * @return list<string> the fixture letters, in pick order
     */
    private function pickUntilEmpty(bool $hold): array
    {
        $daemon = $this->daemon($hold);
        $pick = (new ReflectionClass(FastAnalysis::class))->getMethod('_lockNextProjectToAnalyze');
        $picked = [];

        // More picks than fixtures: a pick that never runs dry fails the test instead of looping
        for ($i = 0; $i <= count($this->pids); $i++) {
            $rows = $pick->invoke($daemon);
            if ($rows === []) {
                return $picked;
            }
            $picked[] = $this->letterOf((int)$rows[0]['id']);
        }

        $this->fail('the pick never ran out of projects: ' . implode(', ', $picked));
    }

    /**
     * The fixtures, among the given ones, that the live project read keeps.
     *
     * @param list<string> $letters
     *
     * @return list<string>
     */
    private function unfinishedAmong(array $letters): array
    {
        $reader = new MtQueueLoadReader($this->realSqlDb, $this->createStub(MatecatLogger::class), static fn(): int => time());
        $pids = array_map(fn(string $letter): int => $this->pids[$letter], $letters);

        $unfinished = (new ReflectionClass(MtQueueLoadReader::class))->getMethod('selectUnfinishedProjects')->invoke($reader, $pids);

        return array_map($this->letterOf(...), $unfinished);
    }

    /** The fixture letter of a project id, so a failure names the fixture. Example: the id of A gives 'A'. */
    private function letterOf(int $pid): string
    {
        return array_flip($this->pids)[$pid] ?? 'project ' . $pid . ' (not a fixture)';
    }

    /**
     * A daemon on the test database whose hold answer is fixed and whose processing lock always succeeds.
     *
     * The same wiring as FastAnalysisTest::daemonWithDb(), which is private to that class.
     * Example: daemon(true) never picks A or C.
     */
    private function daemon(bool $hold): FastAnalysisDbHoldProbe
    {
        $amq = $this->createStub(AMQHandler::class);
        // Each pick locks a different project key, so every SET NX of the fake succeeds
        $amq->method('getRedisClient')->willReturn(new FakeLockRedisClient());

        $daemon = (new ReflectionClass(FastAnalysisDbHoldProbe::class))->newInstanceWithoutConstructor();
        $daemon->hold = $hold;

        $fa = new ReflectionClass(FastAnalysis::class);
        $fa->getProperty('db')->setValue($daemon, $this->realSqlDb);
        $fa->getProperty('logger')->setValue($daemon, $this->createStub(MatecatLogger::class));
        $fa->getProperty('queueHandler')->setValue($daemon, $amq);

        return $daemon;
    }

    private function insertEngine(string $classLoad): int
    {
        $connection = $this->realSqlDb->getConnection();
        $connection->prepare(
            "INSERT INTO engines (name, type, description, base_url, translate_relative_url, class_load, others, extra_parameters, penalty, active) "
            . "VALUES ('mt queue db test', 'MT', 'fixture', 'https://example.invalid', 'translate', ?, '{}', '{}', 0, 1)"
        )->execute([$classLoad]);
        $id = (int)$connection->lastInsertId();
        $this->trackGeneratedId('engines', $id);

        return $id;
    }

    /**
     * An engine id with no row, as a job keeps when its engine row is gone.
     *
     * The gap of 1000 above the highest id stays clear of the engines this test and others insert.
     * Example: with engines up to id 12 it gives 1012.
     */
    private function unusedEngineId(): int
    {
        return (int)$this->realSqlDb->getConnection()->query('SELECT COALESCE(MAX(id), 0) + 1000 FROM engines')->fetchColumn();
    }

    /**
     * @param list<int> $engineIds one job per engine id
     */
    private function insertProject(string $status, array $engineIds, ?int $instanceId = null): int
    {
        $connection = $this->realSqlDb->getConnection();
        $connection->prepare(
            "INSERT INTO projects (id_customer, password, name, create_date, status_analysis, instance_id) VALUES (?, 'mtqueuepw', 'mt queue db test', NOW(), ?, ?)"
        )->execute([self::OWNER, $status, $instanceId ?? AppConfig::$INSTANCE_ID]);
        $pid = (int)$connection->lastInsertId();
        $this->trackGeneratedId('projects', $pid);

        foreach ($engineIds as $engineId) {
            $connection->prepare(
                "INSERT INTO jobs (password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled, id_mt_engine) "
                . "VALUES ('mtqueuejobpw', ?, 'en-US', 'it-IT', 1, 1, ?, '[]', NOW(), 0, ?)"
            )->execute([$pid, self::OWNER, $engineId]);
            $this->trackGeneratedId('jobs', (int)$connection->lastInsertId());
        }

        return $pid;
    }

    private function statusOf(string $letter): string
    {
        $stmt = $this->realSqlDb->getConnection()->prepare('SELECT status_analysis FROM projects WHERE id = ?');
        $stmt->execute([$this->pids[$letter]]);

        return (string)$stmt->fetchColumn();
    }

    private function countUnfinishedProjectsOfThisInstance(): int
    {
        $stmt = $this->realSqlDb->getConnection()->prepare(
            'SELECT COUNT(*) FROM projects WHERE status_analysis IN (?, ?, ?) AND instance_id = ?'
        );
        $stmt->execute([ProjectStatus::STATUS_NEW, ProjectStatus::STATUS_BUSY, ProjectStatus::STATUS_FAST_OK, AppConfig::$INSTANCE_ID]);

        return (int)$stmt->fetchColumn();
    }
}

/**
 * Daemon whose MT queue hold answer is set by the test, so no queue load is read.
 */
class FastAnalysisDbHoldProbe extends FastAnalysis
{
    public bool $hold = false;

    protected function _mtQueuesHold(): bool
    {
        return $this->hold;
    }
}
