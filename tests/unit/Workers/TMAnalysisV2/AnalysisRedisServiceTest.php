<?php

namespace Tests\Unit\Workers\TMAnalysisV2;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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

class AnalysisRedisServiceTest extends TestCase
{
    private RedisClientSpy $redisSpy;
    private AnalysisRedisService $service;

    protected function setUp(): void
    {
        $this->redisSpy = new RedisClientSpy();

        $amqHandlerMock = @$this->createStub(AMQHandler::class);
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
            [RedisKeys::PROJECT_INIT_SEMAPHORE . $pid, 1, 'EX', 86400, 'NX'],
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
    public function incrementAnalyzedCount_callsThreeIncrbyWithScaledValues(): void
    {
        $pid         = 42;
        $numSegments = 5;
        $eqWc        = 10;
        $stWc        = 8;

        $this->service->incrementAnalyzedCount($pid, $numSegments, $eqWc, $stWc);

        $calls = $this->redisSpy->getCallsFor('incrby');
        $this->assertCount(3, $calls);

        $this->assertSame(
            [RedisKeys::PROJ_EQ_WORD_COUNT . $pid, $eqWc * RedisKeys::WORD_COUNT_SCALE],
            $calls[0]['args']
        );
        $this->assertSame(
            [RedisKeys::PROJ_ST_WORD_COUNT . $pid, $stWc * RedisKeys::WORD_COUNT_SCALE],
            $calls[1]['args']
        );
        $this->assertSame(
            [RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, $numSegments],
            $calls[2]['args']
        );
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
            [RedisKeys::PROJECT_ENDING_SEMAPHORE . $pid, 1, 'EX', 86400, 'NX'],
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
    public function reAddProjectToQueue_callsRpushWithPidArray(): void
    {
        $queueKey = 'my_queue';
        $pid      = 42;

        $this->service->reAddProjectToQueue($queueKey, $pid);

        $calls = $this->redisSpy->getCallsFor('rpush');
        $this->assertCount(1, $calls);
        $this->assertSame([$queueKey, [$pid]], $calls[0]['args']);
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
    public function waitForInitialization_returnsImmediately_whenKeyExists(): void
    {
        $pid = 42;
        $this->redisSpy->setReturnForKey(RedisKeys::PROJECT_TOT_SEGMENTS . $pid, '50');

        $this->service->waitForInitialization($pid, 5000);

        $getCalls = $this->redisSpy->getCallsFor('get');
        $this->assertCount(1, $getCalls);
        $this->assertSame([RedisKeys::PROJECT_TOT_SEGMENTS . $pid], $getCalls[0]['args']);
    }

    #[Test]
    public function waitForInitialization_pollsMultipleTimes_whenKeyMissing(): void
    {
        $pid = 42;
        // Don't set the key — it stays null

        $start = hrtime(true);
        $this->service->waitForInitialization($pid, 100);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        $getCalls = $this->redisSpy->getCallsFor('get');
        // With maxWait=100, backoff starts at 50ms: iteration1 sleeps 50 (waited=50), iteration2 sleeps 100 (waited=150 > 100 exit)
        $this->assertCount(2, $getCalls);
        $this->assertGreaterThanOrEqual(100, $elapsedMs);
    }
}
