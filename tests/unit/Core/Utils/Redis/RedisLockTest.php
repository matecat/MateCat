<?php

namespace Matecat\Core\Utils\Redis;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use Utils\Redis\RedisHandler;
use Utils\Redis\RedisLock;

/**
 * Against the real Redis, because the release is a Lua script: only the server can say it is right.
 */
#[Group('PersistenceNeeded')]
class RedisLockTest extends AbstractTest
{
    private Client $redis;
    private RedisLock $lock;
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redis = (new RedisHandler())->getConnection();
        $this->lock = new RedisLock($this->redis);
        $this->key = 'redis_lock_test:' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->redis->del($this->key);

        parent::tearDown();
    }

    #[Test]
    public function acquire_stores_the_token_with_the_ttl(): void
    {
        $token = $this->lock->acquire($this->key, 60);

        self::assertNotNull($token);
        self::assertSame($token, $this->redis->get($this->key));
        self::assertGreaterThan(0, $this->redis->ttl($this->key));
        self::assertLessThanOrEqual(60, $this->redis->ttl($this->key));
    }

    #[Test]
    public function acquire_returns_null_while_the_key_is_held(): void
    {
        $first = $this->lock->acquire($this->key, 60);

        self::assertNull($this->lock->acquire($this->key, 60));
        self::assertSame($first, $this->redis->get($this->key));
    }

    #[Test]
    public function every_acquire_gets_its_own_token(): void
    {
        $first = $this->lock->acquire($this->key, 60);
        $this->redis->del($this->key);
        $second = $this->lock->acquire($this->key, 60);

        self::assertNotSame($first, $second);
    }

    #[Test]
    public function release_with_the_holder_token_deletes_the_key(): void
    {
        $token = (string)$this->lock->acquire($this->key, 60);

        self::assertTrue($this->lock->release($this->key, $token));
        self::assertSame(0, $this->redis->exists($this->key));
        self::assertNotNull($this->lock->acquire($this->key, 60));
    }

    /**
     * The holder ran past its TTL and the key went to somebody else: its release must leave the new
     * holder's lock in place.
     */
    #[Test]
    public function release_with_a_stale_token_leaves_the_next_holder_alone(): void
    {
        $stale = (string)$this->lock->acquire($this->key, 60);
        $this->redis->del($this->key);
        $current = $this->lock->acquire($this->key, 60);

        self::assertFalse($this->lock->release($this->key, $stale));
        self::assertSame($current, $this->redis->get($this->key));
    }

    #[Test]
    public function release_of_an_expired_key_reports_it_was_not_held(): void
    {
        self::assertFalse($this->lock->release($this->key, 'never-acquired'));
    }
}
