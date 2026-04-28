<?php

namespace unit\Traits;

use Controller\Traits\SegmentDisabledTrait;
use Model\DataAccess\DaoCacheTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Predis\Client;
use ReflectionClass;
use TestHelpers\AbstractTest;

/**
 * Concrete class that uses SegmentDisabledTrait for testing purposes.
 * Exposes protected methods as public and allows injecting a mock Redis client.
 */
class SegmentDisabledTraitConsumer
{
    use SegmentDisabledTrait;

    private ?Client $mockRedisClient = null;

    public function setMockRedisClient(?Client $client): void
    {
        $this->mockRedisClient = $client;
        // Inject into the static cache_con property
        $ref = new ReflectionClass($this);
        $prop = $ref->getProperty('cache_con');
        $prop->setAccessible(true);
        $prop->setValue(null, $client);
    }

    public function publicIsSegmentDisabled(int $id_job, int $id_segment): bool
    {
        return $this->isSegmentDisabled($id_job, $id_segment);
    }

    public function publicSaveSegmentDisabledInCache(int $id_job, int $id_segment): void
    {
        $this->saveSegmentDisabledInCache($id_job, $id_segment);
    }

    public function publicDestroySegmentDisabledCache(int $id_job, int $id_segment): void
    {
        $this->destroySegmentDisabledCache($id_job, $id_segment);
    }

    public function getCacheTTL(): int
    {
        return $this->cacheTTL;
    }
}

class SegmentDisabledTraitTest extends AbstractTest
{
    private SegmentDisabledTraitConsumer $consumer;
    private Client|MockObject $redisClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consumer = new SegmentDisabledTraitConsumer();
        $this->redisClient = $this->createMock(Client::class);
        $this->consumer->setMockRedisClient($this->redisClient);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Reset static cache_con
        $ref = new ReflectionClass(SegmentDisabledTraitConsumer::class);
        $prop = $ref->getProperty('cache_con');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    // ─── isSegmentDisabled tests ─────────────────────────────────────

    #[Test]
    public function isSegmentDisabledReturnsTrueWhenCachedValueIsOne(): void
    {
        $this->redisClient->method('hget')
            ->willReturn(serialize([1]));

        $result = $this->consumer->publicIsSegmentDisabled(1, 42);

        $this->assertTrue($result);
    }

    #[Test]
    public function isSegmentDisabledReturnsFalseWhenCachedValueIsZero(): void
    {
        $this->redisClient->method('hget')
            ->willReturn(serialize([0]));

        $result = $this->consumer->publicIsSegmentDisabled(1, 42);

        $this->assertFalse($result);
    }

    #[Test]
    public function isSegmentDisabledReturnsFalseWhenCacheIsEmpty(): void
    {
        // hget returns null (no cache), then SegmentMetadataDao::get is called
        // Since we can't mock static, this test verifies the cache miss path
        // by providing empty serialized value
        $this->redisClient->method('hget')
            ->willReturn('');

        // When cache is empty, the trait calls SegmentMetadataDao::get which hits DB
        // For unit testing, we verify the cache lookup behavior
        // With empty unserialized result, _getFromCacheMap returns null
        // Then the trait queries the DB — this will fail without DB,
        // so we test only the cache-hit scenarios in unit tests
        $this->markTestSkipped('Requires database connection for SegmentMetadataDao::get fallback');
    }

    #[Test]
    public function isSegmentDisabledReturnsFalseWhenCachedValueIsNotOne(): void
    {
        $this->redisClient->method('hget')
            ->willReturn(serialize([0]));

        $result = $this->consumer->publicIsSegmentDisabled(5, 100);

        $this->assertFalse($result);
    }

    #[Test]
    public function isSegmentDisabledReturnsTrueForDifferentJobAndSegmentIds(): void
    {
        $this->redisClient->method('hget')
            ->willReturn(serialize([1]));

        $this->assertTrue($this->consumer->publicIsSegmentDisabled(999, 888));
    }

    #[Test]
    public function isSegmentDisabledUsesCorrectCacheKey(): void
    {
        $idJob = 7;
        $idSegment = 99;
        $expectedKeyMap = 'segment_is_disabled_' . $idJob . '_' . $idSegment;
        $expectedQuery = '__SEGMENT_IS_DISABLED__' . $idJob . '_' . $idSegment;
        $expectedHashKey = md5($expectedQuery);

        $this->redisClient->expects($this->once())
            ->method('hget')
            ->with($expectedKeyMap, $expectedHashKey)
            ->willReturn(serialize([1]));

        $this->consumer->publicIsSegmentDisabled($idJob, $idSegment);
    }

    // ─── saveSegmentDisabledInCache tests ────────────────────────────

    #[Test]
    public function saveSegmentDisabledInCacheSetsValueInRedis(): void
    {
        $idJob = 3;
        $idSegment = 50;
        $expectedKeyMap = 'segment_is_disabled_' . $idJob . '_' . $idSegment;
        $expectedQuery = '__SEGMENT_IS_DISABLED__' . $idJob . '_' . $idSegment;
        $expectedHashKey = md5($expectedQuery);

        $this->redisClient->expects($this->once())
            ->method('hset')
            ->with($expectedKeyMap, $expectedHashKey, serialize([1]));

        $this->redisClient->expects($this->once())
            ->method('expire')
            ->with($expectedKeyMap, 3600);

        $this->redisClient->expects($this->once())
            ->method('setex')
            ->with($expectedHashKey, 3600, $expectedKeyMap);

        $this->consumer->publicSaveSegmentDisabledInCache($idJob, $idSegment);
    }

    #[Test]
    public function saveSegmentDisabledInCacheUsesTtlOf3600(): void
    {
        $this->redisClient->method('hset');
        $this->redisClient->expects($this->once())
            ->method('expire')
            ->with($this->anything(), 3600);
        $this->redisClient->method('setex');

        $this->consumer->publicSaveSegmentDisabledInCache(1, 1);
    }

    #[Test]
    public function saveSegmentDisabledInCacheWithDifferentIds(): void
    {
        $idJob = 123;
        $idSegment = 456;
        $expectedKeyMap = 'segment_is_disabled_123_456';

        $this->redisClient->expects($this->once())
            ->method('hset')
            ->with($expectedKeyMap, $this->anything(), serialize([1]));

        $this->redisClient->method('expire');
        $this->redisClient->method('setex');

        $this->consumer->publicSaveSegmentDisabledInCache($idJob, $idSegment);
    }

    // ─── destroySegmentDisabledCache tests ───────────────────────────

    #[Test]
    public function destroySegmentDisabledCacheDeletesCacheKey(): void
    {
        $idJob = 5;
        $idSegment = 60;
        $expectedKeyMap = 'segment_is_disabled_' . $idJob . '_' . $idSegment;

        // _deleteCacheByKey with isReverseKeyMap = false calls del($key)
        $this->redisClient->expects($this->once())
            ->method('del')
            ->with($expectedKeyMap);

        $this->consumer->publicDestroySegmentDisabledCache($idJob, $idSegment);
    }

    // ─── cacheKeyAndQuery tests (tested indirectly) ──────────────────

    #[Test]
    public function cacheKeyFormatIsConsistentAcrossCalls(): void
    {
        $idJob = 10;
        $idSegment = 20;

        $callCount = 0;
        $capturedKeyMaps = [];

        $this->redisClient->method('hget')
            ->willReturnCallback(function ($keyMap) use (&$capturedKeyMaps) {
                $capturedKeyMaps[] = $keyMap;
                return serialize([1]);
            });

        $this->consumer->publicIsSegmentDisabled($idJob, $idSegment);
        $this->consumer->publicIsSegmentDisabled($idJob, $idSegment);

        $this->assertCount(2, $capturedKeyMaps);
        $this->assertEquals($capturedKeyMaps[0], $capturedKeyMaps[1]);
        $this->assertEquals('segment_is_disabled_10_20', $capturedKeyMaps[0]);
    }

    #[Test]
    public function differentJobAndSegmentIdsProduceDifferentCacheKeys(): void
    {
        $capturedKeyMaps = [];

        $this->redisClient->method('hget')
            ->willReturnCallback(function ($keyMap) use (&$capturedKeyMaps) {
                $capturedKeyMaps[] = $keyMap;
                return serialize([0]);
            });

        $this->consumer->publicIsSegmentDisabled(1, 100);
        $this->consumer->publicIsSegmentDisabled(2, 200);

        $this->assertCount(2, $capturedKeyMaps);
        $this->assertNotEquals($capturedKeyMaps[0], $capturedKeyMaps[1]);
        $this->assertEquals('segment_is_disabled_1_100', $capturedKeyMaps[0]);
        $this->assertEquals('segment_is_disabled_2_200', $capturedKeyMaps[1]);
    }

    // ─── CACHE_TTL constant test ─────────────────────────────────────

    #[Test]
    public function cacheTtlConstantIs3600(): void
    {
        $ref = new ReflectionClass(SegmentDisabledTraitConsumer::class);
        $this->assertEquals(3600, $ref->getConstant('CACHE_TTL'));
    }

    // ─── Edge cases ──────────────────────────────────────────────────

    #[Test]
    public function isSegmentDisabledWithZeroIds(): void
    {
        $this->redisClient->method('hget')
            ->willReturn(serialize([1]));

        $this->assertTrue($this->consumer->publicIsSegmentDisabled(0, 0));
    }

    #[Test]
    public function saveAndCheckConsistency(): void
    {
        $idJob = 42;
        $idSegment = 77;
        $expectedKeyMap = 'segment_is_disabled_42_77';
        $expectedQuery = '__SEGMENT_IS_DISABLED__42_77';
        $expectedHashKey = md5($expectedQuery);

        // First call: save
        $this->redisClient->expects($this->once())
            ->method('hset')
            ->with($expectedKeyMap, $expectedHashKey, serialize([1]));
        $this->redisClient->method('expire');
        $this->redisClient->method('setex');

        $this->consumer->publicSaveSegmentDisabledInCache($idJob, $idSegment);

        // Simulate that after save, reading from cache returns [1]
        $this->redisClient->method('hget')
            ->with($expectedKeyMap, $expectedHashKey)
            ->willReturn(serialize([1]));

        $result = $this->consumer->publicIsSegmentDisabled($idJob, $idSegment);
        $this->assertTrue($result);
    }

    #[Test]
    public function isSegmentDisabledReturnsFalseForNonArrayCacheValue(): void
    {
        // If hget returns a serialized non-array value that unserializes to false/empty
        $this->redisClient->method('hget')
            ->willReturn(serialize(false));

        // unserialize(serialize(false)) = false, which is a bool
        // _getFromCacheMap returns null for bool values
        // This triggers the DB fallback path
        $this->markTestSkipped('Requires database connection for fallback path');
    }

    #[Test]
    public function cacheInitSetsTtlCorrectly(): void
    {
        // After calling any method that triggers cacheInit, TTL should be set
        $this->redisClient->method('hget')->willReturn(serialize([0]));

        $this->consumer->publicIsSegmentDisabled(1, 1);

        $this->assertEquals(3600, $this->consumer->getCacheTTL());
    }
}

