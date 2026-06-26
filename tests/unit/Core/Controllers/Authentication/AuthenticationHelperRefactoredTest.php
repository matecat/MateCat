<?php

namespace Matecat\Core\Controllers\Authentication;

use Controller\Abstracts\Authentication\AuthCookieStore;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\Abstracts\Authentication\UserProfileBuilder;
use Matecat\TestHelpers\AbstractTest;
use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\DataAccess\Database;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Behavioral-parity copy of {@see AuthenticationHelperTest}, exercising the
 * split/refactored implementation. The SAME observable behavior must hold.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(AuthenticationHelper::class)]
class AuthenticationHelperRefactoredTest extends AbstractTest
{
    /** @var ApiKeyDao&MockObject */
    private ApiKeyDao&MockObject $apiKeyDaoMock;

    /** @var UserDao&MockObject */
    private UserDao&MockObject $userDaoMock;

    private UserProfileBuilder&MockObject $profileBuilderMock;
    private AuthCookieStore&MockObject $cookieStoreMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKeyDaoMock      = $this->createMock(ApiKeyDao::class);
        $this->userDaoMock        = $this->createMock(UserDao::class);
        $this->profileBuilderMock = $this->createMock(UserProfileBuilder::class);
        $this->cookieStoreMock    = $this->createMock(AuthCookieStore::class);
    }

    private function createHelper(array &$session, ?string $apiKey = null, ?string $apiSecret = null): TestableAuthenticationHelper
    {
        return TestableAuthenticationHelper::create(
            $session,
            $this->userDaoMock,
            $this->apiKeyDaoMock,
            $this->profileBuilderMock,
            $this->cookieStoreMock,
            $apiKey,
            $apiSecret
        );
    }

    // ─── Basic getters (no auth) ─────────────────────────────────────────

    #[Test]
    public function loggedIsFalseByDefault(): void
    {
        $session = [];
        $helper  = $this->createHelper($session);

        $this->assertFalse($helper->isLogged());
    }

    #[Test]
    public function getUserReturnsUserStruct(): void
    {
        $session = [];
        $helper  = $this->createHelper($session);

        $this->assertInstanceOf(UserStruct::class, $helper->getUser());
    }

    #[Test]
    public function getApiRecordReturnsNullByDefault(): void
    {
        $session = [];
        $helper  = $this->createHelper($session);

        $this->assertNull($helper->getApiRecord());
    }

    // ─── validKeys ───────────────────────────────────────────────────────

    #[Test]
    public function validKeysReturnsFalseWhenBothNull(): void
    {
        $session = [];
        $helper  = $this->createHelper($session);

        $this->assertFalse($helper->validKeys(null, null));
    }

    #[Test]
    public function validKeysReturnsFalseWhenKeyIsEmptyString(): void
    {
        $session = [];
        $helper  = $this->createHelper($session);

        $this->assertFalse($helper->validKeys('', ''));
    }

    #[Test]
    public function validKeysSetsApiRecordWhenKeyFound(): void
    {
        $session = [];
        $helper  = $this->createHelper($session);

        $apiRecord = new ApiKeyStruct(['api_key' => 'k1', 'api_secret' => 's1', 'uid' => 1, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']);
        $this->apiKeyDaoMock->method('findByKey')
            ->with('key123')
            ->willReturn($apiRecord);

        $result = $helper->validKeys('key123', 's1');

        $this->assertTrue($result);
        $this->assertSame($apiRecord, $helper->getApiRecord());
    }

    #[Test]
    public function validKeysReturnsFalseWhenSecretMismatch(): void
    {
        $session = [];
        $helper  = $this->createHelper($session);

        $apiRecord = new ApiKeyStruct(['api_key' => 'k1', 'api_secret' => 'correct', 'uid' => 1, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']);
        $this->apiKeyDaoMock->method('findByKey')
            ->with('key123')
            ->willReturn($apiRecord);

        $result = $helper->validKeys('key123', 'wrong');

        $this->assertFalse($result);
        $this->assertSame($apiRecord, $helper->getApiRecord());
    }

    #[Test]
    public function validKeysReturnsFalseWhenKeyNotFound(): void
    {
        $session = [];
        $helper  = $this->createHelper($session);

        $this->apiKeyDaoMock->method('findByKey')->willReturn(null);

        $this->assertFalse($helper->validKeys('unknown', 'secret'));
        $this->assertNull($helper->getApiRecord());
    }

    #[Test]
    public function validKeysUsesEmptyStringWhenApiKeyIsNull(): void
    {
        $session = [];
        $helper  = $this->createHelper($session);

        $this->apiKeyDaoMock->expects($this->once())
            ->method('findByKey')
            ->with('')
            ->willReturn(null);

        $helper->validKeys(null, 'some_secret');
    }

    // ─── Constructor (authenticate): API key auth path ────────────────────

    #[Test]
    public function constructorWithValidApiKeySetsUserAndLogged(): void
    {
        $user            = new UserStruct();
        $user->uid       = 42;
        $user->email     = 'api@example.com';
        $user->first_name = 'Test';
        $user->last_name  = 'User';

        $this->userDaoMock->method('getByUid')->with(42)->willReturn($user);

        $apiRecord = new ApiKeyStruct(
            ['api_key' => 'k1', 'api_secret' => 's1', 'uid' => 42, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']
        );
        $this->apiKeyDaoMock->method('findByKey')->with('k1')->willReturn($apiRecord);

        $session = [];
        $helper  = $this->createHelper($session, 'k1', 's1');

        $this->assertTrue($helper->isLogged());
        $this->assertSame(42, $helper->getUser()->uid);
        $this->assertSame('api@example.com', $helper->getUser()->email);
        $this->assertNotNull($helper->getApiRecord());
    }

    #[Test]
    public function constructorWithValidApiKeyButNullUserKeepsDefaultUser(): void
    {
        $this->userDaoMock->method('getByUid')->willReturn(null);

        $apiRecord = new ApiKeyStruct(
            ['api_key' => 'k1', 'api_secret' => 's1', 'uid' => 42, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']
        );
        $this->apiKeyDaoMock->method('findByKey')->with('k1')->willReturn($apiRecord);

        $session = [];
        $helper  = $this->createHelper($session, 'k1', 's1');

        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getUser()->uid);
    }

    #[Test]
    public function constructorWithInvalidSecretDoesNotSetUser(): void
    {
        $apiRecord = new ApiKeyStruct(
            ['api_key' => 'k1', 'api_secret' => 'real_secret', 'uid' => 42, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']
        );
        $this->apiKeyDaoMock->method('findByKey')->with('k1')->willReturn($apiRecord);

        $session = [];
        $helper  = $this->createHelper($session, 'k1', 'wrong_secret');

        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getUser()->uid);
    }

    // ─── Constructor (authenticate): session auth path ────────────────────

    #[Test]
    public function constructorWithSessionDataSetsUser(): void
    {
        $user        = new UserStruct();
        $user->uid   = 99;
        $user->email = 'session@example.com';

        $session = [
            'user'         => $user,
            'user_profile' => ['uid' => 99, 'email' => 'session@example.com'],
        ];

        $helper = $this->createHelper($session);

        $this->assertSame(99, $helper->getUser()->uid);
    }

    // ─── Constructor (authenticate): cookie auth path ─────────────────────

    #[Test]
    public function cookiePathLoadsUserFromDaoAndPopulatesSession(): void
    {
        $user        = new UserStruct();
        $user->uid   = 5;
        $user->email = 'cookie@example.com';

        $this->userDaoMock->method('getByUid')->with(5)->willReturn($user);
        $this->cookieStoreMock->method('getCredentials')->willReturn(['user' => ['uid' => 5]]);
        $this->profileBuilderMock->method('build')->willReturn(['profile' => true]);

        $session = [];
        $helper  = $this->createHelper($session); // no api key, empty session → cookie branch

        // TestableAuthenticationHelperRefactored forces sessionIsActive() = true,
        // so setUserSession() populates the injected session array.
        $this->assertSame(5, $helper->getUser()->uid);
        $this->assertSame(5, $session['uid']);
        $this->assertSame(['profile' => true], $session['user_profile']);
    }

    #[Test]
    public function realSessionGuardIsEvaluatedOnCookiePath(): void
    {
        // Uses the REAL class (not the Testable subclass) so the actual
        // sessionIsActive() guard is exercised.
        $user      = new UserStruct();
        $user->uid = 8;
        $this->userDaoMock->method('getByUid')->willReturn($user);
        $this->cookieStoreMock->method('getCredentials')->willReturn(['user' => ['uid' => 8]]);

        $session = [];
        $helper  = new AuthenticationHelper(
            $session, $this->userDaoMock, $this->apiKeyDaoMock, $this->profileBuilderMock, $this->cookieStoreMock
        );
        $helper->authenticate(null, null);

        $this->assertSame(8, $helper->getUser()->uid);
    }

    #[Test]
    public function loggerFailureDuringExceptionHandlingIsSwallowed(): void
    {
        // Outer flow throws (findByKey), then the logger payload itself throws
        // (getCredentials) → the inner catch must swallow it; stays logged-out.
        $this->apiKeyDaoMock->method('findByKey')->willThrowException(new \RuntimeException('db down'));
        $this->cookieStoreMock->method('getCredentials')->willThrowException(new \RuntimeException('cookie boom'));

        $session = [];
        $helper  = $this->createHelper($session, 'k1', 's1');

        $this->assertFalse($helper->isLogged());
    }

    // ─── Constructor (authenticate): exception handling ───────────────────

    #[Test]
    public function constructorCatchesExceptionAndSetsLoggedFalse(): void
    {
        $this->apiKeyDaoMock->method('findByKey')
            ->willThrowException(new \RuntimeException('DB down'));

        $session = [];
        $helper  = $this->createHelper($session, 'some_key', 'some_secret');

        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getApiRecord());
    }

    // ─── fromRequest (composition root, real DB) ─────────────────────────

    #[Test]
    public function fromRequestBuildsLoggedOutHelperForEmptySession(): void
    {
        $session = [];
        $helper  = AuthenticationHelper::fromRequest($session, obtainTestDatabase());

        $this->assertFalse($helper->isLogged());
        $this->assertInstanceOf(UserStruct::class, $helper->getUser());
        $this->assertNull($helper->getApiRecord());
    }

    // ─── refreshSession (instance method) ───────────────────────────────

    #[Test]
    public function refreshSessionClearsSessionVarsOnInstance(): void
    {
        $user      = new UserStruct();
        $user->uid = 99;
        $session   = [
            'user'         => $user,
            'user_profile' => ['some' => 'data'],
        ];
        $helper = $this->createHelper($session);

        $helper->refreshSession();

        $this->assertArrayNotHasKey('user', $session);
        $this->assertArrayNotHasKey('user_profile', $session);
        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getUser()->uid);
    }

    // ─── destroyAuthentication (instance method) ─────────────────────────

    #[Test]
    public function destroyAuthenticationClearsSessionVarsOnInstance(): void
    {
        $user    = new UserStruct();
        $session = [
            'user'         => $user,
            'user_profile' => ['some' => 'data'],
        ];
        $helper = $this->createHelper($session);

        try {
            $helper->destroyAuthentication();
        } catch (\Throwable) {
            // cookie store may throw in test environment without active session
        }

        $this->assertArrayNotHasKey('user', $session);
        $this->assertArrayNotHasKey('user_profile', $session);
    }
}

class TestableAuthenticationHelper extends AuthenticationHelper
{
    public static function create(
        array &$session,
        UserDao $userDao,
        ApiKeyDao $apiKeyDao,
        UserProfileBuilder $profileBuilder,
        AuthCookieStore $cookieStore,
        ?string $api_key = null,
        ?string $api_secret = null,
    ): self {
        $self = new self($session, $userDao, $apiKeyDao, $profileBuilder, $cookieStore);
        $self->authenticate($api_key, $api_secret);

        return $self;
    }

    public function validKeys(?string $api_key = null, ?string $api_secret = null): bool
    {
        return parent::validKeys($api_key, $api_secret);
    }

    protected function sessionIsActive(): bool
    {
        return true;
    }
}
