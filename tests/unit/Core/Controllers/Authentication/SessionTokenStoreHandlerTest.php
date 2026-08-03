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

    /**
     * retireLoginToken() is the primitive under both prev-chain retirement and logout cleanup, and
     * every caller test mocks it — so nothing verified what it actually sends to Redis. Dropping the
     * DEL, or aiming the HDEL at the wrong key, would leave all of those callers green.
     */
    #[Test]
    public function retireLoginTokenDropsTheFieldAndItsReverseKey(): void
    {
        $fieldName = md5('a-superseded-cookie');

        $calls = [];

        $redis = $this->createStub(Client::class);
        $redis->method('__call')->willReturnCallback(function ($method, $args) use (&$calls) {
            $calls[] = [$method, $args];

            return 1;
        });

        $handler = new SessionTokenStoreHandler();
        $handler->setCacheConnection($redis);

        $handler->retireLoginToken(123, $fieldName);

        // The field lives in the uid-keyed map; the reverse key is a top-level key that happens to
        // carry the same name. Both, or the token stays reachable by one route or the other.
        $this->assertSame(
            [
                ['hdel', ['active_user_login_tokens:123', [$fieldName]]],
                ['del', [$fieldName]],
            ],
            $calls
        );
    }

    /**
     * A cookie minted before `prev` existed carries no predecessor, and the renewal path hands that
     * absence straight through. Reaching Redis with an empty name would HDEL nothing and DEL the
     * empty key, so the guard is what keeps legacy cookies from renewing into a wasted round trip.
     */
    #[Test]
    public function retireLoginTokenIgnoresAnEmptyFieldName(): void
    {
        $calls = [];

        $redis = $this->createStub(Client::class);
        $redis->method('__call')->willReturnCallback(function ($method, $args) use (&$calls) {
            $calls[] = [$method, $args];

            return 1;
        });

        $handler = new SessionTokenStoreHandler();
        $handler->setCacheConnection($redis);

        $handler->retireLoginToken(123, '');

        $this->assertSame([], $calls);
    }

    #[Test]
    public function revokeAllLoginTokensDeletesEveryReverseKeyThenTheMap(): void
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

        // One key per DEL, never a multi-key DEL: the reverse keys and the map hash to different
        // slots, and Predis rejects a cross-slot DEL under REDIS_MODE=cluster. A regression here
        // would throw from a password change *after* the password was stored, leaving the tokens it
        // exists to revoke alive. Each recorded argument being a plain string is that guarantee.
        //
        // Order matters as much as the count: reverse keys first, map last. A stale reverse key
        // holds the map's name and that name is reused on the next login, so dropping the map first
        // would leave pointers aimed at a live map.
        $this->assertSame(
            [md5('cookie-one'), md5('cookie-two'), 'active_user_login_tokens:123'],
            $deleted
        );

        foreach ($deleted as $key) {
            $this->assertIsString($key);
        }
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

        // No tokens means the loop body never runs, leaving exactly the map's own DEL. The empty
        // argument list that Redis rejects is unreachable by construction rather than by a guard,
        // because the map's DEL sits outside the loop.
        $this->assertSame(['active_user_login_tokens:123'], $deleted);
    }

    protected function tearDown(): void
    {
        SessionTokenStoreHandler::setCacheConnection(null);
        parent::tearDown();
    }
}
