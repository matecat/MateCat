<?php

namespace Matecat\Core\Workers\Analysis\MtQueue;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client as PredisClient;
use RuntimeException;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\MtQueue\MtQueueLoad;
use Utils\AsyncTasks\Workers\Analysis\MtQueue\MtQueueLoadReader;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;
use Utils\Redis\RedisHandler;
use Utils\TaskRunner\Commons\Context;

class MtQueueLoadReaderTest extends AbstractTest
{
    /** @var list<array{level: string, message: mixed, context: array<mixed>}> */
    private array $logged = [];

    /** @var list<array{0: string, 1: array<string, mixed>}> each prepared SQL with its bound values */
    private array $queries = [];

    private int $now = 1000;

    /**
     * P4 capped at 25000 segments and 80 projects.
     */
    private static function p4(): Context
    {
        return Context::buildFromArray([
            'queue_name'          => 'analysis_queue_P4',
            'max_executors'       => 30,
            'max_queued_segments' => 25000,
            'max_queued_projects' => 80,
        ]);
    }

    /**
     * A reader whose live-status SELECT returns $dbLiveIds, with a capturing logger and the test clock.
     *
     * @param list<int> $dbLiveIds ids the SELECT returns as BUSY or FAST_OK
     */
    private function reader(array $dbLiveIds = []): MtQueueLoadReader
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function (array $params): bool {
            $this->queries[array_key_last($this->queries)][1] = $params;

            return true;
        });
        // PDO hands ids back as strings
        $stmt->method('fetchAll')->willReturn(array_map('strval', $dbLiveIds));

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($stmt): PDOStatement {
            $this->queries[] = [$sql, []];

            return $stmt;
        });

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);
        $db->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $logger = $this->createStub(MatecatLogger::class);
        foreach (['debug', 'info', 'warning', 'error'] as $level) {
            $logger->method($level)->willReturnCallback(function (mixed $message, array $context = []) use ($level): void {
                $this->logged[] = ['level' => $level, 'message' => $message, 'context' => $context];
            });
        }

        return new MtQueueLoadReader($db, $logger, fn (): int => $this->now);
    }

    /**
     * An AMQ handler that answers QueueSize from $queueSizes and hands out $redis.
     *
     * @param array<string, int|null> $queueSizes readQueueSize() result per queue name
     */
    private function amq(PredisClient $redis, array $queueSizes): AMQHandler
    {
        $amq = $this->createStub(AMQHandler::class);
        $amq->method('getRedisClient')->willReturn($redis);
        $amq->method('readQueueSize')->willReturnCallback(fn (string $queueName): ?int => $queueSizes[$queueName] ?? null);

        return $amq;
    }

    /**
     * Seeds one live P4 project, pid 11 with 60 segments left, and 30 workers.
     */
    private static function seedOneLiveProject(MtQueueLoadReaderFakeRedis $redis): void
    {
        $redis->hashes['analysis_queue_P4_live_pids'] = ['11' => MtQueueLoadReader::PUBLISHED];
        $redis->values['p_tot_seg:11']                = '100';
        $redis->values['p_num_done:11']               = '40';
        $redis->sets['analysis_queue_P4_pid_set']     = array_map(fn (int $i): string => "worker-$i", range(1, 30));
    }

    /**
     * @return list<array<mixed>> the context of each entry logged with this message
     */
    private function loggedContexts(string $message): array
    {
        $contexts = [];
        foreach ($this->logged as $entry) {
            if ($entry['message'] === $message) {
                $contexts[] = $entry['context'];
            }
        }

        return $contexts;
    }

    #[Test]
    public function livePidsKeyIsNamedAfterTheQueue(): void
    {
        $this->assertSame('analysis_queue_P4_live_pids', MtQueueLoadReader::livePidsKey(self::p4()));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publishStateProvider(): array
    {
        return [
            'before the publish loop' => [MtQueueLoadReader::PUBLISHING],
            'after the publish loop'  => [MtQueueLoadReader::PUBLISHED],
        ];
    }

    #[Test]
    #[DataProvider('publishStateProvider')]
    public function registerRecordsThePidAndItsPublishStateInTheQueueHashForOneDay(string $state): void
    {
        $redis = new MtQueueLoadReaderFakeRedis();

        $this->reader()->register($this->amq($redis, []), 'P4', self::p4(), 11, $state);

        $this->assertSame([['hsetex', 'analysis_queue_P4_live_pids', [11 => $state], '', 'ex', 86400]], $redis->calls);
        $this->assertSame([], $this->logged);
    }

    /**
     * A publish that stopped part way (broker error, daemon killed) leaves counters for segments never sent:
     * counting them would show the queue full for a day and hold every Lara project, the retry included.
     */
    #[Test]
    public function readCountsAProjectStillPublishingAsLiveWithNoSegmentsLeft(): void
    {
        $redis = new MtQueueLoadReaderFakeRedis();
        $redis->hashes['analysis_queue_P4_live_pids'] = [
            '11' => MtQueueLoadReader::PUBLISHED,  // 60 segments left
            '12' => MtQueueLoadReader::PUBLISHING, // stopped after 1000 of 40000 messages
            '13' => MtQueueLoadReader::PUBLISHING, // stopped before any worker started it
            '14' => MtQueueLoadReader::PUBLISHING, // finished since: DONE in the DB
        ];
        $redis->values = [
            'p_tot_seg:11'  => '100',
            'p_num_done:11' => '40',
            'p_tot_seg:12'  => '40000',
            'p_num_done:12' => '1000',
        ];

        $loads = $this->reader([11, 12, 13])->read($this->amq($redis, ['analysis_queue_P4' => 10]), ['P4' => self::p4()]);

        // still live, so the project cap counts them and a retry goes back to this queue
        $this->assertSame([11, 12, 13], $loads[0]->livePids);
        $this->assertSame(3, $loads[0]->liveProjects);
        // only the published project adds remaining work; the broker size covers the messages already sent
        $this->assertSame(60, $loads[0]->segmentLoad);
        $this->assertSame(['11', '12', '13'], array_map('strval', array_keys($redis->hashes['analysis_queue_P4_live_pids'])));
    }

    /**
     * The code before the publish state wrote the value '1'. During a rolling deploy those fields must keep
     * counting their remaining work, as they did.
     */
    #[Test]
    public function readCountsAFieldWrittenBeforeThePublishStateAsPublished(): void
    {
        $redis = new MtQueueLoadReaderFakeRedis();
        $redis->hashes['analysis_queue_P4_live_pids'] = ['11' => '1'];
        $redis->values = ['p_tot_seg:11' => '100', 'p_num_done:11' => '40'];

        $loads = $this->reader([11])->read($this->amq($redis, ['analysis_queue_P4' => 10]), ['P4' => self::p4()]);

        $this->assertSame([11], $loads[0]->livePids);
        $this->assertSame(60, $loads[0]->segmentLoad);
    }

    #[Test]
    public function registerLogsAFailureAtMostOncePerMinuteAndNeverThrows(): void
    {
        $redis             = new MtQueueLoadReaderFakeRedis();
        $redis->failHashes = true;
        $reader            = $this->reader();

        foreach ([1000, 1030, 1061] as $now) {
            $this->now = $now;
            $reader->register($this->amq($redis, []), 'P4', self::p4(), 11, MtQueueLoadReader::PUBLISHING);
        }

        $warnings = $this->loggedContexts('mt_queue_live_pid_unrecorded');
        $this->assertCount(2, $warnings);
        $this->assertSame(['queue' => 'P4', 'pid' => 11, 'error' => 'Redis down'], $warnings[0]);
    }

    #[Test]
    public function readCountsOnlyLiveProjectsAndForgetsTheOthers(): void
    {
        $redis = new MtQueueLoadReaderFakeRedis();
        $redis->hashes['analysis_queue_P4_live_pids'] = array_fill_keys(['11', '12', '13', '14', '15', '16', 'junk'], MtQueueLoadReader::PUBLISHED);
        $redis->values = [
            'p_tot_seg:11'  => '100', // 60 segments left: live
            'p_num_done:11' => '40',
            'p_tot_seg:12'  => '50',  // no work left: not live
            'p_num_done:12' => '50',
            // 13 has no counters yet: published, no worker has started it, live
            'p_tot_seg:14'  => '50',  // more done than total, as after a worker retry: not live
            'p_num_done:14' => '52',
            // 16 is NEW again, released for a retry before any worker started it: live, so the retry comes back here
        ];
        $redis->sets['analysis_queue_P4_pid_set'] = array_map(fn (int $i): string => "worker-$i", range(1, 30));

        // 15 is DONE, so the SELECT leaves it out; 20 is unfinished but was never published to this queue
        $loads = $this->reader([11, 12, 13, 14, 16, 20])->read($this->amq($redis, ['analysis_queue_P4' => 500]), ['P4' => self::p4()]);

        $this->assertCount(1, $loads);
        $this->assertInstanceOf(MtQueueLoad::class, $loads[0]);
        $this->assertSame('P4', $loads[0]->key);
        $this->assertSame([11, 13, 16], $loads[0]->livePids);
        $this->assertSame(3, $loads[0]->liveProjects);
        $this->assertSame(500, $loads[0]->segmentLoad);
        $this->assertSame(30, $loads[0]->workers);

        // one master read of the unfinished projects from the smallest numeric field up
        $this->assertCount(1, $this->queries);
        $this->assertStringContainsString('status_analysis IN', $this->queries[0][0]);
        $this->assertStringContainsString('id >= ?', $this->queries[0][0]);
        $this->assertEqualsCanonicalizing(
            [11, ProjectStatus::STATUS_NEW, ProjectStatus::STATUS_BUSY, ProjectStatus::STATUS_FAST_OK],
            array_values($this->queries[0][1])
        );

        // counters read once, for the unfinished projects of this queue only (20 is not in the hash)
        $mgets = array_values(array_filter($redis->calls, fn (array $call): bool => $call[0] === 'mget'));
        $this->assertSame([['mget', [
            'p_tot_seg:11', 'p_num_done:11', 'p_tot_seg:12', 'p_num_done:12', 'p_tot_seg:13', 'p_num_done:13',
            'p_tot_seg:14', 'p_num_done:14', 'p_tot_seg:16', 'p_num_done:16',
        ]]], $mgets);

        // every field that is not live leaves the hash in one command
        $this->assertContains(['hdel', 'analysis_queue_P4_live_pids', ['12', '14', '15', 'junk']], $redis->calls);
        $this->assertSame(['11', '13', '16'], array_map('strval', array_keys($redis->hashes['analysis_queue_P4_live_pids'])));
        $this->assertSame([], $this->logged);
    }

    /**
     * A long id list would grow the SELECT with the hash, and past a size the DB stops using its indexes.
     */
    #[Test]
    public function theStatusReadIsTheSameQueryWhateverTheNumberOfRegisteredPids(): void
    {
        $sqlFor = function (array $fields): string {
            $this->queries = [];
            $redis         = new MtQueueLoadReaderFakeRedis();
            $redis->hashes['analysis_queue_P4_live_pids'] = array_fill_keys($fields, MtQueueLoadReader::PUBLISHED);
            $this->reader()->read($this->amq($redis, []), ['P4' => self::p4()]);

            return $this->queries[0][0];
        };

        $this->assertSame(
            $sqlFor(['11', '12', '13']),
            $sqlFor(array_map('strval', range(11, 5010)))
        );
    }

    #[Test]
    public function readSkipsTheDatabaseAndTheCountersWhenNoPidIsRegistered(): void
    {
        $redis = new MtQueueLoadReaderFakeRedis();

        $loads = $this->reader()->read($this->amq($redis, ['analysis_queue_P4' => 0]), ['P4' => self::p4()]);

        $this->assertSame([], $loads[0]->livePids);
        $this->assertSame(0, $loads[0]->liveProjects);
        $this->assertSame(0, $loads[0]->segmentLoad);
        $this->assertSame([], $this->queries);
        $this->assertSame(['hgetall', 'scard'], array_column($redis->calls, 0));
    }

    #[Test]
    public function readReturnsNoLoadWithoutAnMtQueue(): void
    {
        $redis = new MtQueueLoadReaderFakeRedis();

        $this->assertSame([], $this->reader()->read($this->amq($redis, []), []));
        $this->assertSame([], $redis->calls);
    }

    /**
     * Rows: readQueueSize() result, whether the hash read fails, expected segment load.
     *
     * @return array<string, array{int|null, bool, int|null}>
     */
    public static function segmentLoadProvider(): array
    {
        return [
            'broker size above remaining work' => [500, false, 500],
            'remaining work above broker size' => [10, false, 60], // scheduled requeues leave QueueSize
            'broker size unknown'              => [null, false, 60],
            'Redis down, broker size known'    => [500, true, 500],
            'both unknown'                     => [null, true, null],
        ];
    }

    #[Test]
    #[DataProvider('segmentLoadProvider')]
    public function readTakesTheHigherOfBrokerSizeAndRemainingWork(?int $queueSize, bool $hashReadFails, ?int $expected): void
    {
        $redis = new MtQueueLoadReaderFakeRedis();
        self::seedOneLiveProject($redis);
        $redis->failHashes = $hashReadFails;

        $loads = $this->reader([11])->read($this->amq($redis, ['analysis_queue_P4' => $queueSize]), ['P4' => self::p4()]);

        $this->assertSame($expected, $loads[0]->segmentLoad);
        $this->assertSame($hashReadFails ? null : 1, $loads[0]->liveProjects);
        $this->assertSame($hashReadFails ? [] : [11], $loads[0]->livePids);
    }

    #[Test]
    public function readAssumesOneWorkerWhenThePidSetCannotBeRead(): void
    {
        $redis = new MtQueueLoadReaderFakeRedis();
        self::seedOneLiveProject($redis);
        $redis->failSets = true;

        $loads = $this->reader([11])->read($this->amq($redis, ['analysis_queue_P4' => 500]), ['P4' => self::p4()]);

        $this->assertSame(1, $loads[0]->workers);
        $this->assertTrue($loads[0]->isAvailable());
        $this->assertSame(
            [['queue' => 'P4', 'part' => 'workers', 'error' => 'set read failed']],
            $this->loggedContexts('mt_queue_load_unreadable')
        );
    }

    #[Test]
    public function readWarnsOncePerMinuteForEachUnreadablePart(): void
    {
        $redis = new MtQueueLoadReaderFakeRedis();
        self::seedOneLiveProject($redis);
        $redis->failHashes = true;
        $reader = $this->reader([11]);

        $partsLoggedAt = [];
        foreach ([1000, 1030, 1061] as $now) {
            $this->now    = $now;
            $this->logged = [];
            $reader->read($this->amq($redis, []), ['P4' => self::p4()]);
            $partsLoggedAt[$now] = array_map(
                fn (array $context): string => $context['queue'] . ':' . $context['part'],
                $this->loggedContexts('mt_queue_load_unreadable')
            );
        }

        $this->assertSame([
            1000 => ['P4:queue_size', 'P4:projects'],
            1030 => [],
            1061 => ['P4:queue_size', 'P4:projects'],
        ], $partsLoggedAt);
    }

    #[Test]
    public function aRegisteredPidExpiresOnTheRealRedisAndLeavesOnceItIsNoLongerLive(): void
    {
        $redis   = (new RedisHandler())->getConnection();
        $context = Context::buildFromArray(['queue_name' => 'analysis_queue_test_' . uniqid(), 'max_executors' => 1]);
        $key     = MtQueueLoadReader::livePidsKey($context);

        try {
            $this->reader()->register($this->amq($redis, []), 'P4', $context, 77, MtQueueLoadReader::PUBLISHING);
            $this->reader()->register($this->amq($redis, []), 'P4', $context, 77, MtQueueLoadReader::PUBLISHED);
            $this->assertSame(['77' => MtQueueLoadReader::PUBLISHED], $redis->hgetall($key));

            $ttl = $redis->httl($key, ['77'])[0];
            $this->assertGreaterThan(86000, $ttl);
            $this->assertLessThanOrEqual(86400, $ttl);

            $live = $this->reader([77])->read($this->amq($redis, [$context->queue_name => 0]), ['P4' => $context]);
            $this->assertSame([77], $live[0]->livePids);

            $done = $this->reader()->read($this->amq($redis, [$context->queue_name => 0]), ['P4' => $context]);
            $this->assertSame([], $done[0]->livePids);
            $this->assertSame(0, $redis->exists($key));
        } finally {
            $redis->del($key);
        }
    }
}

/**
 * Recording Redis double for the commands the reader sends. Predis\Client dispatches commands through
 * __call, which PHPUnit cannot double, so this subclass declares them.
 */
class MtQueueLoadReaderFakeRedis extends PredisClient
{
    /** @var list<array<int, mixed>> ordered [command, ...args] tuples */
    public array $calls = [];

    /** @var array<string, array<string, string>> field => value per hash */
    public array $hashes = [];

    /** @var array<string, list<string>> members per set */
    public array $sets = [];

    /** @var array<string, string> string values per key */
    public array $values = [];

    public bool $failHashes = false;

    public bool $failSets = false;

    public function __construct()
    {
        // no connection: every command used is declared below
    }

    /**
     * @param array<int|string, string> $fieldValueMap
     */
    public function hsetex(string $key, array $fieldValueMap, string $setModifier = '', string $ttlModifier = '', int|bool $ttlModifierValue = false): int
    {
        $this->calls[] = ['hsetex', $key, $fieldValueMap, $setModifier, $ttlModifier, $ttlModifierValue];
        if ($this->failHashes) {
            throw new RuntimeException('Redis down');
        }

        return 1;
    }

    /**
     * @return array<string, string>
     */
    public function hgetall(string $key): array
    {
        $this->calls[] = ['hgetall', $key];
        if ($this->failHashes) {
            throw new RuntimeException('Redis down');
        }

        return $this->hashes[$key] ?? [];
    }

    /**
     * @param list<string> $fields
     */
    public function hdel(string $key, array $fields): int
    {
        $this->calls[] = ['hdel', $key, $fields];
        $this->hashes[$key] = array_diff_key($this->hashes[$key] ?? [], array_flip($fields));

        return count($fields);
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string|null>
     */
    public function mget(array $keys): array
    {
        $this->calls[] = ['mget', $keys];

        return array_map(fn (string $key): ?string => $this->values[$key] ?? null, $keys);
    }

    public function scard(string $key): int
    {
        $this->calls[] = ['scard', $key];
        if ($this->failSets) {
            throw new RuntimeException('set read failed');
        }

        return count($this->sets[$key] ?? []);
    }
}
