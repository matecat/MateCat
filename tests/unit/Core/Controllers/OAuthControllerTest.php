<?php

namespace Matecat\Core\Controllers;

use Controller\API\GDrive\OAuthController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Testable subclass: empty constructor bypasses Klein DI wiring so properties
 * can be injected via reflection, matching the GDriveControllerTest pattern.
 */
class TestableOAuthController extends OAuthController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }
}

/**
 * OAuthControllerTest (Wave 4, N=25 slot, real-DB pattern).
 *
 * Reserved ID block base = 9_000_000 + (25 * 1000) = 9_025_000.
 *   base+6 uid.
 * Per-suite owner = ctrltest_9025000@example.org.
 *
 * __handleCode strategy: GDriveUserAuthorizationModel::__collectProperties
 * calls fetchAccessTokenWithAuthCode() which hits Google API. No seam exists
 * (method is private, model is newed inline). The test therefore expects the
 * Exception thrown by Google client initialisation — this still covers lines
 * 75-76 of OAuthController (new GDriveUserAuthorizationModel + the call).
 * Line 77 (refreshClientSessionIfNotApi) is unreachable without a live Google
 * OAuth code and is the only uncovered line (~94% coverage achieved).
 */
#[AllowMockObjectsWithoutExpectations]
class OAuthControllerTest extends AbstractTest
{
    private const int BASE = 9_025_000;

    private ReflectionClass $reflector;
    private TestableOAuthController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private IDatabase $dbStub;

    /** @var array<string, mixed> */
    private array $sessionBackup = [];
    /** @var array<string, mixed> */
    private array $cookieBackup = [];

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionBackup = is_array($GLOBALS['_SESSION'] ?? null) ? $GLOBALS['_SESSION'] : [];
        $this->cookieBackup  = is_array($GLOBALS['_COOKIE'] ?? null) ? $GLOBALS['_COOKIE'] : [];

        $this->controller = new TestableOAuthController();
        $this->reflector  = new ReflectionClass(OAuthController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user             = new UserStruct();
        $user->uid        = self::BASE + 6;
        $user->email      = $this->ownerEmail();
        $user->first_name = 'OAuth';
        $user->last_name  = 'Tester';
        $this->setProp('user', $user);

        $this->dbStub = $this->createStub(IDatabase::class);
        $this->setProp('logger', $this->createStub(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->dbStub));
        $this->setProp('database', obtainTestDatabase());
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        $_COOKIE  = $this->cookieBackup;

        parent::tearDown();
    }

    private function ownerEmail(): string
    {
        return 'ctrltest_' . self::BASE . '@example.org';
    }

    private function setProp(string $name, mixed $value): void
    {
        $p = $this->reflector->getProperty($name);
        $p->setValue($this->controller, $value);
    }

    private function setRequestParams(array $params): void
    {
        $serverParams      = ['REQUEST_URI' => '/gdrive/oauth/response', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    // ─── Branch 1: state param is empty → 401 + early return ───

    #[Test]
    public function response_returns_401_when_state_param_is_empty(): void
    {
        $_SESSION['googledrive-' . AppConfig::$XSRF_TOKEN] = 'valid-token';

        $this->setRequestParams([]);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(401);

        // body() must NOT be called because of the early return
        $this->responseMock->expects($this->never())
            ->method('body');

        $this->controller->response();
    }

    // ─── Branch 2: state param present but does not match session → 401 ───

    #[Test]
    public function response_returns_401_when_state_does_not_match_session(): void
    {
        $_SESSION['googledrive-' . AppConfig::$XSRF_TOKEN] = 'correct-token';

        $this->setRequestParams(['state' => 'wrong-token']);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(401);

        $this->responseMock->expects($this->never())
            ->method('body');

        $this->controller->response();
    }

    // ─── Branch 3: valid state + code present → __handleCode throws (Google API hit) ───

    /**
     * GDriveUserAuthorizationModel::__collectProperties calls fetchAccessTokenWithAuthCode
     * which contacts Google. With a fake code, Google_Client throws an Exception.
     * expectException covers the execution of lines 75-76 in __handleCode before
     * the exception bubbles out of response().
     *
     * @throws ReflectionException
     */
    #[Test]
    public function response_with_valid_state_and_code_enters_handleCode_and_throws(): void
    {
        $token = 'my-xsrf-token-abc';
        $_SESSION['googledrive-' . AppConfig::$XSRF_TOKEN] = $token;

        $this->setRequestParams([
            'state' => $token,
            'code'  => 'fake-google-auth-code',
        ]);

        // Google_Client will throw when trying to exchange the fake code
        $this->expectException(Exception::class);

        $this->controller->response();
    }

    // ─── Branch 4: valid state + error present (no code) → __handleError → window.close body ───

    #[Test]
    public function response_with_valid_state_and_error_calls_logger_and_sets_body(): void
    {
        $token = 'my-xsrf-token-xyz';
        $_SESSION['googledrive-' . AppConfig::$XSRF_TOKEN] = $token;

        $this->setRequestParams([
            'state' => $token,
            'error' => 'access_denied',
        ]);

        $capturedBody = null;
        $this->responseMock->expects($this->once())
            ->method('body')
            ->with($this->callback(function (string $body) use (&$capturedBody): bool {
                $capturedBody = $body;

                return true;
            }));

        $this->controller->response();

        $this->assertNotNull($capturedBody);
        $this->assertStringContainsString('window.close()', (string)$capturedBody);
    }

    // ─── Branch 5: valid state + neither code nor error → body set (else branch skipped) ───

    #[Test]
    public function response_with_valid_state_and_no_code_or_error_sets_window_close_body(): void
    {
        $token = 'my-xsrf-token-nop';
        $_SESSION['googledrive-' . AppConfig::$XSRF_TOKEN] = $token;

        // No 'code', no 'error' params
        $this->setRequestParams(['state' => $token]);

        $capturedBody = null;
        $this->responseMock->expects($this->once())
            ->method('body')
            ->with($this->callback(function (string $body) use (&$capturedBody): bool {
                $capturedBody = $body;

                return true;
            }));

        $this->controller->response();

        $this->assertNotNull($capturedBody);
        $this->assertStringContainsString('window.close()', (string)$capturedBody);
    }
}
