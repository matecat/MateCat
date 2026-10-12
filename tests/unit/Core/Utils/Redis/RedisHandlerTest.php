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

    /**
     * Predis assigns the master role only from `role=master`, never from position, so this set can
     * serve reads from a random replica and cannot complete a single write. Failing here is what
     * keeps that from surfacing as "every user is logged out" — authenticate() catches Throwable.
     */
    public function testReplicationModeWithoutANamedMasterThrows(): void
    {
        AppConfig::$REDIS_MODE    = 'replication';
        AppConfig::$REDIS_SERVERS = 'tcp://a:6379,tcp://b:6380';

        $handler = new RedisHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('?role=master');

        $handler->getConnection();
    }

    public function testReplicationModeWithANamedMasterIsAccepted(): void
    {
        AppConfig::$REDIS_MODE    = 'replication';
        AppConfig::$REDIS_SERVERS = 'tcp://a:6379?role=master,tcp://b:6380';

        // Predis connects lazily, so building the client reaches the guard without touching either
        // host — which is what makes this assertable without a replicated Redis to point at.
        $this->assertInstanceOf(Client::class, (new RedisHandler())->getConnection());
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
