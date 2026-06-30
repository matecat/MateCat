<?php

namespace Matecat\Core\Controllers;

use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\OauthResponseHandlerController;
use Klein\Request;
use League\OAuth2\Client\Token\AccessToken;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\AbstractProvider;
use Model\ConnectedServices\Oauth\OauthClient;
use Model\ConnectedServices\Oauth\OauthTokenEncryption;
use Model\ConnectedServices\Oauth\ProviderUser;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Utils\Registry\AppConfig;

// ── Test doubles ──────────────────────────────────────────────────────────

/**
 * Full stub: overrides constructor, render, initDependencies, _initRemoteUser,
 * and _processSuccessfulOAuth. Used for testing the response() flow in
 * complete isolation.
 */
class OauthResponseHandlerControllerTestController extends OauthResponseHandlerController
{
    public bool $processSuccessfulOAuthCalled = false;
    public ?string $processSuccessfulOAuthCode = null;
    public ?string $processSuccessfulOAuthProvider = null;
    public ?int $capturedRenderCode = null;

    public function __construct()
    {
        // Intentionally empty — skip Klein/PHPTAL bootstrap
    }

    public function render(?int $code = null): never
    {
        $this->capturedRenderCode = $code;
        throw new RenderTerminatedException();
    }

    protected function initDependencies(): void
    {
        // No-op — avoids PHPTAL and session setup
    }

    protected function _initRemoteUser(string $code, ?string $provider = null): void
    {
        $user = new ProviderUser();
        $user->email = 'test@example.com';
        $user->name = 'Test';
        $user->lastName = 'User';
        $user->provider = $provider ?? 'test';
        $user->picture = 'https://example.com/pic.jpg';
        $user->authToken = 'test_token';
        $this->remoteUser = $user;
    }

    protected function _processSuccessfulOAuth(string $code, ?string $provider = null): void
    {
        $this->processSuccessfulOAuthCalled = true;
        $this->processSuccessfulOAuthCode = $code;
        $this->processSuccessfulOAuthProvider = $provider;
    }
}

/**
 * Stubs constructor and render. Replaces _initRemoteUser with a fast
 * reflection-based path (no HTTP). Keeps _processSuccessfulOAuth() real
 * so the OAuthSignInModel flow can be tested with a mocked database.
 */
class OauthResponseHandlerControllerRealProcessTestController extends OauthResponseHandlerController
{
    public ?int $capturedRenderCode = null;

    public function __construct()
    {
        // Intentionally empty — skip Klein/PHPTAL bootstrap
    }

    public function render(?int $code = null): never
    {
        $this->capturedRenderCode = $code;
        throw new RenderTerminatedException();
    }

    protected function initDependencies(): void
    {
        // No-op — avoids PHPTAL and session setup
    }

    protected function _initRemoteUser(string $code, ?string $provider = null): void
    {
        $user = new ProviderUser();
        $user->email = 'test@example.com';
        $user->name = 'Test';
        $user->lastName = 'User';
        $user->provider = $provider ?? 'test';
        $user->picture = 'https://example.com/pic.jpg';
        $user->authToken = 'test_token';

        $ref = new ReflectionProperty(OauthResponseHandlerController::class, 'remoteUser');
        $ref->setAccessible(true);
        $ref->setValue($this, $user);
    }

    // _processSuccessfulOAuth is NOT overridden — real code runs
}

/**
 * Stubs only the constructor, render, and initDependencies.
 * _initRemoteUser() and _processSuccessfulOAuth() are real.
 * Used for: initDependencies coverage, _initRemoteUser success path,
 * and _initRemoteUser catch-block (no OauthClient mock → HTTP failure).
 */
class OauthResponseHandlerControllerRealMethodTestController extends OauthResponseHandlerController
{
    public ?int $capturedRenderCode = null;

    public function __construct()
    {
        // Intentionally empty — skip Klein/PHPTAL bootstrap
    }

    public function render(?int $code = null): never
    {
        $this->capturedRenderCode = $code;
        throw new RenderTerminatedException();
    }

    protected function initDependencies(): void
    {
        // No-op — avoids PHPTAL and session setup
    }
}

// ── Test class ────────────────────────────────────────────────────────────

/**
 * Combined suite for OauthResponseHandlerController.
 *
 * - 6 pure-logic tests for response() state validation and delegation
 * - 1 test for real _processSuccessfulOAuth() with mocked database
 * - 1 test for real initDependencies() with view setup
 * - 1 test for real _initRemoteUser() success path (mocked OauthClient)
 * - 1 test for _initRemoteUser() catch block (unmocked → HTTP failure)
 */
class OauthResponseHandlerControllerTest extends AbstractTest
{
    private string $savedXsrftoken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedXsrftoken = AppConfig::$XSRF_TOKEN;
        AppConfig::$XSRF_TOKEN = 'Xsrf-Token';
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        AppConfig::$XSRF_TOKEN = $this->savedXsrftoken;
        $_SESSION = [];

        // Reset singletons that may have been mocked by tests in this class.
        // OauthTokenEncryption::$instance and OauthClient::$instance are
        // private static properties; a leftover mock pollutes subsequent
        // tests that run in the same process (e.g. ConnectedServiceDaoRealSqlTest).
        $encRef = new ReflectionClass(OauthTokenEncryption::class);
        $encProp = $encRef->getProperty('instance');
        $encProp->setAccessible(true);
        $encProp->setValue(null, null);

        $clientRef = new ReflectionClass(OauthClient::class);
        $clientProp = $clientRef->getProperty('instance');
        $clientProp->setAccessible(true);
        $clientProp->setValue(null, null);

        parent::tearDown();
    }

    // ── response(): state validation ────────────────────────────────────

    #[Test]
    public function response_renders_401_when_state_is_empty(): void
    {
        $controller = $this->makeFullStubController();
        $this->setRequestParams($controller, [
            'provider' => 'google',
            'state' => '',
        ]);
        $_SESSION['google-Xsrf-Token'] = 'valid_state';

        try {
            $controller->renderView();
            $this->fail('Expected RenderTerminatedException was not thrown');
        } catch (RenderTerminatedException) {
            // Expected
        }

        $this->assertSame(401, $controller->capturedRenderCode);
        $this->assertFalse($controller->processSuccessfulOAuthCalled,
            'processSuccessfulOAuth should not be called when state is empty');
    }

    #[Test]
    public function response_renders_401_when_state_does_not_match_session(): void
    {
        $controller = $this->makeFullStubController();
        $this->setRequestParams($controller, [
            'provider' => 'google',
            'state' => 'client_state',
        ]);
        $_SESSION['google-Xsrf-Token'] = 'different_state';

        try {
            $controller->renderView();
            $this->fail('Expected RenderTerminatedException was not thrown');
        } catch (RenderTerminatedException) {
            // Expected
        }

        $this->assertSame(401, $controller->capturedRenderCode);
        $this->assertFalse($controller->processSuccessfulOAuthCalled,
            'processSuccessfulOAuth should not be called when state mismatches');
    }

    #[Test]
    public function response_renders_401_when_state_has_wrong_value(): void
    {
        $controller = $this->makeFullStubController();
        $this->setRequestParams($controller, [
            'provider' => 'google',
            'state' => 'client_state',
        ]);
        $_SESSION['google-Xsrf-Token'] = 'wrong_value';

        try {
            $controller->renderView();
            $this->fail('Expected RenderTerminatedException was not thrown');
        } catch (RenderTerminatedException) {
            // Expected
        }

        $this->assertSame(401, $controller->capturedRenderCode);
    }

    // ── response(): code handling ───────────────────────────────────────

    #[Test]
    public function response_renders_200_when_state_matches_and_no_code(): void
    {
        $controller = $this->makeFullStubController();
        $this->setRequestParams($controller, [
            'provider' => 'google',
            'state' => 'valid_state',
        ]);
        $_SESSION['google-Xsrf-Token'] = 'valid_state';

        try {
            $controller->renderView();
            $this->fail('Expected RenderTerminatedException was not thrown');
        } catch (RenderTerminatedException) {
            // Expected
        }

        $this->assertSame(200, $controller->capturedRenderCode);
        $this->assertFalse($controller->processSuccessfulOAuthCalled,
            'processSuccessfulOAuth should not be called when code is absent');
    }

    #[Test]
    public function response_calls_processSuccessfulOAuth_when_code_present(): void
    {
        $controller = $this->makeFullStubController();
        $this->setRequestParams($controller, [
            'provider' => 'google',
            'state' => 'valid_state',
            'code' => 'auth_code_123',
        ]);
        $_SESSION['google-Xsrf-Token'] = 'valid_state';

        try {
            $controller->renderView();
            $this->fail('Expected RenderTerminatedException was not thrown');
        } catch (RenderTerminatedException) {
            // Expected
        }

        $this->assertTrue($controller->processSuccessfulOAuthCalled,
            'processSuccessfulOAuth should be called when code is present');
        $this->assertSame('auth_code_123', $controller->processSuccessfulOAuthCode);
        $this->assertSame('google', $controller->processSuccessfulOAuthProvider);
        $this->assertSame(200, $controller->capturedRenderCode);
    }

    #[Test]
    public function response_passes_null_provider_when_provider_param_omitted(): void
    {
        $controller = $this->makeFullStubController();
        $this->setRequestParams($controller, [
            'state' => 'valid_state',
            'code' => 'auth_code_123',
        ]);
        $_SESSION['-Xsrf-Token'] = 'valid_state';

        try {
            $controller->renderView();
            $this->fail('Expected RenderTerminatedException was not thrown');
        } catch (RenderTerminatedException) {
            // Expected
        }

        $this->assertTrue($controller->processSuccessfulOAuthCalled,
            'processSuccessfulOAuth should be called when code is present even without provider');
        $this->assertNull($controller->processSuccessfulOAuthProvider,
            'provider should be null when params[provider] key is absent');
    }

    // ── Real _processSuccessfulOAuth with mocked DB ─────────────────────

    #[Test]
    public function processSuccessfulOAuth_runs_with_mocked_db(): void
    {
        $controller = new OauthResponseHandlerControllerRealProcessTestController();
        $this->setRequestParams($controller, [
            'provider' => 'google',
            'state' => 'valid_state',
            'code' => 'auth_code_123',
        ]);
        $_SESSION['google-Xsrf-Token'] = 'valid_state';

        // Inject database mock via reflection
        $ref = new ReflectionClass(\Controller\Abstracts\KleinController::class);
        $dbProp = $ref->getProperty('database');
        $dbProp->setAccessible(true);
        [$dbMock, $pdoMock, $stmtMock] = $this->createDatabaseMock();

        $dbMock->method('buildInsertStatement')->willReturnCallback(
            fn(string $table, array $attrs) => [
                'INSERT INTO `' . $table . '` (`' . implode('`, `', array_keys($attrs)) . '`) VALUES (:' . implode(', :', array_keys($attrs)) . ')',
                [],
            ]
        );

        $pdoMock->method('prepare')->willReturnCallback(function () use ($stmtMock) {
            return $stmtMock;
        });

        $stmtMock->method('fetch')->willReturn(false);
        $stmtMock->method('fetchObject')->willReturn(false);
        $stmtMock->method('fetchAll')->willReturn([]);
        $stmtMock->method('execute')->willReturn(true);
        $pdoMock->method('lastInsertId')->willReturn('123');
        $pdoMock->method('inTransaction')->willReturn(true);
        $pdoMock->method('beginTransaction')->willReturn(true);
        $pdoMock->method('commit')->willReturn(true);

        $dbProp->setValue($controller, $dbMock);

        // Mock OauthTokenEncryption singleton
        $encRef = new ReflectionClass(OauthTokenEncryption::class);
        $encProp = $encRef->getProperty('instance');
        $encProp->setAccessible(true);
        $encMock = $this->createStub(OauthTokenEncryption::class);
        $encMock->method('encrypt')->willReturn('encrypted_token');
        $encProp->setValue(null, $encMock);

        try {
            $controller->renderView();
            $this->fail('Expected RenderTerminatedException was not thrown');
        } catch (RenderTerminatedException) {
            // Expected
        }

        $this->assertNotNull($controller->capturedRenderCode);
    }

    // ── Real initDependencies() ─────────────────────────────────────────

    #[Test]
    public function initDependencies_sets_view_with_wanted_url(): void
    {
        $controller = new OauthResponseHandlerControllerRealMethodTestController();
        $ctrlRef = new ReflectionClass(OauthResponseHandlerController::class);

        // isLoggedIn → userIsLogged
        $userIsLoggedProp = $ctrlRef->getParentClass()->getProperty('userIsLogged');
        $userIsLoggedProp->setAccessible(true);
        $userIsLoggedProp->setValue($controller, false);

        // setView → getFeatureSet → featureSet
        [$dbStub] = $this->createDatabaseMock();
        $fsProp = $ctrlRef->getParentClass()->getProperty('featureSet');
        $fsProp->setAccessible(true);
        $fsProp->setValue($controller, new FeatureSet($dbStub));

        // setView → getUser → user (typed property, must be set)
        $userProp = $ctrlRef->getParentClass()->getProperty('user');
        $userProp->setAccessible(true);
        $userProp->setValue($controller, new UserStruct());

        $_SESSION['wanted_url'] = '/translate/123';

        $method = $ctrlRef->getMethod('initDependencies');
        $method->setAccessible(true);
        $method->invoke($controller);

        $viewProp = $ctrlRef->getParentClass()->getProperty('view');
        $viewProp->setAccessible(true);
        $this->assertNotNull($viewProp->getValue($controller));
    }

    // ── Real _initRemoteUser() success path ─────────────────────────────

    #[Test]
    public function initRemoteUser_success_sets_remoteUser(): void
    {
        // Mock OauthClient singleton
        $mockProvider = $this->createStub(AbstractProvider::class);
        $mockToken = new AccessToken(['access_token' => 'mock_token']);
        $mockUser = new ProviderUser();
        $mockUser->email = 'test@example.com';
        $mockUser->name = 'Test';
        $mockUser->lastName = 'User';
        $mockUser->provider = 'google';
        $mockUser->picture = 'https://example.com/pic.jpg';
        $mockUser->authToken = 'mock_token';

        $mockProvider->method('getAccessTokenFromAuthCode')->willReturn($mockToken);
        $mockProvider->method('getResourceOwner')->willReturn($mockUser);

        $mockClient = $this->createStub(OauthClient::class);
        $mockClient->method('getProvider')->willReturn($mockProvider);

        // Set provider_name so getInstance('google') returns our mock
        $clientRef = new ReflectionClass(OauthClient::class);
        $nameProp = $clientRef->getProperty('provider_name');
        $nameProp->setAccessible(true);
        $nameProp->setValue($mockClient, 'google');

        // Inject as singleton
        $instanceProp = $clientRef->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, $mockClient);

        $controller = new OauthResponseHandlerControllerRealMethodTestController();
        $method = (new ReflectionClass(OauthResponseHandlerController::class))
            ->getMethod('_initRemoteUser');
        $method->setAccessible(true);
        $method->invoke($controller, 'auth_code_123', 'google');

        $ref = new ReflectionClass(OauthResponseHandlerController::class);
        $prop = $ref->getProperty('remoteUser');
        $prop->setAccessible(true);
        $this->assertNotNull($prop->getValue($controller));
        $this->assertEquals('test@example.com', $prop->getValue($controller)->email);
    }

    // ── Real _initRemoteUser() catch block ──────────────────────────────

    #[Test]
    public function initRemoteUser_catch_block_renders_error_when_oauth_fails(): void
    {
        $controller = new OauthResponseHandlerControllerRealMethodTestController();
        $this->setRequestParams($controller, [
            'provider' => 'test',
            'state' => 'valid_state',
            'code' => 'auth_code',
        ]);
        $_SESSION['test-Xsrf-Token'] = 'valid_state';

        try {
            $controller->renderView();
            $this->fail('Expected RenderTerminatedException was not thrown');
        } catch (RenderTerminatedException) {
            // Expected
        }

        $this->assertNotNull($controller->capturedRenderCode);
    }

    // ── helpers ─────────────────────────────────────────────────────────

    private function makeFullStubController(): OauthResponseHandlerControllerTestController
    {
        $controller = new OauthResponseHandlerControllerTestController();
        $this->setRequestParams($controller, []);

        return $controller;
    }

    private function setRequestParams(OauthResponseHandlerController $controller, array $params): void
    {
        $ref = new ReflectionClass(\Controller\Abstracts\KleinController::class);
        $prop = $ref->getProperty('request');
        $prop->setAccessible(true);

        $request = new Request($params, [], [], ['REQUEST_URI' => '/oauth/response', 'REQUEST_METHOD' => 'GET']);
        $prop->setValue($controller, $request);
    }
}
