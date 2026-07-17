<?php

namespace Matecat\Core\Services;

use Controller\Services\RateLimiterService;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Utils\Redis\RedisHandler;

#[AllowMockObjectsWithoutExpectations]
class RateLimiterServiceTest extends AbstractTest
{
    private RateLimiterService $service;
    private \Predis\Client $redis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redis = (new RedisHandler())->getConnection();
        $this->service = new RateLimiterService();
    }

    protected function tearDown(): void
    {
        // Clean up all test keys
        $keys = [
            md5('user@test.com' . '/api/route'),
            md5('user1@test.com' . '/route'),
            md5('user2@test.com' . '/route'),
            md5('id' . '/route'),
            md5('sequential@test.com' . '/route'),
            md5('ttl-test' . '/route'),
            md5('default-max' . '/route'),
            md5('penalty-test' . '/route'),
        ];
        $this->redis->del($keys);
        parent::tearDown();
    }

    #[Test]
    public function checkAndIncrementReturnsNullAndIncrementsWhenUnderLimit(): void
    {
        $response = new Response();
        $key = md5('user@test.com' . '/api/route');
        $this->redis->del([$key]);

        $result = $this->service->checkAndIncrement($response, 'user@test.com', '/api/route', 10);

        $this->assertNull($result);
        $this->assertEquals(1, (int)$this->redis->get($key));
    }

    #[Test]
    public function checkAndIncrementReturnsNullWhenExactlyAtLimit(): void
    {
        $response = new Response();
        $key = md5('user@test.com' . '/api/route');
        $this->redis->set($key, 9);

        $result = $this->service->checkAndIncrement($response, 'user@test.com', '/api/route', 10);

        $this->assertNull($result);
        $this->assertEquals(10, (int)$this->redis->get($key));
    }

    #[Test]
    public function checkAndIncrementReturns429WhenOverLimit(): void
    {
        $response = new Response();
        $key = md5('user@test.com' . '/api/route');
        $this->redis->set($key, 10);
        $this->redis->expire($key, 120);

        $result = $this->service->checkAndIncrement($response, 'user@test.com', '/api/route', 10);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(429, $result->code());
        $this->assertNotEmpty($result->headers()->get('Retry-After'));
    }

    #[Test]
    public function checkAndIncrementSetsTtlOnFirstCall(): void
    {
        $response = new Response();
        $key = md5('id' . '/route');
        $this->redis->del([$key]);

        $this->service->checkAndIncrement($response, 'id', '/route', 10);

        $ttl = $this->redis->ttl($key);
        $this->assertGreaterThanOrEqual(61, $ttl);
        $this->assertLessThanOrEqual(120, $ttl);
    }

    #[Test]
    public function checkAndIncrementDoesNotResetTtlOnSubsequentCalls(): void
    {
        $response = new Response();
        $key = md5('id' . '/route');
        // Pre-set to simulate a key that already exists with a short TTL
        $this->redis->set($key, 3);
        $this->redis->expire($key, 30);

        $this->service->checkAndIncrement($response, 'id', '/route', 10);

        $ttl = $this->redis->ttl($key);
        // TTL should NOT have been reset (stays ≤ 30, not bumped to 61-120)
        $this->assertLessThanOrEqual(30, $ttl);
    }

    #[Test]
    public function checkAndIncrementResetsTtlAsPenaltyWhenOverLimit(): void
    {
        $response = new Response();
        $key = md5('penalty-test' . '/route');
        $this->redis->set($key, 5);
        $this->redis->expire($key, 10); // Low TTL that should be reset

        $this->service->checkAndIncrement($response, 'penalty-test', '/route', 5);

        $ttl = $this->redis->ttl($key);
        // TTL should have been reset to 61-120 as penalty
        $this->assertGreaterThan(60, $ttl);
    }

    #[Test]
    public function checkAndIncrementUsesDefaultMaxRetriesOf10(): void
    {
        $response = new Response();
        $key = md5('default-max' . '/route');
        $this->redis->set($key, 10);
        $this->redis->expire($key, 120);

        // Default maxRetries = 10, counter at 10, next INCR → 11 > 10
        $result = $this->service->checkAndIncrement($response, 'default-max', '/route');

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(429, $result->code());
    }

    #[Test]
    public function checkAndIncrementDifferentIdentifiersAreIndependent(): void
    {
        $response = new Response();
        $key1 = md5('user1@test.com' . '/route');
        $key2 = md5('user2@test.com' . '/route');
        $this->redis->del([$key1, $key2]);

        $result1 = $this->service->checkAndIncrement($response, 'user1@test.com', '/route', 10);
        $result2 = $this->service->checkAndIncrement($response, 'user2@test.com', '/route', 10);

        $this->assertNull($result1);
        $this->assertNull($result2);
        $this->assertEquals(1, (int)$this->redis->get($key1));
        $this->assertEquals(1, (int)$this->redis->get($key2));
    }

    #[Test]
    public function checkAndIncrementCountsSequentialCalls(): void
    {
        $response = new Response();
        $key = md5('sequential@test.com' . '/route');
        $this->redis->del([$key]);

        $this->service->checkAndIncrement($response, 'sequential@test.com', '/route', 10);
        $this->service->checkAndIncrement($response, 'sequential@test.com', '/route', 10);
        $this->service->checkAndIncrement($response, 'sequential@test.com', '/route', 10);

        $this->assertEquals(3, (int)$this->redis->get($key));
    }

    #[Test]
    public function ttlValueIsBetween61And120(): void
    {
        $response = new Response();
        $key = md5('ttl-test' . '/route');
        $this->redis->del([$key]);

        $this->service->checkAndIncrement($response, 'ttl-test', '/route', 10);

        $ttl = $this->redis->ttl($key);
        $this->assertGreaterThanOrEqual(61, $ttl);
        $this->assertLessThanOrEqual(120, $ttl);
    }
}
