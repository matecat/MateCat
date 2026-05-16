<?php

namespace unit\Controllers\Authentication;

use Controller\Abstracts\Authentication\AuthCookie;
use Controller\Abstracts\Authentication\CookieManager;
use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Utils\Registry\AppConfig;

#[CoversClass(AuthCookie::class)]
#[CoversClass(CookieManager::class)]
class AuthCookieTest extends TestCase
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

    #[Test]
    public function getCredentialsReturnsNullWhenNoCookieExists(): void
    {
        $result = AuthCookie::getCredentials();

        $this->assertNull($result);
    }

    #[Test]
    public function getCredentialsReturnsNullForInvalidCookie(): void
    {
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = 'invalid-jwt-value';

        $result = AuthCookie::getCredentials();

        $this->assertNull($result);
    }

    #[Test]
    public function getCredentialsReturnsNullForEmptyCookie(): void
    {
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = '';

        $result = AuthCookie::getCredentials();

        $this->assertNull($result);
    }

    #[Test]
    public function getCredentialsReturnsPayloadForValidCookie(): void
    {
        $user = $this->createAuthenticatedUser();
        $cookie = $this->generateTestCookie($user);
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $cookie;

        $result = AuthCookie::getCredentials();

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

        $result = AuthCookie::getCredentials($store);

        $this->assertNull($result);
    }

    #[Test]
    public function getCredentialsReturnsPayloadWhenTokenIsInStore(): void
    {
        $user = $this->createAuthenticatedUser();
        $cookie = $this->generateTestCookie($user);
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $cookie;

        $store = $this->createStub(SessionTokenStoreHandler::class);
        $store->method('isLoginCookieStillActive')
            ->willReturn(true);

        $result = AuthCookie::getCredentials($store);

        $this->assertIsArray($result);
        $this->assertSame(42, $result['user']['uid']);
    }

    #[Test]
    public function generateSignedAuthCookieReturnsArrayWithStringAndInt(): void
    {
        $user = $this->createAuthenticatedUser();
        $method = new ReflectionMethod(AuthCookie::class, 'generateSignedAuthCookie');

        $result = $method->invoke(null, $user);

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
        $store = $this->createStub(SessionTokenStoreHandler::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot set credentials for a user without a UID');

        AuthCookie::setCredentials($user, $store);
    }

    #[Test]
    public function setCredentialsNewLoginActivatesToken(): void
    {
        $user = $this->createAuthenticatedUser();
        $store = $this->createMock(SessionTokenStoreHandler::class);

        $store->expects($this->once())
            ->method('setCookieLoginTokenActive')
            ->with(42, $this->isString());

        AuthCookie::setCredentials($user, $store);
    }

    #[Test]
    public function setCredentialsRevampDoesNotRotateWhenTokenStillValid(): void
    {
        $user = $this->createAuthenticatedUser();
        $cookie = $this->generateTestCookie($user);
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $cookie;

        $store = $this->createMock(SessionTokenStoreHandler::class);
        // Should NOT be called because existing token is still valid
        $store->expects($this->never())
            ->method('setCookieLoginTokenActive');

        AuthCookie::setCredentials($user, $store, true);
    }

    #[Test]
    public function setCredentialsRevampRotatesWhenTokenExpired(): void
    {
        $user = $this->createAuthenticatedUser();
        // No valid cookie in $_COOKIE → payload will be empty
        unset($_COOKIE[AppConfig::$AUTHCOOKIENAME]);

        $store = $this->createMock(SessionTokenStoreHandler::class);
        $store->expects($this->once())
            ->method('setCookieLoginTokenActive')
            ->with(42, $this->isString());
        $store->expects($this->once())
            ->method('removeLoginCookieFromStore')
            ->with(42, '');

        AuthCookie::setCredentials($user, $store, true);
    }

    #[Test]
    public function destroyAuthenticationRemovesCookie(): void
    {
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = 'some-value';

        AuthCookie::destroyAuthentication();

        $this->assertArrayNotHasKey(AppConfig::$AUTHCOOKIENAME, $_COOKIE);
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

        AuthCookie::destroyAuthentication($store);
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
        [$cookieData] = $method->invoke(null, $user);

        return $cookieData;
    }
}
