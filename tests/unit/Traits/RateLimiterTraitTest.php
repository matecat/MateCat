<?php

namespace unit\Traits;

use Controller\Traits\RateLimiterTrait;
use DateTime;
use Klein\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Predis\Client;
use ReflectionClass;
use TestHelpers\AbstractTest;

/**
 * Concrete class that uses RateLimiterTrait for testing purposes.
 * Overrides the private getRedis() by exposing trait methods and injecting a mock client.
 */
class RateLimiterTraitConsumer
{
    use RateLimiterTrait {
        getRedis as private traitGetRedis;
        getKey as private traitGetKey;
        getTtl as private traitGetTtl;
    }

    private Client $mockRedis;

    public function setMockRedis(Client $redis): void
    {
        $this->mockRedis = $redis;
    }

    private function getRedis(): Client
    {
        return $this->mockRedis;
    }

    /**
     * Expose private getKey for testing.
     */
    public function publicGetKey(string $identifier, string $route): string
    {
        return $this->traitGetKey($identifier, $route);
    }

    /**
     * Expose private getTtl for testing.
     */
    public function publicGetTtl(): int
    {
        return $this->traitGetTtl();
    }
}

class RateLimiterTraitTest extends AbstractTest
{
    private RateLimiterTraitConsumer $consumer;
    private Client|MockObject $redis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consumer = new RateLimiterTraitConsumer();
        $this->redis = $this->createMock(Client::class);
        $this->consumer->setMockRedis($this->redis);
    }

    // ─── checkRateLimitResponse tests ────────────────────────────────

    #[Test]
    public function checkRateLimitResponseReturnsNullWhenUnderLimit(): void
    {
        $response = $this->createMock(Response::class);

        $this->redis->method('get')->willReturn(3);

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 10);

        $this->assertNull($result);
    }

    #[Test]
    public function checkRateLimitResponseReturnsNullWhenKeyDoesNotExist(): void
    {
        $response = $this->createMock(Response::class);

        $this->redis->method('get')->willReturn(null);

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 10);

        $this->assertNull($result);
    }

    #[Test]
    public function checkRateLimitResponseReturnsNullWhenExactlyAtLimit(): void
    {
        $response = $this->createMock(Response::class);

        // At limit (10) but not exceeding — condition is > maxRetries
        $this->redis->method('get')->willReturn(10);

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 10);

        $this->assertNull($result);
    }

    #[Test]
    public function checkRateLimitResponseReturns429WhenOverLimit(): void
    {
        $response = $this->createMock(Response::class);

        $this->redis->method('get')->willReturn(11);
        $this->redis->method('ttl')->willReturn(45);

        $response->expects($this->once())->method('code')->with(429);
        $response->expects($this->once())->method('header')->with('Retry-After', 45);

        $this->redis->expects($this->once())->method('expire');

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 10);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function checkRateLimitResponseSetsRetryAfterHeader(): void
    {
        $response = $this->createMock(Response::class);

        $this->redis->method('get')->willReturn(6);
        $this->redis->method('ttl')->willReturn(90);

        $response->expects($this->once())->method('header')->with('Retry-After', 90);

        $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 5);
    }

    #[Test]
    public function checkRateLimitResponseResetsTtlAsPenalty(): void
    {
        $response = $this->createMock(Response::class);

        $key = md5('user@test.com' . '/api/route');

        $this->redis->method('get')->willReturn(20);
        $this->redis->method('ttl')->willReturn(30);

        $this->redis->expects($this->once())
            ->method('expire')
            ->with($key, $this->greaterThan(60));

        $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/api/route', 5);
    }

    #[Test]
    public function checkRateLimitResponseUsesDefaultMaxRetriesOf10(): void
    {
        $response = $this->createMock(Response::class);

        // 11 > 10 (default), should trigger rate limit
        $this->redis->method('get')->willReturn(11);
        $this->redis->method('ttl')->willReturn(60);

        $response->expects($this->once())->method('code')->with(429);

        $result = $this->consumer->checkRateLimitResponse($response, 'id', '/route');

        $this->assertSame($response, $result);
    }

    #[Test]
    public function checkRateLimitResponseUsesCustomMaxRetries(): void
    {
        $response = $this->createMock(Response::class);

        // 4 > 3, should trigger rate limit
        $this->redis->method('get')->willReturn(4);
        $this->redis->method('ttl')->willReturn(60);

        $response->expects($this->once())->method('code')->with(429);

        $result = $this->consumer->checkRateLimitResponse($response, 'id', '/route', 3);

        $this->assertSame($response, $result);
    }

    // ─── incrementRateLimitCounter tests ─────────────────────────────

    #[Test]
    public function incrementRateLimitCounterSetsKeyWhenNotExists(): void
    {
        $key = md5('user@test.com' . '/api/route');

        $this->redis->method('get')->willReturn(null);

        $this->redis->expects($this->once())
            ->method('set')
            ->with($key, 1);

        $this->redis->expects($this->once())
            ->method('expire')
            ->with($key, $this->greaterThan(60));

        $this->consumer->incrementRateLimitCounter('user@test.com', '/api/route');
    }

    #[Test]
    public function incrementRateLimitCounterIncrementsWhenKeyExists(): void
    {
        $key = md5('user@test.com' . '/api/route');

        $this->redis->method('get')->willReturn(5);

        $this->redis->expects($this->never())->method('set');
        $this->redis->expects($this->once())
            ->method('incr')
            ->with($key);

        $this->consumer->incrementRateLimitCounter('user@test.com', '/api/route');
    }

    #[Test]
    public function incrementRateLimitCounterSetsExpireOnNewKey(): void
    {
        $this->redis->method('get')->willReturn(null);
        $this->redis->method('set');

        $this->redis->expects($this->once())
            ->method('expire')
            ->with($this->anything(), $this->logicalAnd($this->greaterThan(60), $this->lessThanOrEqual(120)));

        $this->consumer->incrementRateLimitCounter('id', '/route');
    }

    #[Test]
    public function incrementRateLimitCounterDoesNotResetExpireOnExistingKey(): void
    {
        $this->redis->method('get')->willReturn(3);

        $this->redis->expects($this->never())->method('expire');

        $this->consumer->incrementRateLimitCounter('id', '/route');
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

        // TTL = 60 + (60 - current_second)
        // When second = 0: 60 + 60 = 120
        // When second = 59: 60 + 1 = 61
        $this->assertGreaterThanOrEqual(61, $ttl);
        $this->assertLessThanOrEqual(120, $ttl);
    }

    #[Test]
    public function getTtlIsAlwaysGreaterThan60(): void
    {
        // Run multiple times to verify consistency
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

        // Allow 1 second tolerance for timing
        $this->assertEqualsWithDelta($expectedTtl, $ttl, 1);
    }

    // ─── Integration-style tests ─────────────────────────────────────

    #[Test]
    public function fullFlowIncrementThenCheckStaysUnderLimit(): void
    {
        $response = $this->createMock(Response::class);
        $callCount = 0;

        $this->redis->method('get')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                // First call from increment (key doesn't exist), subsequent from check
                return $callCount <= 1 ? null : 1;
            });

        $this->redis->method('set');
        $this->redis->method('expire');

        $this->consumer->incrementRateLimitCounter('user@test.com', '/route');

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/route', 5);
        $this->assertNull($result);
    }

    #[Test]
    public function fullFlowExceedingLimitTriggersRateLimit(): void
    {
        $response = $this->createMock(Response::class);

        // Simulate counter already at 11
        $this->redis->method('get')->willReturn(11);
        $this->redis->method('ttl')->willReturn(50);
        $this->redis->method('expire');

        $response->expects($this->once())->method('code')->with(429);

        $result = $this->consumer->checkRateLimitResponse($response, 'user@test.com', '/route', 10);
        $this->assertInstanceOf(Response::class, $result);
    }

    #[Test]
    public function differentIdentifiersHaveIndependentCounters(): void
    {
        $keyUser1 = md5('user1@test.com' . '/route');
        $keyUser2 = md5('user2@test.com' . '/route');

        $this->assertNotEquals($keyUser1, $keyUser2);

        // Verify separate keys are used
        $capturedKeys = [];
        $this->redis->method('get')
            ->willReturnCallback(function ($key) use (&$capturedKeys) {
                $capturedKeys[] = $key;
                return null;
            });
        $this->redis->method('set');
        $this->redis->method('expire');

        $this->consumer->incrementRateLimitCounter('user1@test.com', '/route');
        $this->consumer->incrementRateLimitCounter('user2@test.com', '/route');

        $this->assertCount(2, $capturedKeys);
        $this->assertNotEquals($capturedKeys[0], $capturedKeys[1]);
    }
}

