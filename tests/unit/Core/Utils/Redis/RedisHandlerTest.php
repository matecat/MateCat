<?php

namespace Matecat\Core\Utils\Redis;

use Exception;
use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Group;
use Predis\Client;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class RedisHandlerTest extends AbstractTest
{
    private RedisHandler $handler;

    /** @var string|array<string|int, string> */
    private string|array $originalServers;
    private string $originalMode;
    private string $originalSentinelService;
    private ?string $originalPassword;
    private int $originalInstanceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServers         = AppConfig::$REDIS_SERVERS;
        $this->originalMode            = AppConfig::$REDIS_MODE;
        $this->originalSentinelService = AppConfig::$REDIS_SENTINEL_SERVICE;
        $this->originalPassword        = AppConfig::$REDIS_PASSWORD;
        $this->originalInstanceId      = AppConfig::$INSTANCE_ID;

        AppConfig::$REDIS_MODE = 'single';
        $this->handler = new RedisHandler();
    }

    protected function tearDown(): void
    {
        AppConfig::$REDIS_SERVERS          = $this->originalServers;
        AppConfig::$REDIS_MODE             = $this->originalMode;
        AppConfig::$REDIS_SENTINEL_SERVICE = $this->originalSentinelService;
        AppConfig::$REDIS_PASSWORD         = $this->originalPassword;
        AppConfig::$INSTANCE_ID            = $this->originalInstanceId;
        parent::tearDown();
    }

    // ── Single mode (backward compat) ──

    public function testGetConnectionReturnsClient(): void
    {
        $client = $this->handler->getConnection();
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testGetConnectionReturnsFunctionalClient(): void
    {
        $client = $this->handler->getConnection();
        $pong = $client->ping();
        $this->assertSame('PONG', (string) $pong);
    }

    public function testGetConnectionReusesClientOnSecondCall(): void
    {
        $client1 = $this->handler->getConnection();
        $client2 = $this->handler->getConnection();
        $this->assertSame($client1, $client2);
    }

    // ── Lock / Unlock ──

    public function testTryLockAndUnlock(): void
    {
        $key = 'test_lock_' . uniqid();

        $this->handler->tryLock($key, 5);

        $value = $this->handler->getConnection()->get("lock:" . $key);
        $this->assertNotNull($value);

        $this->handler->unlock($key);

        $value = $this->handler->getConnection()->get("lock:" . $key);
        $this->assertNull($value);
    }

    public function testTryLockThrowsOnTimeout(): void
    {
        $key        = 'test_timeout_lock_' . uniqid();
        $connection = $this->handler->getConnection();

        $connection->setnx("lock:" . $key, "other_instance");
        $connection->expire("lock:" . $key, 10);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Lock wait timeout reached.");

        try {
            $this->handler->tryLock($key, 1);
        } finally {
            $connection->del("lock:" . $key);
        }
    }

    public function testUnlockDoesNotRemoveLockOwnedByOtherInstance(): void
    {
        $key        = 'test_other_owner_' . uniqid();
        $connection = $this->handler->getConnection();

        $connection->set("lock:" . $key, "some_other_instance_identifier");

        $this->handler->unlock($key);

        $value = $connection->get("lock:" . $key);
        $this->assertNotNull($value, 'Lock owned by another instance should not be removed');

        $connection->del("lock:" . $key);
    }

    // ── formatDSN ──

    public function testFormatDSNAppendsInstanceId(): void
    {
        AppConfig::$INSTANCE_ID = 5;

        $handler = new class extends RedisHandler {
            public function publicFormatDSN(string $dsnString): string
            {
                return $this->formatDSN($dsnString);
            }
        };

        $this->assertSame('tcp://redis:6379?database=5', $handler->publicFormatDSN('tcp://redis:6379'));
        $this->assertSame('tcp://redis:6379?timeout=5&database=5', $handler->publicFormatDSN('tcp://redis:6379?timeout=5'));
    }

    public function testFormatDSNReturnsUnchangedWhenInstanceIdZero(): void
    {
        AppConfig::$INSTANCE_ID = 0;

        $handler = new class extends RedisHandler {
            public function publicFormatDSN(string $dsnString): string
            {
                return $this->formatDSN($dsnString);
            }
        };

        $this->assertSame('tcp://redis:6379', $handler->publicFormatDSN('tcp://redis:6379'));
    }

    // ── resolveServers (tested via TestableRedisHandler) ──

    public function testResolveServersFromCommaSeparatedString(): void
    {
        AppConfig::$REDIS_SERVERS = 'tcp://a:6379, tcp://b:6380, tcp://c:6381';
        AppConfig::$INSTANCE_ID   = 0;

        $handler = new TestableRedisHandler();
        $servers = $handler->publicResolveServers();

        $this->assertSame([
            'tcp://a:6379',
            'tcp://b:6380',
            'tcp://c:6381',
        ], $servers);
    }

    public function testResolveServersFromSingleString(): void
    {
        AppConfig::$REDIS_SERVERS = 'tcp://redis:6379';
        AppConfig::$INSTANCE_ID   = 0;

        $handler = new TestableRedisHandler();
        $servers = $handler->publicResolveServers();

        $this->assertSame(['tcp://redis:6379'], $servers);
    }

    public function testResolveServersFromArray(): void
    {
        AppConfig::$REDIS_SERVERS = ['tcp://a:6379', 'tcp://b:6380'];
        AppConfig::$INSTANCE_ID   = 0;

        $handler = new TestableRedisHandler();
        $servers = $handler->publicResolveServers();

        $this->assertSame(['tcp://a:6379', 'tcp://b:6380'], $servers);
    }

    public function testResolveServersAppliesInstanceId(): void
    {
        AppConfig::$REDIS_SERVERS = 'tcp://a:6379,tcp://b:6380';
        AppConfig::$INSTANCE_ID   = 7;

        $handler = new TestableRedisHandler();
        $servers = $handler->publicResolveServers();

        $this->assertSame([
            'tcp://a:6379?database=7',
            'tcp://b:6380?database=7',
        ], $servers);
    }

    // ── Mode validation ──

    public function testInvalidModeThrowsException(): void
    {
        AppConfig::$REDIS_MODE    = 'nonexistent';
        AppConfig::$REDIS_SERVERS = 'tcp://redis:6379';

        $handler = new RedisHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown REDIS_MODE: 'nonexistent'");

        $handler->getConnection();
    }

    // ── Instance identifier ──

    public function testGetInstanceIdentifierIsUnique(): void
    {
        $handler1 = new RedisHandler();
        $handler2 = new RedisHandler();

        $ref = new \ReflectionMethod(RedisHandler::class, 'getInstanceIdentifier');

        $id1 = $ref->invoke($handler1);
        $id2 = $ref->invoke($handler2);

        $this->assertNotSame($id1, $id2);
    }

    // ── Single mode via config ──

    public function testSingleModeConnects(): void
    {
        AppConfig::$REDIS_MODE = 'single';

        $handler = new RedisHandler();
        $client  = $handler->getConnection();

        $this->assertSame('PONG', (string) $client->ping());
    }
}

/**
 * Exposes resolveServers() for testing.
 */
class TestableRedisHandler extends RedisHandler
{
    /** @return list<string> */
    public function publicResolveServers(): array
    {
        $ref = new \ReflectionMethod(RedisHandler::class, 'resolveServers');

        return $ref->invoke($this);
    }
}
