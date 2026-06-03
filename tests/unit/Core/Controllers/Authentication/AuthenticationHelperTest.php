<?php

namespace Matecat\Core\Controllers\Authentication;

use Controller\Abstracts\Authentication\AuthenticationHelper;
use Matecat\TestHelpers\AbstractTest;
use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(AuthenticationHelper::class)]
class AuthenticationHelperTest extends AbstractTest
{
    /** @var ApiKeyDao&MockObject */
    private ApiKeyDao&MockObject $apiKeyDaoMock;

    /** @var UserDao&MockObject */
    private UserDao&MockObject $userDaoMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKeyDaoMock = $this->createMock(ApiKeyDao::class);
        $this->userDaoMock = $this->createMock(UserDao::class);
    }

    private function createHelper(array &$session, ?string $apiKey = null, ?string $apiSecret = null): TestableAuthenticationHelper
    {
        return TestableAuthenticationHelper::create($session, $this->apiKeyDaoMock, $apiKey, $apiSecret, $this->userDaoMock);
    }

    // ─── Basic getters (no auth) ─────────────────────────────────────────

    #[Test]
    public function loggedIsFalseByDefault(): void
    {
        $session = [];
        $helper = $this->createHelper($session);

        $this->assertFalse($helper->isLogged());
    }

    #[Test]
    public function getUserReturnsUserStruct(): void
    {
        $session = [];
        $helper = $this->createHelper($session);

        $this->assertInstanceOf(UserStruct::class, $helper->getUser());
    }

    #[Test]
    public function getApiRecordReturnsNullByDefault(): void
    {
        $session = [];
        $helper = $this->createHelper($session);

        $this->assertNull($helper->getApiRecord());
    }

    // ─── validKeys ───────────────────────────────────────────────────────

    #[Test]
    public function validKeysReturnsFalseWhenBothNull(): void
    {
        $session = [];
        $helper = $this->createHelper($session);

        $this->assertFalse($helper->validKeys(null, null));
    }

    #[Test]
    public function validKeysReturnsFalseWhenKeyIsEmptyString(): void
    {
        $session = [];
        $helper = $this->createHelper($session);

        $this->assertFalse($helper->validKeys('', ''));
    }

    #[Test]
    public function validKeysSetsApiRecordWhenKeyFound(): void
    {
        $session = [];
        $helper = $this->createHelper($session);

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
        $helper = $this->createHelper($session);

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
        $helper = $this->createHelper($session);

        $this->apiKeyDaoMock->method('findByKey')->willReturn(null);

        $this->assertFalse($helper->validKeys('unknown', 'secret'));
        $this->assertNull($helper->getApiRecord());
    }

    #[Test]
    public function validKeysUsesEmptyStringWhenApiKeyIsNull(): void
    {
        $session = [];
        $helper = $this->createHelper($session);

        $this->apiKeyDaoMock->expects($this->once())
            ->method('findByKey')
            ->with('')
            ->willReturn(null);

        $helper->validKeys(null, 'some_secret');
    }

    // ─── getUserProfile ──────────────────────────────────────────────────

    #[Test]
    public function getUserProfileReturnsArray(): void
    {
        $method = new ReflectionMethod(AuthenticationHelper::class, 'getUserProfile');

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@test.com';
        $user->first_name = 'Test';
        $user->last_name = 'User';

        $result = $method->invoke(null, $user);

        $this->assertIsArray($result);
    }

    // ─── Constructor: API key auth path ──────────────────────────────────

    #[Test]
    public function constructorWithValidApiKeySetsUserAndLogged(): void
    {
        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'api@example.com';
        $user->first_name = 'Test';
        $user->last_name = 'User';

        $this->userDaoMock->method('getByUid')->with(42)->willReturn($user);

        $apiRecord = new ApiKeyStruct(
            ['api_key' => 'k1', 'api_secret' => 's1', 'uid' => 42, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01'],
            $this->userDaoMock
        );
        $this->apiKeyDaoMock->method('findByKey')->with('k1')->willReturn($apiRecord);

        $session = [];
        $helper = $this->createHelper($session, 'k1', 's1');

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
            ['api_key' => 'k1', 'api_secret' => 's1', 'uid' => 42, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01'],
            $this->userDaoMock
        );
        $this->apiKeyDaoMock->method('findByKey')->with('k1')->willReturn($apiRecord);

        $session = [];
        $helper = $this->createHelper($session, 'k1', 's1');

        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getUser()->uid);
    }

    #[Test]
    public function constructorWithInvalidSecretDoesNotSetUser(): void
    {
        $apiRecord = new ApiKeyStruct(
            ['api_key' => 'k1', 'api_secret' => 'real_secret', 'uid' => 42, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01'],
            $this->userDaoMock
        );
        $this->apiKeyDaoMock->method('findByKey')->with('k1')->willReturn($apiRecord);

        $session = [];
        $helper = $this->createHelper($session, 'k1', 'wrong_secret');

        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getUser()->uid);
    }

    // ─── Constructor: session auth path ──────────────────────────────────

    #[Test]
    public function constructorWithSessionDataSetsUser(): void
    {
        $user = new UserStruct();
        $user->uid = 99;
        $user->email = 'session@example.com';

        $session = [
            'user'         => $user,
            'user_profile' => ['uid' => 99, 'email' => 'session@example.com'],
        ];

        $helper = $this->createHelper($session);

        $this->assertSame(99, $helper->getUser()->uid);
    }

    // ─── Constructor: exception handling ─────────────────────────────────

    #[Test]
    public function constructorCatchesExceptionAndSetsLoggedFalse(): void
    {
        $this->apiKeyDaoMock->method('findByKey')
            ->willThrowException(new \RuntimeException('DB down'));

        $session = [];
        $helper = $this->createHelper($session, 'some_key', 'some_secret');

        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getApiRecord());
    }

    // ─── refreshSession (instance method) ───────────────────────────────

    #[Test]
    public function refreshSessionClearsSessionVarsOnInstance(): void
    {
        $user = new UserStruct();
        $user->uid = 99;
        $session = [
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
        $user = new UserStruct();
        $session = [
            'user'         => $user,
            'user_profile' => ['some' => 'data'],
        ];
        $helper = $this->createHelper($session);

        try {
            $helper->destroyAuthentication();
        } catch (\Throwable) {
            // AuthCookie may throw in test environment without active session
        }

        $this->assertArrayNotHasKey('user', $session);
        $this->assertArrayNotHasKey('user_profile', $session);
    }

    // ─── public constructor ───────────────────────────────────────────────

    #[Test]
    public function canInstantiateDirectlyWithPublicConstructor(): void
    {
        $session = [];
        $helper = new AuthenticationHelper($session);
        $this->assertInstanceOf(AuthenticationHelper::class, $helper);
        $this->assertFalse($helper->isLogged());
    }
}

class TestableAuthenticationHelper extends AuthenticationHelper
{
    public static function create(
        array &$session,
        ?ApiKeyDao $apiKeyDao = null,
        ?string $api_key = null,
        ?string $api_secret = null,
        ?UserDao $userDao = null,
    ): self {
        return new self($session, $api_key, $api_secret, $userDao, $apiKeyDao);
    }

    public function validKeys(?string $api_key = null, ?string $api_secret = null): bool
    {
        return parent::validKeys($api_key, $api_secret);
    }
}
