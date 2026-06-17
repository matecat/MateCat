<?php

declare(strict_types=1);

namespace Matecat\Core\Controller\API\App\Authentication;

use Controller\Abstracts\KleinController;
use Controller\API\App\Authentication\LoginController;
use Controller\Services\RateLimiterService;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;
use Utils\Tools\Utils;

class TestableLoginController extends LoginController
{
    public ?UserDao $mockUserDao = null;
    private ?RateLimiterService $injectedRateLimiter = null;
    public bool $logoutCalled = false;

    public function __construct()
    {
    }

    public function initWith(
        Request             $request,
        Response            $response,
        ?RateLimiterService $rateLimiter = null,
    ): void {
        $ref = new ReflectionClass(KleinController::class);
        $ref->getProperty('request')->setValue($this, $request);
        $ref->getProperty('response')->setValue($this, $response);
        $ref->getProperty('logger')->setValue($this, $this->createStubLogger());

        $this->injectedRateLimiter = $rateLimiter;
    }

    public function checkAndIncrementRateLimit(Response $response, string $identifier, string $route, int $maxRetries = 10, ?RateLimiterService $limiterService = null): ?Response
    {
        return parent::checkAndIncrementRateLimit($response, $identifier, $route, $maxRetries, $limiterService ?? $this->injectedRateLimiter);
    }

    protected function createUserDao(): UserDao
    {
        return $this->mockUserDao ?? parent::createUserDao();
    }

    public function logout(): void
    {
        $this->logoutCalled = true;
    }

    public function getResponse(): Response
    {
        return (new ReflectionClass(KleinController::class))->getProperty('response')->getValue($this);
    }

    private function createStubLogger(): MatecatLogger
    {
        return (new class extends MatecatLogger {
            public function __construct()
            {
            }
        });
    }
}

class LoginControllerTest extends AbstractTest
{
    private TestableLoginController $controller;
    private Request|MockObject $request;
    private Response $response;
    private RateLimiterService $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];

        $this->request = $this->createStub(Request::class);
        $this->response = new Response();
        $this->rateLimiter = $this->createStub(RateLimiterService::class);

        $this->controller = new TestableLoginController();
        $this->controller->initWith($this->request, $this->response, $this->rateLimiter);
    }

    // ─── directLogout ────────────────────────────────────────────────

    #[Test]
    public function directLogout_calls_logout_and_returns_200(): void
    {
        $this->controller->directLogout();

        $this->assertTrue($this->controller->logoutCalled);
        $this->assertSame(200, $this->controller->getResponse()->code());
    }

    // ─── login (rate limiting) ───────────────────────────────────────

    #[Test]
    public function login_returns_rate_limit_on_email(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter->method('checkAndIncrement')
            ->willReturn($rateLimitedResponse);

        $this->request->method('params')->willReturn(['email' => 'test@example.com', 'password' => 'pass']);

        $this->controller->login();

        $this->assertSame(429, $this->controller->getResponse()->code());
    }

    #[Test]
    public function login_returns_rate_limit_on_ip(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter->method('checkAndIncrement')
            ->willReturnOnConsecutiveCalls(null, $rateLimitedResponse);

        $this->request->method('params')->willReturn(['email' => 'test@example.com', 'password' => 'pass']);

        $this->controller->login();

        $this->assertSame(429, $this->controller->getResponse()->code());
    }

    // ─── login (XSRF token) ─────────────────────────────────────────

    #[Test]
    public function login_returns_403_when_xsrf_token_missing(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $headers = $this->createStub(\Klein\DataCollection\HeaderDataCollection::class);
        $headers->method('get')->willReturn(null);

        $this->request->method('params')->willReturn(['email' => 'test@example.com', 'password' => 'pass']);
        $this->request->method('headers')->willReturn($headers);

        $this->controller->login();

        $this->assertSame(403, $this->controller->getResponse()->code());
    }

    #[Test]
    public function login_returns_403_when_xsrf_token_invalid(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $headers = $this->createStub(\Klein\DataCollection\HeaderDataCollection::class);
        $headers->method('get')->willReturn('invalid-jwt-token');

        $this->request->method('params')->willReturn(['email' => 'test@example.com', 'password' => 'pass']);
        $this->request->method('headers')->willReturn($headers);

        $this->controller->login();

        $this->assertSame(403, $this->controller->getResponse()->code());
    }

    // ─── login (user lookup) ─────────────────────────────────────────

    #[Test]
    public function login_returns_404_when_user_not_found(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $jwt = new SimpleJWT(
            ['csrf' => Utils::uuid4()],
            AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            AppConfig::$AUTHSECRET,
            60
        );

        $headers = $this->createStub(\Klein\DataCollection\HeaderDataCollection::class);
        $headers->method('get')->willReturn($jwt->jsonSerialize());

        $this->request->method('params')->willReturn(['email' => 'nonexistent@example.com', 'password' => 'pass']);
        $this->request->method('headers')->willReturn($headers);

        $dao = $this->createStub(UserDao::class);
        $dao->method('getByEmail')->willReturn(null);
        $this->controller->mockUserDao = $dao;

        $this->controller->login();

        $this->assertSame(404, $this->controller->getResponse()->code());
    }

    #[Test]
    public function login_returns_404_when_password_wrong(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $jwt = new SimpleJWT(
            ['csrf' => Utils::uuid4()],
            AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            AppConfig::$AUTHSECRET,
            60
        );

        $headers = $this->createStub(\Klein\DataCollection\HeaderDataCollection::class);
        $headers->method('get')->willReturn($jwt->jsonSerialize());

        $this->request->method('params')->willReturn(['email' => 'test@example.com', 'password' => 'wrong']);
        $this->request->method('headers')->willReturn($headers);

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.com';
        $user->salt = 'test-salt';
        $user->pass = Utils::encryptPass('correct-password', 'test-salt');
        $user->email_confirmed_at = date('Y-m-d H:i:s');

        $dao = $this->createStub(UserDao::class);
        $dao->method('getByEmail')->willReturn($user);
        $this->controller->mockUserDao = $dao;

        $this->controller->login();

        $this->assertSame(404, $this->controller->getResponse()->code());
    }

    // ─── token ───────────────────────────────────────────────────────

    #[Test]
    public function token_returns_200_with_xsrf_header(): void
    {
        $this->controller->token();

        $this->assertSame(200, $this->controller->getResponse()->code());
        $this->assertNotNull($this->controller->getResponse()->headers()->get(AppConfig::$XSRF_TOKEN));
    }

    // ─── socketToken ─────────────────────────────────────────────────

    #[Test]
    public function socketToken_returns_406_when_no_session_user(): void
    {
        $_SESSION = [];
        $this->controller->socketToken();

        $this->assertSame(406, $this->controller->getResponse()->code());
    }

    #[Test]
    public function socketToken_returns_200_with_token_when_session_user_exists(): void
    {
        $user = new UserStruct();
        $user->uid = 42;
        $_SESSION = ['user' => $user];

        $this->controller->socketToken();

        $this->assertSame(200, $this->controller->getResponse()->code());
        $this->assertNotNull($this->controller->getResponse()->headers()->get(AppConfig::$XSRF_TOKEN));
    }
}
