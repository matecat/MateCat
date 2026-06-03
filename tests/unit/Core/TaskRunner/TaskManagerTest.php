<?php

namespace Matecat\Core\TaskRunner;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Utils\ActiveMQ\AMQHandler;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\ContextList;
use Utils\TaskRunner\Commons\ProcessControlInterface;
use Utils\TaskRunner\TaskManager;

#[AllowMockObjectsWithoutExpectations]
class TaskManagerTest extends AbstractTest
{
    private MockObject&ProcessControlInterface $mockProcessControl;
    private MockObject&AMQHandler $mockAmqHandler;
    private int $originalInstanceId;
    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockProcessControl = $this->createMock(ProcessControlInterface::class);
        $this->mockProcessControl->method('getHostname')->willReturn('testhost');
        $this->mockProcessControl->method('getPid')->willReturn(99999);

        $this->mockAmqHandler = $this->createMock(AMQHandler::class);

        $this->originalInstanceId = AppConfig::$INSTANCE_ID;
        AppConfig::$INSTANCE_ID = 42;
    }

    protected function tearDown(): void
    {
        AppConfig::$INSTANCE_ID = $this->originalInstanceId;
        foreach ($this->tempFiles as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function makeManager(?ContextList $contextList = null, int $runningPids = 0): TaskManager
    {
        $ref = new ReflectionClass(TaskManager::class);
        /** @var TaskManager $mgr */
        $mgr = $ref->newInstanceWithoutConstructor();

        $props = [
            'processControl'    => $this->mockProcessControl,
            'queueHandler'      => $this->mockAmqHandler,
            '_queueContextList' => $contextList ?? ContextList::get(),
            'RUNNING'           => true,
            'myProcessPid'      => 99999,
            '_runningPids'      => $runningPids,
            '_destroyContext'   => [],
            '_configFile'       => '',
            '_contextIndex'     => null,
        ];

        foreach ($props as $name => $value) {
            $ref->getProperty($name)->setValue($mgr, $value);
        }

        $ref->getProperty('logger')->setValue(
            $mgr,
            LoggerFactory::getLogger('task_manager', 'test_task_manager.log')
        );

        return $mgr;
    }

    private function contextList(array $queues): ContextList
    {
        $raw = [];
        foreach ($queues as $name => $config) {
            $raw[$name] = [
                'queue_name'    => $name,
                'max_executors' => $config['max'] ?? 0,
            ];
        }
        $list = ContextList::get($raw);
        foreach ($queues as $name => $config) {
            if (isset($config['pids'])) {
                $list->list[$name]->pid_list_len = $config['pids'];
            }
        }
        return $list;
    }

    private function createRedis(array $returns = []): TaskManagerTestRedis
    {
        return new TaskManagerTestRedis($returns);
    }

    private function writeTempConfig(array $queues): string
    {
        $content = "loggerName = \"test.log\"\n\n[context_definitions]\n";
        foreach ($queues as $name => $max) {
            $content .= "{$name}[queue_name] = \"{$name}\"\n";
            $content .= "{$name}[max_executors] = {$max}\n";
        }
        $path = tempnam(sys_get_temp_dir(), 'tm_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    private function getRunningPids(TaskManager $mgr): int
    {
        return (new ReflectionClass($mgr))->getProperty('_runningPids')->getValue($mgr);
    }

    private function getDestroyContext(TaskManager $mgr): array
    {
        return (new ReflectionClass($mgr))->getProperty('_destroyContext')->getValue($mgr);
    }

    private function getContextList(TaskManager $mgr): ContextList
    {
        return (new ReflectionClass($mgr))->getProperty('_queueContextList')->getValue($mgr);
    }

    // ─── _killPids() tests ──────────────────────────────────────────────────────

    #[Test]
    public function test_killPids_specific_pid_from_specific_queue(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 3]]);
        $redis = $this->createRedis(['srem' => [1], 'scard' => [2]]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->expects($this->once())
            ->method('kill')->with(42, SIGTERM);

        $mgr = $this->makeManager($ctx, 5);
        (new ReflectionMethod(TaskManager::class, '_killPids'))->invoke($mgr, $ctx->list['q1'], 42, 0);

        $this->assertSame(2, $ctx->list['q1']->pid_list_len);
        $this->assertSame(4, $this->getRunningPids($mgr));
    }

    #[Test]
    public function test_killPids_seek_and_destroy(): void
    {
        $ctx = $this->contextList([
            'q1' => ['max' => 2, 'pids' => 3],
            'q2' => ['max' => 1, 'pids' => 2],
        ]);
        $redis = $this->createRedis(['srem' => [0, 1], 'scard' => [1]]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->expects($this->once())
            ->method('kill')->with(42, SIGTERM);

        $mgr = $this->makeManager($ctx, 5);
        (new ReflectionMethod(TaskManager::class, '_killPids'))->invoke($mgr, null, 42, 0);

        $this->assertSame(1, $ctx->list['q2']->pid_list_len);
        $this->assertSame(4, $this->getRunningPids($mgr));
    }

    #[Test]
    public function test_killPids_n_from_specific_queue(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 5, 'pids' => 3]]);
        $redis = $this->createRedis([
            'scard'    => [3, 2, 1, 1],
            'smembers' => [['100:testhost:42', '101:testhost:42', '102:otherhost:other']],
            'srem'     => [1, 1],
        ]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->expects($this->exactly(2))->method('kill');

        $mgr = $this->makeManager($ctx, 5);
        (new ReflectionMethod(TaskManager::class, '_killPids'))->invoke($mgr, $ctx->list['q1'], 0, 2);

        $this->assertSame(3, $this->getRunningPids($mgr));
    }

    #[Test]
    public function test_killPids_all_from_queue(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 2]]);
        $redis = $this->createRedis([
            'scard'    => [2],
            'smembers' => [['100:testhost:42', '101:testhost:42']],
            'srem'     => [1, 1],
        ]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->expects($this->exactly(2))->method('kill');

        $mgr = $this->makeManager($ctx, 4);
        (new ReflectionMethod(TaskManager::class, '_killPids'))->invoke($mgr, $ctx->list['q1']);

        $this->assertSame(0, $ctx->list['q1']->pid_list_len);
        $this->assertSame(2, $this->getRunningPids($mgr));
    }

    #[Test]
    public function test_killPids_n_balanced_across_queues(): void
    {
        $ctx = $this->contextList([
            'q1' => ['max' => 2, 'pids' => 3],
            'q2' => ['max' => 1, 'pids' => 2],
        ]);
        $redis = $this->createRedis([
            'spop'  => ['100:testhost:42', '200:testhost:42'],
            'scard' => [2, 1],
        ]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->expects($this->exactly(2))->method('kill');

        $mgr = $this->makeManager($ctx, 5);
        (new ReflectionMethod(TaskManager::class, '_killPids'))->invoke($mgr, null, 0, 2);

        $this->assertSame(3, $this->getRunningPids($mgr));
    }

    #[Test]
    public function test_killPids_all_processes(): void
    {
        $ctx = $this->contextList([
            'q1' => ['max' => 2, 'pids' => 1],
            'q2' => ['max' => 1, 'pids' => 1],
        ]);
        $redis = $this->createRedis([
            'smembers' => [['100:testhost:42'], ['200:testhost:42']],
            'srem'     => [1, 1],
        ]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->expects($this->exactly(2))->method('kill');

        $mgr = $this->makeManager($ctx, 2);
        (new ReflectionMethod(TaskManager::class, '_killPids'))->invoke($mgr);

        $this->assertSame(0, $ctx->list['q1']->pid_list_len);
        $this->assertSame(0, $ctx->list['q2']->pid_list_len);
        $this->assertSame(0, $this->getRunningPids($mgr));
    }

    // ─── _waitPid() tests ───────────────────────────────────────────────────────

    #[Test]
    public function test_waitPid_no_dead_children(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 2]]);
        $this->mockProcessControl->expects($this->once())
            ->method('waitPid')->willReturn(0);

        $mgr = $this->makeManager($ctx);
        (new ReflectionMethod(TaskManager::class, '_waitPid'))->invoke($mgr);

        $this->assertSame(2, $ctx->list['q1']->pid_list_len);
    }

    #[Test]
    public function test_waitPid_clean_exit(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 3]]);
        $redis = $this->createRedis(['sismember' => [0], 'scard' => [2]]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->method('waitPid')
            ->willReturnOnConsecutiveCalls(42, 0);

        $mgr = $this->makeManager($ctx);
        (new ReflectionMethod(TaskManager::class, '_waitPid'))->invoke($mgr);

        $this->assertSame(2, $ctx->list['q1']->pid_list_len);
    }

    #[Test]
    public function test_waitPid_unexpected_death_triggers_killPids(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 3]]);
        $redis = $this->createRedis([
            'sismember' => [1],
            'srem'      => [1],
            'scard'     => [2],
        ]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->method('waitPid')
            ->willReturnOnConsecutiveCalls(42, 0);
        $this->mockProcessControl->expects($this->once())
            ->method('kill')->with(42, SIGTERM);

        $mgr = $this->makeManager($ctx);
        (new ReflectionMethod(TaskManager::class, '_waitPid'))->invoke($mgr);

        $this->assertSame(2, $ctx->list['q1']->pid_list_len);
    }

    #[Test]
    public function test_waitPid_multiple_dead_children(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 5]]);
        $redis = $this->createRedis(['sismember' => [0, 0], 'scard' => [4, 3]]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->method('waitPid')
            ->willReturnOnConsecutiveCalls(42, 43, 0);

        $mgr = $this->makeManager($ctx);
        (new ReflectionMethod(TaskManager::class, '_waitPid'))->invoke($mgr);

        $this->assertSame(3, $ctx->list['q1']->pid_list_len);
    }

    // ─── _forkProcesses() tests ─────────────────────────────────────────────────

    #[Test]
    public function test_forkProcesses_fork_fails(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 0]]);
        $this->mockProcessControl->method('fork')->willReturn(-1);

        $mgr = $this->makeManager($ctx);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(TaskManager::ERR_NOT_FORK);
        (new ReflectionMethod(TaskManager::class, '_forkProcesses'))->invoke($mgr, 1, $ctx->list['q1']);
    }

    #[Test]
    public function test_forkProcesses_parent_increments_counters(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 0]]);
        $this->mockProcessControl->method('fork')
            ->willReturnOnConsecutiveCalls(12345, 12346);

        $mgr = $this->makeManager($ctx);
        (new ReflectionMethod(TaskManager::class, '_forkProcesses'))->invoke($mgr, 2, $ctx->list['q1']);

        $this->assertSame(2, $this->getRunningPids($mgr));
        $this->assertSame(2, $ctx->list['q1']->pid_list_len);
    }

    #[Test]
    public function test_forkProcesses_child_calls_exec(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 1, 'pids' => 0]]);
        $this->mockProcessControl->method('fork')->willReturn(0);
        $this->mockProcessControl->expects($this->once())
            ->method('exec')
            ->with('/usr/bin/php', $this->anything())
            ->willThrowException(new RuntimeException('exec mock'));

        $mgr = $this->makeManager($ctx);

        $this->expectException(RuntimeException::class);
        (new ReflectionMethod(TaskManager::class, '_forkProcesses'))->invoke($mgr, 1, $ctx->list['q1']);
    }

    // ─── _updateConfiguration() tests ───────────────────────────────────────────

    #[Test]
    public function test_updateConfiguration_first_execution_loads_from_config(): void
    {
        $configFile = $this->writeTempConfig(['q1' => 2, 'q2' => 3]);
        $mgr = $this->makeManager(ContextList::get());
        (new ReflectionClass(TaskManager::class))->getProperty('_configFile')->setValue($mgr, $configFile);

        (new ReflectionMethod(TaskManager::class, '_updateConfiguration'))->invoke($mgr);

        $list = $this->getContextList($mgr);
        $this->assertCount(2, $list->list);
        $this->assertSame(2, $list->list['q1']->max_executors);
        $this->assertSame(3, $list->list['q2']->max_executors);
    }

    #[Test]
    public function test_updateConfiguration_adds_new_context(): void
    {
        $configFile = $this->writeTempConfig(['q1' => 2, 'q2' => 3]);
        $existing = $this->contextList(['q1' => ['max' => 2, 'pids' => 1]]);
        $mgr = $this->makeManager($existing);
        (new ReflectionClass(TaskManager::class))->getProperty('_configFile')->setValue($mgr, $configFile);

        (new ReflectionMethod(TaskManager::class, '_updateConfiguration'))->invoke($mgr);

        $list = $this->getContextList($mgr);
        $this->assertCount(2, $list->list);
        $this->assertArrayHasKey('q2', $list->list);
        $this->assertSame(3, $list->list['q2']->max_executors);
    }

    #[Test]
    public function test_updateConfiguration_removes_context_to_destroy_list(): void
    {
        $configFile = $this->writeTempConfig(['q1' => 2]);
        $existing = $this->contextList([
            'q1' => ['max' => 2, 'pids' => 1],
            'q2' => ['max' => 3, 'pids' => 2],
        ]);
        $mgr = $this->makeManager($existing);
        (new ReflectionClass(TaskManager::class))->getProperty('_configFile')->setValue($mgr, $configFile);

        (new ReflectionMethod(TaskManager::class, '_updateConfiguration'))->invoke($mgr);

        $list = $this->getContextList($mgr);
        $this->assertCount(1, $list->list);
        $this->assertArrayNotHasKey('q2', $list->list);

        $destroy = $this->getDestroyContext($mgr);
        $this->assertCount(1, $destroy);
        $this->assertSame('q2', $destroy[0]->queue_name);
    }

    #[Test]
    public function test_updateConfiguration_updates_max_executors(): void
    {
        $configFile = $this->writeTempConfig(['q1' => 5]);
        $existing = $this->contextList(['q1' => ['max' => 2, 'pids' => 1]]);
        $mgr = $this->makeManager($existing);
        (new ReflectionClass(TaskManager::class))->getProperty('_configFile')->setValue($mgr, $configFile);

        (new ReflectionMethod(TaskManager::class, '_updateConfiguration'))->invoke($mgr);

        $list = $this->getContextList($mgr);
        $this->assertSame(5, $list->list['q1']->max_executors);
        $this->assertSame(1, $list->list['q1']->pid_list_len);
    }

    // ─── _cleanContexts() tests ─────────────────────────────────────────────────

    #[Test]
    public function test_cleanContexts_empty_list_does_nothing(): void
    {
        $mgr = $this->makeManager($this->contextList(['q1' => ['max' => 2, 'pids' => 1]]));

        (new ReflectionMethod(TaskManager::class, '_cleanContexts'))->invoke($mgr);

        $this->assertEmpty($this->getDestroyContext($mgr));
    }

    #[Test]
    public function test_cleanContexts_kills_stale_context(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 1]]);
        $stale = Context::buildFromArray(['queue_name' => 'stale_q', 'max_executors' => 1]);
        $redis = $this->createRedis([
            'scard'    => [1],
            'smembers' => [['500:testhost:42']],
            'srem'     => [1],
        ]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->expects($this->once())->method('kill');

        $mgr = $this->makeManager($ctx);
        (new ReflectionClass(TaskManager::class))->getProperty('_destroyContext')->setValue($mgr, [$stale]);

        (new ReflectionMethod(TaskManager::class, '_cleanContexts'))->invoke($mgr);

        $this->assertEmpty($this->getDestroyContext($mgr));
    }

    #[Test]
    public function test_cleanContexts_kills_multiple_stale_contexts(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 1]]);
        $stale1 = Context::buildFromArray(['queue_name' => 'stale1', 'max_executors' => 1]);
        $stale2 = Context::buildFromArray(['queue_name' => 'stale2', 'max_executors' => 1]);
        $redis = $this->createRedis([
            'scard'    => [1, 1],
            'smembers' => [['600:testhost:42'], ['700:testhost:42']],
            'srem'     => [1, 1],
        ]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->expects($this->exactly(2))->method('kill');

        $mgr = $this->makeManager($ctx);
        (new ReflectionClass(TaskManager::class))->getProperty('_destroyContext')->setValue($mgr, [$stale1, $stale2]);

        (new ReflectionMethod(TaskManager::class, '_cleanContexts'))->invoke($mgr);

        $this->assertEmpty($this->getDestroyContext($mgr));
    }

    // ─── main() tests ─────────────────────────────────────────────────────────

    #[Test]
    public function test_main_exits_when_not_running(): void
    {
        $configFile = $this->writeTempConfig(['q1' => 2]);
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 2]]);
        $redis = $this->createRedis([
            'sadd'      => [1],
            'sismember' => [1],
            'smembers'  => [[]],
            'srem'      => [1],
        ]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->method('waitPid')->willReturn(0);

        $mgr = $this->makeManager($ctx);
        $ref = new ReflectionClass(TaskManager::class);
        $ref->getProperty('RUNNING')->setValue($mgr, false);
        $ref->getProperty('_configFile')->setValue($mgr, $configFile);

        $sleepProp = $ref->getProperty('sleepTime');
        $origSleep = $sleepProp->getValue(null);
        $sleepProp->setValue(null, 0);

        try {
            $mgr->main();
            $this->assertSame(0, $this->getRunningPids($mgr));
        } finally {
            $sleepProp->setValue(null, $origSleep);
        }
    }

    #[Test]
    public function test_main_fork_failure_stops_running(): void
    {
        $configFile = $this->writeTempConfig(['q1' => 2]);
        $ctx = $this->contextList(['q1' => ['max' => 2, 'pids' => 0]]);
        $redis = $this->createRedis([
            'sadd'      => [1],
            'sismember' => [1],
            'smembers'  => [[]],
            'srem'      => [1],
        ]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->method('waitPid')->willReturn(0);
        $this->mockProcessControl->method('fork')->willReturn(-1);

        $mgr = $this->makeManager($ctx);
        $ref = new ReflectionClass(TaskManager::class);
        $ref->getProperty('_configFile')->setValue($mgr, $configFile);

        $sleepProp = $ref->getProperty('sleepTime');
        $origSleep = $sleepProp->getValue(null);
        $sleepProp->setValue(null, 0);

        try {
            $mgr->main();
            $this->assertFalse($mgr->RUNNING);
        } finally {
            $sleepProp->setValue(null, $origSleep);
        }
    }

    // ─── cleanShutDown() tests ──────────────────────────────────────────────────

    #[Test]
    public function test_cleanShutDown_kills_all_and_disconnects(): void
    {
        $ctx = $this->contextList(['q1' => ['max' => 1, 'pids' => 1]]);
        $redis = $this->createRedis([
            'smembers' => [['100:testhost:42']],
            'srem'     => [1, 1],
        ]);
        $this->mockAmqHandler->method('getRedisClient')->willReturn($redis);
        $this->mockProcessControl->expects($this->once())->method('kill');

        $mgr = $this->makeManager($ctx, 1);
        $mgr->cleanShutDown();

        $this->assertSame(0, $this->getRunningPids($mgr));
        $disconnectCalls = array_filter($redis->calls, fn($c) => $c[0] === 'disconnect');
        $this->assertCount(1, $disconnectCalls);
    }

    // ─── requireQueueHandler() tests ────────────────────────────────────────────

    #[Test]
    public function test_requireQueueHandler_throws_when_null(): void
    {
        $ref = new ReflectionClass(TaskManager::class);
        /** @var TaskManager $mgr */
        $mgr = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('queueHandler')->setValue($mgr, null);
        $ref->getProperty('logger')->setValue($mgr, LoggerFactory::getLogger('task_manager', 'test.log'));

        $method = new ReflectionMethod(TaskManager::class, 'requireQueueHandler');

        $this->expectException(RuntimeException::class);
        $method->invoke($mgr);
    }
}

// ─── Test Redis stub ────────────────────────────────────────────────────────────

class TaskManagerTestRedis extends \Predis\Client
{
    /** @var array<string, list<mixed>> */
    private array $returns;
    /** @var list<array{0: string, 1?: mixed, 2?: mixed}> */
    public array $calls = [];

    public function __construct(array $returns = [])
    {
        $this->returns = $returns;
    }

    public function srem($key, $members): int
    {
        $this->calls[] = ['srem', $key, $members];
        return (int)(array_shift($this->returns['srem']) ?? 1);
    }

    public function scard($key): int
    {
        $this->calls[] = ['scard', $key];
        return (int)(array_shift($this->returns['scard']) ?? 0);
    }

    public function smembers($key): array
    {
        $this->calls[] = ['smembers', $key];
        return array_shift($this->returns['smembers']) ?? [];
    }

    public function sadd($key, array $members): int
    {
        $this->calls[] = ['sadd', $key, $members];
        return (int)(array_shift($this->returns['sadd']) ?? 1);
    }

    public function sismember($key, $member): int
    {
        $this->calls[] = ['sismember', $key, $member];
        return (int)(array_shift($this->returns['sismember']) ?? 0);
    }

    public function spop($key): string|false
    {
        $this->calls[] = ['spop', $key];
        return array_shift($this->returns['spop']) ?? false;
    }

    public function disconnect(): void
    {
        $this->calls[] = ['disconnect'];
    }
}
