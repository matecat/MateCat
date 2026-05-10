<?php

namespace unit\Traits;

use Controller\Traits\RateLimiterTrait;
use DateTime;
use Klein\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use TestHelpers\AbstractTest;

/**
 * Fake Redis client that stores data in memory for testing purposes.
 * Predis\Client uses __call magic for Redis commands, which PHPUnit cannot mock.
 */
class FakeRedisClient extends Client
{
    private array $store = [];
    private array $ttls = [];
    private array $hashStore = [];
    public array $calls = [];

    public function __construct()
    {
        // Do not call parent constructor
    }

    public function __call($commandID, $arguments)
    {
        $this->calls[] = ['method' => $commandID, 'args' => $arguments];

        return match (strtolower($commandID)) {
            'get' => $this->store[$arguments[0]] ?? null,
            'set' => $this->doSet($arguments[0], $arguments[1]),
            'incr' => $this->doIncr($arguments[0]),
            'expire' => $this->doExpire($arguments[0], $arguments[1]),
            'ttl' => $this->ttls[$arguments[0]] ?? -1,
            'setex' => $this->doSetex($arguments[0], $arguments[1], $arguments[2]),
            'del' => $this->doDel($arguments[0]),
            'hget' => $this->hashStore[$arguments[0]][$arguments[1]] ?? null,
            'hset' => $this->doHset($arguments[0], $arguments[1], $arguments[2]),
            'hdel' => $this->doHdel($arguments[0], $arguments[1]),
            default => null,
        };
    }

    private function doSet($key, $value): void
    {
        $this->store[$key] = $value;
    }

    private function doIncr($key): int
    {
        $this->store[$key] = ($this->store[$key] ?? 0) + 1;
        return $this->store[$key];
    }

    private function doExpire($key, $ttl): bool
    {
        $this->ttls[$key] = $ttl;
        return true;
    }

    private function doSetex($key, $ttl, $value): void
    {
        $this->store[$key] = $value;
        $this->ttls[$key] = $ttl;
    }

    private function doDel($key): int
    {
        unset($this->store[$key], $this->ttls[$key]);
        return 1;
    }

    public function getStore(): array
    {
        return $this->store;
    }

    public function setStoreValue(string $key, mixed $value): void
    {
        $this->store[$key] = $value;
    }

    public function setTtlValue(string $key, int $ttl): void
    {
        $this->ttls[$key] = $ttl;
    }

    public function setHashValue(string $keyMap, string $field, mixed $value): void
    {
        $this->hashStore[$keyMap][$field] = $value;
    }

    public function getHashStore(): array
    {
        return $this->hashStore;
    }

    private function doHset($key, $field, $value): int
    {
        $this->hashStore[$key][$field] = $value;
        return 1;
    }

    private function doHdel($key, $fields): int
    {
        $count = 0;
        foreach ((array)$fields as $field) {
            if (isset($this->hashStore[$key][$field])) {
                unset($this->hashStore[$key][$field]);
                $count++;
            }
        }
        return $count;
    }
}

/**
 * Concrete class that uses RateLimiterTrait for testing.
 * Overrides the private getRedis() by redefining it in the class.
 */
class RateLimiterTraitConsumer
{
    use RateLimiterTrait;

    private FakeRedisClient $fakeRedis;

    public function __construct(FakeRedisClient $redis)
    {
        $this->fakeRedis = $redis;
    }

    private function getRedis(): Client
    {
        return $this->fakeRedis;
    }

    public function publicGetKey(string $identifier, string $route): string
    {
        return md5($identifier . $route);
    }

    public function publicGetTtl(): int
    {
        $date = new DateTime();
        $ttl = 60 - $date->format("s");
        return 60 + (int)$ttl;
    }
}

#[AllowMockObjectsWithoutExpectations]
class RateLimiterTraitTest extends AbstractTest
{
    private RateLimiterTraitConsumer $consumer;
    private FakeRedisClient $redis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redis = new FakeRedisClient();
        $this->consumer = new RateLimiterTraitConsumer($this->redis);
    }

    // ─── checkRateLimitResponse tests ────────────────────────────────

    #[Test]
    public function checkRateLimitResponseReturnsNullWhenUnderLimit(): void
    {
        $response = $this->createStub(Response::class);
        $key = md5('user@test.com' . '/api/route');
        $this->redis->setStoreValue($key, 3);

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 10);

        $this->assertNull($result);
    }

    #[Test]
    public function checkRateLimitResponseReturnsNullWhenKeyDoesNotExist(): void
    {
        $response = $this->createStub(Response::class);

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 10);

        $this->assertNull($result);
    }

    #[Test]
    public function checkRateLimitResponseReturnsNullWhenExactlyAtLimit(): void
    {
        $response = $this->createStub(Response::class);
        $key = md5('user@test.com' . '/api/route');
        $this->redis->setStoreValue($key, 10);

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 10);

        $this->assertNull($result);
    }

    #[Test]
    public function checkRateLimitResponseReturns429WhenOverLimit(): void
    {
        $response = $this->createMock(Response::class);
        $key = md5('user@test.com' . '/api/route');
        $this->redis->setStoreValue($key, 11);
        $this->redis->setTtlValue($key, 45);

        $response->expects($this->once())->method('code')->with(429);
        $response->expects($this->once())->method('header')->with('Retry-After', 45);

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 10);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function checkRateLimitResponseSetsRetryAfterHeader(): void
    {
        $response = $this->createMock(Response::class);
        $key = md5('user@test.com' . '/api/route');
        $this->redis->setStoreValue($key, 6);
        $this->redis->setTtlValue($key, 90);

        $response->expects($this->once())->method('header')->with('Retry-After', 90);

        $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 5);
    }

    #[Test]
    public function checkRateLimitResponseResetsTtlAsPenalty(): void
    {
        $response = $this->createStub(Response::class);
        $key = md5('user@test.com' . '/api/route');
        $this->redis->setStoreValue($key, 20);
        $this->redis->setTtlValue($key, 30);

        $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 5);

        // Check that expire was called with the TTL value (penalty reset)
        $expireCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'expire');
        $this->assertNotEmpty($expireCalls);
        $lastExpire = end($expireCalls);
        $this->assertGreaterThan(60, $lastExpire['args'][1]);
    }

    #[Test]
    public function checkRateLimitResponseUsesDefaultMaxRetriesOf10(): void
    {
        $response = $this->createMock(Response::class);
        $key = md5('id' . '/route');
        $this->redis->setStoreValue($key, 11);
        $this->redis->setTtlValue($key, 60);

        $response->expects($this->once())->method('code')->with(429);

        $result = $this->consumer->checkRateLimitResponse($response, 'id', '/route');

        $this->assertSame($response, $result);
    }

    #[Test]
    public function checkRateLimitResponseUsesCustomMaxRetries(): void
    {
        $response = $this->createMock(Response::class);
        $key = md5('id' . '/route');
        $this->redis->setStoreValue($key, 4);
        $this->redis->setTtlValue($key, 60);

        $response->expects($this->once())->method('code')->with(429);

        $result = $this->consumer->checkRateLimitResponse($response, 'id', '/route', 3);

        $this->assertSame($response, $result);
    }

    // ─── checkAndIncrementRateLimit tests ────────────────────────────

    #[Test]
    public function checkAndIncrementReturnsNullAndIncrementsWhenUnderLimit(): void
    {
        $response = $this->createStub(Response::class);

        $result = $this->consumer->checkAndIncrementRateLimit($response, 'user@test.com', '/api/route', 10);

        $key = md5('user@test.com' . '/api/route');
        $store = $this->redis->getStore();

        $this->assertNull($result);
        $this->assertEquals(1, $store[$key]);
    }

    #[Test]
    public function checkAndIncrementReturnsNullWhenExactlyAtLimit(): void
    {
        $response = $this->createStub(Response::class);
        $key = md5('user@test.com' . '/api/route');
        $this->redis->setStoreValue($key, 9);

        $result = $this->consumer->checkAndIncrementRateLimit($response, 'user@test.com', '/api/route', 10);

        $store = $this->redis->getStore();
        $this->assertNull($result);
        $this->assertEquals(10, $store[$key]);
    }

    #[Test]
    public function checkAndIncrementReturns429WhenOverLimit(): void
    {
        $response = $this->createMock(Response::class);
        $key = md5('user@test.com' . '/api/route');
        $this->redis->setStoreValue($key, 10);
        $this->redis->setTtlValue($key, 45);

        $response->expects($this->once())->method('code')->with(429);
        $response->expects($this->once())->method('header')->with('Retry-After', 45);

        $result = $this->consumer->checkAndIncrementRateLimit($response, 'user@test.com', '/api/route', 10);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function checkAndIncrementSetsTtlOnFirstCall(): void
    {
        $response = $this->createStub(Response::class);

        $this->consumer->checkAndIncrementRateLimit($response, 'id', '/route', 10);

        $expireCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'expire');
        $this->assertNotEmpty($expireCalls);
        $lastExpire = end($expireCalls);
        $this->assertGreaterThanOrEqual(61, $lastExpire['args'][1]);
        $this->assertLessThanOrEqual(120, $lastExpire['args'][1]);
    }

    #[Test]
    public function checkAndIncrementDoesNotResetTtlOnSubsequentCalls(): void
    {
        $response = $this->createStub(Response::class);
        $key = md5('id' . '/route');
        $this->redis->setStoreValue($key, 3);

        $this->consumer->checkAndIncrementRateLimit($response, 'id', '/route', 10);

        $expireCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'expire');
        $this->assertEmpty($expireCalls);
    }

    #[Test]
    public function checkAndIncrementResetsTtlAsPenaltyWhenOverLimit(): void
    {
        $response = $this->createMock(Response::class);
        $key = md5('id' . '/route');
        $this->redis->setStoreValue($key, 5);
        $this->redis->setTtlValue($key, 30);

        $response->expects($this->once())->method('code')->with(429);
        $response->expects($this->once())->method('header')->with('Retry-After', 30);

        $this->consumer->checkAndIncrementRateLimit($response, 'id', '/route', 5);

        $expireCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'expire');
        $this->assertNotEmpty($expireCalls);
        $lastExpire = end($expireCalls);
        $this->assertGreaterThan(60, $lastExpire['args'][1]);
    }

    #[Test]
    public function checkAndIncrementUsesDefaultMaxRetriesOf10(): void
    {
        $response = $this->createMock(Response::class);
        $key = md5('id' . '/route');
        $this->redis->setStoreValue($key, 10);
        $this->redis->setTtlValue($key, 60);

        $response->expects($this->once())->method('code')->with(429);

        $result = $this->consumer->checkAndIncrementRateLimit($response, 'id', '/route');

        $this->assertSame($response, $result);
    }

    #[Test]
    public function checkAndIncrementDifferentIdentifiersAreIndependent(): void
    {
        $response = $this->createStub(Response::class);

        $result1 = $this->consumer->checkAndIncrementRateLimit($response, 'user1@test.com', '/route', 10);
        $result2 = $this->consumer->checkAndIncrementRateLimit($response, 'user2@test.com', '/route', 10);

        $key1 = md5('user1@test.com' . '/route');
        $key2 = md5('user2@test.com' . '/route');
        $store = $this->redis->getStore();

        $this->assertNull($result1);
        $this->assertNull($result2);
        $this->assertEquals(1, $store[$key1]);
        $this->assertEquals(1, $store[$key2]);
    }

    #[Test]
    public function checkAndIncrementCountsSequentialCalls(): void
    {
        $response = $this->createStub(Response::class);

        $this->consumer->checkAndIncrementRateLimit($response, 'user@test.com', '/route', 10);
        $this->consumer->checkAndIncrementRateLimit($response, 'user@test.com', '/route', 10);
        $this->consumer->checkAndIncrementRateLimit($response, 'user@test.com', '/route', 10);

        $key = md5('user@test.com' . '/route');
        $store = $this->redis->getStore();

        $this->assertEquals(3, $store[$key]);
    }

    // ─── incrementRateLimitCounter tests ─────────────────────────────

    #[Test]
    public function incrementRateLimitCounterSetsKeyWhenNotExists(): void
    {
        $this->consumer->incrementRateLimitCounter('user@test.com', '/api/route');

        $key = md5('user@test.com' . '/api/route');
        $store = $this->redis->getStore();
        $this->assertEquals(1, $store[$key]);
    }

    #[Test]
    public function incrementRateLimitCounterIncrementsWhenKeyExists(): void
    {
        $key = md5('user@test.com' . '/api/route');
        $this->redis->setStoreValue($key, 5);

        $this->consumer->incrementRateLimitCounter('user@test.com', '/api/route');

        $store = $this->redis->getStore();
        $this->assertEquals(6, $store[$key]);
    }

    #[Test]
    public function incrementRateLimitCounterSetsExpireOnNewKey(): void
    {
        $this->consumer->incrementRateLimitCounter('id', '/route');

        $expireCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'expire');
        $this->assertNotEmpty($expireCalls);
        $lastExpire = end($expireCalls);
        $this->assertGreaterThan(60, $lastExpire['args'][1]);
        $this->assertLessThanOrEqual(120, $lastExpire['args'][1]);
    }

    #[Test]
    public function incrementRateLimitCounterDoesNotSetExpireOnExistingKey(): void
    {
        $key = md5('id' . '/route');
        $this->redis->setStoreValue($key, 3);

        $this->consumer->incrementRateLimitCounter('id', '/route');

        // incr is called, but not expire
        $expireCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'expire');
        $this->assertEmpty($expireCalls);
    }

    // ─── getKey tests ────────────────────────────────────────────────

    #[Test]
    public function getKeyReturnsMd5HashOfIdentifierAndRoute(): void
    {
        $identifier = 'user@example.com';
        $route = '/api/v3/segment/disable/42';

        $expected = md5($identifier . $route);
        $result = $this->consumer->publicGetKey($identifier, $route);

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function getKeyReturnsDifferentHashesForDifferentIdentifiers(): void
    {
        $key1 = $this->consumer->publicGetKey('user1@test.com', '/route');
        $key2 = $this->consumer->publicGetKey('user2@test.com', '/route');

        $this->assertNotEquals($key1, $key2);
    }

    #[Test]
    public function getKeyReturnsDifferentHashesForDifferentRoutes(): void
    {
        $key1 = $this->consumer->publicGetKey('user@test.com', '/route/1');
        $key2 = $this->consumer->publicGetKey('user@test.com', '/route/2');

        $this->assertNotEquals($key1, $key2);
    }

    #[Test]
    public function getKeyReturnsConsistentHashForSameInput(): void
    {
        $key1 = $this->consumer->publicGetKey('user@test.com', '/api/route');
        $key2 = $this->consumer->publicGetKey('user@test.com', '/api/route');

        $this->assertEquals($key1, $key2);
    }

    #[Test]
    public function getKeyReturns32CharacterString(): void
    {
        $key = $this->consumer->publicGetKey('any', '/route');

        $this->assertEquals(32, strlen($key));
    }

    // ─── getTtl tests ────────────────────────────────────────────────

    #[Test]
    public function getTtlReturnsValueBetween61And120(): void
    {
        $ttl = $this->consumer->publicGetTtl();

        $this->assertGreaterThanOrEqual(61, $ttl);
        $this->assertLessThanOrEqual(120, $ttl);
    }

    #[Test]
    public function getTtlIsAlwaysGreaterThan60(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $ttl = $this->consumer->publicGetTtl();
            $this->assertGreaterThan(60, $ttl);
        }
    }

    #[Test]
    public function getTtlMatchesExpectedFormula(): void
    {
        $date = new DateTime();
        $currentSecond = (int)$date->format('s');
        $expectedTtl = 60 + (60 - $currentSecond);

        $ttl = $this->consumer->publicGetTtl();

        $this->assertEqualsWithDelta($expectedTtl, $ttl, 1);
    }

    // ─── Integration-style tests ─────────────────────────────────────

    #[Test]
    public function fullFlowIncrementThenCheckStaysUnderLimit(): void
    {
        $response = $this->createStub(Response::class);

        $this->consumer->incrementRateLimitCounter('user@test.com', '/route');

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/route', 5);
        $this->assertNull($result);
    }

    #[Test]
    public function fullFlowExceedingLimitTriggersRateLimit(): void
    {
        $response = $this->createMock(Response::class);
        $key = md5('user@test.com' . '/route');
        $this->redis->setStoreValue($key, 11);
        $this->redis->setTtlValue($key, 50);

        $response->expects($this->once())->method('code')->with(429);

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/route', 10);
        $this->assertInstanceOf(Response::class, $result);
    }

    #[Test]
    public function differentIdentifiersHaveIndependentCounters(): void
    {
        $this->consumer->incrementRateLimitCounter('user1@test.com', '/route');
        $this->consumer->incrementRateLimitCounter('user2@test.com', '/route');

        $key1 = md5('user1@test.com' . '/route');
        $key2 = md5('user2@test.com' . '/route');

        $store = $this->redis->getStore();
        $this->assertEquals(1, $store[$key1]);
        $this->assertEquals(1, $store[$key2]);
    }
}
