<?php

namespace unit\Traits;

require_once __DIR__ . '/RateLimiterTraitTest.php';

use Controller\Traits\SegmentDisabledTrait;
use Model\DataAccess\DaoCacheTrait;
use Model\DataAccess\Database;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use ReflectionClass;
use TestHelpers\AbstractTest;

/**
 * Concrete class that uses SegmentDisabledTrait for testing purposes.
 * Injects a FakeRedisClient via the static cache_con property.
 */
class SegmentDisabledTraitConsumer
{
    use SegmentDisabledTrait;

    public function setFakeRedis(FakeRedisClient $client): void
    {
        $ref = new ReflectionClass($this);
        $prop = $ref->getProperty('cache_con');
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

#[AllowMockObjectsWithoutExpectations]
class SegmentDisabledTraitTest extends AbstractTest
{
    private SegmentDisabledTraitConsumer $consumer;
    private FakeRedisClient $redis;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean slate: remove any leftover test data from prior runs
        $conn = Database::obtain()->getConnection();
        $conn->prepare("DELETE FROM segment_metadata WHERE id_segment = ?")->execute([888888]);

        // Reset static cache_con
        $ref = new ReflectionClass(SegmentDisabledTraitConsumer::class);
        $prop = $ref->getProperty('cache_con');
        $prop->setValue(null, null);

        $this->redis = new FakeRedisClient();
        $this->consumer = new SegmentDisabledTraitConsumer();
        $this->consumer->setFakeRedis($this->redis);
    }

    // ─── isSegmentDisabled tests ─────────────────────────────────────

    #[Test]
    public function isSegmentDisabledReturnsTrueWhenCachedValueIsOne(): void
    {
        $keyMap = 'segment_is_disabled_1_42';
        $query = '__SEGMENT_IS_DISABLED__1_42';
        $hashKey = md5($query);

        $this->redis->setHashValue($keyMap, $hashKey, serialize([1]));

        $result = $this->consumer->publicIsSegmentDisabled(1, 42);

        $this->assertTrue($result);
    }

    #[Test]
    public function isSegmentDisabledReturnsFalseWhenCachedValueIsZero(): void
    {
        $keyMap = 'segment_is_disabled_1_42';
        $query = '__SEGMENT_IS_DISABLED__1_42';
        $hashKey = md5($query);

        $this->redis->setHashValue($keyMap, $hashKey, serialize([0]));

        $result = $this->consumer->publicIsSegmentDisabled(1, 42);

        $this->assertFalse($result);
    }

    #[Test]
    public function isSegmentDisabledReturnsTrueForDifferentIds(): void
    {
        $keyMap = 'segment_is_disabled_999_888';
        $query = '__SEGMENT_IS_DISABLED__999_888';
        $hashKey = md5($query);

        $this->redis->setHashValue($keyMap, $hashKey, serialize([1]));

        $this->assertTrue($this->consumer->publicIsSegmentDisabled(999, 888));
    }

    #[Test]
    public function isSegmentDisabledUsesCorrectCacheKey(): void
    {
        $idJob = 7;
        $idSegment = 99;
        $expectedKeyMap = 'segment_is_disabled_7_99';
        $expectedQuery = '__SEGMENT_IS_DISABLED__7_99';
        $expectedHashKey = md5($expectedQuery);

        $this->redis->setHashValue($expectedKeyMap, $expectedHashKey, serialize([1]));

        $result = $this->consumer->publicIsSegmentDisabled($idJob, $idSegment);

        $this->assertTrue($result);

        // Verify hget was called with the correct key
        $hgetCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'hget');
        $firstHget = reset($hgetCalls);
        $this->assertEquals($expectedKeyMap, $firstHget['args'][0]);
        $this->assertEquals($expectedHashKey, $firstHget['args'][1]);
    }

    // ─── saveSegmentDisabledInCache tests ────────────────────────────

    #[Test]
    public function saveSegmentDisabledInCacheSetsValueInRedis(): void
    {
        $this->consumer->publicSaveSegmentDisabledInCache(3, 50);

        $expectedKeyMap = 'segment_is_disabled_3_50';
        $expectedQuery = '__SEGMENT_IS_DISABLED__3_50';
        $expectedHashKey = md5($expectedQuery);

        // Verify hset was called
        $hsetCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'hset');
        $this->assertNotEmpty($hsetCalls);
        $firstHset = reset($hsetCalls);
        $this->assertEquals($expectedKeyMap, $firstHset['args'][0]);
        $this->assertEquals($expectedHashKey, $firstHset['args'][1]);
        $this->assertEquals(serialize([1]), $firstHset['args'][2]);
    }

    #[Test]
    public function saveSegmentDisabledInCacheSetsTtl(): void
    {
        $this->consumer->publicSaveSegmentDisabledInCache(1, 1);

        $expireCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'expire');
        $this->assertNotEmpty($expireCalls);
        $firstExpire = reset($expireCalls);
        $this->assertEquals(3600, $firstExpire['args'][1]);
    }

    #[Test]
    public function saveSegmentDisabledInCacheWithDifferentIds(): void
    {
        $this->consumer->publicSaveSegmentDisabledInCache(123, 456);

        $expectedKeyMap = 'segment_is_disabled_123_456';

        $hsetCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'hset');
        $firstHset = reset($hsetCalls);
        $this->assertEquals($expectedKeyMap, $firstHset['args'][0]);
    }

    // ─── destroySegmentDisabledCache tests ───────────────────────────

    #[Test]
    public function destroySegmentDisabledCacheDeletesCacheKey(): void
    {
        $idJob     = 999999;
        $idSegment = 888888;

        // Insert a row so SegmentMetadataDao::delete has something to remove
        $conn = Database::obtain()->getConnection();
        $conn->prepare(
            "INSERT IGNORE INTO segment_metadata (id_segment, meta_key, meta_value) VALUES (?, ?, ?)"
        )->execute([$idSegment, 'translation_disabled', '1']);

        // Pre-populate the Redis hash so we can verify it gets cleared
        $expectedKeyMap = 'segment_is_disabled_' . $idJob . '_' . $idSegment;
        $expectedQuery  = '__SEGMENT_IS_DISABLED__' . $idJob . '_' . $idSegment;
        $expectedHash   = md5($expectedQuery);
        $this->redis->setHashValue($expectedKeyMap, $expectedHash, serialize([1]));

        // Act
        $this->consumer->publicDestroySegmentDisabledCache($idJob, $idSegment);

        // Assert DB row is gone
        $row = $conn->prepare("SELECT * FROM segment_metadata WHERE id_segment = ? AND meta_key = ?");
        $row->execute([$idSegment, 'translation_disabled']);
        $this->assertEmpty($row->fetchAll(), 'DB row should be deleted');

        // Assert Redis DEL was called on the cache key
        $delCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'del');
        $deletedKeys = array_map(fn($c) => $c['args'][0], array_values($delCalls));
        $this->assertContains($expectedKeyMap, $deletedKeys, 'Redis key should be deleted');
    }

    // ─── Cache key consistency tests ─────────────────────────────────

    #[Test]
    public function cacheKeyFormatIsConsistent(): void
    {
        $keyMap = 'segment_is_disabled_10_20';
        $query = '__SEGMENT_IS_DISABLED__10_20';
        $hashKey = md5($query);

        $this->redis->setHashValue($keyMap, $hashKey, serialize([1]));

        // Call twice
        $this->consumer->publicIsSegmentDisabled(10, 20);
        $this->consumer->publicIsSegmentDisabled(10, 20);

        $hgetCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'hget');
        $keys = array_map(fn($c) => $c['args'][0], $hgetCalls);

        $this->assertCount(2, $keys);
        $this->assertEquals($keys[0], $keys[1]);
        $this->assertEquals('segment_is_disabled_10_20', $keys[0]);
    }

    #[Test]
    public function differentJobAndSegmentIdsProduceDifferentCacheKeys(): void
    {
        $keyMap1 = 'segment_is_disabled_1_100';
        $query1 = '__SEGMENT_IS_DISABLED__1_100';
        $this->redis->setHashValue($keyMap1, md5($query1), serialize([0]));

        $keyMap2 = 'segment_is_disabled_2_200';
        $query2 = '__SEGMENT_IS_DISABLED__2_200';
        $this->redis->setHashValue($keyMap2, md5($query2), serialize([0]));

        $this->consumer->publicIsSegmentDisabled(1, 100);
        $this->consumer->publicIsSegmentDisabled(2, 200);

        $hgetCalls = array_filter($this->redis->calls, fn($c) => $c['method'] === 'hget');
        $keys = array_map(fn($c) => $c['args'][0], array_values($hgetCalls));

        $this->assertNotEquals($keys[0], $keys[1]);
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
        $keyMap = 'segment_is_disabled_0_0';
        $query = '__SEGMENT_IS_DISABLED__0_0';
        $this->redis->setHashValue($keyMap, md5($query), serialize([1]));

        $this->assertTrue($this->consumer->publicIsSegmentDisabled(0, 0));
    }

    #[Test]
    public function saveAndCheckConsistency(): void
    {
        $this->consumer->publicSaveSegmentDisabledInCache(42, 77);

        // After saving, the hash should contain the value
        $keyMap = 'segment_is_disabled_42_77';
        $query = '__SEGMENT_IS_DISABLED__42_77';
        $hashKey = md5($query);

        $store = $this->redis->getHashStore();
        $this->assertEquals(serialize([1]), $store[$keyMap][$hashKey] ?? null);
    }

    #[Test]
    public function cacheInitSetsTtlCorrectly(): void
    {
        $keyMap = 'segment_is_disabled_1_1';
        $query = '__SEGMENT_IS_DISABLED__1_1';
        $this->redis->setHashValue($keyMap, md5($query), serialize([0]));

        $this->consumer->publicIsSegmentDisabled(1, 1);

        $this->assertEquals(3600, $this->consumer->getCacheTTL());
    }
}

