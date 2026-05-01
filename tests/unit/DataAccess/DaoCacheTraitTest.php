<?php

declare(strict_types=1);

namespace Tests\Unit\DataAccess;

use Model\DataAccess\DaoCacheTrait;
use Model\DataAccess\XFetchEnvelope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Utils\Registry\AppConfig;

class DaoCacheTraitHarness
{
    use DaoCacheTrait;

    public function setTTL(int $ttl): void
    {
        $this->cacheTTL = $ttl;
    }

    public function getTTL(): int
    {
        return $this->cacheTTL;
    }

    public function setXFetchEnabled(bool $enabled): void
    {
        $this->xFetchEnabled = $enabled;
    }

    public function callSetLastComputeDelta(float $delta): void
    {
        $this->_setLastComputeDelta($delta);
    }

    public function callShouldRecompute(float $storedAt, float $delta, int $ttl): bool
    {
        return $this->_shouldRecompute($storedAt, $delta, $ttl);
    }

    public function callGetFromCacheMap(string $keyMap, string $query): ?array
    {
        return $this->_getFromCacheMap($keyMap, $query);
    }

    public function callSetInCacheMap(string $keyMap, string $query, array $value): void
    {
        $this->_setInCacheMap($keyMap, $query, $value);
    }

    public function callSerializeForCacheKey(array $params): string
    {
        return $this->_serializeForCacheKey($params);
    }
}

class FakeRedisClient extends Client
{
    private array $hashes = [];
    private array $strings = [];
    public array $calls = [];

    public function __construct()
    {
        // Skip parent constructor — no real connection
    }

    public function __call($commandID, $arguments)
    {
        $this->calls[] = [$commandID, $arguments];

        return match (strtolower($commandID)) {
            'hget' => $this->hashes[$arguments[0]][$arguments[1]] ?? null,
            'hset' => $this->doHset($arguments[0], $arguments[1], $arguments[2]),
            'hdel' => $this->doHdel($arguments[0], $arguments[1]),
            'expire' => true,
            'setex' => $this->doSetex($arguments[0], $arguments[1], $arguments[2]),
            'get' => $this->strings[$arguments[0]] ?? null,
            'del' => $this->doDel($arguments[0]),
            default => null,
        };
    }

    private function doHset(string $key, string $field, string $value): int
    {
        $this->hashes[$key][$field] = $value;
        return 1;
    }

    private function doHdel(string $key, array $fields): int
    {
        $count = 0;
        foreach ($fields as $field) {
            if (isset($this->hashes[$key][$field])) {
                unset($this->hashes[$key][$field]);
                $count++;
            }
        }
        return $count;
    }

    private function doSetex(string $key, int $ttl, string $value): void
    {
        $this->strings[$key] = $value;
    }

    private function doDel(string $key): int
    {
        $existed = isset($this->hashes[$key]) || isset($this->strings[$key]);
        unset($this->hashes[$key], $this->strings[$key]);
        return $existed ? 1 : 0;
    }

    public function getStoredHash(string $key): array
    {
        return $this->hashes[$key] ?? [];
    }
}

class DaoCacheTraitTest extends TestCase
{
    private DaoCacheTraitHarness $harness;
    private FakeRedisClient $redis;

    protected function setUp(): void
    {
        $this->harness = new DaoCacheTraitHarness();
        $this->redis = new FakeRedisClient();
        DaoCacheTraitHarness::setCacheConnection($this->redis);
        AppConfig::$SKIP_SQL_CACHE = false;
    }

    protected function tearDown(): void
    {
        DaoCacheTraitHarness::setCacheConnection(null);
        AppConfig::$SKIP_SQL_CACHE = false;
    }

    #[Test]
    public function setCacheTTLSetsTTL(): void
    {
        $this->harness->setCacheTTL(120);

        self::assertSame(120, $this->harness->getTTL());
    }

    #[Test]
    public function setCacheTTLWithNullSetsZero(): void
    {
        $this->harness->setCacheTTL(100);
        $this->harness->setCacheTTL(null);

        self::assertSame(0, $this->harness->getTTL());
    }

    #[Test]
    public function setCacheTTLIsNoOpWhenCacheSkipped(): void
    {
        AppConfig::$SKIP_SQL_CACHE = true;
        $this->harness->setCacheTTL(300);

        self::assertSame(0, $this->harness->getTTL());
    }

    #[Test]
    public function shouldRecomputeReturnsFalseWhenDeltaIsZero(): void
    {
        self::assertFalse($this->harness->callShouldRecompute(microtime(true), 0.0, 60));
    }

    #[Test]
    public function shouldRecomputeReturnsFalseWhenDeltaIsNegative(): void
    {
        self::assertFalse($this->harness->callShouldRecompute(microtime(true), -1.0, 60));
    }

    #[Test]
    public function shouldRecomputeReturnsTrueWhenEntryIsExpired(): void
    {
        // storedAt 200s ago, TTL 60s → deterministically expired
        self::assertTrue($this->harness->callShouldRecompute(microtime(true) - 200.0, 0.5, 60));
    }

    #[Test]
    public function shouldRecomputeReturnsFalseWhenEntryIsFresh(): void
    {
        // storedAt = now, TTL 3600s → never recompute (100 iterations to account for randomness)
        $storedAt = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            if ($this->harness->callShouldRecompute($storedAt, 0.01, 3600)) {
                self::fail('Fresh entry should not trigger recompute');
            }
        }
        self::assertTrue(true);
    }

    #[Test]
    public function getFromCacheMapReturnsNullWhenCacheSkipped(): void
    {
        AppConfig::$SKIP_SQL_CACHE = true;
        $this->harness->setTTL(60);

        self::assertNull($this->harness->callGetFromCacheMap('map', 'SELECT 1'));
    }

    #[Test]
    public function getFromCacheMapReturnsNullWhenTTLIsZero(): void
    {
        $this->harness->setTTL(0);

        self::assertNull($this->harness->callGetFromCacheMap('map', 'SELECT 1'));
    }

    #[Test]
    public function getFromCacheMapReturnsNullOnCacheMiss(): void
    {
        $this->harness->setTTL(60);

        self::assertNull($this->harness->callGetFromCacheMap('map', 'SELECT 1'));
    }

    #[Test]
    public function getFromCacheMapReturnsValueFromRawSerializedArray(): void
    {
        $data = [['id' => 1, 'name' => 'test']];
        $key = md5('SELECT 1');
        $this->redis->__call('hset', ['map', $key, serialize($data)]);

        $this->harness->setTTL(60);
        $this->harness->setXFetchEnabled(false);

        self::assertSame($data, $this->harness->callGetFromCacheMap('map', 'SELECT 1'));
    }

    #[Test]
    public function getFromCacheMapUnwrapsXFetchEnvelopeWhenFresh(): void
    {
        $data = [['id' => 2]];
        $envelope = new XFetchEnvelope($data, microtime(true), 0.01);
        $key = md5('SELECT 1');
        $this->redis->__call('hset', ['map', $key, serialize($envelope)]);

        $this->harness->setTTL(3600);
        $this->harness->setXFetchEnabled(true);

        self::assertSame($data, $this->harness->callGetFromCacheMap('map', 'SELECT 1'));
    }

    #[Test]
    public function getFromCacheMapReturnsNullForExpiredXFetchEnvelope(): void
    {
        $data = [['id' => 3]];
        $envelope = new XFetchEnvelope($data, microtime(true) - 200.0, 0.5);
        $key = md5('SELECT 1');
        $this->redis->__call('hset', ['map', $key, serialize($envelope)]);

        $this->harness->setTTL(60);
        $this->harness->setXFetchEnabled(true);

        self::assertNull($this->harness->callGetFromCacheMap('map', 'SELECT 1'));
    }

    #[Test]
    public function getFromCacheMapSkipsXFetchWhenDisabled(): void
    {
        $data = [['id' => 4]];
        $envelope = new XFetchEnvelope($data, microtime(true) - 200.0, 0.5);
        $key = md5('SELECT 1');
        $this->redis->__call('hset', ['map', $key, serialize($envelope)]);

        $this->harness->setTTL(60);
        $this->harness->setXFetchEnabled(false);

        self::assertSame($data, $this->harness->callGetFromCacheMap('map', 'SELECT 1'));
    }

    #[Test]
    public function getFromCacheMapSkipsXFetchWhenTTLBelowThreshold(): void
    {
        $data = [['id' => 5]];
        $envelope = new XFetchEnvelope($data, microtime(true) - 200.0, 0.5);
        $key = md5('SELECT 1');
        $this->redis->__call('hset', ['map', $key, serialize($envelope)]);

        // TTL=5 is below XFETCH_MIN_TTL_THRESHOLD (10) → XFetch skipped, value returned
        $this->harness->setTTL(5);
        $this->harness->setXFetchEnabled(true);

        self::assertSame($data, $this->harness->callGetFromCacheMap('map', 'SELECT 1'));
    }

    #[Test]
    public function setInCacheMapStoresXFetchEnvelopeWhenEnabled(): void
    {
        $this->harness->setTTL(120);
        $this->harness->setXFetchEnabled(true);
        $this->harness->callSetLastComputeDelta(0.03);

        $value = [['id' => 10]];
        $this->harness->callSetInCacheMap('map', 'SELECT 1', $value);

        $stored = $this->redis->getStoredHash('map');
        $key = md5('SELECT 1');
        $envelope = unserialize($stored[$key]);

        self::assertInstanceOf(XFetchEnvelope::class, $envelope);
        self::assertSame($value, $envelope->value);
        self::assertEqualsWithDelta(0.03, $envelope->delta, 0.001);
        self::assertEqualsWithDelta(microtime(true), $envelope->storedAt, 1.0);
    }

    #[Test]
    public function setInCacheMapStoresRawArrayWhenXFetchDisabled(): void
    {
        $this->harness->setTTL(120);
        $this->harness->setXFetchEnabled(false);

        $value = [['id' => 20]];
        $this->harness->callSetInCacheMap('map', 'SELECT 1', $value);

        $stored = $this->redis->getStoredHash('map');
        $key = md5('SELECT 1');
        $deserialized = unserialize($stored[$key]);

        self::assertIsArray($deserialized);
        self::assertSame($value, $deserialized);
    }

    #[Test]
    public function setInCacheMapUsesFallbackDeltaWhenNoneSet(): void
    {
        $this->harness->setTTL(120);
        $this->harness->setXFetchEnabled(true);
        // No callSetLastComputeDelta → falls back to XFETCH_FALLBACK_DELTA (0.05)

        $this->harness->callSetInCacheMap('map', 'SELECT 1', [['id' => 30]]);

        $stored = $this->redis->getStoredHash('map');
        $key = md5('SELECT 1');
        $envelope = unserialize($stored[$key]);

        self::assertInstanceOf(XFetchEnvelope::class, $envelope);
        self::assertEqualsWithDelta(0.05, $envelope->delta, 0.001);
    }

    #[Test]
    public function setInCacheMapDoesNothingWhenTTLIsZero(): void
    {
        $this->harness->setTTL(0);
        $this->harness->callSetInCacheMap('map', 'SELECT 1', [['id' => 40]]);

        self::assertSame([], $this->redis->getStoredHash('map'));
    }

    #[Test]
    public function setInCacheMapSetsExpireAndReverseKey(): void
    {
        $this->harness->setTTL(300);
        $this->harness->setXFetchEnabled(false);
        $this->harness->callSetInCacheMap('map', 'SELECT 1', [['id' => 50]]);

        $expireCalled = false;
        $setexCalled = false;
        foreach ($this->redis->calls as [$cmd, $args]) {
            if (strtolower($cmd) === 'expire' && $args[0] === 'map' && $args[1] === 300) {
                $expireCalled = true;
            }
            if (strtolower($cmd) === 'setex' && $args[0] === md5('SELECT 1') && $args[1] === 300 && $args[2] === 'map') {
                $setexCalled = true;
            }
        }

        self::assertTrue($expireCalled, 'expire should be called on the hash key');
        self::assertTrue($setexCalled, 'setex should store reverse mapping');
    }

    #[Test]
    public function serializeForCacheKeyCastsValuesToStrings(): void
    {
        $result = $this->harness->callSerializeForCacheKey(['id' => 42, 'active' => true]);

        self::assertSame(serialize(['id' => '42', 'active' => '1']), $result);
    }

    #[Test]
    public function roundTripSetThenGetReturnsOriginalData(): void
    {
        $this->harness->setTTL(120);
        $this->harness->setXFetchEnabled(true);
        $this->harness->callSetLastComputeDelta(0.02);

        $value = [['id' => 100, 'name' => 'round_trip']];
        $this->harness->callSetInCacheMap('mymap', 'SELECT * FROM t', $value);

        $result = $this->harness->callGetFromCacheMap('mymap', 'SELECT * FROM t');

        self::assertSame($value, $result);
    }

    #[Test]
    public function setCacheConnectionAcceptsNull(): void
    {
        DaoCacheTraitHarness::setCacheConnection(null);

        $reflection = new \ReflectionProperty(DaoCacheTraitHarness::class, 'cache_con');
        self::assertNull($reflection->getValue());
    }
}
