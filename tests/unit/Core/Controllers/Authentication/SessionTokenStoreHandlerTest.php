<?php

namespace Matecat\Core\Controllers\Authentication;

use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use ReflectionProperty;

#[CoversClass(SessionTokenStoreHandler::class)]
class SessionTokenStoreHandlerTest extends AbstractTest
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

    #[Test]
    public function pruneDropsExpiredTokensAndLeavesLiveOnesAlone(): void
    {
        $expiredField = md5('expired-cookie');
        $liveField    = md5('live-cookie');

        $touched = [];

        $redis = $this->createStub(Client::class);
        $redis->method('__call')
            ->willReturnCallback(
                function (string $method, array $args) use (&$touched, $expiredField, $liveField) {
                    if ($method === 'hgetall') {
                        // _setInCacheMap() stores a serialized single-element list per field.
                        return [
                            $expiredField => serialize(['expired-cookie']),
                            $liveField => serialize(['live-cookie']),
                        ];
                    }

                    $touched[] = [$method, $args[1] ?? $args[0]];

                    return 1;
                }
            );

        SessionTokenStoreHandler::setCacheConnection($redis);

        (new SessionTokenStoreHandler())->pruneExpiredLoginTokens(
            36,
            static fn(string $token): bool => $token === 'expired-cookie'
        );

        // Field name and reverse-key name are the same md5, so a dropped token costs one hdel plus
        // one del — and the live token must be touched by neither.
        $this->assertSame(
            [['hdel', [$expiredField]], ['del', $expiredField]],
            $touched
        );
    }

    #[Test]
    public function pruneKeepsAnyStoredValueItCannotRead(): void
    {
        $touched = [];

        $redis = $this->createStub(Client::class);
        $redis->method('__call')
            ->willReturnCallback(function (string $method) use (&$touched) {
                if ($method === 'hgetall') {
                    // Not the shape _setInCacheMap() writes, so the token cannot be recovered.
                    return ['some-field' => serialize('not a single-element list')];
                }

                $touched[] = $method;

                return 1;
            });

        SessionTokenStoreHandler::setCacheConnection($redis);

        (new SessionTokenStoreHandler())->pruneExpiredLoginTokens(
            36,
            // Would condemn everything if it were ever consulted. It must not be: an unreadable
            // value is kept, because a slightly larger hash beats logging someone out.
            static fn(string $token): bool => true
        );

        $this->assertSame([], $touched);
    }

    #[Test]
    public function revokeAllLoginTokensDeletesEveryTokenAndTheMapInOneCall(): void
    {
        $deleted = [];
        $redis   = $this->createStub(Client::class);
        $redis->method('__call')
            ->willReturnCallback(function (string $method, array $args) use (&$deleted) {
                if ($method === 'hkeys') {
                    $this->assertSame('active_user_login_tokens:123', $args[0]);

                    return [md5('cookie-one'), md5('cookie-two')];
                }

                if ($method === 'del') {
                    $deleted[] = $args[0];

                    return 1;
                }

                return null;
            });

        SessionTokenStoreHandler::setCacheConnection($redis);
        (new SessionTokenStoreHandler())->revokeAllLoginTokens(123);

        // Reverse keys and the map go together in a single DEL, so there is no window where the map
        // is gone while its reverse keys still name it.
        $this->assertSame(
            [[md5('cookie-one'), md5('cookie-two'), 'active_user_login_tokens:123']],
            $deleted
        );
    }

    #[Test]
    public function revokeAllLoginTokensStillDropsTheMapWhenItHoldsNoTokens(): void
    {
        $deleted = [];
        $redis   = $this->createStub(Client::class);
        $redis->method('__call')
            ->willReturnCallback(function (string $method, array $args) use (&$deleted) {
                if ($method === 'hkeys') {
                    return [];
                }

                if ($method === 'del') {
                    $deleted[] = $args[0];

                    return 1;
                }

                return null;
            });

        SessionTokenStoreHandler::setCacheConnection($redis);
        (new SessionTokenStoreHandler())->revokeAllLoginTokens(123);

        // A map with no tokens still costs exactly one DEL, carrying the map alone. Redis rejects
        // DEL with no keys, and the single-call form makes that unreachable by construction rather
        // than by a guard: the argument always holds the map name.
        $this->assertSame([['active_user_login_tokens:123']], $deleted);
    }

    protected function tearDown(): void
    {
        SessionTokenStoreHandler::setCacheConnection(null);
        parent::tearDown();
    }
}
