<?php

namespace unit\Controllers\Authentication;

use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use ReflectionProperty;

#[CoversClass(SessionTokenStoreHandler::class)]
class SessionTokenStoreHandlerTest extends TestCase
{
    #[Test]
    public function constructorSetsCacheTtlTo7Days(): void
    {
        $handler = new SessionTokenStoreHandler();
        $prop = new ReflectionProperty(SessionTokenStoreHandler::class, 'cacheTTL');

        $this->assertSame(60 * 60 * 24 * 7, $prop->getValue($handler));
    }

    #[Test]
    public function constructorDisablesXFetch(): void
    {
        $handler = new SessionTokenStoreHandler();
        $prop = new ReflectionProperty(SessionTokenStoreHandler::class, 'xFetchEnabled');

        $this->assertFalse($prop->getValue($handler));
    }

    #[Test]
    public function setCookieLoginTokenActiveWritesToRedis(): void
    {
        $hsetCalled = false;
        $redis = $this->createStub(Client::class);
        $redis->method('__call')
            ->willReturnCallback(function (string $method, array $args) use (&$hsetCalled) {
                if ($method === 'hset') {
                    $hsetCalled = true;
                    $this->assertSame('active_user_login_tokens:123', $args[0]);
                    $this->assertSame(md5('token-value'), $args[1]);

                    return 1;
                }

                return null;
            });

        SessionTokenStoreHandler::setCacheConnection($redis);
        $handler = new SessionTokenStoreHandler();
        $handler->setCookieLoginTokenActive(123, 'token-value');

        $this->assertTrue($hsetCalled, 'Expected hset to be called on Redis');
    }

    #[Test]
    public function isLoginCookieStillActiveReturnsTrueWhenTokenExists(): void
    {
        $redis = $this->createStub(Client::class);
        $redis->method('__call')
            ->willReturnCallback(function (string $method) {
                if ($method === 'hget') {
                    return serialize(['token-value']);
                }

                return null;
            });

        SessionTokenStoreHandler::setCacheConnection($redis);
        $handler = new SessionTokenStoreHandler();

        $this->assertTrue($handler->isLoginCookieStillActive(123, 'token-value'));
    }

    #[Test]
    public function isLoginCookieStillActiveReturnsFalseWhenTokenMissing(): void
    {
        $redis = $this->createStub(Client::class);
        $redis->method('__call')
            ->willReturn(null);

        SessionTokenStoreHandler::setCacheConnection($redis);
        $handler = new SessionTokenStoreHandler();

        $this->assertFalse($handler->isLoginCookieStillActive(123, 'nonexistent-token'));
    }

    #[Test]
    public function removeLoginCookieFromStoreSkipsEmptyValue(): void
    {
        $redis = $this->createMock(Client::class);
        $redis->expects($this->never())
            ->method('__call');

        SessionTokenStoreHandler::setCacheConnection($redis);
        $handler = new SessionTokenStoreHandler();
        $handler->removeLoginCookieFromStore(123, '');
    }

    #[Test]
    public function removeLoginCookieFromStoreCallsRedis(): void
    {
        $methodsCalled = [];
        $redis = $this->createStub(Client::class);
        $redis->method('__call')
            ->willReturnCallback(function (string $method) use (&$methodsCalled) {
                $methodsCalled[] = $method;

                return ($method === 'hdel') ? 1 : 0;
            });

        SessionTokenStoreHandler::setCacheConnection($redis);
        $handler = new SessionTokenStoreHandler();
        $handler->removeLoginCookieFromStore(123, 'token-value');

        $this->assertContains('del', $methodsCalled);
        $this->assertContains('hdel', $methodsCalled);
    }

    protected function tearDown(): void
    {
        SessionTokenStoreHandler::setCacheConnection(null);
        parent::tearDown();
    }
}
