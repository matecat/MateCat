<?php

namespace Matecat\Core\Workers\Analysis;

use Exception;
use LogicException;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\Jobs\MetadataStruct;
use Model\FilesStorage\AbstractFilesStorage;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\Projects\MetadataDao as ProjectMetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Projects\ProjectsMetadataMarshaller;
use PDO;
use PDOStatement;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Predis\Client as PredisClient;
use ReflectionClass;
use RuntimeException;
use Stomp\Exception\ConnectionException;
use Stomp\Transport\Message;
use Throwable;
use UnexpectedValueException;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\FastAnalysis;
use Utils\AsyncTasks\Workers\Analysis\MtQueue\MtQueueLoad;
use Utils\AsyncTasks\Workers\Analysis\MtQueue\MtQueueLoadReader;
use Utils\Constants\ProjectStatus;
use Utils\Registry\AppConfig;
use Utils\Constants\TranslationStatus;
use Utils\Engines\AbstractEngine;
use Utils\Engines\DeepL;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Engines\MyMemory;
use Utils\Engines\NONE;
use Utils\TaskRunner\Commons\AbstractDaemon;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\ContextList;
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
     * Build a FastAnalysis, or one of its probe subclasses, with no constructor and the given IDatabase seeded
     * into its private $db composition-root property.
     *
     * @template T of FastAnalysis
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function daemonWithDb(IDatabase $db, string $class = FastAnalysis::class): FastAnalysis
    {
        $daemon = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        $ref = new ReflectionClass(FastAnalysis::class);
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

    /**
     * Read a private/protected property of the daemon. Example: getProp($daemon, '_queueContextList')->list['P4'].
     */
    private function getProp(FastAnalysis $daemon, string $name): mixed
    {
        return (new ReflectionClass(FastAnalysis::class))->getProperty($name)->getValue($daemon);
    }

    /** @var list<string> config files written by the reload tests, deleted in tearDown */
    private array $configFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->configFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
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
     * A retry inserts the fast analysis rows again. A row the TM workers already analysed keeps its values: the
     * workers never update a DONE row again, so the values written here would be summed at completion.
     */
    #[Test]
    public function executeInsertKeepsTheValuesOfTheRowsTheTmWorkersAlreadyAnalysed(): void
    {
        $database = obtainTestDatabase();
        $pdo      = $database->getConnection();
        $idJob    = 99999993;
        $rows     = ['DONE' => 4711001, 'SKIPPED' => 4711002, 'UNDONE' => 4711003];
        $insert   = $pdo->prepare(
            'INSERT INTO segment_translations (id_segment, id_job, segment_hash, match_type, eq_word_count, standard_word_count, tm_analysis_status)'
            . " VALUES (?, ?, 'hash', '100%', 3, 4, ?)"
        );

        try {
            foreach ($rows as $status => $idSegment) {
                $insert->execute([$idSegment, $idJob, $status]);
            }

            $tuples = [];
            $binds  = [];
            foreach ($rows as $idSegment) {
                $tuples[] = '(?, ?, ?, ?, ?, ?)';
                array_push($binds, $idJob, $idSegment, 'hash', 'NO_MATCH', 10, 12);
            }
            $this->invoke($this->daemonWithDb($database), '_executeInsert', $tuples, $binds);

            $select = $pdo->prepare('SELECT tm_analysis_status, match_type, eq_word_count, standard_word_count FROM segment_translations WHERE id_job = ? ORDER BY id_segment');
            $select->execute([$idJob]);
            $this->assertEquals([
                ['tm_analysis_status' => 'DONE', 'match_type' => '100%', 'eq_word_count' => 3, 'standard_word_count' => 4],
                ['tm_analysis_status' => 'SKIPPED', 'match_type' => '100%', 'eq_word_count' => 3, 'standard_word_count' => 4],
                ['tm_analysis_status' => 'UNDONE', 'match_type' => 'NO_MATCH', 'eq_word_count' => 10, 'standard_word_count' => 12],
            ], $select->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            $pdo->prepare('DELETE FROM segment_translations WHERE id_job = ?')->execute([$idJob]);
        }
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
     * @return list<array{0: string, 1: string}> the [key, token] of each lock release, in order
     */
    private function releasedLocks(FastAnalysisFakeRedis $redis): array
    {
        $out = [];
        foreach ($redis->calls as $call) {
            if ($call[0] === 'eval') {
                $out[] = [$call[1], $call[2]];
            }
        }

        return $out;
    }

    /**
     * @param FastAnalysisFakeRedis $redis
     *
     * @return string the token the daemon stored when it took the processing lock
     */
    private function acquiredToken(FastAnalysisFakeRedis $redis): string
    {
        foreach ($redis->calls as $call) {
            if ($call[0] === 'set') {
                return $call[2];
            }
        }

        $this->fail('the processing lock was never acquired');
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
        $this->setProp($daemon, 'processingLockTokens', [7 => 'token-7']);

        $this->invoke($daemon, '_releaseFailedProject', 7, 'insert/publish failed', true);

        // countable failure: attempt counter incremented + given a TTL, no read-only get()
        $this->assertContains('incr', $this->redisCommands($redis));
        $this->assertContains('expire', $this->redisCommands($redis));
        $this->assertNotContains('get', $this->redisCommands($redis));
        // below the cap: only the processing lock is released, the attempt counter is kept
        $this->assertSame([], $this->delArgs($redis));
        $this->assertSame([['_fPid:7', 'token-7']], $this->releasedLocks($redis));
    }

    #[Test]
    public function releaseFailedProjectReleasesNoLockThisBatchDidNotTake(): void
    {
        // No token stored for the project: the lock, if any, belongs to another daemon and stays.
        $daemon = $this->daemonExpectingStatusChange(7, ProjectStatus::STATUS_NEW);
        $redis  = $this->seedQueueHandlerWithRedis($daemon);
        $redis->incrReturn = 2;

        $this->invoke($daemon, '_releaseFailedProject', 7, 'insert/publish failed', true);

        $this->assertSame([], $this->releasedLocks($redis));
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
        $this->setProp($daemon, 'processingLockTokens', [7 => 'token-7']);

        $this->invoke($daemon, '_releaseFailedProject', 7, 'insert/publish failed', true);

        // at the cap: both the attempt counter and the processing lock are cleared
        $this->assertSame([['_fAttempts:7']], $this->delArgs($redis));
        $this->assertSame([['_fPid:7', 'token-7']], $this->releasedLocks($redis));
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
        $this->setProp($daemon, 'processingLockTokens', [7 => 'token-7']);

        $this->invoke($daemon, '_releaseFailedProject', 7, 'broker unreachable', false);

        // infrastructure failure: counter is read-only (get), never incremented or TTL'd
        $this->assertNotContains('incr', $this->redisCommands($redis));
        $this->assertNotContains('expire', $this->redisCommands($redis));
        $this->assertSame([['_fPid:7', 'token-7']], $this->releasedLocks($redis));
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

    // ─── _fetchMyMemoryFast ───────────────────────────────────────────────

    /**
     * _fetchMyMemoryFast() opens with EnginesFactory::getInstance(1, ..., MyMemory::class), and the
     * test fixture has no engine id 1. Install one for the duration of the callback, defeating the
     * EngineDAO Redis cache on both sides so neither this test nor its neighbours see a stale row.
     *
     * @param callable(IDatabase):void $fn
     */
    private function withEngineOneAsMyMemory(callable $fn): void
    {
        $database   = obtainTestDatabase();
        $connection = $database->getConnection();

        $struct     = EngineStruct::getStruct();
        $struct->id = 1;
        $dao        = new EngineDAO($database);

        // id 1 is not part of tests/inc/unittest_matecat_local.sql, but an environment may still
        // carry it. Snapshot whatever is there and put it back verbatim afterwards, so this fixture
        // can never destroy a row it did not create.
        $existing = $connection->query("SELECT * FROM engines WHERE id = 1")->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($existing !== null) {
            $connection->exec("DELETE FROM engines WHERE id = 1");
        }

        $connection->exec(
            "INSERT INTO engines (id, name, type, description, base_url, translate_relative_url, class_load, others, extra_parameters, penalty, active) "
            . "VALUES (1, 'MyMemory', 'TM', 'fixture', 'https://api.mymemory.translated.net', 'get', 'MyMemory', '{}', '{}', 0, 1)"
        );
        $dao->destroyCache($struct);

        try {
            $fn($database);
        } finally {
            $connection->exec("DELETE FROM engines WHERE id = 1");

            if ($existing !== null) {
                $columns      = array_keys($existing);
                $placeholders = array_map(static fn(string $c): string => ':' . $c, $columns);
                $restore      = $connection->prepare(
                    'INSERT INTO engines (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $placeholders) . ')'
                );
                $restore->execute($existing);
            }

            $dao->destroyCache($struct);
        }
    }

    /**
     * @return AbstractFilesStorage&Stub
     */
    private function filesStorageThrowingOnFastAnalysisData(): AbstractFilesStorage
    {
        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('getFastAnalysisData')
            ->willThrowException(new \UnexpectedValueException('no data on disk'));

        return $fs;
    }

    /**
     * Disk read fails, the database fallback runs and finds nothing, so the project is reported as
     * fully pre-translated rather than as an error.
     */
    #[Test]
    public function fetchMyMemoryFastFallsBackToTheDatabaseAndReportsAFullyPreTranslatedProject(): void
    {
        $this->withEngineOneAsMyMemory(function (IDatabase $database): void {
            $daemon = $this->daemonWithDb($database);
            $this->setProp($daemon, 'files_storage', $this->filesStorageThrowingOnFastAnalysisData());

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('There is no analysis on that file, it is all pre-translated');
            $this->expectExceptionCode(FastAnalysis::ERR_NO_SEGMENTS);

            // No project rows exist for this pid, so the fallback query returns an empty set
            $this->invoke($daemon, '_fetchMyMemoryFast', 99_999_999);
        });
    }

    /**
     * Both the disk read and the database fallback fail: that is the "too large" classification.
     */
    #[Test]
    public function fetchMyMemoryFastReportsTooLargeWhenTheDatabaseFallbackAlsoFails(): void
    {
        $this->withEngineOneAsMyMemory(function (IDatabase $database): void {
            $ref    = new ReflectionClass(FastAnalysisPdoFailingProbe::class);
            $daemon = $ref->newInstanceWithoutConstructor();
            (new ReflectionClass(FastAnalysis::class))->getProperty('db')->setValue($daemon, $database);
            (new ReflectionClass(FastAnalysis::class))->getProperty('logger')
                ->setValue($daemon, $this->createStub(MatecatLogger::class));
            (new ReflectionClass(FastAnalysis::class))->getProperty('files_storage')
                ->setValue($daemon, $this->filesStorageThrowingOnFastAnalysisData());

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Error fetching data for project. Too large. Skip.');
            $this->expectExceptionCode(FastAnalysis::ERR_TOO_LARGE);

            (new ReflectionClass(FastAnalysis::class))
                ->getMethod('_fetchMyMemoryFast')->invoke($daemon, 12_345);
        });
    }

    #[Test]
    public function setTotalThrowsWhenThePidIsMissing(): void
    {
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot set a Total without a Queue ID.');

        $this->invoke($daemon, '_setTotal', ['total' => 5, 'pid' => 0, 'queueInfo' => null]);
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

    /**
     * Instance ids the pick query must filter on, 0 included.
     *
     * @return array<string, array{int}>
     */
    public static function instanceIdProvider(): array
    {
        return [
            'instance 0' => [0],
            'instance 3' => [3],
        ];
    }

    #[Test]
    #[DataProvider('instanceIdProvider')]
    public function lockProjectPicksOnlyTheProjectsOfItsOwnInstance(int $instanceId): void
    {
        // Each instance has its own Redis database, so its processing locks are invisible to the others.
        // A daemon that picked another instance's projects could analyse them twice; instance 0 included.
        $query  = null;
        $params = null;

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function (array $bound) use (&$params) {
            $params = $bound;

            return true;
        });
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$query, $stmt) {
            $query = $sql;

            return $stmt;
        });

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);
        $db->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $originalInstanceId    = AppConfig::$INSTANCE_ID;
        AppConfig::$INSTANCE_ID = $instanceId;
        try {
            $this->invoke($this->daemonWithDb($db), '_lockNextProjectToAnalyze');
        } finally {
            AppConfig::$INSTANCE_ID = $originalInstanceId;
        }

        $this->assertStringContainsString('AND instance_id = :instance_id', (string)$query);
        $this->assertSame($instanceId, $params['instance_id'] ?? null);
    }

    #[Test]
    public function lockProjectKeepsProjectInBatchWhenBusyUpdateSucceeds(): void
    {
        // Happy path: lock acquired + BUSY set → the project stays in the returned batch and its
        // _fPid processing lock is NOT released.
        [$daemon, $redis] = $this->daemonLockingOneProject(fn () => 1);

        $result = $this->invoke($daemon, '_lockNextProjectToAnalyze');

        $this->assertCount(1, $result);
        $this->assertSame('42', array_values($result)[0]['id']);
        $this->assertSame([], $this->releasedLocks($redis)); // lock kept
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

        $result = $this->invoke($daemon, '_lockNextProjectToAnalyze');

        $this->assertSame([], $result, 'the un-BUSYable project must not be returned to main()');
        $this->assertSame(
            [['_fPid:42', $this->acquiredToken($redis)]],
            $this->releasedLocks($redis),
            'the processing lock must be released with the token that took it'
        );
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

        $result = $this->invoke($daemon, '_lockNextProjectToAnalyze');

        $this->assertSame([], $result);
        $this->assertSame([['_fPid:42', $this->acquiredToken($redis)]], $this->releasedLocks($redis));
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
        $logger->method('info')->willReturnCallback($capture('info'));
        $logger->method('warning')->willReturnCallback($capture('warning'));
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

        $this->invoke($daemon, '_lockNextProjectToAnalyze');

        $this->assertContains(['set', '_fPid:42', $this->acquiredToken($redis), 'EX', 86400, 'NX'], $redis->calls);
        foreach ($redis->calls as $call) {
            $this->assertNotSame('expire', $call[0], 'lock acquisition must not use a separate expire');
            $this->assertNotSame('setnx', $call[0], 'lock acquisition must be an atomic SET, not setnx');
        }
    }

    #[Test]
    public function lockProjectForgetsTheTokensOfThePreviousBatch(): void
    {
        [$daemon, $redis] = $this->daemonLockingOneProject(fn () => 1);
        $this->setProp($daemon, 'processingLockTokens', [7 => 'token-of-the-previous-batch']);

        $this->invoke($daemon, '_lockNextProjectToAnalyze');

        $tokens = (new ReflectionClass(FastAnalysis::class))->getProperty('processingLockTokens')->getValue($daemon);
        $this->assertSame([42 => $this->acquiredToken($redis)], $tokens);
    }

    #[Test]
    public function lockProjectSkipsProjectWhenLockAlreadyHeld(): void
    {
        // NX fails (another daemon already holds the lock) → SET returns null → the project is
        // dropped from the batch and nothing is released (we never acquired it).
        [$daemon, $redis] = $this->daemonLockingOneProject(fn () => 1);
        $redis->setReturn = null; // NX failed

        $result = $this->invoke($daemon, '_lockNextProjectToAnalyze');

        $this->assertSame([], $result);
        $this->assertSame([], $this->releasedLocks($redis));
    }

    /**
     * Seed the lock picker so its master-routed SELECT returns exactly one NEW project, backed by
     * a ProjectDao whose changeProjectStatusIfNotDone() runs $onBusyUpdate (return a row count, or throw).
     * The daemon records in its `events` the transaction, each prepared SQL and its bound params, in order.
     *
     * @param callable                            $onBusyUpdate invoked as the BUSY write inside _updateProject
     * @param array<string, array<string, mixed>> $queues       context list settings; none leaves the list unset
     *
     * @return array{0: FastAnalysisMtQueueProbe, 1: FastAnalysisFakeRedis}
     */
    private function daemonLockingOneProject(callable $onBusyUpdate, array $queues = []): array
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

        // The stubs below record into the daemon, which is built last: they capture it by reference
        $daemon = null;

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function (array $params) use (&$daemon): bool {
            $daemon->events[] = ['execute', $params];

            return true;
        });
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$daemon, $stmt): PDOStatement {
            $daemon->events[] = ['prepare', $sql];

            return $stmt;
        });

        $project                  = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_NEW; // != DONE → the BUSY update runs

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($project);
        $projectDao->method('changeProjectStatusIfNotDone')->willReturnCallback($onBusyUpdate);

        $injected = $this->createStub(IDatabase::class);
        $injected->method('getConnection')->willReturn($pdo);
        // pass-through: both the picker SELECT and _updateProject route through transaction()
        $injected->method('transaction')->willReturnCallback(function (callable $cb) use (&$daemon) {
            $daemon->events[] = ['transaction'];

            return $cb();
        });

        $daemon = $this->daemonWithDb($injected, FastAnalysisMtQueueProbe::class);
        if ($queues !== []) {
            (new ReflectionClass(FastAnalysis::class))->getProperty('_queueContextList')->setValue($daemon, ContextList::get($queues));
        }
        $this->setProp($daemon, 'projectDao', $projectDao);
        $redis            = $this->seedQueueHandlerWithRedis($daemon);
        $redis->setReturn = 'OK'; // lock acquired (atomic SET NX EX)

        return [$daemon, $redis];
    }

    /**
     * The SQL of the first statement the picker prepared, and the params it bound.
     *
     * The picker SELECT is the first statement: the BUSY update of each picked project runs after it.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function pickQuery(FastAnalysisMtQueueProbe $daemon): array
    {
        $sql    = null;
        $params = null;
        foreach ($daemon->events as $event) {
            if ($event[0] === 'prepare' && $sql === null) {
                $sql = $event[1];
            } elseif ($event[0] === 'execute' && $params === null) {
                $params = $event[1];
            }
        }

        return [(string)$sql, $params ?? []];
    }

    /**
     * Load of an MT queue built from the given settings.
     *
     * @param array<string, mixed> $settings context settings of the queue
     * @param list<int>            $livePids projects the queue is analysing now
     */
    private static function mtLoad(string $key, array $settings, ?int $segmentLoad, int $workers = 30, array $livePids = []): MtQueueLoad
    {
        return new MtQueueLoad($key, Context::buildFromArray($settings), $segmentLoad, count($livePids), $workers, $livePids);
    }

    /**
     * Rows: context list settings, the loads _readMtQueueLoads() returns (null: it must not be called),
     * whether the pick query holds MT projects back.
     *
     * @return array<string, array{array<string, array<string, mixed>>, list<MtQueueLoad>|null, bool}>
     */
    public static function mtQueueHoldProvider(): array
    {
        $p4 = ['queue_name' => 'analysis_queue_P4', 'max_executors' => 30, 'max_queued_segments' => 25000];
        $p5 = ['queue_name' => 'analysis_queue_P5', 'max_executors' => 10, 'max_queued_segments' => 25000];
        $p1 = ['queue_name' => 'analysis_queue_P1', 'max_executors' => 1];
        $p4NoCaps = ['queue_name' => 'analysis_queue_P4', 'max_executors' => 30];
        $p4Drain  = ['queue_name' => 'analysis_queue_P4', 'max_executors' => 30, 'accept_new_projects' => false];
        $p5Drain  = ['queue_name' => 'analysis_queue_P5', 'max_executors' => 10, 'accept_new_projects' => false];
        $p4ProjectCap = ['queue_name' => 'analysis_queue_P4', 'max_executors' => 30, 'max_queued_projects' => 80];

        return [
            'caps off, every queue accepts'  => [['P1' => $p1, 'P4' => $p4NoCaps], null, false],
            // picked, a Lara project with no MT queue would fail every attempt within seconds and be parked
            'no MT queue configured'         => [['P1' => $p1], null, true],
            'caps on, P4 open'               => [['P1' => $p1, 'P4' => $p4], [self::mtLoad('P4', $p4, 100)], false],
            // a queue that refuses new projects is a limit even with every cap off
            'caps off, P4 and P5 drained'    => [
                ['P1' => $p1, 'P4' => $p4Drain, 'P5' => $p5Drain],
                [self::mtLoad('P4', $p4Drain, 0), self::mtLoad('P5', $p5Drain, 0)],
                true,
            ],
            // the project cap alone turns the load read on
            'project cap only, P4 open'      => [['P1' => $p1, 'P4' => $p4ProjectCap], [self::mtLoad('P4', $p4ProjectCap, 0)], false],
            'P4 and P5 full'                 => [
                ['P1' => $p1, 'P4' => $p4, 'P5' => $p5],
                [self::mtLoad('P4', $p4, 25000), self::mtLoad('P5', $p5, 30000)],
                true,
            ],
            'P5 absent, P4 full'             => [['P1' => $p1, 'P4' => $p4], [self::mtLoad('P4', $p4, 25000)], true],
            'P4 full, P5 open with no worker' => [
                ['P1' => $p1, 'P4' => $p4, 'P5' => $p5],
                [self::mtLoad('P4', $p4, 25000), self::mtLoad('P5', $p5, segmentLoad: 0, workers: 0)],
                true,
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $queues
     * @param list<MtQueueLoad>|null              $loads
     */
    #[Test]
    #[DataProvider('mtQueueHoldProvider')]
    public function lockProjectHoldsMtProjectsOnlyWhenNoMtQueueCanTakeThem(array $queues, ?array $loads, bool $holds): void
    {
        [$daemon] = $this->daemonLockingOneProject(fn () => 1, $queues);
        $daemon->loadsScript = $loads === null ? null : [$loads];

        $this->invoke($daemon, '_lockNextProjectToAnalyze');

        [$sql, $params] = self::pickQuery($daemon);
        $mtClassParams  = array_filter($params, fn (string $name): bool => str_starts_with($name, 'mt_class_'), ARRAY_FILTER_USE_KEY);
        $loadReads      = count(array_keys(array_column($daemon->events, 0), 'loads', true));
        $this->assertSame($loads === null ? 0 : 1, $loadReads);
        if ($holds) {
            $this->assertStringContainsString('NOT EXISTS', $sql);
            $this->assertStringContainsString('engines', $sql);
            $this->assertStringContainsString(':mt_class_0', $sql);
            $this->assertStringContainsString(':mt_class_1', $sql);
            $this->assertEqualsCanonicalizing(FastAnalysis::mtQueueEngineClassLoads(), array_values($mtClassParams));
        } else {
            $this->assertStringNotContainsString('NOT EXISTS', $sql);
            $this->assertSame([], $mtClassParams);
        }
    }

    #[Test]
    public function lockProjectSelectsTheProjectCreateDate(): void
    {
        [$daemon] = $this->daemonLockingOneProject(fn () => 1);

        $this->invoke($daemon, '_lockNextProjectToAnalyze');

        // the routing log of a later task reads the project age
        $this->assertStringContainsString('p.create_date', self::pickQuery($daemon)[0]);
    }

    /**
     * One project per pick: the MT queue hold check runs before each one, so a queue goes at most one project
     * past its cap.
     */
    #[Test]
    public function lockNextProjectToAnalyzeTakesOneProjectAtATime(): void
    {
        [$daemon] = $this->daemonLockingOneProject(fn () => 1);

        $this->invoke($daemon, '_lockNextProjectToAnalyze');

        $this->assertMatchesRegularExpression('/ORDER BY id LIMIT 1\s*$/', self::pickQuery($daemon)[0]);
    }

    #[Test]
    public function lockProjectReadsTheMtQueueLoadsBeforeItsTransaction(): void
    {
        $p4 = ['queue_name' => 'analysis_queue_P4', 'max_executors' => 30, 'max_queued_segments' => 25000];
        [$daemon] = $this->daemonLockingOneProject(fn () => 1, ['P4' => $p4]);
        $daemon->loadsScript = [[self::mtLoad('P4', $p4, 25000)]];

        $this->invoke($daemon, '_lockNextProjectToAnalyze');

        $this->assertSame(['loads', 'transaction', 'prepare'], array_slice(array_column($daemon->events, 0), 0, 3));
    }

    #[Test]
    public function mtQueueEngineClassLoadsNamesEachEngineAsTheEnginesTableMayStoreIt(): void
    {
        $this->assertEqualsCanonicalizing(['Utils\Engines\Lara', 'Lara'], FastAnalysis::mtQueueEngineClassLoads());
    }

    /**
     * The SQL hold and the routing must agree: each name the pick query skips loads an engine routed to the MT queues.
     */
    #[Test]
    public function eachMtQueueEngineClassLoadLoadsAnEngineRoutedToTheMtQueues(): void
    {
        $daemon = $this->daemonWithDb($this->createStub(IDatabase::class));

        foreach (FastAnalysis::mtQueueEngineClassLoads() as $classLoad) {
            $className = EnginesFactory::getFullyQualifiedClassName($classLoad);
            $engine    = (new ReflectionClass($className))->newInstanceWithoutConstructor();

            $this->assertTrue($this->invoke($daemon, '_usesMtQueue', $engine), "$classLoad must route to the MT queues");
        }
    }

    #[Test]
    public function mtQueuesHoldLogsTheFullStateOncePerMinuteAndTheReopeningOnce(): void
    {
        $p4 = ['queue_name' => 'analysis_queue_P4', 'max_executors' => 30, 'max_queued_segments' => 25000];
        $logged = [];
        [$daemon] = $this->mtQueueDaemon(['P4' => $p4], [], $logged);
        $full = [self::mtLoad('P4', $p4, 25000)];
        $open = [self::mtLoad('P4', $p4, 10)];
        $daemon->loadsScript = [$open, $full, $full, $full, $open];

        // At each time: what _mtQueuesHold() returned, and the messages it logged
        $observed = [];
        foreach ([990, 1000, 1030, 1060, 1070] as $now) {
            $daemon->now    = $now;
            $before         = count($logged);
            $observed[$now] = [$this->invoke($daemon, '_mtQueuesHold'), array_column(array_slice($logged, $before), 'message')];
        }

        $this->assertSame([
            990  => [false, []], // open and never held: nothing to report
            1000 => [true, ['mt_queues_full']],
            1030 => [true, []],
            1060 => [true, ['mt_queues_full']], // exactly 60 s after the last log
            1070 => [false, ['mt_queues_open']],
        ], $observed);
        $this->assertSame(
            ['queues' => ['P4' => $full[0]->toLogArray()]],
            self::loggedContexts($logged, 'mt_queues_full')[0]
        );
        $this->assertSame(
            ['queues' => ['P4' => $open[0]->toLogArray()]],
            self::loggedContexts($logged, 'mt_queues_open')[0]
        );
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
        $fa->getProperty('_queueContextList')->setValue($probe, ContextList::get([]));
        // A missing config file, its hash ('') already seen: main() never reloads and keeps the list set above
        $fa->getProperty('_configFile')->setValue($probe, sys_get_temp_dir() . '/fast-analysis-no-config.ini');
        $fa->getProperty('_contextIndex')->setValue($probe, null);
        (new ReflectionClass(AbstractDaemon::class))->getProperty('configHash')->setValue($probe, '');

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
        // PARK keeps its lock; RESET and both retries release theirs with the token that took it.
        $this->assertSame(
            [['_fPid:12', 'token-12'], ['_fPid:13', 'token-13'], ['_fPid:14', 'token-14']],
            $this->releasedLocks($redis)
        );
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
        $this->assertSame([['_fPid:1606', 'token-1606']], $this->releasedLocks($redis));
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
        $this->assertSame([['_fPid:1606', 'token-1606']], $this->releasedLocks($redis), 'the inert processing lock is dropped');
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
        $this->assertSame([['_fPid:1606', 'token-1606']], $this->releasedLocks($redis));
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

    // ---------------------------------------------------------------------------------------
    // resolveJobAnalysisMetadata(): the per-job settings the publish loop puts on each element
    //
    // The MT application threshold became editable after project creation, so the analysis can no
    // longer read it once for the whole project. These tests pin the precedence, which is the part
    // that decides what the TM workers are actually told.
    //
    // The DAO and the resolver are both constructed inline from db(), so there is nothing to inject:
    // these drive the real test connection with real job_metadata rows, the same way the engine
    // suites do for the same reason (@see LaraEngineTest).
    // ---------------------------------------------------------------------------------------

    private const int JOB_METADATA_TEST_JOB_ID = 990111;

    /**
     * @param array<string, string> $rows
     */
    private function seedJobMetadata(string $password, array $rows): void
    {
        (new JobsMetadataDao(obtainTestDatabase()))->bulkSet(self::JOB_METADATA_TEST_JOB_ID, $password, $rows);
    }

    private function cleanJobMetadata(string $password): void
    {
        $dao = new JobsMetadataDao(obtainTestDatabase());
        foreach ($dao->getByJobIdAndPassword(self::JOB_METADATA_TEST_JOB_ID, $password) as $row) {
            $dao->delete(self::JOB_METADATA_TEST_JOB_ID, $password, $row->key);
        }
    }

    /**
     * @param array<string, string> $rows
     * @return array<string, mixed>
     */
    private function resolveWithSeededRows(array $rows, ?int $projectMtQualityValue, ?int $idProject = null): array
    {
        // The DAOs are asked for a 10-minute TTL; without this they would open a Redis connection,
        // and setCacheTTL() is a no-op under the flag so both reads stay pure PDO.
        $previousSkipCache = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        $password = 'fapw_' . bin2hex(random_bytes(4));

        try {
            $this->seedJobMetadata($password, $rows);

            /** @var array<string, mixed> $resolved */
            $resolved = $this->invoke(
                $this->daemonWithDb(obtainTestDatabase()),
                'resolveJobAnalysisMetadata',
                self::JOB_METADATA_TEST_JOB_ID,
                $password,
                $idProject,
                $projectMtQualityValue
            );

            return $resolved;
        } finally {
            $this->cleanJobMetadata($password);
            AppConfig::$SKIP_SQL_CACHE = $previousSkipCache;
        }
    }

    #[Test]
    public function jobThresholdWinsOverTheProjectOne(): void
    {
        $resolved = $this->resolveWithSeededRows(
            [JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => '95'],
            85
        );

        self::assertSame(95, $resolved['mt_quality_value_in_editor']);
    }

    #[Test]
    public function aStoredZeroThresholdWinsInsteadOfFallingThrough(): void
    {
        // The one case `?:` would get wrong: 0 is falsy but it is a real value the owner chose, and
        // silently analysing at the project value instead would misprice the job.
        $resolved = $this->resolveWithSeededRows(
            [JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => '0'],
            85
        );

        self::assertSame(0, $resolved['mt_quality_value_in_editor']);
    }

    #[Test]
    public function withoutAJobRowTheProjectThresholdIsUsed(): void
    {
        // Every project created before the settings moved to the job takes this path.
        $resolved = $this->resolveWithSeededRows(
            [JobsMetadataMarshaller::TM_PRIORITIZATION->value => '1'],
            85
        );

        self::assertSame(85, $resolved['mt_quality_value_in_editor']);
    }

    #[Test]
    public function withNeitherScopeSetTheQueueElementCarriesFalse(): void
    {
        $resolved = $this->resolveWithSeededRows(
            [JobsMetadataMarshaller::TM_PRIORITIZATION->value => '1'],
            null
        );

        self::assertFalse($resolved['mt_quality_value_in_editor']);
    }

    /**
     * The regression this pins: with the resolver's project leg switched off, a job read that missed
     * fell straight through to the hard-coded default. That read runs in the publish loop, outside
     * any transaction, so ProxySQL can answer it from a replica that has not caught up with a
     * project created seconds earlier, and the analysis then priced MT at a threshold the owner
     * never chose.
     */
    #[Test]
    public function theProjectRowIsConsultedWhenTheJobHasNoRow(): void
    {
        $projectId = 990112;
        $projectMetadataDao = new ProjectMetadataDao(obtainTestDatabase());
        $projectMetadataDao->set($projectId, JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value, '70');

        try {
            $resolved = $this->resolveWithSeededRows(
                [JobsMetadataMarshaller::TM_PRIORITIZATION->value => '1'],
                JobsMetadataMarshaller::DEFAULT_MT_QUALITY_VALUE,
                $projectId
            );

            self::assertSame(70, $resolved['mt_quality_value_in_editor']);
        } finally {
            $projectMetadataDao->delete($projectId, JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value);
        }
    }

    /**
     * The job row still wins over the project row, which is what makes the owner's override take
     * effect on the very next analysis.
     */
    #[Test]
    public function theJobRowWinsOverTheProjectRow(): void
    {
        $projectId = 990113;
        $projectMetadataDao = new ProjectMetadataDao(obtainTestDatabase());
        $projectMetadataDao->set($projectId, JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value, '70');

        try {
            $resolved = $this->resolveWithSeededRows(
                [JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value => '95'],
                JobsMetadataMarshaller::DEFAULT_MT_QUALITY_VALUE,
                $projectId
            );

            self::assertSame(95, $resolved['mt_quality_value_in_editor']);
        } finally {
            $projectMetadataDao->delete($projectId, JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value);
        }
    }

    /**
     * With neither row readable, the value main() materialised is the last resort. It is last rather
     * than second because it is not the fresher of the two: main() reads it through
     * setCacheTTL(3600), so it can be answered from Redis without touching the master.
     */
    #[Test]
    public function theMaterialisedProjectValueIsTheLastResort(): void
    {
        $resolved = $this->resolveWithSeededRows(
            [JobsMetadataMarshaller::TM_PRIORITIZATION->value => '1'],
            80,
            990114
        );

        self::assertSame(80, $resolved['mt_quality_value_in_editor']);
    }

    #[Test]
    public function theOtherThreeKeysComeBackAsRawRowsTheLoopReadsValueFrom(): void
    {
        // The publish loop does `$public_tm_penalty->value`, so these must stay MetadataStructs
        // rather than being reduced like the threshold is.
        $resolved = $this->resolveWithSeededRows([
            JobsMetadataMarshaller::TM_PRIORITIZATION->value => '1',
            JobsMetadataMarshaller::DIALECT_STRICT->value => '1',
            JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value => '7',
        ], 85);

        self::assertInstanceOf(MetadataStruct::class, $resolved['tm_prioritization']);
        self::assertInstanceOf(MetadataStruct::class, $resolved['dialect_strict']);
        self::assertInstanceOf(MetadataStruct::class, $resolved['public_tm_penalty']);
        self::assertSame('7', $resolved['public_tm_penalty']->value);
    }

    #[Test]
    public function absentKeysComeBackAsNullSoTheLoopSkipsThem(): void
    {
        $resolved = $this->resolveWithSeededRows([], 85);

        self::assertNull($resolved['tm_prioritization']);
        self::assertNull($resolved['dialect_strict']);
        self::assertNull($resolved['public_tm_penalty']);
    }

    #[Test]
    public function everyKeyThePublishLoopReadsIsPresentInTheResult(): void
    {
        // The loop indexes the returned array directly, so a missing key would be an undefined-index
        // notice per segment rather than a visible failure.
        $resolved = $this->resolveWithSeededRows([], null);

        self::assertSame(
            ['tm_prioritization', 'dialect_strict', 'public_tm_penalty', 'mt_quality_value_in_editor'],
            array_keys($resolved)
        );
    }

    /**
     * The daemon writes the projects row through the raw query builder rather than through the DAO,
     * so nothing evicts the row's cached reads for it. Both call sites below do it by hand, and both
     * used to name several key families one by one; they go through the DAO's single door now.
     */
    private function daemonWithProjectDao(IDatabase $db, ProjectDao $projectDao): FastAnalysis
    {
        $daemon = $this->daemonWithDb($db);
        // getProjectDao() memoises, so seeding the property is enough to hand both call sites the
        // same double without a partial mock over a private method.
        $this->setProp($daemon, 'projectDao', $projectDao);

        return $daemon;
    }

    #[Test]
    public function purgeProjectCachesEvictsTheProjectThroughTheDaoDoorWithItsPassword(): void
    {
        $projectDao = $this->createMock(ProjectDao::class);
        $projectDao->expects($this->once())
            ->method('destroyCache')
            ->with(4242, 'proj-pw');

        $daemon = $this->daemonWithProjectDao($this->stubDatabase(), $projectDao);

        $this->invoke($daemon, '_purgeProjectCaches', 4242, 'proj-pw');
    }

    #[Test]
    public function insertFastAnalysisEvictsTheProjectRowAfterWritingItOutsideTheDao(): void
    {
        $projectDao = $this->createMock(ProjectDao::class);
        // Only the id: the word-count write does not touch the password, and the DAO reads the
        // current one itself.
        $projectDao->expects($this->once())
            ->method('destroyCache')
            ->with(77);

        $daemon = $this->daemonWithProjectDao($this->stubDatabase(), $projectDao);
        // No segments: the insert loop and its batched flush have their own cases, and this one is
        // about the eviction that follows the projects write.
        $this->setProp($daemon, 'segments', []);
        $this->setProp($daemon, 'actual_project_row', ['id_mt_engine' => 0]);

        $project = new ProjectStruct();
        $project->id = 77;

        try {
            $this->invoke(
                $daemon,
                '_insertFastAnalysis',
                $project,
                '',
                [],
                $this->createStub(FeatureSet::class),
                false
            );
        } catch (\Throwable $e) {
            // The engine lookup that follows the eviction needs a live engine row; reaching it means
            // the eviction already ran, which is what this case is about.
        }
    }

    /**
     * The five pre-translation options as stored in project metadata (string values, as the
     * metadata table holds them).
     *
     * @return array<string, string>
     */
    private function pretranslateMetadata(): array
    {
        return [
            ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value => '1',
            ProjectsMetadataMarshaller::PRE_TRANSLATE_101_STATUS->value => TranslationStatus::STATUS_APPROVED2,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_101_LOCK->value => '0',
            ProjectsMetadataMarshaller::PRE_TRANSLATE_100_STATUS->value => TranslationStatus::STATUS_APPROVED,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_100_LOCK->value => '1',
        ];
    }

    #[Test]
    public function main_threadsThePretranslateOptionsFromProjectMetadata(): void
    {
        [$probe, $redis] = $this->wireMainProbe();
        $probe->metadataDaoStub  = $this->makeMetadataDaoStub($this->pretranslateMetadata());
        $redis->sismemberReturns = [true, false];
        $probe->lockBatches      = [[$this->projectRow(1607)]];
        $probe->fetchScript      = [new AnalyzeResponse(['data' => []])];
        $probe->insertResults    = [0];

        $this->runMain($probe);

        $this->assertSame([
            [
                'pretranslate_101' => true,
                'pretranslate_101_status' => TranslationStatus::STATUS_APPROVED2,
                'pretranslate_101_lock' => false,
                'pretranslate_100_status' => TranslationStatus::STATUS_APPROVED,
                'pretranslate_100_lock' => true,
            ],
        ], $probe->pretranslateArgs);
    }

    #[Test]
    public function main_leavesThePretranslateOptionsNullWhenTheProjectHasNoSuchMetadata(): void
    {
        [$probe, $redis] = $this->wireMainProbe();
        $redis->sismemberReturns = [true, false];
        $probe->lockBatches      = [[$this->projectRow(1608)]];
        $probe->fetchScript      = [new AnalyzeResponse(['data' => []])];
        $probe->insertResults    = [0];

        $this->runMain($probe);

        $this->assertSame([
            [
                'pretranslate_101' => null,
                'pretranslate_101_status' => null,
                'pretranslate_101_lock' => null,
                'pretranslate_100_status' => null,
                'pretranslate_100_lock' => null,
            ],
        ], $probe->pretranslateArgs);
    }

    /**
     * Run the real _insertFastAnalysis() over one segment for one job and return the params of
     * every message it publishes. The engine lookup reads engine 0 (NONE) from the test database;
     * the insert, the queue-total bookkeeping and the queue routing are stubbed by the probe.
     *
     * @param array<int, mixed>  $pretranslateArgs
     * @param int                $wordCount        the fast analysis word count of the segment; 0 skips it
     * @param MatecatLogger|null $logger           where the daemon logs; a silent stub when null
     * @param array<string, array<string, mixed>> $queues context list settings; with a P4 entry the probe routes to P4
     * @param FastAnalysisFakeRedis|null $redis    the Redis the daemon writes to, to inspect it afterwards
     *
     * @return list<array<string, mixed>>
     */
    private function publishedParams(
        array $pretranslateArgs,
        int $wordCount = 2,
        ?MatecatLogger $logger = null,
        array $queues = [],
        ?FastAnalysisFakeRedis $redis = null,
        ?Throwable $publishFailure = null
    ): array {
        $ref    = new ReflectionClass(FastAnalysisPublishProbe::class);
        $daemon = $ref->newInstanceWithoutConstructor();
        $this->setProp($daemon, 'db', obtainTestDatabase());
        $this->setProp($daemon, 'logger', $logger ?? $this->createStub(MatecatLogger::class));
        $this->setProp($daemon, 'projectDao', $this->createStub(ProjectDao::class));

        $published = [];
        $redis   ??= new FastAnalysisFakeRedis();
        $amq       = $this->createStub(AMQHandler::class);
        $amq->method('getRedisClient')->willReturn($redis);
        $amq->method('publishToQueues')->willReturnCallback(
            function (string $queue, Message $message) use (&$published, $redis, $publishFailure): bool {
                // In the Redis log too, so a test can check what was written before and after each publish
                $redis->calls[] = ['publish', $queue];
                if ($publishFailure !== null) {
                    throw $publishFailure;
                }
                $published[] = json_decode($message->getBody(), true)['params'];

                return true;
            }
        );
        $this->setProp($daemon, 'queueHandler', $amq);
        $this->setProp($daemon, '_queueContextList', ContextList::get($queues));

        $this->setProp($daemon, 'actual_project_row', [
            'id_mt_engine' => 0,
            'pretranslate_100' => 1,
            'tm_keys' => '[]',
            'id_tms' => 1,
            'only_private_tm' => 0,
        ]);
        $this->setProp($daemon, 'segments', [[
            'id' => 4711,
            'jsid' => '4711-99999991:pw1',
            'segment' => 'Hello world',
            'segment_hash' => 'abc',
            'raw_word_count' => 2,
            'match_type' => 'NO_MATCH',
            'wc' => $wordCount,
            'source' => 'en-US',
            'target' => '99999991:it-IT',
            'payable_rates' => ['99999991' => '{"NO_MATCH":100}'],
        ]]);

        $project           = new ProjectStruct();
        $project->id       = 99999990;
        $project->password = 'ppw';

        $this->invoke(
            $daemon,
            '_insertFastAnalysis',
            $project,
            '',
            ['NO_MATCH' => 100],
            $this->createStub(FeatureSet::class),
            true,
            false,
            false,
            null,
            85,
            [],
            false,
            ...$pretranslateArgs
        );

        return $published;
    }

    #[Test]
    public function insertFastAnalysisPublishesThePretranslateOptionsOnTheQueueElement(): void
    {
        $published = $this->publishedParams([true, TranslationStatus::STATUS_APPROVED2, false, TranslationStatus::STATUS_APPROVED, true]);

        $this->assertCount(1, $published);
        $params = $published[0];
        $this->assertSame(1, $params['pretranslate_100'], 'pretranslate_100 still comes from the project row');
        $this->assertTrue($params[ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value]);
        $this->assertSame(TranslationStatus::STATUS_APPROVED2, $params[ProjectsMetadataMarshaller::PRE_TRANSLATE_101_STATUS->value]);
        $this->assertFalse($params[ProjectsMetadataMarshaller::PRE_TRANSLATE_101_LOCK->value]);
        $this->assertSame(TranslationStatus::STATUS_APPROVED, $params[ProjectsMetadataMarshaller::PRE_TRANSLATE_100_STATUS->value]);
        $this->assertTrue($params[ProjectsMetadataMarshaller::PRE_TRANSLATE_100_LOCK->value]);
    }

    #[Test]
    public function insertFastAnalysisPublishesNullPretranslateOptionsWhenTheProjectHasNone(): void
    {
        $published = $this->publishedParams([]);

        $this->assertCount(1, $published);
        $params = $published[0];
        foreach ([
            ProjectsMetadataMarshaller::PRE_TRANSLATE_101,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_101_STATUS,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_101_LOCK,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_100_STATUS,
            ProjectsMetadataMarshaller::PRE_TRANSLATE_100_LOCK,
        ] as $key) {
            $this->assertArrayHasKey($key->value, $params);
            $this->assertNull($params[$key->value], $key->value . ' must stay null for the worker to default it');
        }
    }

    #[Test]
    public function insertFastAnalysisLogsAndDropsASegmentWithNoWordsToAnalyze(): void
    {
        // A segment whose fast analysis word count is 0 is not sent to the TM analysis queue:
        // the daemon logs it as skipped and publishes nothing for it.
        $logged    = [];
        $published = $this->publishedParams([], 0, $this->capturingStructuredLogger($logged));

        $this->assertSame([], $published);
        $skipped = array_filter($logged, fn(array $entry): bool => $entry['level'] === 'debug'
            && str_starts_with((string)$entry['message'], 'Skipped Fast Segment: '));
        $this->assertCount(1, $skipped);
    }

    #[Test]
    public function getSegmentsForFastVolumeAnalysisLogsAndRethrowsWhenTheQueryFails(): void
    {
        // The query error is logged at error level, then re-thrown for main() to classify.
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('Segments query failed'));

        $injected = $this->createStub(IDatabase::class);
        $injected->method('getConnection')->willReturn($pdo);

        $logged = [];
        $daemon = $this->daemonWithDb($injected);
        $this->setProp($daemon, 'logger', $this->capturingStructuredLogger($logged));

        try {
            $this->invoke($daemon, '_getSegmentsForFastVolumeAnalysis', 7);
            $this->fail('The PDOException must reach the caller');
        } catch (PDOException $e) {
            $this->assertSame('Segments query failed', $e->getMessage());
        }

        $this->assertSame([['level' => 'error', 'message' => 'Segments query failed', 'context' => []]], $logged);
    }

    private function stubDatabase(): IDatabase
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->queryString = '';
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        return $db;
    }

    // ─── _getQueueAddressesByPriority ────────────────────────────────────

    /**
     * Lara is the only adaptive engine left, so it alone takes the P4 queue; any other MT engine still goes
     * to P3; NONE goes to P1, or to P2 from 10000 queued segments. Each case installs a real engine row, builds
     * its engine as main() does, and removes the row afterwards, evicting the EngineDAO cache on both sides so
     * no neighbouring test reads it.
     */
    #[Test]
    #[DataProvider('queuePriorityByEngineProvider')]
    public function getQueueAddressesByPriorityRoutesTheEngineToItsQueue(string $classLoad, int $queueLen, string $expectedQueue): void
    {
        $database   = obtainTestDatabase();
        $connection = $database->getConnection();
        $connection->exec(
            "INSERT INTO engines (name, type, description, base_url, translate_relative_url, class_load, others, extra_parameters, penalty, active) "
            . "VALUES ('queue priority fixture', 'MT', 'fixture', 'https://example.invalid', 'translate', " . $connection->quote($classLoad) . ", '{}', '{}', 0, 1)"
        );
        $struct     = EngineStruct::getStruct();
        $struct->id = (int)$connection->lastInsertId();
        $dao        = new EngineDAO($database);
        $dao->destroyCache($struct);

        try {
            $daemon = $this->daemonWithDb($database);
            $this->setProp($daemon, '_queueContextList', ContextList::get([
                'P1' => ['queue_name' => 'p1', 'max_executors' => 1],
                'P2' => ['queue_name' => 'p2', 'max_executors' => 1],
                'P3' => ['queue_name' => 'p3', 'max_executors' => 1],
                'P4' => ['queue_name' => 'p4', 'max_executors' => 1],
            ]));
            // main() sets the project being published before it chooses the queue; an MT route logs its id
            $this->setProp($daemon, 'actual_project_row', ['id' => 301]);

            $engine  = EnginesFactory::getInstance($struct->id, $database, AbstractEngine::class);
            $context = $this->invoke($daemon, '_getQueueAddressesByPriority', $queueLen, $engine);

            $this->assertInstanceOf(Context::class, $context);
            $this->assertSame($expectedQueue, $context->queue_name);
        } finally {
            $connection->exec('DELETE FROM engines WHERE id = ' . $struct->id);
            $dao->destroyCache($struct);
        }
    }

    /**
     * Rows: engines.class_load, queue length, expected queue name.
     *
     * @return array<string, array{string, int, string}>
     */
    public static function queuePriorityByEngineProvider(): array
    {
        return [
            'Lara is adaptive'                => ['Lara', 0, 'p4'],
            'Lara by its full name'           => ['Utils\Engines\Lara', 0, 'p4'],
            'DeepL is a plain MT'             => ['DeepL', 0, 'p3'],
            'no engine, short queue'          => ['NONE', 9999, 'p1'],
            'no engine, long queue'           => ['NONE', 10000, 'p2'],
            'unknown class reads as NONE'     => ['NoSuchEngineClass', 0, 'p1'],
        ];
    }

    // ─── Routing MT projects between P4 and P5 ───────────────────────────

    /** The project being published in the routing tests: created 300 s before the daemon's clock of 10:05:00. */
    private const array ROUTED_ROW = ['id' => 301, 'create_date' => '2026-11-18 10:00:00'];

    /**
     * Context list settings for the routing tests: P1 to P4, plus P5 when asked; P4 and P5 cap 25000 segments.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function routingSettings(bool $withP5): array
    {
        $settings = [];
        foreach ($withP5 ? ['P1', 'P2', 'P3', 'P4', 'P5'] : ['P1', 'P2', 'P3', 'P4'] as $key) {
            $settings[$key] = ['queue_name' => 'analysis_queue_' . $key, 'max_executors' => 30]
                + (in_array($key, ['P4', 'P5'], true) ? ['max_queued_segments' => 25000] : []);
        }

        return $settings;
    }

    /**
     * A daemon publishing $row at 10:05:00, whose single load read gives $loads.
     *
     * @param list<MtQueueLoad>|null                                         $loads  what the load read gives; null when none is expected
     * @param list<array{level: string, message: mixed, context: array<mixed>}> $logged
     * @param array<string, mixed>                                           $row    the project row being published
     */
    private function routingDaemon(
        bool $withP5,
        ?array $loads,
        array &$logged,
        array $row = self::ROUTED_ROW
    ): FastAnalysisMtQueueProbe {
        [$daemon]            = $this->mtQueueDaemon(self::routingSettings($withP5), [], $logged);
        $daemon->now         = (int)strtotime('2026-11-18 10:05:00');
        $daemon->loadsScript = $loads === null ? null : [$loads];
        $this->setProp($daemon, 'actual_project_row', $row);

        return $daemon;
    }

    /**
     * An engine built without its constructor: routing only checks its class.
     *
     * @param class-string<AbstractEngine> $class
     */
    private static function engineOfClass(string $class): AbstractEngine
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    /**
     * Rows: whether P5 is configured, the loads read (null: no read expected), the queue the project goes to.
     *
     * @return array<string, array{bool, list<MtQueueLoad>|null, string}>
     */
    public static function mtRoutingProvider(): array
    {
        $settings = self::routingSettings(true);

        return [
            'no P5 configured: P4, no load read'          => [false, null, 'P4'],
            'P5 has fewer segments queued: P5'            => [true, [self::mtLoad('P4', $settings['P4'], 5000), self::mtLoad('P5', $settings['P5'], 100)], 'P5'],
            'retried project live on P4: stays on P4'     => [true, [self::mtLoad('P4', $settings['P4'], 5000, livePids: [301]), self::mtLoad('P5', $settings['P5'], 0)], 'P4'],
            // Reachable only in a race: the daemon holds Lara projects in NEW while both queues are full
            'both queues over the cap: the first, P4'     => [true, [self::mtLoad('P4', $settings['P4'], 30000), self::mtLoad('P5', $settings['P5'], 28000)], 'P4'],
            'P5 lighter but with no worker: P4'           => [true, [self::mtLoad('P4', $settings['P4'], 5000), self::mtLoad('P5', $settings['P5'], 100, 0)], 'P4'],
            'both loads unknown: the first, P4'           => [true, [self::mtLoad('P4', $settings['P4'], null), self::mtLoad('P5', $settings['P5'], null)], 'P4'],
        ];
    }

    /**
     * @param list<MtQueueLoad>|null $loads
     */
    #[Test]
    #[DataProvider('mtRoutingProvider')]
    public function getQueueAddressesByPrioritySendsALaraProjectToItsMtQueue(bool $withP5, ?array $loads, string $expectedKey): void
    {
        $logged = [];
        $daemon = $this->routingDaemon($withP5, $loads, $logged);

        $context = $this->invoke($daemon, '_getQueueAddressesByPriority', 42, self::engineOfClass(Lara::class));

        $this->assertSame($this->invoke($daemon, '_mtQueueContexts')[$expectedKey], $context);
        $this->assertSame($loads === null ? [] : [['loads']], $daemon->events);
    }

    /**
     * Rows: the queues removed from the config, the engine, the segments to publish, the queue the project needs,
     * the queue it gets.
     *
     * @return array<string, array{list<string>, class-string<AbstractEngine>, int, string, string}>
     */
    public static function queueFallbackProvider(): array
    {
        return [
            'DeepL with no P3: P1'            => [['P3'], DeepL::class, 42, 'P3', 'P1'],
            '20000 segments with no P2: P1'   => [['P2'], MyMemory::class, 20000, 'P2', 'P1'],
            'small with no P1: P2'            => [['P1'], NONE::class, 42, 'P1', 'P2'],
            'small with only P3: P3'          => [['P1', 'P2'], MyMemory::class, 42, 'P1', 'P3'],
            'DeepL with only P2: P2'          => [['P1', 'P3'], DeepL::class, 42, 'P3', 'P2'],
        ];
    }

    /**
     * @param list<string>                 $removedQueues
     * @param class-string<AbstractEngine> $engineClass
     */
    #[Test]
    #[DataProvider('queueFallbackProvider')]
    public function getQueueAddressesByPriorityFallsBackToAnotherAnalysisQueue(array $removedQueues, string $engineClass, int $queueLen, string $wanted, string $used): void
    {
        $logged   = [];
        $settings = array_diff_key(self::routingSettings(false), array_flip($removedQueues));
        [$daemon] = $this->mtQueueDaemon($settings, [], $logged);
        $this->setProp($daemon, 'actual_project_row', self::ROUTED_ROW);

        $context = $this->invoke($daemon, '_getQueueAddressesByPriority', $queueLen, self::engineOfClass($engineClass));

        // Every queue runs the same TM analysis worker, so the project is analysed, only by other workers
        $this->assertSame($this->getProp($daemon, '_queueContextList')->list[$used], $context);
        $this->assertSame(
            [['pid' => self::ROUTED_ROW['id'], 'queue' => $wanted, 'used' => $used]],
            self::loggedContexts($logged, 'analysis_queue_fallback')
        );
        $this->assertSame('warning', array_column($logged, 'level', 'message')['analysis_queue_fallback']);
    }

    /**
     * Rows: the queues removed from the config, the engine. Each state is kept out on purpose: the config load
     * refuses the first, and _mtQueuesHold() keeps Lara projects in NEW in the second.
     *
     * @return array<string, array{list<string>, class-string<AbstractEngine>}>
     */
    public static function noQueueProvider(): array
    {
        return [
            'none of P1, P2 and P3' => [['P1', 'P2', 'P3'], MyMemory::class],
            'Lara with no MT queue' => [['P4'], Lara::class],
        ];
    }

    /**
     * @param list<string>                 $removedQueues
     * @param class-string<AbstractEngine> $engineClass
     */
    #[Test]
    #[DataProvider('noQueueProvider')]
    public function getQueueAddressesByPriorityThrowsWhenNoQueueCanTakeTheProject(array $removedQueues, string $engineClass): void
    {
        $logged   = [];
        $settings = array_diff_key(self::routingSettings(false), array_flip($removedQueues));
        [$daemon] = $this->mtQueueDaemon($settings, [], $logged);
        $this->setProp($daemon, 'actual_project_row', self::ROUTED_ROW);

        $this->expectException(LogicException::class);
        $this->invoke($daemon, '_getQueueAddressesByPriority', 42, self::engineOfClass($engineClass));
    }

    /**
     * Rows: whether P5 is configured, the loads read, the project row, the expected `mt_project_routed` context.
     *
     * @return array<string, array{bool, list<MtQueueLoad>|null, array<string, mixed>, array<string, mixed>}>
     */
    public static function mtRoutedLogProvider(): array
    {
        $settings = self::routingSettings(true);
        $p4       = self::mtLoad('P4', $settings['P4'], 5000);
        $p5       = self::mtLoad('P5', $settings['P5'], 100);
        $route    = ['pid' => 301, 'queue' => 'P4', 'age_seconds' => 300, 'segments_to_publish' => 42];

        return [
            'two queues: the route and both loads' => [true, [$p4, $p5], self::ROUTED_ROW, array_replace($route, ['queue' => 'P5'])
                + ['queues' => ['P4' => $p4->toLogArray(), 'P5' => $p5->toLogArray()]]],
            'one queue: no load to log'            => [false, null, self::ROUTED_ROW, $route],
            'no create_date: no age'               => [false, null, ['id' => 301], array_replace($route, ['age_seconds' => null])],
            'create_date after the clock: age 0'   => [false, null, ['id' => 301, 'create_date' => '2026-11-18 10:06:00'], array_replace($route, ['age_seconds' => 0])],
        ];
    }

    /**
     * @param list<MtQueueLoad>|null $loads
     * @param array<string, mixed>   $row
     * @param array<string, mixed>   $expectedLog
     */
    #[Test]
    #[DataProvider('mtRoutedLogProvider')]
    public function getQueueAddressesByPriorityLogsTheRouteOfALaraProject(bool $withP5, ?array $loads, array $row, array $expectedLog): void
    {
        $logged = [];
        $daemon = $this->routingDaemon($withP5, $loads, $logged, $row);

        $this->invoke($daemon, '_getQueueAddressesByPriority', 42, self::engineOfClass(Lara::class));

        $this->assertSame([$expectedLog], self::loggedContexts($logged, 'mt_project_routed'));
    }

    /**
     * Rows: engine class, queue length, expected queue.
     *
     * @return array<string, array{class-string<AbstractEngine>, int, string}>
     */
    public static function notMtRoutingProvider(): array
    {
        return [
            'MyMemory with 20000 segments: P2'  => [MyMemory::class, 20000, 'P2'],
            'DeepL: P3'                         => [DeepL::class, 0, 'P3'],
            'no engine with 9999 segments: P1'  => [NONE::class, 9999, 'P1'],
        ];
    }

    /**
     * @param class-string<AbstractEngine> $engineClass
     */
    #[Test]
    #[DataProvider('notMtRoutingProvider')]
    public function getQueueAddressesByPriorityRoutesOtherEnginesWithoutAnMtRouteLog(string $engineClass, int $queueLen, string $expectedKey): void
    {
        $logged = [];
        $daemon = $this->routingDaemon(true, null, $logged);

        $context = $this->invoke($daemon, '_getQueueAddressesByPriority', $queueLen, self::engineOfClass($engineClass));

        $contextList = $this->getProp($daemon, '_queueContextList');
        $this->assertSame($contextList->list[$expectedKey], $context);
        $this->assertSame([], $daemon->events, 'no MT queue load is read for another engine');
        $this->assertSame([], self::loggedContexts($logged, 'mt_project_routed'));
        $this->assertSame([], self::loggedContexts($logged, 'analysis_queue_fallback'));
    }

    // ─── Engines that use the MT queues ──────────────────────────────────

    /**
     * @param class-string<AbstractEngine>|null $engineClass
     */
    #[Test]
    #[DataProvider('mtQueueEngineProvider')]
    public function usesMtQueueMatchesTheMtQueueEngines(?string $engineClass, bool $expected): void
    {
        // instanceof needs no engine settings, so the constructor is skipped.
        $engine = $engineClass === null ? null : self::engineOfClass($engineClass);
        $daemon = $this->daemonWithDb($this->stubDatabase());

        $this->assertSame($expected, $this->invoke($daemon, '_usesMtQueue', $engine));
    }

    /**
     * @return array<string, array{class-string<AbstractEngine>|null, bool}>
     */
    public static function mtQueueEngineProvider(): array
    {
        return [
            'Lara'      => [Lara::class, true],
            'MyMemory'  => [MyMemory::class, false],
            'NONE'      => [NONE::class, false],
            'DeepL'     => [DeepL::class, false],
            'no engine' => [null, false],
        ];
    }

    // ─── MT queue loads ──────────────────────────────────────────────────

    /**
     * Settings of the queues used by the MT load tests: P4 capped at 25000 segments and 80 projects.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function mtQueueSettings(): array
    {
        return [
            'P1' => ['queue_name' => 'analysis_queue_P1', 'max_executors' => 1],
            'P3' => ['queue_name' => 'analysis_queue_P3', 'max_executors' => 1],
            'P4' => ['queue_name' => 'analysis_queue_P4', 'max_executors' => 30, 'max_queued_segments' => 25000, 'max_queued_projects' => 80],
        ];
    }

    /**
     * Builds a daemon that reads the MT queue loads with no broker or Redis: Jolokia answers from $queueSizes
     * and Redis is the recording fake.
     *
     * Example: mtQueueDaemon(self::mtQueueSettings(), ['analysis_queue_P4' => 500], $logged).
     *
     * @param array<string, array<string, mixed>>                           $settings   context list settings, keyed 'P1'..'P5'
     * @param array<string, int|null>                                       $queueSizes readQueueSize() result per queue name
     * @param list<array{level: string, message: mixed, context: array<mixed>}> $logged
     *
     * @return array{0: FastAnalysisMtQueueProbe, 1: FastAnalysisFakeRedis}
     */
    private function mtQueueDaemon(array $settings, array $queueSizes, array &$logged): array
    {
        $probe = (new ReflectionClass(FastAnalysisMtQueueProbe::class))->newInstanceWithoutConstructor();

        $redis = new FastAnalysisFakeRedis();
        $amq   = $this->createStub(AMQHandler::class);
        $amq->method('getRedisClient')->willReturn($redis);
        $amq->method('readQueueSize')->willReturnCallback(fn (string $queueName): ?int => $queueSizes[$queueName] ?? null);

        $fa = new ReflectionClass(FastAnalysis::class);
        $fa->getProperty('db')->setValue($probe, $this->createStub(IDatabase::class));
        $fa->getProperty('logger')->setValue($probe, $this->capturingStructuredLogger($logged));
        $fa->getProperty('queueHandler')->setValue($probe, $amq);
        $fa->getProperty('_queueContextList')->setValue($probe, ContextList::get($settings));

        return [$probe, $redis];
    }

    /**
     * @param list<array{level: string, message: mixed, context: array<mixed>}> $logged
     *
     * @return list<array<mixed>> the context of each entry logged with this message
     */
    private static function loggedContexts(array $logged, string $message): array
    {
        $contexts = [];
        foreach ($logged as $entry) {
            if ($entry['message'] === $message) {
                $contexts[] = $entry['context'];
            }
        }

        return $contexts;
    }

    /**
     * Rows: context list settings, expected MT queue keys in order.
     *
     * @return array<string, array{array<string, array<string, mixed>>, list<string>}>
     */
    public static function mtQueueContextsProvider(): array
    {
        $queue = fn (string $key): array => ['queue_name' => 'analysis_queue_' . $key, 'max_executors' => 1];

        return [
            'P4 among other queues' => [['P1' => $queue('P1'), 'P3' => $queue('P3'), 'P4' => $queue('P4')], ['P4']],
            'P5 written before P4'  => [['P5' => $queue('P5'), 'P1' => $queue('P1'), 'P4' => $queue('P4')], ['P4', 'P5']],
            'no MT queue'           => [['P1' => $queue('P1'), 'P2' => $queue('P2'), 'P3' => $queue('P3')], []],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $settings
     * @param list<string>                        $expectedKeys
     */
    #[Test]
    #[DataProvider('mtQueueContextsProvider')]
    public function mtQueueContextsAreP4ThenP5WhenConfigured(array $settings, array $expectedKeys): void
    {
        $logged = [];
        [$daemon] = $this->mtQueueDaemon($settings, [], $logged);

        $contexts = $this->invoke($daemon, '_mtQueueContexts');

        $this->assertSame($expectedKeys, array_keys($contexts));
        foreach ($contexts as $key => $context) {
            $this->assertSame($this->getProp($daemon, '_queueContextList')->list[$key], $context);
        }
    }

    #[Test]
    public function readMtQueueLoadsReadsEachMtQueueOnTheDaemonClock(): void
    {
        $logged   = [];
        $settings = self::mtQueueSettings();
        $settings['P5'] = ['queue_name' => 'analysis_queue_P5', 'max_executors' => 10];
        [$daemon, $redis] = $this->mtQueueDaemon($settings, ['analysis_queue_P4' => 500], $logged);
        $redis->sets['analysis_queue_P4_pid_set'] = ['worker-1', 'worker-2'];

        $unreadableWarnings = [];
        foreach ([1000, 1030] as $now) {
            $daemon->now = $now;
            $logged      = [];
            $loads       = $this->invoke($daemon, '_readMtQueueLoads');
            $unreadableWarnings[$now] = array_column(self::loggedContexts($logged, 'mt_queue_load_unreadable'), 'part', 'queue');
        }

        // both reads return the same loads; the assertions check the second one
        $this->assertSame(['P4', 'P5'], array_map(fn (MtQueueLoad $load): string => $load->key, $loads));
        $this->assertSame([500, 0], array_map(fn (MtQueueLoad $load): ?int => $load->segmentLoad, $loads));
        $this->assertSame([2, 0], array_map(fn (MtQueueLoad $load): int => $load->workers, $loads));
        // P5 QueueSize is unreadable on both reads; 30 seconds later the warning is not repeated
        $this->assertSame([1000 => ['P5' => 'queue_size'], 1030 => []], $unreadableWarnings);
    }

    #[Test]
    public function publishingToAnMtQueueRecordsTheProjectAsLive(): void
    {
        $redis = new FastAnalysisFakeRedis();

        $published = $this->publishedParams([], 2, null, self::mtQueueSettings(), $redis);

        $this->assertCount(1, $published);
        // publishing before the loop, published once every message is sent
        $this->assertSame([
            ['hsetex', 'analysis_queue_P4_live_pids', [99999990 => MtQueueLoadReader::PUBLISHING], '', 'ex', 86400],
            ['publish', 'analysis_queue_P4'],
            ['hsetex', 'analysis_queue_P4_live_pids', [99999990 => MtQueueLoadReader::PUBLISHED], '', 'ex', 86400],
        ], array_values(array_filter($redis->calls, fn (array $call): bool => in_array($call[0], ['hsetex', 'publish'], true))));
    }

    #[Test]
    public function aPublishThatStopsLeavesTheProjectMarkedAsStillPublishing(): void
    {
        $redis = new FastAnalysisFakeRedis();

        try {
            $this->publishedParams([], 2, null, self::mtQueueSettings(), $redis, new ConnectionException('Broker gone'));
            $this->fail('the publish failure must reach main(), which releases the project');
        } catch (ConnectionException) {
        }

        $this->assertSame(
            [['hsetex', 'analysis_queue_P4_live_pids', [99999990 => MtQueueLoadReader::PUBLISHING], '', 'ex', 86400]],
            array_values(array_filter($redis->calls, fn (array $call): bool => $call[0] === 'hsetex'))
        );
    }

    #[Test]
    public function publishingToAnotherQueueRecordsNothing(): void
    {
        $redis = new FastAnalysisFakeRedis();

        $this->publishedParams([], 2, null, [], $redis);

        $this->assertSame([], array_filter($redis->calls, fn (array $call): bool => $call[0] === 'hsetex'));
    }

    #[Test]
    public function registerOnMtQueueRecordsOnlyProjectsPublishedToAnMtQueue(): void
    {
        $logged = [];
        [$daemon, $redis] = $this->mtQueueDaemon(self::mtQueueSettings(), [], $logged);
        $contexts = $this->getProp($daemon, '_queueContextList')->list;

        $this->invoke($daemon, '_registerOnMtQueue', 11, $contexts['P4'], MtQueueLoadReader::PUBLISHED);
        $this->invoke($daemon, '_registerOnMtQueue', 12, $contexts['P1'], MtQueueLoadReader::PUBLISHED);

        $this->assertSame([['hsetex', 'analysis_queue_P4_live_pids', [11 => MtQueueLoadReader::PUBLISHED], '', 'ex', 86400]], $redis->calls);
    }

    #[Test]
    public function logMtQueueSettingsReportsTheMtQueueSettingsAndEachRejectedValue(): void
    {
        $logged = [];
        $settings = self::mtQueueSettings();
        $settings['P5'] = ['queue_name' => 'analysis_queue_P5', 'max_executors' => 10, 'max_queued_segments' => '25.000', 'accept_new_projects' => false];
        $settings['P1']['max_queued_projects'] = '-1';
        [$daemon] = $this->mtQueueDaemon($settings, [], $logged);

        $this->invoke($daemon, '_logMtQueueSettings');

        $this->assertSame([[
            'P4' => ['max_queued_segments' => 25000, 'max_queued_projects' => 80, 'accept_new_projects' => true, 'max_executors' => 30],
            'P5' => ['max_queued_segments' => 0, 'max_queued_projects' => 0, 'accept_new_projects' => false, 'max_executors' => 10],
        ]], self::loggedContexts($logged, 'mt_queue_settings'));
        // only MT queues read the caps, so a rejected P1 value is not reported
        $this->assertSame(
            [['queue' => 'P5', 'key' => 'max_queued_segments', 'value' => '25.000']],
            self::loggedContexts($logged, 'mt_queue_setting_invalid')
        );
    }

    #[Test]
    public function logMtQueueSettingsLogsNoSettingsWithoutAnMtQueue(): void
    {
        $logged = [];
        [$daemon] = $this->mtQueueDaemon(['P1' => ['queue_name' => 'analysis_queue_P1', 'max_executors' => 1]], [], $logged);

        $this->invoke($daemon, '_logMtQueueSettings');

        $this->assertSame([], $logged);
    }

    // ─── Live config reload ──────────────────────────────────────────────

    /**
     * Writes a task manager config with P1 and a P4 segment cap to a new temp file, and returns its path.
     *
     * Example: newConfigFile('25000') writes `P4[max_queued_segments] = 25000`.
     */
    private function newConfigFile(string $p4SegmentCap): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fast-config-');
        $this->assertNotFalse($path, 'a temp file for the config');
        $this->configFiles[] = $path;
        self::writeConfig($path, $p4SegmentCap);

        return $path;
    }

    /**
     * Rewrites $path with P1, and P4 capped at $p4SegmentCap segments. Example: writeConfig($path, '30000').
     */
    private static function writeConfig(string $path, string $p4SegmentCap): void
    {
        file_put_contents($path, implode("\n", [
            'loggerName = "fastAnalysis.log"',
            '[context_definitions]',
            'P1[queue_name] = "analysis_queue_P1"',
            'P1[max_executors] = 1',
            'P4[queue_name] = "analysis_queue_P4"',
            'P4[max_executors] = 30',
            "P4[max_queued_segments] = $p4SegmentCap",
            '',
        ]));
    }

    /**
     * A daemon that has loaded $path as the constructor does.
     *
     * @param list<array{level: string, message: mixed, context: array<mixed>}> $logged
     */
    private function daemonOnConfigFile(string $path, array &$logged): FastAnalysisMtQueueProbe
    {
        $daemon = (new ReflectionClass(FastAnalysisMtQueueProbe::class))->newInstanceWithoutConstructor();
        $this->setProp($daemon, 'logger', $this->capturingStructuredLogger($logged));
        $this->setProp($daemon, '_configFile', $path);
        $this->setProp($daemon, '_contextIndex', null);
        $this->invoke($daemon, '_updateConfiguration');

        return $daemon;
    }

    private function p4SegmentCap(FastAnalysis $daemon): ?int
    {
        return ($this->getProp($daemon, '_queueContextList')->list['P4'] ?? null)?->max_queued_segments;
    }

    /**
     * Writes a config with the given queues, each with one worker. Example: writeQueues($path, ['P4']).
     *
     * @param list<string> $keys
     */
    private static function writeQueues(string $path, array $keys): void
    {
        $content = "loggerName = \"fastAnalysis.log\"\n[context_definitions]\n";
        foreach ($keys as $key) {
            $content .= "{$key}[queue_name] = \"analysis_queue_$key\"\n{$key}[max_executors] = 1\n";
        }
        file_put_contents($path, $content);
    }

    #[Test]
    public function updateConfigurationRefusesAtStartAConfigWithNoAnalysisQueue(): void
    {
        $logged = [];
        $path = $this->newConfigFile('0');
        self::writeQueues($path, ['P4', 'P5']);

        // The constructor turns this into exit 1, before any project is taken
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The config has none of the analysis queues P1, P2 and P3');
        $this->daemonOnConfigFile($path, $logged);
    }

    #[Test]
    public function updateConfigurationKeepsItsQueuesWhenAReloadHasNoAnalysisQueue(): void
    {
        $logged = [];
        $path = $this->newConfigFile('25000');
        $daemon = $this->daemonOnConfigFile($path, $logged);
        $logged = [];
        self::writeQueues($path, ['P4']);

        $this->invoke($daemon, '_updateConfiguration');
        $this->invoke($daemon, '_updateConfiguration');

        $this->assertSame(['P1', 'P4'], array_keys($this->getProp($daemon, '_queueContextList')->list));
        $this->assertSame(
            [['file' => $path, 'error' => 'The config has none of the analysis queues P1, P2 and P3']],
            self::loggedContexts($logged, 'config_reload_failed')
        );
    }

    /**
     * Rows: the queues in the config, the analysis queues the load reports missing ([] = no log line).
     *
     * @return array<string, array{list<string>, list<string>}>
     */
    public static function missingAnalysisQueuesProvider(): array
    {
        return [
            'all three'   => [['P1', 'P2', 'P3', 'P4'], []],
            'only P1'     => [['P1', 'P4'], ['P2', 'P3']],
            'no P2'       => [['P1', 'P3'], ['P2']],
        ];
    }

    /**
     * @param list<string> $queues
     * @param list<string> $missing
     */
    #[Test]
    #[DataProvider('missingAnalysisQueuesProvider')]
    public function updateConfigurationLogsTheAnalysisQueuesMissingFromTheFile(array $queues, array $missing): void
    {
        $logged = [];
        $path = $this->newConfigFile('0');
        self::writeQueues($path, $queues);

        $this->daemonOnConfigFile($path, $logged);

        $this->assertSame($missing === [] ? [] : [['queues' => $missing]], self::loggedContexts($logged, 'analysis_queues_missing'));
        if ($missing !== []) {
            $this->assertSame('error', array_column($logged, 'level', 'message')['analysis_queues_missing']);
        }
    }

    #[Test]
    public function updateConfigurationLogsTheMtQueueSettingsOfTheFirstLoad(): void
    {
        $logged = [];
        $this->daemonOnConfigFile($this->newConfigFile('25000'), $logged);

        $this->assertSame(
            [['P4' => ['max_queued_segments' => 25000, 'max_queued_projects' => 0, 'accept_new_projects' => true, 'max_executors' => 30]]],
            self::loggedContexts($logged, 'mt_queue_settings')
        );
    }

    #[Test]
    public function updateConfigurationTakesAChangeOnceTheFileHoldsStillForACycle(): void
    {
        $logged = [];
        $path = $this->newConfigFile('25000');
        $daemon = $this->daemonOnConfigFile($path, $logged);
        $logged = [];
        self::writeConfig($path, '30000');

        $this->invoke($daemon, '_updateConfiguration');
        $this->assertSame(25000, $this->p4SegmentCap($daemon), 'a file seen changed once may still be being written');

        $this->invoke($daemon, '_updateConfiguration');
        $this->assertSame(30000, $this->p4SegmentCap($daemon));
        $this->assertSame(
            [['P4' => ['max_queued_segments' => 30000, 'max_queued_projects' => 0, 'accept_new_projects' => true, 'max_executors' => 30]]],
            self::loggedContexts($logged, 'mt_queue_settings')
        );
    }

    #[Test]
    public function updateConfigurationDoesNothingWhileTheFileIsUnchanged(): void
    {
        $logged = [];
        $daemon = $this->daemonOnConfigFile($this->newConfigFile('25000'), $logged);
        $list = $this->getProp($daemon, '_queueContextList');
        $logged = [];

        $this->invoke($daemon, '_updateConfiguration');
        $this->invoke($daemon, '_updateConfiguration');

        $this->assertSame($list, $this->getProp($daemon, '_queueContextList'), 'an unchanged file is not parsed again');
        $this->assertSame([], $logged);
    }

    #[Test]
    public function updateConfigurationDropsAQueueRemovedFromTheFile(): void
    {
        $logged = [];
        $path = $this->newConfigFile('25000');
        file_put_contents($path, "P5[queue_name] = \"analysis_queue_P5\"\nP5[max_executors] = 2\n", FILE_APPEND);
        $daemon = $this->daemonOnConfigFile($path, $logged);
        self::writeConfig($path, '25000');

        $this->invoke($daemon, '_updateConfiguration');
        $this->invoke($daemon, '_updateConfiguration');

        $this->assertSame(['P1', 'P4'], array_keys($this->getProp($daemon, '_queueContextList')->list));
    }

    #[Test]
    public function mainReloadsTheConfigurationBeforeEachPick(): void
    {
        [$probe, $redis] = $this->wireMainProbe();
        $path = $this->newConfigFile('30000');
        $this->setProp($probe, '_configFile', $path);
        // Read the file as the constructor does: wireMainProbe() marked the daemon as already loaded
        (new ReflectionClass(AbstractDaemon::class))->getProperty('configHash')->setValue($probe, null);
        $this->invoke($probe, '_updateConfiguration');
        $redis->sismemberReturns = [true, true, true, false]; // three working cycles, then suicide
        $probe->lockBatches      = [[$this->projectRow(1606)], [$this->projectRow(1607)], [$this->projectRow(1608)]];
        $probe->fetchScript      = array_fill(0, 3, new AnalyzeResponse(['data' => []]));
        $probe->insertResults    = [0, 0, 0];
        // The file changes while the first project is analysed
        $probe->onPick = static fn () => self::writeConfig($path, '35000');

        $this->runMain($probe);

        // Cycle 2 sees the file changed and waits; cycle 3 sees it unchanged since and takes it
        $this->assertSame([30000, 30000, 35000], $probe->p4SegmentCapAtPick);
    }

    #[Test]
    public function mtQueuesHoldLogsTheReopeningWhenTheLimitsAreTurnedOffWhileHolding(): void
    {
        $p4 = ['queue_name' => 'analysis_queue_P4', 'max_executors' => 30, 'max_queued_segments' => 25000];
        $logged = [];
        [$daemon] = $this->mtQueueDaemon(['P4' => $p4], [], $logged);
        $full = [self::mtLoad('P4', $p4, 25000)];
        $daemon->loadsScript = [$full, $full];
        $daemon->now = 1000;
        $this->assertTrue($this->invoke($daemon, '_mtQueuesHold'));

        // A reload turns every limit off
        $this->setProp($daemon, '_queueContextList', ContextList::get(['P4' => ['queue_name' => 'analysis_queue_P4', 'max_executors' => 30]]));
        $daemon->now = 1010;
        $this->assertFalse($this->invoke($daemon, '_mtQueuesHold'));

        // The cap comes back and the queue is still full: the full state is logged again at once
        $this->setProp($daemon, '_queueContextList', ContextList::get(['P4' => $p4]));
        $daemon->now = 1020;
        $this->assertTrue($this->invoke($daemon, '_mtQueuesHold'));

        $this->assertSame(['mt_queues_full', 'mt_queues_open', 'mt_queues_full'], array_column($logged, 'message'));
        $this->assertSame(['queues' => [], 'reason' => 'limits_off'], self::loggedContexts($logged, 'mt_queues_open')[0], 'no load is read while the limits are off');
    }

    #[Test]
    public function mtQueuesHoldKeepsLaraProjectsWaitingWhileNoMtQueueIsConfigured(): void
    {
        $logged = [];
        [$daemon] = $this->mtQueueDaemon(['P1' => ['queue_name' => 'analysis_queue_P1', 'max_executors' => 1]], [], $logged);
        $observed = [];
        foreach ([1000, 1010, 1060] as $now) {
            $daemon->now = $now;
            $before = count($logged);
            $observed[$now] = [$this->invoke($daemon, '_mtQueuesHold'), array_column(array_slice($logged, $before), 'context')];
        }

        $why = ['queues' => [], 'reason' => 'no_mt_queue'];
        $this->assertSame([1000 => [true, [$why]], 1010 => [true, []], 1060 => [true, [$why]]], $observed);
        $this->assertSame(['mt_queues_full', 'mt_queues_full'], array_column($logged, 'message'));
        $this->assertSame([], $daemon->events, 'no load is read with no MT queue');
    }

    #[Test]
    public function nowIsTheCurrentUnixTime(): void
    {
        $fa  = new ReflectionClass(FastAnalysis::class);
        $now = $fa->getMethod('_now')->invoke($fa->newInstanceWithoutConstructor());

        $this->assertEqualsWithDelta(time(), $now, 2);
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

    /**
     * The only script the daemon sends is RedisLock's release, recorded as ['eval', key, token].
     */
    public function eval($script, $numkeys, ...$keyOrArg): int
    {
        $this->calls[] = ['eval', $keyOrArg[0], $keyOrArg[1]];

        return 1;
    }

    public function setex($key, $seconds, $value): string
    {
        $this->calls[] = ['setex', $key, $seconds, $value];

        return 'OK';
    }

    /** @var array<string, list<string>> set key => members, counted by scard */
    public array $sets = [];

    public function lrem($key, $count, $value): int
    {
        $this->calls[] = ['lrem', $key, $count, $value];

        return 0;
    }

    public function scard($key): int
    {
        $this->calls[] = ['scard', $key];

        return count($this->sets[$key] ?? []);
    }

    /**
     * @return array<string, string>
     */
    public function hgetall($key): array
    {
        $this->calls[] = ['hgetall', $key];

        return [];
    }

    /**
     * @param array<int|string, string> $fieldValueMap
     */
    public function hsetex($key, array $fieldValueMap, $setModifier = '', $ttlModifier = '', $ttlModifierValue = false): int
    {
        $this->calls[] = ['hsetex', $key, $fieldValueMap, $setModifier, $ttlModifier, $ttlModifierValue];

        return 1;
    }

    public function rpush($key, $values): int
    {
        $this->calls[] = ['rpush', $key, $values];

        return 1;
    }
}

/**
 * Probe subclass that makes the database fallback inside _fetchMyMemoryFast() fail the way a
 * too-large project does. The engine lookup that runs before the try block still uses the real
 * connection, so only the segments query is affected.
 */
class FastAnalysisPdoFailingProbe extends FastAnalysis
{
    protected function _getSegmentsForFastVolumeAnalysis(int $pid): array
    {
        throw new PDOException('simulated statement failure');
    }
}

/**
 * Lets _insertFastAnalysis() reach the publish loop without a broker: the segment_translations
 * insert and the queue-total bookkeeping do nothing, and every segment routes to P4 when the context list
 * has it, otherwise to one fixed queue.
 */
class FastAnalysisPublishProbe extends FastAnalysis
{
    protected function _executeInsert(array $tuple_list, array $bind_values): void
    {
    }

    protected function _setTotal(array $config = []): void
    {
    }

    protected function _getQueueAddressesByPriority(int $queueLen, AbstractEngine $mtEngine): Context
    {
        return $this->_queueContextList->list['P4']
            ?? Context::buildFromArray(['queue_name' => 'fast_analysis_test_queue', 'max_executors' => 1]);
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

    /** @var list<array<string, bool|string|null>> pre-translation options of each _insertFastAnalysis() call */
    public array $pretranslateArgs = [];

    public int $featureSetCallCount = 0;

    public int $rebuildCount = 0;

    /** @var list<int|null> the P4 segment cap in the context list at each pick; null with no P4 */
    public array $p4SegmentCapAtPick = [];

    /** Runs after each pick, as if something happened while that project was analysed. */
    public ?\Closure $onPick = null;

    protected function _checkDatabaseConnection(): void
    {
        if ($this->dbCheckThrows) {
            $this->dbCheckThrows = false; // only the first cycle fails; the next reconnects

            throw new PDOException('simulated DB unavailable');
        }
    }

    protected function _lockNextProjectToAnalyze(): array
    {
        $this->p4SegmentCapAtPick[] = ($this->_queueContextList->list['P4'] ?? null)?->max_queued_segments;
        if ($this->onPick !== null) {
            ($this->onPick)();
        }

        $batch = array_shift($this->lockBatches) ?? [];

        // Store a token per project, as the real picker does, so the tests can assert main()'s releases.
        $tokens = [];
        foreach ($batch as $row) {
            $tokens[(int)$row['id']] = 'token-' . $row['id'];
        }
        (new ReflectionClass(FastAnalysis::class))->getProperty('processingLockTokens')->setValue($this, $tokens);

        return $batch;
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
        bool                $icu_enabled = false,
        ?bool               $pretranslate_101 = null,
        ?string             $pretranslate_101_status = null,
        ?bool               $pretranslate_101_lock = null,
        ?string             $pretranslate_100_status = null,
        ?bool               $pretranslate_100_lock = null
    ): int {
        $this->insertCallCount++;
        $this->pretranslateArgs[] = [
            'pretranslate_101' => $pretranslate_101,
            'pretranslate_101_status' => $pretranslate_101_status,
            'pretranslate_101_lock' => $pretranslate_101_lock,
            'pretranslate_100_status' => $pretranslate_100_status,
            'pretranslate_100_lock' => $pretranslate_100_lock,
        ];
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

/**
 * Probe subclass for the MT queue tests: a settable clock, scripted load reads, and a record of what it did.
 */
class FastAnalysisMtQueueProbe extends FastAnalysis
{
    public int $now = 1000;

    /** @var list<list<MtQueueLoad>>|null loads returned by each _readMtQueueLoads() call in turn; null reads them for real */
    public ?array $loadsScript = null;

    /**
     * What the daemon did, in order. Each event is a list: its name first, then its data.
     * ['loads'] a load read, ['transaction'] a transaction opened, ['prepare', <sql>], ['execute', <params>].
     *
     * @var list<array<int, mixed>>
     */
    public array $events = [];

    protected function _now(): int
    {
        return $this->now;
    }

    protected function _readMtQueueLoads(): array
    {
        // Recorded before the real read too, so a test can prove that no read happened at all
        $this->events[] = ['loads'];
        if ($this->loadsScript === null) {
            return parent::_readMtQueueLoads();
        }

        return array_shift($this->loadsScript) ?? [];
    }
}
