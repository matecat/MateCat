<?php

namespace Matecat\Core\Utils\Engines\Traits;

use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Predis\Client;
use Utils\Engines\Traits\HotSwap;
use Utils\Redis\RedisHandler;

class HotSwapTestClass
{
    use HotSwap {
        swapOn as public;
        swapOff as public;
    }
}

class FakePredisClient extends Client
{
    /** @var array<string, string> */
    public array $store = [];

    /** @var array<string, int> */
    public array $ttls = [];

    public function __construct()
    {
        // skip parent constructor — no real connection needed
    }

    public function __call($commandID, $arguments)
    {
        return match (strtolower($commandID)) {
            'setnx' => $this->fakeSetnx($arguments[0], $arguments[1]),
            'expire' => $this->fakeExpire($arguments[0], $arguments[1]),
            'get' => $this->fakeGet($arguments[0]),
            'del' => $this->fakeDel($arguments[0]),
            default => null,
        };
    }

    private function fakeSetnx(string $key, mixed $value): bool
    {
        if (isset($this->store[$key])) {
            return false;
        }
        $this->store[$key] = (string) $value;
        return true;
    }

    private function fakeExpire(string $key, int $ttl): bool
    {
        $this->ttls[$key] = $ttl;
        return true;
    }

    private function fakeGet(string $key): ?string
    {
        return $this->store[$key] ?? null;
    }

    private function fakeDel(string $key): int
    {
        if (isset($this->store[$key])) {
            unset($this->store[$key]);
            return 1;
        }
        return 0;
    }
}

class HotSwapTest extends AbstractTest
{
    private HotSwapTestClass $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new HotSwapTestClass();
    }

    private function createFakeRedisHandler(): RedisHandler&\PHPUnit\Framework\MockObject\Stub
    {
        $handler = $this->createStub(RedisHandler::class);
        $handler->method('getConnection')->willReturn(new FakePredisClient());
        return $handler;
    }

    private function createFakeRedisHandlerWith(FakePredisClient $client): RedisHandler&\PHPUnit\Framework\MockObject\Stub
    {
        $handler = $this->createStub(RedisHandler::class);
        $handler->method('getConnection')->willReturn($client);
        return $handler;
    }

    public function testSwapOnSetsNewEnginesWhenKeysDoNotExist(): void
    {
        $redis = new FakePredisClient();
        $handler = $this->createFakeRedisHandlerWith($redis);

        $jobStruct = new JobStruct();
        $jobStruct->id_project = 1;
        $jobStruct->password = 'abc123';
        $jobStruct->id_mt_engine = 5;
        $jobStruct->id_tms = 3;

        $this->sut->swapOn($handler, $jobStruct, 0, 0);

        $this->assertSame(0, $jobStruct->id_mt_engine);
        $this->assertSame(0, $jobStruct->id_tms);
        $this->assertSame('5', $redis->store['_old_mt_engine:1:abc123']);
        $this->assertSame('3', $redis->store['_old_tms_engine:1:abc123']);
    }

    public function testSwapOnSetsExpiry24Hours(): void
    {
        $redis = new FakePredisClient();
        $handler = $this->createFakeRedisHandlerWith($redis);

        $jobStruct = new JobStruct();
        $jobStruct->id_project = 1;
        $jobStruct->password = 'abc';
        $jobStruct->id_mt_engine = 5;
        $jobStruct->id_tms = 3;

        $this->sut->swapOn($handler, $jobStruct, 0, 0);

        $this->assertSame(86400, $redis->ttls['_old_mt_engine:1:abc']);
        $this->assertSame(86400, $redis->ttls['_old_tms_engine:1:abc']);
    }

    public function testSwapOnDoesNotOverwriteWhenKeysAlreadyExist(): void
    {
        $redis = new FakePredisClient();
        $redis->store['_old_mt_engine:1:abc123'] = '99';
        $redis->store['_old_tms_engine:1:abc123'] = '88';
        $handler = $this->createFakeRedisHandlerWith($redis);

        $jobStruct = new JobStruct();
        $jobStruct->id_project = 1;
        $jobStruct->password = 'abc123';
        $jobStruct->id_mt_engine = 5;
        $jobStruct->id_tms = 3;

        $this->sut->swapOn($handler, $jobStruct, 0, 0);

        $this->assertSame(5, $jobStruct->id_mt_engine);
        $this->assertSame(3, $jobStruct->id_tms);
    }

    public function testSwapOnUsesDefaultEngineValues(): void
    {
        $handler = $this->createFakeRedisHandler();

        $jobStruct = new JobStruct();
        $jobStruct->id_project = 1;
        $jobStruct->password = 'abc';
        $jobStruct->id_mt_engine = 5;
        $jobStruct->id_tms = 3;

        $this->sut->swapOn($handler, $jobStruct);

        $this->assertSame(1, $jobStruct->id_mt_engine);
        $this->assertSame(1, $jobStruct->id_tms);
    }

    public function testSwapOffRestoresEnginesFromRedis(): void
    {
        $redis = new FakePredisClient();
        $redis->store['_old_mt_engine:10:pass'] = '5';
        $redis->store['_old_tms_engine:10:pass'] = '3';
        $handler = $this->createFakeRedisHandlerWith($redis);

        $jobStruct = new JobStruct();
        $jobStruct->id_project = 10;
        $jobStruct->password = 'pass';
        $jobStruct->id_mt_engine = 0;
        $jobStruct->id_tms = 0;

        $jobDao = $this->createMock(JobDao::class);
        $jobDao->method('getNotDeletedByProjectId')->willReturn([$jobStruct]);
        $jobDao->expects($this->once())->method('updateStruct')->with(
            $this->callback(fn(JobStruct $js) => $js->id_mt_engine === 5 && $js->id_tms === 3),
            $this->equalTo(['fields' => ['id_tms', 'id_mt_engine']])
        );

        $this->sut->swapOff(10, $jobDao, $handler);

        $this->assertSame(5, $jobStruct->id_mt_engine);
        $this->assertSame(3, $jobStruct->id_tms);
        $this->assertArrayNotHasKey('_old_mt_engine:10:pass', $redis->store);
    }

    public function testSwapOffDoesNotUpdateWhenNoRedisKeys(): void
    {
        $redis = new FakePredisClient();
        $handler = $this->createFakeRedisHandlerWith($redis);

        $jobStruct = new JobStruct();
        $jobStruct->id_project = 10;
        $jobStruct->password = 'pass';
        $jobStruct->id_mt_engine = 0;
        $jobStruct->id_tms = 0;

        $jobDao = $this->createMock(JobDao::class);
        $jobDao->method('getNotDeletedByProjectId')->willReturn([$jobStruct]);
        $jobDao->expects($this->never())->method('updateStruct');

        $this->sut->swapOff(10, $jobDao, $handler);

        $this->assertSame(0, $jobStruct->id_mt_engine);
        $this->assertSame(0, $jobStruct->id_tms);
    }

    public function testSwapOffHandlesNoJobs(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedByProjectId')->willReturn([]);

        $handler = $this->createFakeRedisHandler();

        $this->sut->swapOff(99, $jobDao, $handler);

        $this->assertTrue(true);
    }

    public function testSwapOffHandlesMultipleJobs(): void
    {
        $redis = new FakePredisClient();
        $redis->store['_old_mt_engine:10:p1'] = '7';
        $redis->store['_old_tms_engine:10:p1'] = '8';
        $redis->store['_old_mt_engine:10:p2'] = '9';
        $redis->store['_old_tms_engine:10:p2'] = '11';
        $handler = $this->createFakeRedisHandlerWith($redis);

        $job1 = new JobStruct();
        $job1->id_project = 10;
        $job1->password = 'p1';
        $job1->id_mt_engine = 0;
        $job1->id_tms = 0;

        $job2 = new JobStruct();
        $job2->id_project = 10;
        $job2->password = 'p2';
        $job2->id_mt_engine = 0;
        $job2->id_tms = 0;

        $jobDao = $this->createMock(JobDao::class);
        $jobDao->method('getNotDeletedByProjectId')->willReturn([$job1, $job2]);
        $jobDao->expects($this->exactly(2))->method('updateStruct');

        $this->sut->swapOff(10, $jobDao, $handler);

        $this->assertSame(7, $job1->id_mt_engine);
        $this->assertSame(8, $job1->id_tms);
        $this->assertSame(9, $job2->id_mt_engine);
        $this->assertSame(11, $job2->id_tms);
    }

    public function testSwapOffCastsRedisStringToInt(): void
    {
        $redis = new FakePredisClient();
        $redis->store['_old_mt_engine:10:pass'] = '42';
        $redis->store['_old_tms_engine:10:pass'] = '99';
        $handler = $this->createFakeRedisHandlerWith($redis);

        $jobStruct = new JobStruct();
        $jobStruct->id_project = 10;
        $jobStruct->password = 'pass';
        $jobStruct->id_mt_engine = 0;
        $jobStruct->id_tms = 0;

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedByProjectId')->willReturn([$jobStruct]);

        $this->sut->swapOff(10, $jobDao, $handler);

        $this->assertIsInt($jobStruct->id_mt_engine);
        $this->assertIsInt($jobStruct->id_tms);
        $this->assertSame(42, $jobStruct->id_mt_engine);
        $this->assertSame(99, $jobStruct->id_tms);
    }

    public function testSwapOffPartialRestore_OnlyMtExists(): void
    {
        $redis = new FakePredisClient();
        $redis->store['_old_mt_engine:10:pass'] = '9';
        $handler = $this->createFakeRedisHandlerWith($redis);

        $jobStruct = new JobStruct();
        $jobStruct->id_project = 10;
        $jobStruct->password = 'pass';
        $jobStruct->id_mt_engine = 0;
        $jobStruct->id_tms = 0;

        $jobDao = $this->createMock(JobDao::class);
        $jobDao->method('getNotDeletedByProjectId')->willReturn([$jobStruct]);
        $jobDao->expects($this->once())->method('updateStruct');

        $this->sut->swapOff(10, $jobDao, $handler);

        $this->assertSame(9, $jobStruct->id_mt_engine);
        $this->assertSame(0, $jobStruct->id_tms);
    }
}
