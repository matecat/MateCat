<?php

declare(strict_types=1);

namespace Matecat\Core\DataAccess;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\DaoCacheTrait;
use Model\DataAccess\IDatabase;
use PDO;
use Model\DataAccess\XFetchEnvelope;
use PHPUnit\Framework\Attributes\Test;
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

    private ?IDatabase $transactionScope = null;

    public function setTransactionScope(?IDatabase $database): void
    {
        $this->transactionScope = $database;
    }

    protected function _cacheTransactionScope(): ?IDatabase
    {
        return $this->transactionScope;
    }

    public function callIsInsideTransaction(): bool
    {
        return $this->_isInsideTransaction();
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

    public function callDeleteCacheByKey(string $key, ?bool $isReverseKeyMap = true): bool
    {
        return $this->_deleteCacheByKey($key, $isReverseKeyMap);
    }

    public function callRemoveObjectCacheMapElement(string $keyMap, string $keyElementName): bool
    {
        return $this->_removeObjectCacheMapElement($keyMap, $keyElementName);
    }

    public function callSerializeForCacheKey(array $params): string
    {
        return $this->_serializeForCacheKey($params);
    }

    public function callGetManyFromCacheMap(array $specs): array
    {
        return $this->_getManyFromCacheMap($specs);
    }

    public function callSetManyInCacheMap(array $entries): void
    {
        $this->_setManyInCacheMap($entries);
    }

    public function getLastComputeDelta(): float
    {
        return $this->lastComputeDelta;
    }

    /** @var list<string> Every log type this harness was asked to emit, in order. */
    public array $logCalls = [];

    protected function _logCache(string $type, string $key, mixed $value, string $sqlQuery): void
    {
        $this->logCalls[] = $type;
    }
}

/**
 * Collects the commands a pipelined block queues and returns their responses in order, which is
 * what Predis hands back from `Client::pipeline()`.
 */
class FakeRedisPipeline
{
    public array $responses = [];

    public function __construct(private readonly FakeRedisClient $client)
    {
    }

    public function __call($commandID, $arguments)
    {
        $this->responses[] = $this->client->__call($commandID, $arguments);

        return $this;
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

    /**
     * `pipeline()` is a real method on Predis\Client, not one the __call dispatcher below sees, so
     * it has to be overridden explicitly. Commands still run through __call, which keeps them
     * visible in $calls and lets a test count the batch.
     */
    public function pipeline(...$arguments)
    {
        $callback = null;

        foreach ($arguments as $argument) {
            if (is_callable($argument)) {
                $callback = $argument;
            }
        }

        if ($callback === null) {
            return [];
        }

        $pipeline = new FakeRedisPipeline($this);
        $callback($pipeline);

        return $pipeline->responses;
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

class DaoCacheTraitTest extends AbstractTest
{
    private DaoCacheTraitHarness $harness;
    private FakeRedisClient $redis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->harness = new DaoCacheTraitHarness();
        $this->redis = new FakeRedisClient();
        DaoCacheTraitHarness::setCacheConnection($this->redis);
        AppConfig::$SKIP_SQL_CACHE = false;
    }

    protected function tearDown(): void
    {
        DaoCacheTraitHarness::setCacheConnection(null);
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
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

    #[Test]
    public function isInsideTransactionIsFalseWithoutAScope(): void
    {
        $this->harness->setTransactionScope(null);

        self::assertFalse($this->harness->callIsInsideTransaction());
    }

    #[Test]
    public function isInsideTransactionFollowsTheScopedConnection(): void
    {
        $connection = $this->createStub(PDO::class);
        $connection->method('inTransaction')->willReturn(true);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($connection);

        $this->harness->setTransactionScope($database);

        self::assertTrue($this->harness->callIsInsideTransaction());
    }

    #[Test]
    public function isInsideTransactionIsFalseWhenTheScopedConnectionHasNone(): void
    {
        $connection = $this->createStub(PDO::class);
        $connection->method('inTransaction')->willReturn(false);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($connection);

        $this->harness->setTransactionScope($database);

        self::assertFalse($this->harness->callIsInsideTransaction());
    }

    #[Test]
    public function setInCacheMapWritesNothingWhileATransactionIsOpen(): void
    {
        $connection = $this->createStub(PDO::class);
        $connection->method('inTransaction')->willReturn(true);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($connection);

        $this->harness->setTransactionScope($database);
        $this->harness->setTTL(3600);

        $this->harness->callSetInCacheMap('map', 'SELECT 1', [['id' => 60]]);

        self::assertSame([], $this->redis->getStoredHash('map'));
    }

    #[Test]
    public function setInCacheMapWritesOnceNoTransactionIsOpen(): void
    {
        $connection = $this->createStub(PDO::class);
        $connection->method('inTransaction')->willReturn(false);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($connection);

        $this->harness->setTransactionScope($database);
        $this->harness->setTTL(3600);

        $this->harness->callSetInCacheMap('map', 'SELECT 1', [['id' => 60]]);

        self::assertNotSame([], $this->redis->getStoredHash('map'));
    }

    #[Test]
    public function deleteCacheByKeyRunsImmediatelyOutsideATransaction(): void
    {
        $this->harness->setTransactionScope(null);
        $this->redis->setex('plain:key', 60, 'value');

        $this->harness->callDeleteCacheByKey('plain:key', false);

        self::assertNull($this->redis->get('plain:key'));
    }

    #[Test]
    public function deleteCacheByKeyIsDeferredWhileATransactionIsOpen(): void
    {
        $queued = [];
        $this->harness->setTransactionScope($this->transactionalDatabaseCollecting($queued));
        $this->redis->setex('plain:key', 60, 'value');

        self::assertTrue($this->harness->callDeleteCacheByKey('plain:key', false));

        // Still there: the eviction is queued for the commit, not run.
        self::assertSame('value', $this->redis->get('plain:key'));
        self::assertCount(1, $queued);

        ($queued[0])();

        self::assertNull($this->redis->get('plain:key'));
    }

    #[Test]
    public function removeObjectCacheMapElementIsDeferredWhileATransactionIsOpen(): void
    {
        $queued = [];
        $this->harness->setTransactionScope($this->transactionalDatabaseCollecting($queued));
        $this->harness->setTTL(300);
        $this->harness->callSetInCacheMap('map', 'SELECT 1', [['id' => 70]]);

        self::assertTrue($this->harness->callRemoveObjectCacheMapElement('map', 'SELECT 1'));
        self::assertCount(1, $queued);
    }

    /**
     * An IDatabase reporting an open transaction whose onCommit() collects instead of running, so a
     * test can assert both that nothing happened yet and that the queued work does the job.
     *
     * @param list<callable> $queued
     */
    private function transactionalDatabaseCollecting(array &$queued): IDatabase
    {
        $connection = $this->createStub(PDO::class);
        $connection->method('inTransaction')->willReturn(true);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($connection);
        $database->method('onCommit')->willReturnCallback(
            static function (callable $callback) use (&$queued): void {
                $queued[] = $callback;
            }
        );

        return $database;
    }

    // ── batched read/write primitives ────────────────────────────────────────
    //
    // These back `AbstractDao::_fetchObjectMapPerId()`, which caches a set of entities one entry
    // per entity so the per-entity eviction door reaches them. That only holds if the batched and
    // the single accessor address entries identically, so the two round-trip tests below are the
    // load-bearing ones: they read what the other wrote.

    #[Test]
    public function getManyFromCacheMapReturnsOnlyTheHits(): void
    {
        $this->harness->setCacheTTL(60);
        $this->harness->callSetInCacheMap('map:1', 'q1', ['one']);
        $this->harness->callSetInCacheMap('map:2', 'q2', ['two']);

        $hits = $this->harness->callGetManyFromCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1'],
            2 => ['keyMap' => 'map:2', 'query' => 'q2'],
            3 => ['keyMap' => 'map:3', 'query' => 'q3'],
        ]);

        self::assertSame([1 => ['one'], 2 => ['two']], $hits);
        self::assertArrayNotHasKey(3, $hits, 'a miss is absent, not null');
    }

    #[Test]
    public function getManyFromCacheMapPreservesCallerKeysWhateverTheOrder(): void
    {
        $this->harness->setCacheTTL(60);
        $this->harness->callSetInCacheMap('map:b', 'qb', ['b']);
        $this->harness->callSetInCacheMap('map:c', 'qc', ['c']);

        $hits = $this->harness->callGetManyFromCacheMap([
            'a' => ['keyMap' => 'map:a', 'query' => 'qa'],
            'b' => ['keyMap' => 'map:b', 'query' => 'qb'],
            'c' => ['keyMap' => 'map:c', 'query' => 'qc'],
        ]);

        self::assertSame(['b' => ['b'], 'c' => ['c']], $hits, 'a leading miss must not shift the rest');
    }

    #[Test]
    public function getManyFromCacheMapIssuesOneCommandPerSpecInOneBatch(): void
    {
        $this->harness->setCacheTTL(60);
        $this->redis->calls = [];

        $this->harness->callGetManyFromCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1'],
            2 => ['keyMap' => 'map:2', 'query' => 'q2'],
            3 => ['keyMap' => 'map:3', 'query' => 'q3'],
        ]);

        $hgets = array_filter($this->redis->calls, static fn(array $call): bool => $call[0] === 'hget');

        self::assertCount(3, $hgets, 'one HGET per entity, sent as a single pipelined batch');
    }

    #[Test]
    public function getManyFromCacheMapReturnsEmptyWhenTTLIsZero(): void
    {
        $this->harness->setCacheTTL(60);
        $this->harness->callSetInCacheMap('map:1', 'q1', ['one']);
        $this->harness->setCacheTTL(0);

        self::assertSame([], $this->harness->callGetManyFromCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1'],
        ]));
    }

    #[Test]
    public function getManyFromCacheMapReturnsEmptyWhenCacheSkipped(): void
    {
        $this->harness->setCacheTTL(60);
        $this->harness->callSetInCacheMap('map:1', 'q1', ['one']);
        AppConfig::$SKIP_SQL_CACHE = true;

        self::assertSame([], $this->harness->callGetManyFromCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1'],
        ]));
    }

    #[Test]
    public function getManyFromCacheMapTouchesNothingForEmptySpecs(): void
    {
        $this->harness->setCacheTTL(60);
        $this->redis->calls = [];

        self::assertSame([], $this->harness->callGetManyFromCacheMap([]));
        self::assertSame([], $this->redis->calls, 'an empty batch must not reach Redis at all');
    }

    #[Test]
    public function getManyFromCacheMapUnwrapsXFetchEnvelopeWhenFresh(): void
    {
        $this->harness->setCacheTTL(60);
        $this->harness->setXFetchEnabled(true);
        $this->redis->__call('hset', ['map:1', md5('q1'), serialize(new XFetchEnvelope(['fresh'], microtime(true), 0.001))]);

        $hits = $this->harness->callGetManyFromCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1'],
        ]);

        self::assertSame([1 => ['fresh']], $hits);
    }

    #[Test]
    public function getManyFromCacheMapTreatsAnExpiredEnvelopeAsAMiss(): void
    {
        $this->harness->setCacheTTL(60);
        $this->harness->setXFetchEnabled(true);
        $this->redis->__call('hset', ['map:1', md5('q1'), serialize(new XFetchEnvelope(['stale'], microtime(true) - 3600, 10.0))]);

        self::assertSame([], $this->harness->callGetManyFromCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1'],
        ]));
    }

    /**
     * The property the whole design rests on: an entry written in a batch is the same entry the
     * single reader finds. If these two drift, a member list caches under an address its own
     * eviction door cannot name — which is the bug this replaced.
     */
    #[Test]
    public function entriesWrittenInABatchAreReadableBySingleReader(): void
    {
        $this->harness->setCacheTTL(60);

        $this->harness->callSetManyInCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1', 'value' => ['one']],
            2 => ['keyMap' => 'map:2', 'query' => 'q2', 'value' => ['two']],
        ]);

        self::assertSame(['one'], $this->harness->callGetFromCacheMap('map:1', 'q1'));
        self::assertSame(['two'], $this->harness->callGetFromCacheMap('map:2', 'q2'));
    }

    #[Test]
    public function entriesWrittenSinglyAreReadableByBatchedReader(): void
    {
        $this->harness->setCacheTTL(60);

        $this->harness->callSetInCacheMap('map:1', 'q1', ['one']);

        self::assertSame(
            [1 => ['one']],
            $this->harness->callGetManyFromCacheMap([1 => ['keyMap' => 'map:1', 'query' => 'q1']])
        );
    }

    /**
     * The reverse pointer is what `_deleteCacheByKey()` follows to find the hash to drop, so a
     * batch that skipped it would write entries no eviction door could reach.
     */
    #[Test]
    public function setManyInCacheMapWritesTheReverseKeyEvictionFollows(): void
    {
        $this->harness->setCacheTTL(60);

        $this->harness->callSetManyInCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1', 'value' => ['one']],
        ]);

        self::assertTrue($this->harness->callDeleteCacheByKey(md5('q1')));
        self::assertNull($this->harness->callGetFromCacheMap('map:1', 'q1'));
    }

    #[Test]
    public function setManyInCacheMapIsNoOpWhenTTLIsZero(): void
    {
        $this->harness->setCacheTTL(0);
        $this->redis->calls = [];

        $this->harness->callSetManyInCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1', 'value' => ['one']],
        ]);

        self::assertSame([], $this->redis->calls);
    }

    #[Test]
    public function setManyInCacheMapTouchesNothingForEmptyEntries(): void
    {
        $this->harness->setCacheTTL(60);
        $this->redis->calls = [];

        $this->harness->callSetManyInCacheMap([]);

        self::assertSame([], $this->redis->calls);
    }

    /**
     * Same rule as the single write: a row read inside an open transaction is private to this
     * connection, and a rollback un-makes it.
     */
    #[Test]
    public function setManyInCacheMapWritesNothingInsideATransaction(): void
    {
        $queued = [];
        $this->harness->setCacheTTL(60);
        $this->harness->setTransactionScope($this->transactionalDatabaseCollecting($queued));
        $this->redis->calls = [];

        $this->harness->callSetManyInCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1', 'value' => ['one']],
        ]);

        self::assertSame([], $this->redis->calls);
        self::assertNull($this->harness->callGetFromCacheMap('map:1', 'q1'));
    }

    /**
     * One query produced the whole batch, so its cost is consumed once. Charging it per entry
     * would leave a stale delta behind for the next unrelated write.
     */
    #[Test]
    public function setManyInCacheMapConsumesTheComputeDeltaOnce(): void
    {
        $this->harness->setCacheTTL(60);
        $this->harness->setXFetchEnabled(true);
        $this->harness->callSetLastComputeDelta(0.25);

        $this->harness->callSetManyInCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1', 'value' => ['one']],
            2 => ['keyMap' => 'map:2', 'query' => 'q2', 'value' => ['two']],
        ]);

        self::assertSame(0.0, $this->harness->getLastComputeDelta());
    }

    #[Test]
    public function testBatchedReadLogsOnceForTheWholeBatchNotOncePerEntry(): void
    {
        // The single-key reader logs one line per key, which is proportional to the work it did.
        // The batched reader does one round trip for the whole batch, so logging per entry turns a
        // 327-member team into 327 near-identical DEBUG lines. Measured on real data that logging
        // was 95% of the batched read's wall clock: 29.96 ms with it, 1.49 ms without.
        $this->redis->hset('map:1', md5('q1'), serialize(['one']));
        $this->redis->hset('map:2', md5('q2'), serialize(['two']));
        $this->redis->hset('map:3', md5('q3'), serialize(['three']));

        $this->harness->setCacheTTL(60);
        $this->harness->logCalls = [];

        $this->harness->callGetManyFromCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1'],
            2 => ['keyMap' => 'map:2', 'query' => 'q2'],
            3 => ['keyMap' => 'map:3', 'query' => 'q3'],
        ]);

        self::assertCount(1, $this->harness->logCalls);
    }

    #[Test]
    public function testBatchedReadStillLogsOnceWhenEveryEntryMisses(): void
    {
        $this->harness->setCacheTTL(60);
        $this->harness->logCalls = [];

        $this->harness->callGetManyFromCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1'],
            2 => ['keyMap' => 'map:2', 'query' => 'q2'],
        ]);

        self::assertCount(1, $this->harness->logCalls);
    }

    #[Test]
    public function testBatchedWriteLogsOnceForTheWholeBatchNotOncePerEntry(): void
    {
        $this->harness->setCacheTTL(60);
        $this->harness->logCalls = [];

        $this->harness->callSetManyInCacheMap([
            1 => ['keyMap' => 'map:1', 'query' => 'q1', 'value' => ['one']],
            2 => ['keyMap' => 'map:2', 'query' => 'q2', 'value' => ['two']],
            3 => ['keyMap' => 'map:3', 'query' => 'q3', 'value' => ['three']],
        ]);

        self::assertCount(1, $this->harness->logCalls);
    }

    #[Test]
    public function testSingleReadStillLogsItsOwnEntry(): void
    {
        // The per-entry line is only dropped where it is amplified. A single read does one round
        // trip for one key, so its one line stays.
        $this->redis->hset('map:1', md5('q1'), serialize(['one']));

        $this->harness->setCacheTTL(60);
        $this->harness->logCalls = [];

        $this->harness->callGetFromCacheMap('map:1', 'q1');

        self::assertCount(1, $this->harness->logCalls);
    }

}
