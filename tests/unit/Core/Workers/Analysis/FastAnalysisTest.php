<?php

namespace Matecat\Core\Workers\Analysis;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PDO;
use PDOStatement;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client as PredisClient;
use ReflectionClass;
use RuntimeException;
use Stomp\Exception\ConnectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\FastAnalysis;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;

/**
 * Unit-covers the injected-database plumbing of the FastAnalysis daemon.
 *
 * The daemon constructor opens an ActiveMQ/Redis connection (and installs signal
 * handlers), so it cannot be exercised in a unit test. We instead build the
 * instance with newInstanceWithoutConstructor() and seed the protected $db with a
 * stub, then drive the methods that thread that handle into the DAOs. This proves
 * the daemon resolves its DB once (db()) and passes it down instead of calling the
 * Database::obtain() singleton.
 */
class FastAnalysisTest extends AbstractTest
{
    /**
     * Build a FastAnalysis with no constructor and the given IDatabase seeded
     * into its private $db composition-root property.
     */
    private function daemonWithDb(IDatabase $db): FastAnalysis
    {
        $ref    = new ReflectionClass(FastAnalysis::class);
        $daemon = $ref->newInstanceWithoutConstructor();

        $ref->getProperty('db')->setValue($daemon, $db);
        // The constructor normally wires the logger; seed a no-op stub so the
        // methods under test can log without a NullPointer on the typed property.
        $ref->getProperty('logger')->setValue($daemon, $this->createStub(MatecatLogger::class));

        return $daemon;
    }

    /**
     * Invoke a non-public method by name, with optional arguments.
     */
    private function invoke(FastAnalysis $daemon, string $method, mixed ...$args): mixed
    {
        $m = (new ReflectionClass(FastAnalysis::class))->getMethod($method);

        return $m->invoke($daemon, ...$args);
    }

    /**
     * Set a private/protected property on the daemon (e.g. a seeded collaborator).
     */
    private function setProp(FastAnalysis $daemon, string $name, mixed $value): void
    {
        (new ReflectionClass(FastAnalysis::class))->getProperty($name)->setValue($daemon, $value);
    }

    #[Test]
    public function dbReturnsTheSeededHandleWithoutHittingTheSingleton(): void
    {
        $injected = $this->createStub(IDatabase::class);
        $daemon   = $this->daemonWithDb($injected);

        // db() must return the already-resolved instance (the ??= short-circuit),
        // never fall back to Database::obtain().
        $this->assertSame($injected, $this->invoke($daemon, 'db'));
    }

    #[Test]
    public function getProjectDaoBuildsDaoWithTheInjectedDatabase(): void
    {
        $injected = $this->createStub(IDatabase::class);
        $daemon   = $this->daemonWithDb($injected);

        $dao = $this->invoke($daemon, 'getProjectDao');

        $this->assertInstanceOf(ProjectDao::class, $dao);
        // The DAO carries the injected handle, not the singleton.
        $this->assertSame($injected, $dao->getDatabaseHandler());
    }

    #[Test]
    public function checkDatabaseConnectionResolvesThroughInjectedDatabase(): void
    {
        // A non-Database IDatabase stub makes _checkDatabaseConnection() take the
        // early return after `$db = $this->db()` — covering the injected-handle
        // resolution without needing a live connection. expects(never()) on the
        // poisoned singleton proves it is not consulted.
        $injected = $this->createStub(IDatabase::class);

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $daemon = $this->daemonWithDb($injected);

        $this->assertNotInstanceOf(Database::class, $injected);
        $this->invoke($daemon, '_checkDatabaseConnection');
    }

    #[Test]
    public function executeInsertPreparesOnTheInjectedDatabaseConnection(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->willReturn(true);
        $stmt->expects($this->once())->method('closeCursor')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $injected = $this->createMock(IDatabase::class);
        $injected->expects($this->atLeastOnce())->method('getConnection')->willReturn($pdo);

        // The singleton must never be consulted for the insert.
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $daemon = $this->daemonWithDb($injected);

        $this->invoke($daemon, '_executeInsert', ['(:a,:b,:c,:d,:e,:f)'], ['x']);
    }

    #[Test]
    public function updateProjectChangesStatusThroughTheInjectedDatabaseTransaction(): void
    {
        $project = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_NEW; // != DONE → status change runs

        $projectDao = $this->createMock(ProjectDao::class);
        $projectDao->method('findById')->willReturn($project);
        $projectDao->expects($this->once())
            ->method('changeProjectStatus')
            ->with(7, ProjectStatus::STATUS_BUSY)
            ->willReturn(1);

        // transaction() must run on the injected db and execute the closure.
        $injected = $this->createMock(IDatabase::class);
        $injected->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn (callable $cb) => $cb());

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('transaction');
        $this->setDatabaseInstance($poison);

        $daemon = $this->daemonWithDb($injected);
        $this->setProp($daemon, 'projectDao', $projectDao); // getProjectDao() short-circuits to this

        $this->invoke($daemon, '_updateProject', 7, ProjectStatus::STATUS_BUSY);
    }

    /**
     * Seed a recording Redis fake behind a mocked AMQHandler so
     * requireQueueHandler()->getRedisClient() returns it. Predis\Client routes
     * commands through __call (not real methods), so PHPUnit cannot mock them on this
     * version; a hand-rolled subclass records calls and returns canned values instead.
     */
    private function seedQueueHandlerWithRedis(FastAnalysis $daemon): FastAnalysisFakeRedis
    {
        $redis = new FastAnalysisFakeRedis();

        $amq = $this->createStub(AMQHandler::class);
        $amq->method('getRedisClient')->willReturn($redis);

        $this->setProp($daemon, 'queueHandler', $amq);

        return $redis;
    }

    /**
     * @param FastAnalysisFakeRedis $redis
     *
     * @return list<mixed> the argument of each del() call, in order
     */
    private function delArgs(FastAnalysisFakeRedis $redis): array
    {
        $out = [];
        foreach ($redis->calls as $call) {
            if ($call[0] === 'del') {
                $out[] = $call[1];
            }
        }

        return $out;
    }

    /**
     * @param FastAnalysisFakeRedis $redis
     *
     * @return list<string> the command names invoked, in order
     */
    private function redisCommands(FastAnalysisFakeRedis $redis): array
    {
        return array_map(static fn (array $c): string => $c[0], $redis->calls);
    }

    /**
     * Seed a ProjectDao that expects exactly one status change to $expectedStatus,
     * driven through a pass-through transaction on the injected database.
     */
    private function daemonExpectingStatusChange(int $pid, string $expectedStatus): FastAnalysis
    {
        $project                  = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_BUSY; // != DONE → change runs

        $projectDao = $this->createMock(ProjectDao::class);
        $projectDao->method('findById')->willReturn($project);
        $projectDao->expects($this->once())
            ->method('changeProjectStatus')
            ->with($pid, $expectedStatus)
            ->willReturn(1);

        $injected = $this->createStub(IDatabase::class);
        $injected->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $daemon = $this->daemonWithDb($injected);
        $this->setProp($daemon, 'projectDao', $projectDao);

        return $daemon;
    }

    #[Test]
    public function releaseFailedProjectResetsToNewAndClearsLockBelowCap(): void
    {
        // S1a: a countable failure below the cap releases the project back to NEW and
        // drops the _fPid processing lock so the next cycle can re-pick it.
        $daemon = $this->daemonExpectingStatusChange(7, ProjectStatus::STATUS_NEW);
        $redis  = $this->seedQueueHandlerWithRedis($daemon);
        $redis->incrReturn = 2; // below MAX_FAST_ANALYSIS_ATTEMPTS

        $this->invoke($daemon, '_releaseFailedProject', 7, 'insert/publish failed', true);

        // countable failure: attempt counter incremented + given a TTL, no read-only get()
        $this->assertContains('incr', $this->redisCommands($redis));
        $this->assertContains('expire', $this->redisCommands($redis));
        $this->assertNotContains('get', $this->redisCommands($redis));
        // below the cap: only the processing lock is released, the attempt counter is kept
        $this->assertSame([['_fPid:7']], $this->delArgs($redis));
    }

    #[Test]
    public function releaseFailedProjectParksAsNotToAnalyzeAtCap(): void
    {
        // S1a: once a countable failure reaches MAX_FAST_ANALYSIS_ATTEMPTS the project is
        // parked as NOT_TO_ANALYZE and both Redis keys are cleared, so a poison project
        // cannot loop forever.
        $cap    = (int)(new ReflectionClass(FastAnalysis::class))->getConstant('MAX_FAST_ANALYSIS_ATTEMPTS');
        $daemon = $this->daemonExpectingStatusChange(7, ProjectStatus::STATUS_NOT_TO_ANALYZE);
        $redis  = $this->seedQueueHandlerWithRedis($daemon);
        $redis->incrReturn = $cap; // reaches the cap

        $this->invoke($daemon, '_releaseFailedProject', 7, 'insert/publish failed', true);

        // at the cap: both the attempt counter and the processing lock are cleared
        $this->assertEqualsCanonicalizing([['_fAttempts:7'], ['_fPid:7']], $this->delArgs($redis));
    }

    #[Test]
    public function releaseFailedProjectDoesNotCountInfrastructureFailures(): void
    {
        // S1a: an infrastructure failure (e.g. broker unreachable) must NOT increment the
        // attempt counter, so a transient outage never mass-parks healthy projects — it
        // just releases them to NEW to be retried when the broker returns.
        $daemon = $this->daemonExpectingStatusChange(7, ProjectStatus::STATUS_NEW);
        $redis  = $this->seedQueueHandlerWithRedis($daemon);
        $redis->getReturn = null; // no prior attempts recorded

        $this->invoke($daemon, '_releaseFailedProject', 7, 'broker unreachable', false);

        // infrastructure failure: counter is read-only (get), never incremented or TTL'd
        $this->assertNotContains('incr', $this->redisCommands($redis));
        $this->assertNotContains('expire', $this->redisCommands($redis));
        $this->assertSame([['_fPid:7']], $this->delArgs($redis));
    }

    #[Test]
    public function alreadyAnalyzedSegmentsIsEmptyAndSkipsDbOnFirstAttempt(): void
    {
        // S1b (light): on the first attempt nothing is analyzed yet, so the .ser IS the
        // to-do list — publish everything, and do NOT touch the DB on the common hot path.
        $injected = $this->createMock(IDatabase::class);
        $injected->expects($this->never())->method('transaction');
        $injected->expects($this->never())->method('getConnection');

        $daemon = $this->daemonWithDb($injected);
        $redis  = $this->seedQueueHandlerWithRedis($daemon);
        $redis->getReturn = null; // _fAttempts absent → first attempt

        $skip = $this->invoke($daemon, '_getAlreadyAnalyzedSegments', 7);

        $this->assertSame([], $skip);
        $this->assertContains(['get', '_fAttempts:7'], $redis->calls); // cheap retry gate
    }

    #[Test]
    public function alreadyAnalyzedSegmentsQueriesPerSegmentJobOnRetry(): void
    {
        // S1b (light): only on a retry do we skip. Granularity MUST be (id_segment, id_job):
        // the same source segment is published once per job/target-language, so a per-segment
        // skip would drop the second language. The diff is the already-DONE/SKIPPED rows.
        $rows = [
            ['id_segment' => '10', 'id_job' => '100'],
            ['id_segment' => '10', 'id_job' => '101'], // same segment, different job/language
        ];

        $preparedSql = '';
        $stmt        = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with([':pid' => 7])->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$preparedSql, $stmt) {
            $preparedSql = $sql;

            return $stmt;
        });

        $injected = $this->createMock(IDatabase::class);
        $injected->method('getConnection')->willReturn($pdo);
        $injected->expects($this->once())->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $daemon = $this->daemonWithDb($injected);
        $redis  = $this->seedQueueHandlerWithRedis($daemon);
        $redis->getReturn = '2'; // _fAttempts > 0 → retry

        $skip = $this->invoke($daemon, '_getAlreadyAnalyzedSegments', 7);

        // membership keyed "id_segment:id_job" — both jobs of segment 10 tracked separately
        $this->assertArrayHasKey('10:100', $skip);
        $this->assertArrayHasKey('10:101', $skip);

        // the diff query is the already-analyzed rows, per (segment, job)
        $normalized = preg_replace('/\s+/', ' ', $preparedSql);
        $this->assertMatchesRegularExpression('/select\s+st\.id_segment\s*,\s*st\.id_job/i', $normalized);
        $this->assertMatchesRegularExpression("/tm_analysis_status\s+IN\s*\(\s*'DONE'\s*,\s*'SKIPPED'\s*\)/i", $normalized);
    }

    #[Test]
    public function getSegmentsForFastVolumeAnalysisMatchesSerFilterNotLockedIce(): void
    {
        // The fallback (used when the .ser file is missing) must reconstruct the SAME set
        // ProjectManager writes to the .ser: filtered by show_in_cattool only, INCLUDING
        // segments later marked ICE/locked by pre-translation. It must NOT drop locked/ICE
        // (the old behaviour), which diverged from the .ser and from the completion gate.
        $rows = [
            [
                'jsid'           => '10-100:pw',
                'segment'        => 'hello',
                'source'         => 'en-US',
                'segment_hash'   => 'abc',
                'id'             => '10',
                'raw_word_count' => 1,
                'target'         => '100:fr-FR',
                'payable_rates'  => '{"100":{"NO_MATCH":1}}',
            ],
        ];

        $preparedSql = '';
        $stmt        = $this->createMock(PDOStatement::class);
        $stmt->method('setFetchMode')->willReturn(true);
        $stmt->expects($this->once())->method('execute')->with([7])->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$preparedSql, $stmt) {
            $preparedSql = $sql;

            return $stmt;
        });

        $injected = $this->createStub(IDatabase::class);
        $injected->method('getConnection')->willReturn($pdo);

        $daemon = $this->daemonWithDb($injected);

        $segments = $this->invoke($daemon, '_getSegmentsForFastVolumeAnalysis', 7);

        // payload transform preserved: payable_rates decoded then per-job re-encoded
        $this->assertSame('10', $segments[0]['id']);
        $this->assertSame('{"NO_MATCH":1}', $segments[0]['payable_rates']['100']);

        // SQL now complies with the .ser: show_in_cattool only, no locked/ICE filters
        $normalized = preg_replace('/\s+/', ' ', $preparedSql);
        $this->assertMatchesRegularExpression('/show_in_cattool\s*=\s*1/i', $normalized);
        $this->assertDoesNotMatchRegularExpression('/locked/i', $normalized, 'locked segments belong in the .ser; must not be filtered out');
        $this->assertDoesNotMatchRegularExpression('/ICE/i', $normalized, 'ICE segments belong in the .ser; must not be filtered out');
    }

    /**
     * @return array<string, array{0: PDOException, 1: bool}>
     */
    public static function pdoFailureClassification(): array
    {
        $mk = static function (string $sqlState, ?int $driverCode): PDOException {
            $e            = new PDOException('db error');
            $e->errorInfo = [$sqlState, $driverCode, 'driver message'];

            return $e;
        };

        return [
            // connection / transient → infrastructure (retry, do not count toward the park cap)
            'server gone away (2006)'    => [$mk('HY000', 2006), true],
            'lost connection (2013)'     => [$mk('HY000', 2013), true],
            'cannot connect (2002)'      => [$mk('HY000', 2002), true],
            'SQLSTATE class 08'          => [$mk('08S01', null), true],
            'too many connections (1040)' => [$mk('HY000', 1040), true],
            'deadlock (1213)'            => [$mk('40001', 1213), true],
            'lock wait timeout (1205)'   => [$mk('HY000', 1205), true],
            // data / statement errors → poison (count toward the cap, do NOT retry forever)
            'duplicate key (1062)'       => [$mk('23000', 1062), false],
            'null in NOT NULL col (1048)' => [$mk('23000', 1048), false],
            'syntax error (1064)'        => [$mk('42000', 1064), false],
            'table missing (1146)'       => [$mk('42S02', 1146), false],
        ];
    }

    #[Test]
    #[DataProvider('pdoFailureClassification')]
    public function isInfrastructureFailureClassifiesPdoErrors(PDOException $e, bool $expected): void
    {
        // Differentiate "database unreachable / transient" (retry) from "statement error"
        // (poison data) via SQLSTATE class + MySQL driver code in PDOException::$errorInfo.
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        $this->assertSame($expected, $this->invoke($daemon, '_isInfrastructureFailure', $e));
    }

    #[Test]
    public function isInfrastructureFailureTrueForStompConnectionException(): void
    {
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        $this->assertTrue($this->invoke($daemon, '_isInfrastructureFailure', new ConnectionException('broker down')));
        // also when wrapped as a previous exception
        $wrapped = new RuntimeException('publish failed', 0, new ConnectionException('broker down'));
        $this->assertTrue($this->invoke($daemon, '_isInfrastructureFailure', $wrapped));
    }

    #[Test]
    public function isInfrastructureFailureFalseForGenericError(): void
    {
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        $this->assertFalse($this->invoke($daemon, '_isInfrastructureFailure', new RuntimeException('boom')));
    }

    /**
     * Build a probe subclass (via reflection, no constructor) that overrides the
     * _newQueueHandler() seam so the real broker-connecting factory is never called.
     */
    private function rebuildProbe(): FastAnalysisRebuildProbe
    {
        $probe = (new ReflectionClass(FastAnalysisRebuildProbe::class))->newInstanceWithoutConstructor();
        // seed the typed logger property (declared on FastAnalysis) so _rebuildQueueHandler can log
        (new ReflectionClass(FastAnalysis::class))->getProperty('logger')->setValue($probe, $this->createStub(MatecatLogger::class));

        return $probe;
    }

    #[Test]
    public function rebuildQueueHandlerReplacesHandlerAndClosesOld(): void
    {
        // U2: a zombified Stomp client can never self-heal; rebuilding swaps in a fresh handler
        // (fresh client + connection) and disposes the old one.
        $probe = $this->rebuildProbe();

        $old = $this->createMock(AMQHandler::class);
        $old->expects($this->once())->method('close');
        $this->setProp($probe, 'queueHandler', $old);

        $fresh                 = $this->createStub(AMQHandler::class);
        $probe->newHandlerStub = $fresh;

        $this->invoke($probe, '_rebuildQueueHandler');

        $current = (new ReflectionClass(FastAnalysis::class))->getProperty('queueHandler')->getValue($probe);
        $this->assertSame($fresh, $current);
    }

    #[Test]
    public function rebuildQueueHandlerSwallowsFailingCloseAndStillReplaces(): void
    {
        // U2: the whole point of a rebuild is to discard an unhealthy handler, so a throwing
        // close() (dead socket) must not abort the swap.
        $probe = $this->rebuildProbe();

        // stub __destruct too, so the throwing close() fires only for the in-method call and
        // not again when the mock is garbage-collected at shutdown
        $old = $this->getMockBuilder(AMQHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['close', '__destruct'])
            ->getMock();
        $old->expects($this->once())->method('close')->willThrowException(new RuntimeException('socket dead'));
        $this->setProp($probe, 'queueHandler', $old);

        $fresh                 = $this->createStub(AMQHandler::class);
        $probe->newHandlerStub = $fresh;

        $this->invoke($probe, '_rebuildQueueHandler'); // must not throw

        $current = (new ReflectionClass(FastAnalysis::class))->getProperty('queueHandler')->getValue($probe);
        $this->assertSame($fresh, $current);
    }
}

/**
 * Recording Redis double. Predis\Client dispatches commands through __call rather than
 * declared methods, which PHPUnit cannot double on this version; this subclass declares
 * the handful of commands the daemon uses, records each call, and returns canned values.
 */
class FastAnalysisFakeRedis extends PredisClient
{
    /** @var list<array<int, mixed>> ordered [command, ...args] tuples */
    public array $calls = [];

    public int $incrReturn = 1;

    public int|string|null $getReturn = null;

    // Bypass Predis\Client's connection setup — this double never talks to a server.
    public function __construct() {}

    public function incr($key): int
    {
        $this->calls[] = ['incr', $key];

        return $this->incrReturn;
    }

    public function expire($key, $seconds = null): int
    {
        $this->calls[] = ['expire', $key, $seconds];

        return 1;
    }

    public function get($key): int|string|null
    {
        $this->calls[] = ['get', $key];

        return $this->getReturn;
    }

    public function del($keys): int
    {
        $this->calls[] = ['del', $keys];

        return 1;
    }
}

/**
 * Probe subclass that overrides the _newQueueHandler() seam so _rebuildQueueHandler can be
 * unit-tested without the real broker-connecting factory (AMQHandler::getNewInstanceForDaemons).
 */
class FastAnalysisRebuildProbe extends FastAnalysis
{
    public ?AMQHandler $newHandlerStub = null;

    protected function _newQueueHandler(): AMQHandler
    {
        return $this->newHandlerStub;
    }
}
