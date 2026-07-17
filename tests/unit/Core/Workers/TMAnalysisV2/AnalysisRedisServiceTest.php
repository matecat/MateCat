<?php

namespace Matecat\Core\Workers\TMAnalysisV2;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Generator\Generator;
use Predis\Client;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\RedisKeys;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\AnalysisRedisService;

class RedisClientSpy extends Client
{
    private array $calls = [];
    private array $returnsByKey = [];
    private array $returnsByMethod = [];

    public function __construct() {}

    public function setReturn(string $method, mixed $value): void
    {
        $this->returnsByMethod[strtolower($method)] = $value;
    }

    public function setReturnForKey(string $key, mixed $value): void
    {
        $this->returnsByKey[$key] = $value;
    }

    public function __call($commandID, $arguments): mixed
    {
        $method = strtolower($commandID);
        $this->calls[] = ['method' => $method, 'args' => $arguments];

        $firstArg = $arguments[0] ?? null;
        if (is_string($firstArg) && isset($this->returnsByKey[$firstArg])) {
            return $this->returnsByKey[$firstArg];
        }

        return $this->returnsByMethod[$method] ?? null;
    }

    /**
     * Override transaction() to execute the callback against $this so
     * inner commands are recorded by __call(). Real Client::transaction()
     * creates a MultiExecTransaction context we can't easily spy on.
     */
    public function transaction(...$arguments): array|null
    {
        $callable = null;
        foreach ($arguments as $arg) {
            if (is_callable($arg)) {
                $callable = $arg;
                break;
            }
        }

        if ($callable !== null) {
            $callable($this);
        }

        return [];
    }

    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getCallsFor(string $method): array
    {
        return array_values(
            array_filter($this->calls, fn($c) => $c['method'] === strtolower($method))
        );
    }

    public function countCallsFor(string $method): int
    {
        return count($this->getCallsFor($method));
    }
}

class AnalysisRedisServiceTest extends AbstractTest
{
    private RedisClientSpy $redisSpy;
    private AnalysisRedisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redisSpy = new RedisClientSpy();

        $amqHandlerMock = (new Generator())->testDouble(AMQHandler::class, true);
        $amqHandlerMock->method('getRedisClient')->willReturn($this->redisSpy);

        $this->service = new AnalysisRedisService($amqHandlerMock);
    }

    #[Test]
    public function acquireInitLock_returnsTrue_whenRedisSetReturnsOk(): void
    {
        $pid = 42;
        $this->redisSpy->setReturn('set', 'OK');

        $this->assertTrue($this->service->acquireInitLock($pid));

        $calls = $this->redisSpy->getCallsFor('set');
        $this->assertCount(1, $calls);
        $this->assertSame(
            [RedisKeys::PROJECT_INIT_SEMAPHORE . $pid, 1, 'EX', 30, 'NX'],
            $calls[0]['args']
        );
    }

    #[Test]
    public function acquireInitLock_returnsFalse_whenRedisSetReturnsNull(): void
    {
        $pid = 42;

        $this->assertFalse($this->service->acquireInitLock($pid));
        $this->assertCount(1, $this->redisSpy->getCallsFor('set'));
    }

    #[Test]
    public function setProjectTotalSegments_callsSetexWithCorrectArgs(): void
    {
        $pid   = 42;
        $total = 100;

        $this->service->setProjectTotalSegments($pid, $total);

        $calls = $this->redisSpy->getCallsFor('setex');
        $this->assertCount(1, $calls);
        $this->assertSame(
            [RedisKeys::PROJECT_TOT_SEGMENTS . $pid, 86400, $total],
            $calls[0]['args']
        );
    }

    #[Test]
    public function getProjectTotalSegments_returnsInt_whenKeyExists(): void
    {
        $pid = 42;
        $this->redisSpy->setReturnForKey(RedisKeys::PROJECT_TOT_SEGMENTS . $pid, '200');

        $this->assertSame(200, $this->service->getProjectTotalSegments($pid));

        $calls = $this->redisSpy->getCallsFor('get');
        $this->assertCount(1, $calls);
        $this->assertSame([RedisKeys::PROJECT_TOT_SEGMENTS . $pid], $calls[0]['args']);
    }

    #[Test]
    public function getProjectTotalSegments_returnsNull_whenKeyMissing(): void
    {
        $this->assertNull($this->service->getProjectTotalSegments(42));
    }

    #[Test]
    public function getProjectAnalyzedCount_returnsInt_whenKeyExists(): void
    {
        $pid = 42;
        $this->redisSpy->setReturnForKey(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, '50');

        $this->assertSame(50, $this->service->getProjectAnalyzedCount($pid));

        $calls = $this->redisSpy->getCallsFor('get');
        $this->assertCount(1, $calls);
        $this->assertSame([RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid], $calls[0]['args']);
    }

    #[Test]
    public function getProjectAnalyzedCount_returnsNull_whenKeyMissing(): void
    {
        $this->assertNull($this->service->getProjectAnalyzedCount(42));
    }

    #[Test]
    public function incrementAnalyzedCount_runsIdempotentEvalWithScaledValues(): void
    {
        $pid       = 42;
        $idSegment = 777;
        $idJob     = 3;
        $eqWc      = 10;
        $stWc      = 8;

        $this->service->incrementAnalyzedCount($pid, $idSegment, $idJob, $eqWc, $stWc);

        // The counter update must be a single atomic Lua script (idempotent under the
        // applyPostCommitSideEffects retry loop), not a bare MULTI of INCRBYs.
        $this->assertCount(0, $this->redisSpy->getCallsFor('incrby'));

        $calls = $this->redisSpy->getCallsFor('eval');
        $this->assertCount(1, $calls);

        $args = $calls[0]['args'];
        // args: [script, numkeys, KEYS..., ARGV...]
        $this->assertIsString($args[0]);
        $this->assertStringContainsString('SADD', $args[0]);
        $this->assertStringContainsString('SCARD', $args[0]);
        $this->assertSame(4, $args[1]);
        // KEYS: idempotency set, analyzed counter, eq wc, st wc
        $this->assertSame(RedisKeys::PROJECT_ANALYZED_SEGMENTS_SET . $pid, $args[2]);
        $this->assertSame(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, $args[3]);
        $this->assertSame(RedisKeys::PROJ_EQ_WORD_COUNT . $pid, $args[4]);
        $this->assertSame(RedisKeys::PROJ_ST_WORD_COUNT . $pid, $args[5]);
        // ARGV: id_segment:id_job (the dedup member), analyzed delta, scaled eq, scaled st, ttl
        $this->assertSame($idSegment . ':' . $idJob, $args[6]);
        $this->assertSame('1', $args[7]);
        $this->assertSame((string)($eqWc * RedisKeys::WORD_COUNT_SCALE), $args[8]);
        $this->assertSame((string)($stWc * RedisKeys::WORD_COUNT_SCALE), $args[9]);
        $this->assertSame('86400', $args[10]);
    }

    #[Test]
    public function incrementAnalyzedCount_dedupesPerSegmentAndJobNotPerSegment(): void
    {
        // A source segment shared by N target-language jobs produces N distinct analysis units
        // (one segment_translation row per (id_segment, id_job)), each of which must be counted.
        // Keying the idempotency set on id_segment ALONE collapsed them to one, so the analyzed
        // counter could never reach a total that counts per (segment, job) — multi-language
        // projects stranded at FAST_OK. The dedup member must be "id_segment:id_job".
        $pid       = 42;
        $idSegment = 777;

        $this->service->incrementAnalyzedCount($pid, $idSegment, 3, 10, 8);
        $this->service->incrementAnalyzedCount($pid, $idSegment, 4, 10, 8);

        $calls = $this->redisSpy->getCallsFor('eval');
        $this->assertCount(2, $calls);
        // same source segment, different job → two DISTINCT set members, so both increment
        $this->assertSame('777:3', $calls[0]['args'][6]);
        $this->assertSame('777:4', $calls[1]['args'][6]);
        $this->assertNotSame($calls[0]['args'][6], $calls[1]['args'][6]);
    }

    #[Test]
    public function acquireCompletionLock_returnsTrue_whenRedisSetReturnsOk(): void
    {
        $pid = 42;
        $this->redisSpy->setReturn('set', 'OK');

        $this->assertTrue($this->service->acquireCompletionLock($pid));

        $calls = $this->redisSpy->getCallsFor('set');
        $this->assertCount(1, $calls);
        $this->assertSame(
            // Bounded TTL (300s) for crash-safety; wide margin over the finalization
            // critical section it guards. NX preserved. Was 86400s.
            [RedisKeys::PROJECT_ENDING_SEMAPHORE . $pid, 1, 'EX', 300, 'NX'],
            $calls[0]['args']
        );
    }

    #[Test]
    public function acquireCompletionLock_returnsFalse_whenRedisSetReturnsNull(): void
    {
        $pid = 42;

        $this->assertFalse($this->service->acquireCompletionLock($pid));
        $this->assertCount(1, $this->redisSpy->getCallsFor('set'));
    }

    #[Test]
    public function releaseCompletionLock_callsDelWithSemaphoreKey(): void
    {
        $pid = 42;

        $this->service->releaseCompletionLock($pid);

        $calls = $this->redisSpy->getCallsFor('del');
        $this->assertCount(1, $calls);
        $this->assertSame([RedisKeys::PROJECT_ENDING_SEMAPHORE . $pid], $calls[0]['args']);
    }

    #[Test]
    public function getWorkingProjects_callsLrangeAndReturnsArray(): void
    {
        $queueKey = 'my_queue';
        $expected = ['1', '2', '3'];
        $this->redisSpy->setReturnForKey($queueKey, $expected);

        $this->assertSame($expected, $this->service->getWorkingProjects($queueKey));

        $calls = $this->redisSpy->getCallsFor('lrange');
        $this->assertCount(1, $calls);
        $this->assertSame([$queueKey, 0, -1], $calls[0]['args']);
    }

    #[Test]
    public function removeProjectFromQueue_callsLremWithStringPid(): void
    {
        $queueKey = 'my_queue';
        $pid      = 42;

        $this->service->removeProjectFromQueue($queueKey, $pid);

        $calls = $this->redisSpy->getCallsFor('lrem');
        $this->assertCount(1, $calls);
        $this->assertSame([$queueKey, 0, (string)$pid], $calls[0]['args']);
    }

    #[Test]
    public function getProjectWordCounts_makesFourGetCallsAndDividesWcBy1000(): void
    {
        $pid = 42;
        $this->redisSpy->setReturnForKey(RedisKeys::PROJECT_TOT_SEGMENTS . $pid, '100');
        $this->redisSpy->setReturnForKey(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, '50');
        $this->redisSpy->setReturnForKey(RedisKeys::PROJ_EQ_WORD_COUNT . $pid, '10000');
        $this->redisSpy->setReturnForKey(RedisKeys::PROJ_ST_WORD_COUNT . $pid, '8500');

        $result = $this->service->getProjectWordCounts($pid);

        $this->assertCount(4, $this->redisSpy->getCallsFor('get'));
        $this->assertSame('100', $result['project_segments']);
        $this->assertSame('50', $result['num_analyzed']);
        $this->assertEqualsWithDelta(10.0, $result['eq_wc'], 0.0001);
        $this->assertEqualsWithDelta(8.5, $result['st_wc'], 0.0001);
    }

    #[Test]
    public function decrementWaitingSegments_callsDecrAndReturnsInt(): void
    {
        $qid = 'job:1234';
        $this->redisSpy->setReturn('decr', 9);

        $this->assertSame(9, $this->service->decrementWaitingSegments($qid));

        $calls = $this->redisSpy->getCallsFor('decr');
        $this->assertCount(1, $calls);
        $this->assertSame([RedisKeys::TOTAL_SEGMENTS_TO_WAIT . $qid], $calls[0]['args']);
    }

    #[Test]
    public function waitForInitialization_returnsTrue_whenBothKeysExist(): void
    {
        $pid = 42;
        $this->redisSpy->setReturnForKey(RedisKeys::PROJECT_TOT_SEGMENTS . $pid, '50');
        $this->redisSpy->setReturnForKey(RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, '10');

        $result = $this->service->waitForInitialization($pid, 5000);

        $this->assertTrue($result);
        $getCalls = $this->redisSpy->getCallsFor('get');
        $this->assertCount(2, $getCalls);
    }

    #[Test]
    public function waitForInitialization_returnsFalse_whenTimeout(): void
    {
        $pid = 42;
        // Winner holds the init lock but has not published the counters yet, so waiting
        // is correct. Neither counter is set — the loser should poll until the timeout.
        $this->redisSpy->setReturnForKey(RedisKeys::PROJECT_INIT_SEMAPHORE . $pid, '1');

        $start = hrtime(true);
        $result = $this->service->waitForInitialization($pid, 100);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        $this->assertFalse($result);
        $this->assertGreaterThanOrEqual(100, $elapsedMs);
    }

    #[Test]
    public function waitForInitialization_returnsFalseImmediately_whenInitLockAbandoned(): void
    {
        $pid = 42;
        // No init semaphore and no counters: the winner abandoned initialization (failed
        // doInit released the lock, or crashed and its TTL expired). The loser must bail
        // immediately so it can re-acquire and re-init — not block for the full timeout
        // waiting on counters that will never arrive.
        $start = hrtime(true);
        $result = $this->service->waitForInitialization($pid, 5000);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        $this->assertFalse($result);
        $this->assertLessThan(100, $elapsedMs);
    }

    #[Test]
    public function waitForInitialization_waits_whenOnlyTotSegmentsExists(): void
    {
        $pid = 42;
        // Winner still holds the init lock, and only PROJECT_TOT_SEGMENTS is set —
        // PROJECT_NUM_SEGMENTS_DONE is missing (TOCTOU scenario). The loser must keep
        // polling (not early-exit) because init is still in progress.
        $this->redisSpy->setReturnForKey(RedisKeys::PROJECT_INIT_SEMAPHORE . $pid, '1');
        $this->redisSpy->setReturnForKey(RedisKeys::PROJECT_TOT_SEGMENTS . $pid, '50');

        $result = $this->service->waitForInitialization($pid, 100);

        // Should timeout because second key is missing
        $this->assertFalse($result);
    }

    #[Test]
    public function releaseInitLock_callsDelWithInitSemaphoreKey(): void
    {
        $pid = 42;

        $this->service->releaseInitLock($pid);

        $calls = $this->redisSpy->getCallsFor('del');
        $this->assertCount(1, $calls);
        $this->assertSame([RedisKeys::PROJECT_INIT_SEMAPHORE . $pid], $calls[0]['args']);
    }

    #[Test]
    public function initializeProjectCounters_delsAnalyzedSegmentsSetInsideTransaction(): void
    {
        $pid = 42;

        $this->service->initializeProjectCounters($pid, 100, 10);

        // The spy runs the transaction closure against itself, so the inner commands are
        // recorded. Each run must start with an empty idempotency set, so the closure
        // deletes PROJECT_ANALYZED_SEGMENTS_SET before writing the counters.
        $delCalls = $this->redisSpy->getCallsFor('del');
        $this->assertCount(1, $delCalls);
        $this->assertSame([RedisKeys::PROJECT_ANALYZED_SEGMENTS_SET . $pid], $delCalls[0]['args']);

        // The counters are written as ABSOLUTE resets (setex, not incrby) so re-analysis of
        // the same PID within the 24h TTL does not accumulate onto stale values. Expect four
        // setex calls: tot_segments, eq_wc, st_wc and num_segments_done. No incrby.
        $setexCalls = $this->redisSpy->getCallsFor('setex');
        $this->assertCount(4, $setexCalls);
        $this->assertSame(0, $this->redisSpy->countCallsFor('incrby'));

        $setexByKey = [];
        foreach ($setexCalls as $call) {
            $setexByKey[$call['args'][0]] = [$call['args'][1], $call['args'][2]];
        }

        $this->assertSame([86400, 100], $setexByKey[RedisKeys::PROJECT_TOT_SEGMENTS . $pid]);
        $this->assertSame([86400, 0], $setexByKey[RedisKeys::PROJ_EQ_WORD_COUNT . $pid]);
        $this->assertSame([86400, 0], $setexByKey[RedisKeys::PROJ_ST_WORD_COUNT . $pid]);
        $this->assertSame([86400, 10], $setexByKey[RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid]);
    }
}
