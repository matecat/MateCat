<?php

namespace Matecat\Core\Controllers\Authentication;

use Controller\Abstracts\Authentication\UserStateStore;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\XFetchEnvelope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use ReflectionProperty;
use Utils\Registry\AppConfig;

#[CoversClass(UserStateStore::class)]
class UserStateStoreTest extends AbstractTest
{
    /**
     * Calls recorded off the Redis stub, as [method, args] pairs.
     *
     * @var list<array{0: string, 1: array<int, mixed>}>
     */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->calls = [];
    }

    protected function tearDown(): void
    {
        UserStateStore::setCacheConnection(null);
        parent::tearDown();
    }

    /**
     * A Redis stub that records every call and answers `hget` from the given field map.
     *
     * @param array<string, string> $hashFields field name (already md5-ed) => raw stored string
     */
    private function redisStub(array $hashFields = []): Client
    {
        $redis = $this->createStub(Client::class);
        $redis->method('__call')
            ->willReturnCallback(function (string $method, array $args) use ($hashFields) {
                $this->calls[] = [$method, $args];

                if ($method === 'hget') {
                    return $hashFields[$args[1]] ?? null;
                }

                if ($method === 'hdel' || $method === 'del') {
                    return 1;
                }

                return null;
            });

        return $redis;
    }

    /**
     * @return list<array<int, mixed>> the argument lists of every recorded call to $method
     */
    private function callsTo(string $method): array
    {
        $out = [];
        foreach ($this->calls as [$called, $args]) {
            if ($called === $method) {
                $out[] = $args;
            }
        }

        return $out;
    }

    #[Test]
    public function constructorSetsCacheTtlTo24Hours(): void
    {
        $store = new UserStateStore();
        $prop = new ReflectionProperty(UserStateStore::class, 'cacheTTL');

        $this->assertSame(60 * 60 * 24, $prop->getValue($store));
    }

    /**
     * The opposite of SessionTokenStoreHandler, which disables XFetch because a token is stored
     * state. The profile is computed and expensive, so it wants early recomputation.
     */
    #[Test]
    public function xFetchStaysEnabled(): void
    {
        $store = new UserStateStore();
        $prop = new ReflectionProperty(UserStateStore::class, 'xFetchEnabled');

        $this->assertTrue($prop->getValue($store));
    }

    #[Test]
    public function setProfileWritesToThePerUserHash(): void
    {
        UserStateStore::setCacheConnection($this->redisStub());

        (new UserStateStore())->setProfile(123, ['user' => ['uid' => 123]], 0.5);

        $hset = $this->callsTo('hset');
        $this->assertCount(1, $hset);
        $this->assertSame('user_state:123', $hset[0][0]);
        $this->assertSame(md5('user_profile:123'), $hset[0][1]);
    }

    /**
     * The measured build cost must reach the envelope. A consumer that forgets to pass it inherits
     * the trait's 0.05s fallback, which collapses the early-recomputation window.
     */
    #[Test]
    public function setProfileStoresTheMeasuredComputeDelta(): void
    {
        UserStateStore::setCacheConnection($this->redisStub());

        (new UserStateStore())->setProfile(7, ['user' => ['uid' => 7]], 1.75);

        $envelope = unserialize($this->callsTo('hset')[0][2]);
        $this->assertInstanceOf(XFetchEnvelope::class, $envelope);
        $this->assertSame(1.75, $envelope->delta);
        $this->assertNotSame(0.05, $envelope->delta);
        $this->assertSame([['user' => ['uid' => 7]]], $envelope->value);
    }

    #[Test]
    public function setProfileRefreshesTheHashTtl(): void
    {
        UserStateStore::setCacheConnection($this->redisStub());

        (new UserStateStore())->setProfile(7, ['user' => ['uid' => 7]], 0.5);

        $expire = $this->callsTo('expire');
        $this->assertCount(1, $expire);
        $this->assertSame('user_state:7', $expire[0][0]);
        $this->assertSame(60 * 60 * 24, $expire[0][1]);
    }

    #[Test]
    public function getProfileReturnsTheStoredPayload(): void
    {
        $payload = ['user' => ['uid' => 7], 'teams' => [], 'metadata' => null];
        UserStateStore::setCacheConnection($this->redisStub([
            md5('user_profile:7') => serialize(new XFetchEnvelope([$payload], microtime(true), 1.0)),
        ]));

        $this->assertSame($payload, (new UserStateStore())->getProfile(7));
    }

    /**
     * Values written before XFetch was enabled are stored bare, so the reader must accept both
     * shapes — the trait unwraps, and this asserts the store does not assume an envelope.
     */
    #[Test]
    public function getProfileReadsAnUnwrappedPayload(): void
    {
        $payload = ['user' => ['uid' => 7]];
        UserStateStore::setCacheConnection($this->redisStub([
            md5('user_profile:7') => serialize([$payload]),
        ]));

        $this->assertSame($payload, (new UserStateStore())->getProfile(7));
    }

    #[Test]
    public function getProfileReturnsNullOnAMiss(): void
    {
        UserStateStore::setCacheConnection($this->redisStub());

        $this->assertNull((new UserStateStore())->getProfile(7));
    }

    /**
     * A malformed entry is a miss, so the caller rebuilds, rather than a partial payload handed to
     * the client.
     */
    #[Test]
    public function getProfileReturnsNullWhenTheStoredShapeIsNotAPayload(): void
    {
        UserStateStore::setCacheConnection($this->redisStub([
            md5('user_profile:7') => serialize(['not-an-array']),
        ]));

        $this->assertNull((new UserStateStore())->getProfile(7));
    }

    /**
     * Field-scoped invalidation: only the profile field is dropped, which is what keeps future
     * fields on this hash unaffected.
     */
    #[Test]
    public function invalidateProfileRemovesOnlyTheProfileField(): void
    {
        UserStateStore::setCacheConnection($this->redisStub());

        $this->assertTrue((new UserStateStore())->invalidateProfile(7));

        $hdel = $this->callsTo('hdel');
        $this->assertCount(1, $hdel);
        $this->assertSame('user_state:7', $hdel[0][0]);
        $this->assertSame([md5('user_profile:7')], $hdel[0][1]);
    }

    /**
     * The reverse key the trait deletes alongside the field is uid-scoped. With a uid-less field
     * string every user would share md5('user_profile'), so one user's invalidation would delete a
     * reverse key pointing at another user's hash — and _deleteCacheByKey() follows that key to a
     * map and deletes the map.
     */
    #[Test]
    public function invalidateProfileDeletesAUidScopedReverseKey(): void
    {
        UserStateStore::setCacheConnection($this->redisStub());

        (new UserStateStore())->invalidateProfile(7);

        $del = $this->callsTo('del');
        $this->assertCount(1, $del);
        $this->assertSame(md5('user_profile:7'), $del[0][0]);
        $this->assertNotSame(md5('user_profile'), $del[0][0]);
    }

    #[Test]
    public function twoUsersShareNeitherHashNorField(): void
    {
        UserStateStore::setCacheConnection($this->redisStub());
        $store = new UserStateStore();

        $store->setProfile(1, ['user' => ['uid' => 1]], 0.5);
        $store->setProfile(2, ['user' => ['uid' => 2]], 0.5);

        $hset = $this->callsTo('hset');
        $this->assertNotSame($hset[0][0], $hset[1][0], 'each user must have its own hash');
        $this->assertNotSame($hset[0][1], $hset[1][1], 'each user must have its own field');
    }

    /**
     * Whole-user invalidation deletes the hash directly. Passing it as a reverse key would make the
     * trait GET the key first and delete whatever name it found there.
     */
    #[Test]
    public function invalidateDeletesTheHashDirectly(): void
    {
        UserStateStore::setCacheConnection($this->redisStub());

        $this->assertTrue((new UserStateStore())->invalidate(7));

        $del = $this->callsTo('del');
        $this->assertCount(1, $del);
        $this->assertSame('user_state:7', $del[0][0]);
        $this->assertSame([], $this->callsTo('get'));
    }

    /**
     * This is a cache, so the kill switch has to reach it — unlike the token handler, which bypasses
     * it on purpose.
     */
    #[Test]
    public function skipSqlCacheDisablesTheStore(): void
    {
        $previous = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        try {
            UserStateStore::setCacheConnection($this->redisStub([
                md5('user_profile:7') => serialize([['user' => ['uid' => 7]]]),
            ]));
            $store = new UserStateStore();

            $store->setProfile(7, ['user' => ['uid' => 7]], 0.5);

            $this->assertNull($store->getProfile(7));
            $this->assertSame([], $this->callsTo('hset'));
        } finally {
            AppConfig::$SKIP_SQL_CACHE = $previous;
        }
    }

}
