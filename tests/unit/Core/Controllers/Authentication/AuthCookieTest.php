<?php

namespace Matecat\Core\Controllers\Authentication;

use Controller\Abstracts\Authentication\AuthCookie;
use Controller\Abstracts\Authentication\CookieManager;
use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;

#[CoversClass(AuthCookie::class)]
class AuthCookieTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure AppConfig has valid values for testing
        AppConfig::$AUTHSECRET = 'test-secret-key-for-unit-tests';
        AppConfig::$AUTHCOOKIENAME = 'matecat_login_test';
        AppConfig::$AUTHCOOKIEDURATION = 3600;
        AppConfig::$BUILD_NUMBER = '1.0.0';
        AppConfig::$COOKIE_DOMAIN = '.example.com';
        unset($_COOKIE[AppConfig::$AUTHCOOKIENAME]);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[AppConfig::$AUTHCOOKIENAME]);
        parent::tearDown();
    }

    /**
     * A CookieManager whose low-level write is intercepted so the emitted
     * name/value/options can be asserted without touching PHP's setcookie().
     */
    private function spyingCookieManager(): CookieManager
    {
        return new class extends CookieManager {
            /** @var list<array{name:string,value:string,options:array<string,mixed>}> */
            public array $writes = [];

            protected function writeCookie(string $name, string $value, array $options): bool
            {
                $this->writes[] = ['name' => $name, 'value' => $value, 'options' => $options];

                return true;
            }
        };
    }

    /**
     * A ring that accepts whatever token it is shown.
     *
     * getCredentials() now always consults the ring. The previous static API took an *optional*
     * handler and skipped the check entirely when given none, so a test could get a payload back
     * without any ring at all — which is precisely the bypass this refactor removes. Tests that
     * want a successful read have to say so explicitly now.
     */
    private function acceptingTokenStore(): SessionTokenStoreHandler
    {
        $store = $this->createStub(SessionTokenStoreHandler::class);
        $store->method('isLoginCookieStillActive')->willReturn(true);

        return $store;
    }

    private function authCookie(
        ?SessionTokenStoreHandler $tokenStore = null,
        ?CookieManager $cookieManager = null,
    ): AuthCookie {
        return new AuthCookie(
            $tokenStore ?? $this->acceptingTokenStore(),
            $cookieManager ?? $this->spyingCookieManager()
        );
    }

    #[Test]
    public function getCredentialsReturnsNullWhenNoCookieExists(): void
    {
        $result = $this->authCookie()->getCredentials();

        $this->assertNull($result);
    }

    #[Test]
    public function getCredentialsReturnsNullForInvalidCookie(): void
    {
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = 'invalid-jwt-value';

        $result = $this->authCookie()->getCredentials();

        $this->assertNull($result);
    }

    /**
     * An unparseable cookie is dropped locally and the ring is left alone.
     *
     * This pins the half of the recursion fix that the count assertion in
     * {@see anUnparseableCookieIsClearedWithoutRecursing} cannot see. Both halves of the cycle had
     * to be broken — getData() clearing directly rather than through destroyAuthentication(), and
     * destroyAuthentication() reading through readPayload() rather than getData() — so undoing
     * either one alone leaves the tests green. Routing this path back through
     * destroyAuthentication() would reach for the ring, which is what is asserted against here.
     */
    #[Test]
    public function anUnparseableCookieIsDroppedWithoutTouchingTheRing(): void
    {
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = 'not-a-jwt';

        $store = $this->createMock(SessionTokenStoreHandler::class);
        $store->expects($this->never())->method('removeLoginCookieFromStore');
        $store->expects($this->never())->method('retireLoginToken');

        $cookieManager = $this->spyingCookieManager();

        $this->assertNull($this->authCookie($store, $cookieManager)->getCredentials());
        $this->assertCount(1, $cookieManager->writes);
    }

    #[Test]
    public function getCredentialsReturnsNullForEmptyCookie(): void
    {
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = '';

        $result = $this->authCookie()->getCredentials();

        $this->assertNull($result);
    }

    #[Test]
    public function getCredentialsReturnsPayloadForValidCookieTheRingAccepts(): void
    {
        $user = $this->createAuthenticatedUser();
        $cookie = $this->generateTestCookie($user);
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $cookie;

        $result = $this->authCookie()->getCredentials();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertSame(42, $result['user']['uid']);
        $this->assertSame('test@example.com', $result['user']['email']);
    }

    #[Test]
    public function getCredentialsReturnsNullWhenTokenNotInStore(): void
    {
        $user = $this->createAuthenticatedUser();
        $cookie = $this->generateTestCookie($user);
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $cookie;

        $store = $this->createStub(SessionTokenStoreHandler::class);
        $store->method('isLoginCookieStillActive')
            ->willReturn(false);

        // A revoked user still holds a perfectly valid, unexpired cookie. The ring is what stops
        // them, so this must be null even though the signature verifies.
        $result = $this->authCookie($store)->getCredentials();

        $this->assertNull($result);
    }

    #[Test]
    public function generateSignedAuthCookieReturnsArrayWithStringAndInt(): void
    {
        $user = $this->createAuthenticatedUser();
        $method = new ReflectionMethod(AuthCookie::class, 'generateSignedAuthCookie');

        $result = $method->invoke($this->authCookie(), $user);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertIsString($result[0]);
        $this->assertIsInt($result[1]);
        $this->assertGreaterThan(time(), $result[1]);
    }

    #[Test]
    public function setCredentialsThrowsRuntimeExceptionForUserWithoutUid(): void
    {
        $user = new UserStruct();
        $user->uid = null;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot set credentials for a user without a UID');

        $this->authCookie()->setCredentials($user);
    }

    #[Test]
    public function setCredentialsNewLoginActivatesToken(): void
    {
        $user = $this->createAuthenticatedUser();
        $store = $this->createMock(SessionTokenStoreHandler::class);

        $store->expects($this->once())
            ->method('setCookieLoginTokenActive')
            ->with(42, $this->isString());

        $cookieManager = $this->spyingCookieManager();

        $this->authCookie($store, $cookieManager)->setCredentials($user);

        // The signed auth cookie that actually ships to the browser.
        $this->assertCount(1, $cookieManager->writes);
        $write = $cookieManager->writes[0];
        $this->assertSame(AppConfig::$AUTHCOOKIENAME, $write['name']);
        $this->assertNotEmpty($write['value']);
        $this->assertSame('Lax', $write['options']['samesite']);
        $this->assertTrue($write['options']['secure']);
        $this->assertTrue($write['options']['httponly']);
        $this->assertGreaterThan(time(), $write['options']['expires']);
    }

    /**
     * setcookie() only queues a Set-Cookie header; it never populates $_COOKIE. So without this,
     * anything reading the cookie later in the same request sees whatever arrived with the request
     * instead of the token just issued. renewIfStale() has always done this; setCredentials() did not.
     */
    #[Test]
    public function setCredentialsMakesTheNewTokenVisibleToLaterReadsInTheSameRequest(): void
    {
        $user          = $this->createAuthenticatedUser();
        $cookieManager = $this->spyingCookieManager();

        $this->authCookie(null, $cookieManager)->setCredentials($user);

        $this->assertSame(
            $cookieManager->writes[0]['value'],
            $_COOKIE[AppConfig::$AUTHCOOKIENAME] ?? null
        );
    }

    /**
     * The security case behind the test above. LoginController::login() calls setCredentials() and then
     * AuthenticationHelper::fromRequest(), which resolves identity from $_COOKIE. On a browser that
     * still holds a live cookie for another user — an account switch without logging out first, or a
     * planted cookie — a stale $_COOKIE meant that second step authenticated the *previous* user,
     * stamped their uid into the session, and could renew their cookie over the one just issued. Two
     * Set-Cookie headers for one name, last one wins, so the browser kept the wrong account.
     */
    #[Test]
    public function loggingInReplacesAPreviousUsersCookieRatherThanLeavingItInPlace(): void
    {
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = 'previous-users-live-cookie';

        $user          = $this->createAuthenticatedUser();
        $cookieManager = $this->spyingCookieManager();

        $this->authCookie(null, $cookieManager)->setCredentials($user);

        $this->assertNotSame('previous-users-live-cookie', $_COOKIE[AppConfig::$AUTHCOOKIENAME]);
        $this->assertSame($cookieManager->writes[0]['value'], $_COOKIE[AppConfig::$AUTHCOOKIENAME]);
    }

    #[Test]
    public function loggingInNamesAReplacedCookieOfTheSameAccountAsItsPredecessor(): void
    {
        $user = $this->createAuthenticatedUser();

        $replaced                            = $this->generateTestCookie($user);
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $replaced;

        $cookieManager = $this->spyingCookieManager();
        $this->authCookie(null, $cookieManager)->setCredentials($user);

        // Without this the replaced token had nothing left to retire it: the browser leaves holding
        // the new cookie, so no request can ever renew the old chain, and the field sat in the ring
        // until its own expiry.
        $this->assertSame(md5($replaced), $this->prevClaimOf($cookieManager->writes[0]['value']));
    }

    /**
     * A field name only means anything inside its own user's ring, and `prev` is retired from the
     * ring of whoever the new cookie belongs to — so inheriting one across an account switch would
     * ask user B's ring to retire user A's token and drop a reverse key from A's live chain.
     */
    #[Test]
    public function loggingInDoesNotInheritAPredecessorFromADifferentAccount(): void
    {
        $previousOccupant      = $this->createAuthenticatedUser();
        $previousOccupant->uid = 7;

        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $this->generateTestCookie($previousOccupant);

        $cookieManager = $this->spyingCookieManager();
        $this->authCookie(null, $cookieManager)->setCredentials($this->createAuthenticatedUser());

        $this->assertNull($this->prevClaimOf($cookieManager->writes[0]['value']));
    }

    #[Test]
    public function aFirstLoginMintsNoPredecessor(): void
    {
        $cookieManager = $this->spyingCookieManager();
        $this->authCookie(null, $cookieManager)->setCredentials($this->createAuthenticatedUser());

        $this->assertNull($this->prevClaimOf($cookieManager->writes[0]['value']));
    }

    #[Test]
    public function anUnreadableCookieIsNotNamedAsAPredecessor(): void
    {
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = 'not-a-jwt';

        $cookieManager = $this->spyingCookieManager();
        $this->authCookie(null, $cookieManager)->setCredentials($this->createAuthenticatedUser());

        $this->assertNull($this->prevClaimOf($cookieManager->writes[0]['value']));
    }

    /**
     * @throws Exception
     */
    private function prevClaimOf(string $cookieValue): ?string
    {
        $prev = SimpleJWT::getValidatedInstanceFromString($cookieValue, AppConfig::$AUTHSECRET)->getPayload()['prev'] ?? null;

        return is_string($prev) ? $prev : null;
    }

    #[Test]
    public function destroyAuthenticationRemovesCookie(): void
    {
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = 'some-value';

        $cookieManager = $this->spyingCookieManager();

        $this->authCookie(null, $cookieManager)->destroyAuthentication();

        $this->assertArrayNotHasKey(AppConfig::$AUTHCOOKIENAME, $_COOKIE);

        // The browser-facing deletion cookie: empty value, past expiry.
        $this->assertCount(1, $cookieManager->writes);
        $write = $cookieManager->writes[0];
        $this->assertSame(AppConfig::$AUTHCOOKIENAME, $write['name']);
        $this->assertSame('', $write['value']);
        $this->assertLessThan(time(), $write['options']['expires']);
    }

    #[Test]
    public function destroyAuthenticationRemovesTokenFromStore(): void
    {
        $user = $this->createAuthenticatedUser();
        $cookie = $this->generateTestCookie($user);
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $cookie;

        $store = $this->createMock(SessionTokenStoreHandler::class);
        $store->expects($this->once())
            ->method('removeLoginCookieFromStore')
            ->with(42, $cookie);

        $this->authCookie($store)->destroyAuthentication();
    }

    #[Test]
    public function anUnparseableCookieIsClearedWithoutRecursing(): void
    {
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = 'not-a-jwt';

        $cookieManager = $this->spyingCookieManager();

        // getData() clears the cookie when it cannot parse, and destroyAuthentication() calls
        // getData(). While the ring handler was an optional per-call argument, getData() passed
        // none and the ring branch was skipped, which is what kept this from recursing. With the
        // handler bound in the constructor the clearing step has to be separate, or this call
        // re-enters destroyAuthentication() until the stack blows.
        $this->authCookie(null, $cookieManager)->destroyAuthentication();

        $this->assertArrayNotHasKey(AppConfig::$AUTHCOOKIENAME, $_COOKIE);

        // Exactly one deletion. Reading through getData() instead of readPayload() would clear
        // once inside the parse failure and once here, so the count is what pins the split.
        $this->assertCount(1, $cookieManager->writes);
    }

    private function createAuthenticatedUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';
        $user->first_name = 'Test';
        $user->last_name = 'User';
        $user->pass = 'hashed_password';

        return $user;
    }

    private function generateTestCookie(UserStruct $user): string
    {
        $method = new ReflectionMethod(AuthCookie::class, 'generateSignedAuthCookie');
        [$cookieData] = $method->invoke($this->authCookie(), $user);

        return $cookieData;
    }

    // ─── Sliding renewal and prev-chaining ────────────────────────────────────

    /**
     * Mints a cookie value that is genuinely $ageSeconds old.
     *
     * SimpleJWT captures time() in its constructor and sign() re-reads that captured value for
     * both iat and exp, so moving the captured clock is the only way to age a token without
     * re-implementing the signature in the test.
     *
     * @param array<string, mixed> $extraClaims
     */
    private function agedCookieValue(UserStruct $user, int $ageSeconds, array $extraClaims = []): string
    {
        $jwt = new SimpleJWT(
            array_merge([
                'user' => [
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'has_password' => !is_null($user->pass),
                    'last_name' => $user->last_name,
                    'uid' => (int)$user->uid,
                ],
            ], $extraClaims),
            AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            AppConfig::$AUTHSECRET,
            AppConfig::$AUTHCOOKIEDURATION
        );

        (new ReflectionProperty(SimpleJWT::class, 'now'))->setValue($jwt, time() - $ageSeconds);

        return $jwt->jsonSerialize();
    }

    private function renewableUser(): UserStruct
    {
        $user = $this->createAuthenticatedUser();
        $user->uid = 42;

        // The renewal window has to fit inside the cookie lifetime, or an aged token is simply
        // expired and gets rejected before renewal is ever considered.
        AppConfig::$AUTHCOOKIEDURATION = 60 * 60 * 24 * 7;

        return $user;
    }

    #[Test]
    public function aCookieOlderThanTheRenewalIntervalIsReissued(): void
    {
        $user = $this->renewableUser();
        $old  = $this->agedCookieValue($user, 60 * 60 * 48);

        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $old;

        $spy = $this->spyingCookieManager();

        $handler = $this->createMock(SessionTokenStoreHandler::class);
        $handler->expects($this->once())
            ->method('setCookieLoginTokenActive')
            ->with(42, $this->isString());

        $this->authCookie($handler, $spy)->renewIfStale($user);

        $this->assertCount(1, $spy->writes);
        $this->assertNotSame($old, $spy->writes[0]['value']);

        // Later reads in this same request must see the token just issued, not the one the browser
        // is about to stop sending.
        $this->assertSame($spy->writes[0]['value'], $_COOKIE[AppConfig::$AUTHCOOKIENAME]);
    }

    /**
     * The renewal threshold cannot sit past the expiry.
     *
     * Renewal only ever happens to a token that still validates, so a fixed one-day threshold under
     * a sub-day cookie lifetime means renewal never fires at all and every user is hard-logged-out
     * once per lifetime — remember-me quietly stops working. A 1-hour lifetime has to renew at 30
     * minutes, not at 24 hours.
     */
    #[Test]
    public function theRenewalThresholdIsBoundedByTheCookieLifetime(): void
    {
        $originalDuration = AppConfig::$AUTHCOOKIEDURATION;

        try {
            $user = $this->createAuthenticatedUser();

            AppConfig::$AUTHCOOKIEDURATION = 60 * 60;

            // Past half the lifetime, so due for renewal, but still inside it, so still valid.
            $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $this->agedCookieValue($user, 60 * 40);

            $spy = $this->spyingCookieManager();

            $handler = $this->createMock(SessionTokenStoreHandler::class);
            $handler->expects($this->once())->method('setCookieLoginTokenActive');

            $this->authCookie($handler, $spy)->renewIfStale($user);

            $this->assertCount(1, $spy->writes);
        } finally {
            AppConfig::$AUTHCOOKIEDURATION = $originalDuration;
        }
    }

    #[Test]
    public function aCookieYoungerThanTheRenewalIntervalIsLeftAlone(): void
    {
        $user = $this->renewableUser();

        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $this->agedCookieValue($user, 60);

        $spy = $this->spyingCookieManager();

        $handler = $this->createMock(SessionTokenStoreHandler::class);
        $handler->expects($this->never())->method('setCookieLoginTokenActive');
        $handler->expects($this->never())->method('retireLoginToken');

        $this->authCookie($handler, $spy)->renewIfStale($user);

        $this->assertSame([], $spy->writes);
    }

    #[Test]
    public function theReissuedCookieNamesTheSupersededTokenAsItsPrev(): void
    {
        $user = $this->renewableUser();
        $old  = $this->agedCookieValue($user, 60 * 60 * 48);

        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $old;

        $spy = $this->spyingCookieManager();

        $this->authCookie($this->createStub(SessionTokenStoreHandler::class), $spy)->renewIfStale($user);

        $payload = SimpleJWT::getValidatedInstanceFromString(
            $spy->writes[0]['value'],
            AppConfig::$AUTHSECRET
        )->getPayload();

        // The ring stores each token under md5(cookieValue), so prev is already the field name of
        // the token being superseded — nothing extra is hashed or stored to make the chain work.
        $this->assertSame(md5($old), $payload['prev']);
    }

    #[Test]
    public function renewalRetiresTheGrandparentAndNeverTheParent(): void
    {
        $user        = $this->renewableUser();
        $grandparent = md5('the-token-from-two-renewals-ago');
        $old         = $this->agedCookieValue($user, 60 * 60 * 48, ['prev' => $grandparent]);

        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $old;

        // The parent is what the browser is holding at this instant, so in-flight requests are
        // carrying it; retiring it here is the logout storm this design exists to avoid. The
        // grandparent is a full renewal interval old and no request lives that long.
        $handler = $this->createMock(SessionTokenStoreHandler::class);
        $handler->expects($this->once())
            ->method('retireLoginToken')
            ->with(42, $grandparent);

        $this->authCookie($handler)->renewIfStale($user);
    }

    #[Test]
    public function aLegacyCookieWithoutPrevRenewsAndRetiresNothing(): void
    {
        $user = $this->renewableUser();

        // Cookies already in browsers when this ships carry no prev claim. They must renew
        // normally rather than being rejected or triggering a bogus retirement.
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $this->agedCookieValue($user, 60 * 60 * 48);

        $spy = $this->spyingCookieManager();

        $handler = $this->createMock(SessionTokenStoreHandler::class);
        $handler->expects($this->once())->method('setCookieLoginTokenActive');
        $handler->expects($this->never())->method('retireLoginToken');

        $this->authCookie($handler, $spy)->renewIfStale($user);

        $this->assertCount(1, $spy->writes);
    }

    #[Test]
    public function twoRenewalsInTheSameSecondConvergeOnOneToken(): void
    {
        $user = $this->renewableUser();
        $old  = $this->agedCookieValue($user, 60 * 60 * 48);

        $issued = [];

        foreach ([1, 2] as $ignored) {
            $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $old;
            $spy = $this->spyingCookieManager();

            $this->authCookie($this->createStub(SessionTokenStoreHandler::class), $spy)->renewIfStale($user);

            $issued[] = $spy->writes[0]['value'];
        }

        // Parallel requests both see the same current cookie, and SimpleJWT stamps iat from time()
        // with no jti and no randomness. Identical payloads mean the HSET is idempotent, so the
        // ring gains one field rather than one per racing request.
        $this->assertSame($issued[0], $issued[1]);
    }

    #[Test]
    public function logoutRetiresTheSupersededTokenAsWell(): void
    {
        $user       = $this->renewableUser();
        $superseded = md5('the-token-this-one-replaced');

        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $this->agedCookieValue($user, 60, ['prev' => $superseded]);

        // Renewal deliberately leaves the parent live for in-flight requests. Without this the
        // browser stops holding it at logout but a captured copy keeps passing
        // isLoginCookieStillActive() for a full renewal interval.
        $handler = $this->createMock(SessionTokenStoreHandler::class);
        $handler->expects($this->once())->method('removeLoginCookieFromStore');
        $handler->expects($this->once())
            ->method('retireLoginToken')
            ->with(42, $superseded);

        $this->authCookie($handler)->destroyAuthentication();
    }
}
