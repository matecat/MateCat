<?php

namespace Matecat\Core\Workers\Analysis;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\FilesStorage\AbstractFilesStorage;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\Projects\MetadataDao as ProjectMetadataDao;
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
use Utils\Engines\Results\MyMemory\AnalyzeResponse;
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
     * Seed a ProjectDao that expects exactly one atomic conditional status write to
     * $expectedStatus (the write skips itself, returning 0, if the project is already DONE).
     */
    private function daemonExpectingStatusChange(int $pid, string $expectedStatus): FastAnalysis
    {
        $projectDao = $this->createMock(ProjectDao::class);
        $projectDao->expects($this->once())
            ->method('changeProjectStatusIfNotDone')
            ->with($pid, $expectedStatus)
            ->willReturn(1);

        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));
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
    public function releaseFailedProjectLogsStructuredContextWhenParkingProject(): void
    {
        // Regression guard for report §11.7: the park decision must be queryable by pid/attempts/
        // reason (structured $context), not only interpolated into a free-text message.
        $cap    = (int)(new ReflectionClass(FastAnalysis::class))->getConstant('MAX_FAST_ANALYSIS_ATTEMPTS');
        $daemon = $this->daemonExpectingStatusChange(7, ProjectStatus::STATUS_NOT_TO_ANALYZE);
        $redis  = $this->seedQueueHandlerWithRedis($daemon);
        $redis->incrReturn = $cap;

        $logged = [];
        $this->setProp($daemon, 'logger', $this->capturingStructuredLogger($logged));

        $this->invoke($daemon, '_releaseFailedProject', 7, 'insert/publish failed', true);

        // $logged[0] is the park/retry decision itself; _updateProject() logs separately afterwards.
        self::assertSame('error', $logged[0]['level']);
        self::assertSame(['pid' => 7, 'attempts' => $cap, 'reason' => 'insert/publish failed'], $logged[0]['context']);
    }

    #[Test]
    public function releaseFailedProjectLogsStructuredContextWhenReleasingForRetry(): void
    {
        // Same guard as above, for the retry (below-cap) branch.
        $daemon = $this->daemonExpectingStatusChange(7, ProjectStatus::STATUS_NEW);
        $redis  = $this->seedQueueHandlerWithRedis($daemon);
        $redis->incrReturn = 2;

        $logged = [];
        $this->setProp($daemon, 'logger', $this->capturingStructuredLogger($logged));

        $this->invoke($daemon, '_releaseFailedProject', 7, 'insert/publish failed', true);

        // $logged[0] is the park/retry decision itself; _updateProject() logs separately afterwards.
        self::assertSame('debug', $logged[0]['level']);
        self::assertSame(['pid' => 7, 'attempts' => 2, 'reason' => 'insert/publish failed'], $logged[0]['context']);
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

    #[Test]
    public function mapJobPasswordsKeysPasswordByJobIdNotByPosition(): void
    {
        // R4: the `jsid` and `target` GROUP_CONCAT(DISTINCT ...) columns share no guaranteed row
        // order, so the password must be resolved by job id — never by array position, which could
        // hand a job another job's password.
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        $map = $this->invoke($daemon, '_mapJobPasswords', '49:passA,50:passB,51:passC');

        $this->assertSame(['49' => 'passA', '50' => 'passB', '51' => 'passC'], $map);
        // resolving by id yields the right password regardless of the concat's emitted order
        $this->assertSame('passB', $map['50']);
    }

    #[Test]
    public function getSegmentsForFastVolumeAnalysisRaisesGroupConcatMaxLenBeforeQuerying(): void
    {
        // D1: the payable_rates JSON + password/target concats can exceed MySQL's default
        // group_concat_max_len (1024 B) on multi-job/multi-language projects; a silent truncation
        // yields invalid JSON (json_decode → null → array_map TypeError). Raise the session limit,
        // and do it before the SELECT is prepared.
        $calls = [];

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('setFetchMode')->willReturn(true);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('exec')->willReturnCallback(function (string $sql) use (&$calls) {
            $calls[] = ['exec', $sql];

            return 0;
        });
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$calls, $stmt) {
            $calls[] = ['prepare', $sql];

            return $stmt;
        });

        $injected = $this->createStub(IDatabase::class);
        $injected->method('getConnection')->willReturn($pdo);

        $this->invoke($this->daemonWithDb($injected), '_getSegmentsForFastVolumeAnalysis', 7);

        $this->assertNotEmpty($calls);
        $this->assertSame('exec', $calls[0][0], 'group_concat_max_len must be raised before the SELECT is prepared');
        $this->assertMatchesRegularExpression('/SET\s+SESSION\s+group_concat_max_len\s*=\s*67108864/i', $calls[0][1]);
        $this->assertSame('prepare', $calls[1][0]);
    }

    #[Test]
    public function setTotalRemovesStalePidBeforePushingToThePositionList(): void
    {
        // D3: S1a makes a released project re-run _setTotal on each bounded retry, so the pid must
        // be LREM'd from the queue-position list before the RPUSH — otherwise it appears once per
        // attempt and inflates the position/ETA display for every project behind it.
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));
        $redis  = $this->seedQueueHandlerWithRedis($daemon);

        $queueInfo            = new \stdClass();
        $queueInfo->redis_key = 'p_queue_position';

        $this->invoke($daemon, '_setTotal', ['total' => 5, 'pid' => 7, 'queueInfo' => $queueInfo]);

        $ops       = array_map(static fn (array $c): string => $c[0], $redis->calls);
        $lremIndex = array_search('lrem', $ops, true);
        $rpushIndex = array_search('rpush', $ops, true);

        $this->assertNotFalse($lremIndex, 'the stale pid must be LREM-ed before being re-pushed');
        $this->assertNotFalse($rpushIndex);
        $this->assertLessThan($rpushIndex, $lremIndex, 'LREM must precede RPUSH');
        $this->assertSame(['lrem', 'p_queue_position', 0, '7'], $redis->calls[$lremIndex]);
        $this->assertSame(['rpush', 'p_queue_position', [7]], $redis->calls[$rpushIndex]);
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

    #[Test]
    #[DataProvider('fastAnalysisResponseStatusProvider')]
    public function assertFastAnalysisSucceededClassifiesResponseStatus(int $responseStatus, int|string|null $errorCode, ?int $expectedThrownCode): void
    {
        // S2: _fetchMyMemoryFast must be the single classifier — a 200 passes, every other outcome
        // throws a typed code so main() never falls through to zero-wc processing (FAST_OK stall).
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        if ($expectedThrownCode === null) {
            $this->expectNotToPerformAssertions(); // 200 is the only non-throwing outcome
            $this->invoke($daemon, '_assertFastAnalysisSucceeded', $responseStatus, $errorCode, 42);

            return;
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionCode($expectedThrownCode);
        $this->invoke($daemon, '_assertFastAnalysisSucceeded', $responseStatus, $errorCode, 42);
    }

    /**
     * @return array<string, array{0: int, 1: int|string|null, 2: ?int}>
     */
    public static function fastAnalysisResponseStatusProvider(): array
    {
        return [
            '200 ok → no throw'                    => [200, null, null],
            'curl timeout (-28) → too large'       => [0, -28, FastAnalysis::ERR_TOO_LARGE],
            '504 gateway timeout → too large'      => [504, null, FastAnalysis::ERR_TOO_LARGE],
            '500 server error → err_500'           => [500, null, FastAnalysis::ERR_500],
            '502 bad gateway → err_500'            => [502, null, FastAnalysis::ERR_500],
            '0 transport → transient'              => [0, null, FastAnalysis::ERR_ANALYSIS_TRANSIENT],
            '0 transport (-7 refused) → transient' => [0, -7, FastAnalysis::ERR_ANALYSIS_TRANSIENT],
            '503 overload → transient'             => [503, null, FastAnalysis::ERR_ANALYSIS_TRANSIENT],
            '501 → transient'                      => [501, null, FastAnalysis::ERR_ANALYSIS_TRANSIENT],
            '400 malformed → failed'               => [400, null, FastAnalysis::ERR_ANALYSIS_FAILED],
            '404 misconfig → failed'               => [404, null, FastAnalysis::ERR_ANALYSIS_FAILED],
        ];
    }

    #[Test]
    #[DataProvider('fetchFailureActionProvider')]
    public function decideFetchFailureActionMapsExceptionToAction(\Throwable $e, bool $performTmsAnalysis, string $expectedAction): void
    {
        // S3: all fetch-failure decision logic lives in this one tested method; main() only
        // dispatches the returned action. A truly unexpected error must NEVER map to DONE when
        // analysis is enabled (that was the bogus-DONE bug) — it maps to a retry instead.
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        $this->assertSame(
            $expectedAction,
            $this->invoke($daemon, '_decideFetchFailureAction', $e, $performTmsAnalysis)
        );
    }

    /**
     * @return array<string, array{0: \Throwable, 1: bool, 2: string}>
     */
    public static function fetchFailureActionProvider(): array
    {
        return [
            'too large → park'                        => [new \Exception('x', FastAnalysis::ERR_TOO_LARGE), true, FastAnalysis::ACTION_PARK],
            'err500 → park'                           => [new \Exception('x', FastAnalysis::ERR_500), true, FastAnalysis::ACTION_PARK],
            'empty response → reset'                  => [new \Exception('x', FastAnalysis::ERR_EMPTY_RESPONSE), true, FastAnalysis::ACTION_RESET],
            'transient + enabled → retry uncounted'   => [new \Exception('x', FastAnalysis::ERR_ANALYSIS_TRANSIENT), true, FastAnalysis::ACTION_RETRY_UNCOUNTED],
            'transient + disabled → done'             => [new \Exception('x', FastAnalysis::ERR_ANALYSIS_TRANSIENT), false, FastAnalysis::ACTION_DONE],
            'failed + enabled → retry counted'        => [new \Exception('x', FastAnalysis::ERR_ANALYSIS_FAILED), true, FastAnalysis::ACTION_RETRY_COUNTED],
            'failed + disabled → done'                => [new \Exception('x', FastAnalysis::ERR_ANALYSIS_FAILED), false, FastAnalysis::ACTION_DONE],
            'no segments → done (enabled)'            => [new \Exception('x', FastAnalysis::ERR_NO_SEGMENTS), true, FastAnalysis::ACTION_DONE],
            'no segments → done (disabled)'           => [new \Exception('x', FastAnalysis::ERR_NO_SEGMENTS), false, FastAnalysis::ACTION_DONE],
            'unexpected + enabled → retry counted'    => [new RuntimeException('boom'), true, FastAnalysis::ACTION_RETRY_COUNTED],
            'unexpected infra + enabled → retry unc.' => [new ConnectionException('broker down'), true, FastAnalysis::ACTION_RETRY_UNCOUNTED],
            'unexpected + disabled → done'            => [new RuntimeException('boom'), false, FastAnalysis::ACTION_DONE],
        ];
    }

    #[Test]
    public function isBrokerFailureTrueOnlyForDirectOrWrappedConnectionException(): void
    {
        // The broker check (which gates the Stomp rebuild) must fire for a ConnectionException
        // thrown directly OR wrapped as a previous exception — both zombify the Stomp client —
        // and for nothing else. It is the single source of truth also reused by
        // _isInfrastructureFailure's first branch.
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        $this->assertTrue($this->invoke($daemon, '_isBrokerFailure', new ConnectionException('broker down')));
        $wrapped = new RuntimeException('publish failed', 0, new ConnectionException('broker down'));
        $this->assertTrue($this->invoke($daemon, '_isBrokerFailure', $wrapped));

        // a plain error and a DB error are NOT broker failures
        $this->assertFalse($this->invoke($daemon, '_isBrokerFailure', new RuntimeException('boom')));
        $this->assertFalse($this->invoke($daemon, '_isBrokerFailure', new PDOException('server gone')));
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

    #[Test]
    public function rebuildQueueHandlerBuildsOnColdStartWhenNoHandlerYet(): void
    {
        // ctor / cold-start path: $queueHandler is an UNINITIALIZED typed property. _rebuildQueueHandler
        // must not read it with ?-> (which fatals on an uninitialized property) — it uses isset() — and
        // simply installs and returns the fresh handler. This is what lets the ctor route through it.
        $probe = $this->rebuildProbe(); // queueHandler deliberately NOT seeded → uninitialized

        $fresh                 = $this->createStub(AMQHandler::class);
        $probe->newHandlerStub = $fresh;

        $returned = $this->invoke($probe, '_rebuildQueueHandler');

        $this->assertSame($fresh, $returned, 'returns the freshly built handler');
        $current = (new ReflectionClass(FastAnalysis::class))->getProperty('queueHandler')->getValue($probe);
        $this->assertSame($fresh, $current, 'installs the fresh handler');
    }

    #[Test]
    public function checkDatabaseConnectionProbesTheMasterInsideATransaction(): void
    {
        // The health probe must ride the master route (a transaction) — the same route the
        // lock/update writes use. A bare ping() is a plain read a replica can answer while the
        // master is down (false green), so the probe must run inside a transaction.
        //
        // A stub (no expectations) records behaviour through flags: Database::__destruct() calls
        // close() at GC, which a stub absorbs harmlessly, whereas an expects() count would trip.
        $db = $this->createStub(Database::class);

        $txEntered = $pingedInsideTx = $closedDuringProbe = false;
        $db->method('transaction')->willReturnCallback(function (callable $cb) use (&$txEntered) {
            $txEntered = true;

            return $cb();
        });
        $db->method('ping')->willReturnCallback(function () use (&$pingedInsideTx) {
            $pingedInsideTx = true;

            return true;
        });
        $db->method('close')->willReturnCallback(function () use (&$closedDuringProbe) {
            $closedDuringProbe = true;
        });

        $daemon = $this->daemonWithDb($db);
        $this->invoke($daemon, '_checkDatabaseConnection');

        $this->assertTrue($txEntered, 'the probe must go through a transaction (master route)');
        $this->assertTrue($pingedInsideTx, 'the ping must run inside the transaction callback');
        $this->assertFalse($closedDuringProbe, 'a healthy probe must not close/reconnect');
    }

    #[Test]
    public function checkDatabaseConnectionReconnectsWhenTheMasterProbeThrows(): void
    {
        // If the master probe throws (master unreachable), the connection is dropped and rebuilt
        // so the next cycle starts from a clean handle instead of trusting a false green.
        $db = $this->createStub(Database::class);

        $closed = $reconnected = false;
        $db->method('transaction')->willThrowException(new PDOException('master down'));
        $db->method('close')->willReturnCallback(function () use (&$closed) {
            $closed = true;
        });
        $pdo = $this->createStub(PDO::class);
        $db->method('getConnection')->willReturnCallback(function () use (&$reconnected, $pdo) {
            $reconnected = true;

            return $pdo;
        });

        $daemon = $this->daemonWithDb($db);
        $this->invoke($daemon, '_checkDatabaseConnection'); // must swallow and reconnect, not throw

        $this->assertTrue($closed, 'a failed probe must drop the stale connection');
        $this->assertTrue($reconnected, 'a failed probe must rebuild the connection');
    }

    #[Test]
    public function lockProjectKeepsProjectInBatchWhenBusyUpdateSucceeds(): void
    {
        // Happy path: lock acquired + BUSY set → the project stays in the returned batch and its
        // _fPid processing lock is NOT released.
        [$daemon, $redis] = $this->daemonLockingOneProject(fn () => 1);

        $result = $this->invoke($daemon, '_getLockProjectForVolumeAnalysis', 5);

        $this->assertCount(1, $result);
        $this->assertSame('42', array_values($result)[0]['id']);
        $this->assertNotContains(['del', '_fPid:42'], $redis->calls); // lock kept
    }

    #[Test]
    public function lockProjectReleasesAndDropsProjectWhenBusyUpdateThrows(): void
    {
        // Zone-B fix: a failed BUSY write must release the _fPid lock AND drop the project from the
        // batch. Leaving it in hands main() a still-NEW, now-unlocked project → lockless analysis
        // here plus concurrent re-pickup by another daemon (double analysis / counter pollution).
        [$daemon, $redis] = $this->daemonLockingOneProject(
            fn () => throw new PDOException('master write failed')
        );

        $result = $this->invoke($daemon, '_getLockProjectForVolumeAnalysis', 5);

        $this->assertSame([], $result, 'the un-BUSYable project must not be returned to main()');
        $this->assertContains(['del', '_fPid:42'], $redis->calls, 'the processing lock must be released');
    }

    #[Test]
    public function lockProjectReleasesAndDropsProjectWhenBusyUpdateThrowsError(): void
    {
        // Zone-B fix (widened catch): the guard must catch \Throwable, not just \Exception. A
        // \TypeError/\Error from the DAO path would otherwise escape → the _fPid lock is never
        // released and the project is stranded NEW-but-locked for the full 24h TTL.
        [$daemon, $redis] = $this->daemonLockingOneProject(
            fn () => throw new \TypeError('unexpected type from DAO')
        );

        $result = $this->invoke($daemon, '_getLockProjectForVolumeAnalysis', 5);

        $this->assertSame([], $result);
        $this->assertContains(['del', '_fPid:42'], $redis->calls);
    }

    #[Test]
    public function updateProjectWritesConditionallyWithoutReadingFirst(): void
    {
        // R3 fix: the status write must be a single atomic conditional UPDATE — no findById read
        // beforehand (the old read-then-write left a lost-update window against a TM worker's DONE).
        $projectDao = $this->createMock(ProjectDao::class);
        $projectDao->expects($this->never())->method('findById');
        $projectDao->expects($this->once())
            ->method('changeProjectStatusIfNotDone')
            ->with(42, ProjectStatus::STATUS_FAST_OK)
            ->willReturn(1);

        $logged = [];
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));
        $this->setProp($daemon, 'projectDao', $projectDao);
        $this->setProp($daemon, 'logger', $this->capturingLogger($logged));

        $this->invoke($daemon, '_updateProject', 42, ProjectStatus::STATUS_FAST_OK);

        $this->assertStringNotContainsString(
            '0 rows',
            implode("\n", $logged),
            'a successful write must not log a concurrency skip'
        );
    }

    #[Test]
    public function updateProjectLogsConcurrencyWhenTheWriteMatchesNoRows(): void
    {
        // 0 affected rows = the project was already DONE (a TM worker finished first) or is gone;
        // the daemon logs the skip so the concurrency is observable instead of silently swallowed.
        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('changeProjectStatusIfNotDone')->willReturn(0);

        $logged = [];
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));
        $this->setProp($daemon, 'projectDao', $projectDao);
        $this->setProp($daemon, 'logger', $this->capturingLogger($logged));

        $this->invoke($daemon, '_updateProject', 42, ProjectStatus::STATUS_FAST_OK);

        $this->assertCount(1, $logged);
        $this->assertStringContainsString('0 rows', $logged[0]);
        $this->assertStringContainsString('42', $logged[0]);
    }

    /**
     * A MatecatLogger stub that appends every debug() message to $sink for assertions.
     *
     * @param array<int, string> $sink
     */
    private function capturingLogger(array &$sink): MatecatLogger
    {
        $logger = $this->createStub(MatecatLogger::class);
        $logger->method('debug')->willReturnCallback(function (string $message) use (&$sink) {
            $sink[] = $message;
        });

        return $logger;
    }

    /**
     * @param list<array{level: string, message: mixed, context: array<mixed>}> $sink
     */
    private function capturingStructuredLogger(array &$sink): MatecatLogger
    {
        $logger = $this->createStub(MatecatLogger::class);
        $capture = function (string $level) use (&$sink) {
            return function (mixed $message, array $context = []) use (&$sink, $level) {
                $sink[] = ['level' => $level, 'message' => $message, 'context' => $context];
            };
        };
        $logger->method('debug')->willReturnCallback($capture('debug'));
        $logger->method('error')->willReturnCallback($capture('error'));

        return $logger;
    }

    #[Test]
    public function lockProjectAcquiresWithASingleAtomicSetNxEx(): void
    {
        // R1: the lock must be taken with one atomic SET … NX EX, never setnx + a separate expire
        // (a crash between the two would leave _fPid with no TTL → the project locked until a
        // manual Redis delete).
        [$daemon, $redis] = $this->daemonLockingOneProject(fn () => 1); // acquired + BUSY ok

        $this->invoke($daemon, '_getLockProjectForVolumeAnalysis', 5);

        $this->assertContains(['set', '_fPid:42', 1, 'EX', 86400, 'NX'], $redis->calls);
        foreach ($redis->calls as $call) {
            $this->assertNotSame('expire', $call[0], 'lock acquisition must not use a separate expire');
            $this->assertNotSame('setnx', $call[0], 'lock acquisition must be an atomic SET, not setnx');
        }
    }

    #[Test]
    public function lockProjectSkipsProjectWhenLockAlreadyHeld(): void
    {
        // NX fails (another daemon already holds the lock) → SET returns null → the project is
        // dropped from the batch and nothing is released (we never acquired it).
        [$daemon, $redis] = $this->daemonLockingOneProject(fn () => 1);
        $redis->setReturn = null; // NX failed

        $result = $this->invoke($daemon, '_getLockProjectForVolumeAnalysis', 5);

        $this->assertSame([], $result);
        $this->assertNotContains(['del', '_fPid:42'], $redis->calls);
    }

    /**
     * Seed the lock picker so its master-routed SELECT returns exactly one NEW project, backed by
     * a ProjectDao whose changeProjectStatusIfNotDone() runs $onBusyUpdate (return a row count, or throw).
     *
     * @param callable $onBusyUpdate invoked as the BUSY write inside _updateProject
     *
     * @return array{0: FastAnalysis, 1: FastAnalysisFakeRedis}
     */
    private function daemonLockingOneProject(callable $onBusyUpdate): array
    {
        $rows = [[
            'id'               => '42',
            'id_tms'           => 1,
            'id_mt_engine'     => 0,
            'tm_keys'          => '',
            'pretranslate_100' => 0,
            'jid_list'         => '100',
            'only_private_tm'  => 0,
            'id_customer'      => 'c1',
        ]];

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $project                  = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_NEW; // != DONE → the BUSY update runs

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($project);
        $projectDao->method('changeProjectStatusIfNotDone')->willReturnCallback($onBusyUpdate);

        $injected = $this->createStub(IDatabase::class);
        $injected->method('getConnection')->willReturn($pdo);
        // pass-through: both the picker SELECT and _updateProject route through transaction()
        $injected->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $daemon = $this->daemonWithDb($injected);
        $this->setProp($daemon, 'projectDao', $projectDao);
        $redis            = $this->seedQueueHandlerWithRedis($daemon);
        $redis->setReturn = 'OK'; // lock acquired (atomic SET NX EX)

        return [$daemon, $redis];
    }

    // ────────────────────────────────────────────────────────────────────────
    // main() loop coverage
    //
    // main() is the daemon orchestration loop: it never returns until $this->RUNNING
    // is cleared, and its body constructs a FeatureSet, a metadata DAO and the cache
    // purge, publishes through _insertFastAnalysis and finalizes the project status.
    // FastAnalysisMainProbe (bottom of this file) replaces every external seam with a
    // recording double so a full cycle can be driven with no broker/Redis/DB, and a
    // scripted sismember() makes the loop run a controllable number of cycles and then
    // suicide (the FAST_PID_SET membership check at the top of every cycle).
    // ────────────────────────────────────────────────────────────────────────

    /**
     * @return array{0: FastAnalysisMainProbe, 1: FastAnalysisMainLoopRedis, 2: ProjectDao}
     */
    private function wireMainProbe(): array
    {
        $probe = (new ReflectionClass(FastAnalysisMainProbe::class))->newInstanceWithoutConstructor();

        $fa = new ReflectionClass(FastAnalysis::class);

        $db = $this->createStub(IDatabase::class);
        // transaction() passes through so the metadata read and _updateProject run for real.
        $db->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());
        $fa->getProperty('db')->setValue($probe, $db);

        $fa->getProperty('logger')->setValue($probe, $this->createStub(MatecatLogger::class));
        $fa->getProperty('myProcessPid')->setValue($probe, 4242);
        $fa->getProperty('RUNNING')->setValue($probe, true);

        $filesStorage = $this->createStub(AbstractFilesStorage::class);
        $fa->getProperty('files_storage')->setValue($probe, $filesStorage);

        $redis   = new FastAnalysisMainLoopRedis();
        $handler = $this->createStub(AMQHandler::class);
        $handler->method('getRedisClient')->willReturn($redis);
        $fa->getProperty('queueHandler')->setValue($probe, $handler);
        // rebuild seam returns a handler backed by the same recording Redis, so the loop
        // stays controllable after a broker-failure rebuild.
        $probe->newQueueHandlerStub = $handler;

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('changeProjectStatusIfNotDone')->willReturn(1);
        $projectDao->method('findById')->willReturnCallback(fn (int $pid) => $this->makeProjectStruct($pid));
        $fa->getProperty('projectDao')->setValue($probe, $projectDao);

        $probe->featureSetStub  = $this->createStub(FeatureSet::class);
        $probe->metadataDaoStub = $this->makeMetadataDaoStub();

        return [$probe, $redis, $projectDao];
    }

    private function makeProjectStruct(int $pid): ProjectStruct
    {
        $p           = new ProjectStruct();
        $p->id       = $pid;
        $p->password = 'pw' . $pid;

        return $p;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function makeMetadataDaoStub(array $metadata = []): ProjectMetadataDao
    {
        $dao = $this->createStub(ProjectMetadataDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('allByProjectIdAsKeyValue')->willReturn($metadata);

        return $dao;
    }

    /**
     * @return array<string, mixed>
     */
    private function projectRow(int $pid, bool $tmsEnabled = true): array
    {
        return ['id' => $pid, 'id_tms' => $tmsEnabled ? 1 : 0, 'id_mt_engine' => 0];
    }

    private function runMain(FastAnalysisMainProbe $probe): void
    {
        (new ReflectionClass(FastAnalysis::class))->getMethod('main')->invoke($probe);
    }

    #[Test]
    public function main_suicidesWhenProcessPidNotInFastPidSet(): void
    {
        [$probe, $redis] = $this->wireMainProbe();
        // The very first membership check returns false → the daemon must clear RUNNING and exit
        // without ever querying for projects.
        $redis->sismemberReturns = [false];
        $probe->lockBatches      = [];

        $this->runMain($probe);

        $this->assertFalse($this->readRunning($probe), 'daemon should stop when its pid is not registered');
        $this->assertSame(0, $probe->insertCallCount, 'no project should be processed on suicide');
        $this->assertSame(['sismember'], $this->redisCommands($redis));
    }

    #[Test]
    public function main_finalizesProjectOnSuccessfulFastAnalysis(): void
    {
        [$probe, $redis] = $this->wireMainProbe();
        $redis->sismemberReturns = [true, false]; // one working cycle, then suicide
        $probe->lockBatches      = [[$this->projectRow(1606)]];
        $probe->fetchScript      = [new AnalyzeResponse(['data' => []])];
        $probe->insertResults    = [0];

        $this->runMain($probe);

        $this->assertSame(1, $probe->insertCallCount, 'the project should be published once');
        $this->assertSame([1606], $probe->purgedPids, 'the finalized project caches should be purged');
        $this->assertContains('del', $this->redisCommands($redis), 'the failed-attempts key should be cleared on success');
    }

    #[Test]
    public function main_remapsLowMatchTypesFromPopulatedFastAnalysisData(): void
    {
        [$probe] = $this->wireMainProbe();
        $redis   = null;
        $probe->lockBatches   = [[$this->projectRow(1606)]];
        $probe->fetchScript   = [new AnalyzeResponse(['data' => [
            0 => ['type' => '50%-74%', 'wc' => 3], // must be rewritten to NO_MATCH
            1 => ['type' => '95%-99%', 'wc' => 2],
        ]])];
        $probe->insertResults = [0];

        // Seed the reverse-lookup + segments map main() writes the per-segment wc/match_type into.
        $fa = new ReflectionClass(FastAnalysis::class);
        $fa->getProperty('segment_hashes')->setValue($probe, [0 => 'h0', 1 => 'h1']);
        $fa->getProperty('segments')->setValue($probe, [
            'h0' => ['jsid' => '1-1:pw'],
            'h1' => ['jsid' => '1-2:pw'],
        ]);

        $this->runMain($probe);

        $segments = $fa->getProperty('segments')->getValue($probe);
        $this->assertSame('NO_MATCH', $segments['h0']['match_type'], '50%-74% must be remapped to NO_MATCH');
        $this->assertSame(3, $segments['h0']['wc']);
        $this->assertSame('95%-99%', $segments['h1']['match_type'], 'other match types are upper-cased as-is');
        $this->assertSame(1, $probe->insertCallCount);
    }

    #[Test]
    public function main_dispatchesEachFetchFailureAction(): void
    {
        [$probe, $redis, $projectDao] = $this->wireMainProbe();
        // Four working cycles (one per continue-2 arm) then suicide. Each cycle re-picks a
        // fresh single-project batch because every arm restarts the do-while via `continue 2`.
        $redis->sismemberReturns = [true, true, true, true, false];
        $probe->lockBatches      = [
            [$this->projectRow(11)], // ERR_TOO_LARGE     → ACTION_PARK
            [$this->projectRow(12)], // ERR_EMPTY_RESPONSE→ ACTION_RESET
            [$this->projectRow(13)], // ERR_ANALYSIS_FAILED    → ACTION_RETRY_COUNTED
            [$this->projectRow(14)], // ERR_ANALYSIS_TRANSIENT → ACTION_RETRY_UNCOUNTED
        ];
        $probe->fetchScript = [
            new \Exception('too large', FastAnalysis::ERR_TOO_LARGE),
            new \Exception('empty', FastAnalysis::ERR_EMPTY_RESPONSE),
            new \Exception('failed', FastAnalysis::ERR_ANALYSIS_FAILED),
            new \Exception('transient', FastAnalysis::ERR_ANALYSIS_TRANSIENT),
        ];

        $this->runMain($probe);

        // No project reaches the publish step; every arm routes to a status write / release instead.
        $this->assertSame(0, $probe->insertCallCount, 'a fetch failure must never reach the publish step');
        $this->assertContains('incr', $this->redisCommands($redis), 'the counted-retry arm bumps the attempt counter');
        $this->assertFalse($this->readRunning($probe));
    }

    #[Test]
    public function main_finalizesDoneWhenAllSegmentsPreTranslated(): void
    {
        [$probe] = $this->wireMainProbe();
        $probe->lockBatches   = [[$this->projectRow(1606)]];
        // ERR_NO_SEGMENTS → ACTION_DONE → break → proceed to publish with empty data, finalize DONE.
        $probe->fetchScript   = [new \Exception('all pre-translated', FastAnalysis::ERR_NO_SEGMENTS)];
        $probe->insertResults = [0];

        $this->runMain($probe);

        $this->assertSame(1, $probe->insertCallCount, 'the pre-translated project is still finalized through publish');
        $this->assertSame([1606], $probe->purgedPids);
    }

    #[Test]
    public function main_finalizesDoneWhenTmsDisabledAndFetchFails(): void
    {
        [$probe] = $this->wireMainProbe();
        $probe->lockBatches   = [[$this->projectRow(1606, tmsEnabled: false)]]; // id_tms=0 && id_mt_engine=0
        // A transient failure on a TMS-disabled project keeps the prior DONE (ACTION_DONE).
        $probe->fetchScript   = [new \Exception('transient', FastAnalysis::ERR_ANALYSIS_TRANSIENT)];
        $probe->insertResults = [0];

        $this->runMain($probe);

        $this->assertSame(1, $probe->featureSetCallCount, 'the disabled path dispatches through the FeatureSet');
        $this->assertSame(1, $probe->insertCallCount);
    }

    #[Test]
    public function main_releasesProjectWhenPublishReturnsFailure(): void
    {
        [$probe, $redis] = $this->wireMainProbe();
        $probe->lockBatches   = [[$this->projectRow(1606)]];
        $probe->fetchScript   = [new AnalyzeResponse(['data' => []])];
        $probe->insertResults = [-1]; // publish failed (not an infra failure)

        $this->runMain($probe);

        $this->assertSame([], $probe->purgedPids, 'a failed publish must not finalize the project');
        $this->assertContains('incr', $this->redisCommands($redis), 'a countable failure bumps the attempt counter');
    }

    #[Test]
    public function main_rebuildsQueueHandlerOnBrokerFailureDuringPublish(): void
    {
        [$probe] = $this->wireMainProbe();
        $probe->lockBatches = [[$this->projectRow(1606)]];
        $probe->fetchScript = [new AnalyzeResponse(['data' => []])];
        // A broker-connection failure during publish zombifies the Stomp client → rebuild required.
        $probe->insertThrows = new ConnectionException('broker unreachable');

        $this->runMain($probe);

        $this->assertSame(1, $probe->rebuildCount, 'a broker failure during publish must rebuild the AMQ handler');
        $this->assertSame([], $probe->purgedPids);
    }

    #[Test]
    public function main_skipsProjectDeletedMidAnalysis(): void
    {
        [$probe, $redis] = $this->wireMainProbe();

        // findById returns null on master → the project was deleted mid-analysis and must be skipped.
        $deletedDao = $this->createStub(ProjectDao::class);
        $deletedDao->method('findById')->willReturn(null);
        (new ReflectionClass(FastAnalysis::class))->getProperty('projectDao')->setValue($probe, $deletedDao);

        $probe->lockBatches   = [[$this->projectRow(1606)]];
        $probe->fetchScript   = [new AnalyzeResponse(['data' => []])];
        $probe->insertResults = [0];

        $this->runMain($probe);

        $this->assertSame(0, $probe->insertCallCount, 'a deleted project must not be published');
        $this->assertContains('del', $this->redisCommands($redis), 'the inert processing lock is dropped');
    }

    #[Test]
    public function main_recoversFromUnexpectedThrowableWhileFinalizing(): void
    {
        [$probe, $redis] = $this->wireMainProbe();

        // An unexpected Throwable raised in the finalize tail (here: cache-file delete) must be
        // absorbed by the per-project safety net and the project released, not kill the daemon.
        $throwingStorage = $this->createStub(AbstractFilesStorage::class);
        $throwingStorage->method('deleteFastAnalysisFile')->willThrowException(new RuntimeException('disk error'));
        (new ReflectionClass(FastAnalysis::class))->getProperty('files_storage')->setValue($probe, $throwingStorage);

        $probe->lockBatches   = [[$this->projectRow(1606)]];
        $probe->fetchScript   = [new AnalyzeResponse(['data' => []])];
        $probe->insertResults = [0];

        $this->runMain($probe);

        $this->assertFalse($this->readRunning($probe), 'the daemon should have completed its cycles, not crashed');
        $this->assertContains('incr', $this->redisCommands($redis), 'the recovered project is released for retry');
    }

    #[Test]
    public function main_continuesAfterDatabaseConnectionFailure(): void
    {
        [$probe] = $this->wireMainProbe();
        $redis = (new ReflectionClass(FastAnalysis::class))->getProperty('queueHandler')->getValue($probe);
        $probe->dbCheckThrows = true;        // first cycle: DB probe throws → reconnect next cycle
        $this->setRedisSismember($probe, [true, false]);

        $this->runMain($probe);

        $this->assertSame(0, $probe->insertCallCount, 'no project is processed while the DB is unreachable');
        $this->assertFalse($this->readRunning($probe));
    }

    #[Test]
    public function main_sleepsWhenNoProjectsAreAvailable(): void
    {
        [$probe] = $this->wireMainProbe();
        $this->setRedisSismember($probe, [true, false]);
        $probe->lockBatches = [[]]; // picker returns an empty batch → idle sleep, then suicide

        $this->runMain($probe);

        $this->assertSame(0, $probe->insertCallCount);
        $this->assertFalse($this->readRunning($probe));
    }

    #[Test]
    public function newFeatureSet_buildsFeatureSetFromInjectedDatabase(): void
    {
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        $this->assertInstanceOf(FeatureSet::class, $this->invoke($daemon, '_newFeatureSet'));
    }

    #[Test]
    public function getProjectMetadataDao_buildsDaoFromInjectedDatabase(): void
    {
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        $this->assertInstanceOf(ProjectMetadataDao::class, $this->invoke($daemon, '_getProjectMetadataDao'));
    }

    private function readRunning(FastAnalysisMainProbe $probe): bool
    {
        return (bool)(new ReflectionClass(FastAnalysis::class))->getProperty('RUNNING')->getValue($probe);
    }

    /**
     * @param list<bool> $script
     */
    private function setRedisSismember(FastAnalysisMainProbe $probe, array $script): void
    {
        $handler = (new ReflectionClass(FastAnalysis::class))->getProperty('queueHandler')->getValue($probe);
        $handler->getRedisClient()->sismemberReturns = $script;
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

    public string|null $setReturn = 'OK';

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

    public function set($key, $value, ...$options): string|null
    {
        $this->calls[] = array_merge(['set', $key, $value], $options);

        return $this->setReturn;
    }

    public function setex($key, $seconds, $value): string
    {
        $this->calls[] = ['setex', $key, $seconds, $value];

        return 'OK';
    }

    public function lrem($key, $count, $value): int
    {
        $this->calls[] = ['lrem', $key, $count, $value];

        return 0;
    }

    public function rpush($key, $values): int
    {
        $this->calls[] = ['rpush', $key, $values];

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

/**
 * Recording Redis double for main(): adds the membership + shutdown commands the loop uses on
 * top of FastAnalysisFakeRedis. sismember() is scripted so the daemon runs a controllable
 * number of cycles and then suicides (empty script → false → RUNNING cleared).
 */
class FastAnalysisMainLoopRedis extends FastAnalysisFakeRedis
{
    /** @var list<bool> popped per sismember() call; empty ⇒ false (loop exits) */
    public array $sismemberReturns = [true, false];

    public function sismember($key, $member): int
    {
        $this->calls[] = ['sismember', $key, $member];

        return array_shift($this->sismemberReturns) ? 1 : 0;
    }

    public function srem($key, ...$members): int
    {
        $this->calls[] = array_merge(['srem', $key], $members);

        return 1;
    }

    // Predis\Client::disconnect() has no declared return type; keep it compatible (no ": void").
    public function disconnect()
    {
        $this->calls[] = ['disconnect'];
    }
}

/**
 * Probe subclass that replaces every external seam main() reaches so the daemon loop can be
 * driven end-to-end with no broker/Redis/DB. Each public knob scripts one seam; recorders
 * (insertCallCount, purgedPids, rebuildCount, featureSetCallCount) expose what the loop did.
 */
class FastAnalysisMainProbe extends FastAnalysis
{
    public bool $dbCheckThrows = false;

    /** @var list<array<int, array<string, mixed>>> one project-list per do-while cycle */
    public array $lockBatches = [];

    /** @var list<AnalyzeResponse|\Throwable> one fetch outcome per _fetchMyMemoryFast() call */
    public array $fetchScript = [];

    /** @var list<int> one return code per _insertFastAnalysis() call */
    public array $insertResults = [];

    public ?\Throwable $insertThrows = null;

    public ?FeatureSet $featureSetStub = null;

    public ?ProjectMetadataDao $metadataDaoStub = null;

    public ?AMQHandler $newQueueHandlerStub = null;

    /** @var list<int> pids whose caches were purged on finalize */
    public array $purgedPids = [];

    public int $insertCallCount = 0;

    public int $featureSetCallCount = 0;

    public int $rebuildCount = 0;

    protected function _checkDatabaseConnection(): void
    {
        if ($this->dbCheckThrows) {
            $this->dbCheckThrows = false; // only the first cycle fails; the next reconnects

            throw new PDOException('simulated DB unavailable');
        }
    }

    protected function _getLockProjectForVolumeAnalysis(int $limit = 1): array
    {
        return array_shift($this->lockBatches) ?? [];
    }

    protected function _newFeatureSet(): FeatureSet
    {
        $this->featureSetCallCount++;

        return $this->featureSetStub;
    }

    protected function _getProjectMetadataDao(): ProjectMetadataDao
    {
        return $this->metadataDaoStub;
    }

    protected function _fetchMyMemoryFast(int $pid): AnalyzeResponse
    {
        $next = array_shift($this->fetchScript);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    protected function _insertFastAnalysis(
        ProjectStruct       $projectStruct,
        string              $projectFeaturesString,
        array               $equivalentWordMapping,
        FeatureSet          $featureSet,
        bool                $perform_Tms_Analysis = true,
        ?bool               $mt_evaluation = false,
        ?bool               $mt_qe_workflow_enabled = false,
        ?MTQEWorkflowParams $mt_qe_workflow_parameters = null,
        ?int                $mt_quality_value_in_editor = 85,
        ?array              $subfiltering_handlers = [],
        bool                $icu_enabled = false
    ): int {
        $this->insertCallCount++;
        if ($this->insertThrows !== null) {
            throw $this->insertThrows;
        }

        return array_shift($this->insertResults) ?? 0;
    }

    protected function _purgeProjectCaches(int $pid, string $password): void
    {
        $this->purgedPids[] = $pid;
    }

    // Only reached via _rebuildQueueHandler(); counting here counts rebuilds.
    protected function _newQueueHandler(): AMQHandler
    {
        $this->rebuildCount++;

        return $this->newQueueHandlerStub;
    }

    public function cleanShutDown(): void
    {
        // no-op: skip the real broker/Redis teardown in unit tests
    }
}
