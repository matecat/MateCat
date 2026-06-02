<?php

declare(strict_types=1);

namespace unit\Controller\API\App\Authentication;

use Controller\Abstracts\KleinController;
use Controller\API\App\Authentication\UserController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Services\RateLimiterService;
use Klein\Request;
use Klein\Response;
use Model\Users\Authentication\ChangePasswordModel;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

class TestableUserController extends UserController
{
    public ?ChangePasswordModel $mockChangePasswordModel = null;
    private ?RateLimiterService $injectedRateLimiter = null;
    public bool $broadcastLogoutCalled = false;

    public function __construct()
    {
    }

    public function initWith(
        Request             $request,
        Response            $response,
        UserStruct          $user,
        ?RateLimiterService $rateLimiter = null,
    ): void {
        $ref = new ReflectionClass(KleinController::class);
        $ref->getProperty('request')->setValue($this, $request);
        $ref->getProperty('response')->setValue($this, $response);
        $ref->getProperty('user')->setValue($this, $user);
        $ref->getProperty('userIsLogged')->setValue($this, true);
        $ref->getProperty('logger')->setValue($this, $this->createStubLogger());

        $this->injectedRateLimiter = $rateLimiter;
    }

    public function checkAndIncrementRateLimit(Response $response, string $identifier, string $route, int $maxRetries = 10, ?RateLimiterService $limiterService = null): ?Response
    {
        return parent::checkAndIncrementRateLimit($response, $identifier, $route, $maxRetries, $limiterService ?? $this->injectedRateLimiter);
    }

    protected function createChangePasswordModel(): ChangePasswordModel
    {
        return $this->mockChangePasswordModel ?? parent::createChangePasswordModel();
    }

    public function broadcastLogout(?\Utils\ActiveMQ\AMQHandler $amqHandler = null): void
    {
        $this->broadcastLogoutCalled = true;
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

class UserControllerTest extends AbstractTest
{
    private TestableUserController $controller;
    private Request|MockObject $request;
    private Response $response;
    private RateLimiterService $rateLimiter;
    private UserStruct $user;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];

        $this->request = $this->createStub(Request::class);
        $this->response = new Response();
        $this->rateLimiter = $this->createStub(RateLimiterService::class);

        $this->user = new UserStruct();
        $this->user->uid = 1;
        $this->user->email = 'test@example.com';

        $this->controller = new TestableUserController();
        $this->controller->initWith($this->request, $this->response, $this->user, $this->rateLimiter);
    }

    // ─── show ────────────────────────────────────────────────────────

    #[Test]
    public function show_returns_401_when_no_session_profile(): void
    {
        $_SESSION = [];
        $request = new Request();
        $response = new Response();
        $controller = new TestableUserController();
        $controller->initWith($request, $response, $this->user);

        $controller->show();

        $this->assertSame(401, $controller->getResponse()->code());
    }

    #[Test]
    public function show_returns_user_profile_from_session(): void
    {
        $_SESSION = ['user_profile' => ['email' => 'test@example.com', 'name' => 'Test']];

        ob_start();
        try {
            $this->controller->show();
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame('test@example.com', $decoded['email']);
        $this->assertSame('Test', $decoded['name']);
    }

    #[Test]
    public function show_returns_error_json_when_session_empty(): void
    {
        $_SESSION = [];

        ob_start();
        try {
            $this->controller->show();
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame('Invalid login.', $decoded['error']);
    }

    // ─── redeemProject ───────────────────────────────────────────────

    #[Test]
    public function redeemProject_sets_session_flag(): void
    {
        $_SESSION = [];
        $this->controller->redeemProject();

        $this->assertTrue($_SESSION['redeem_project']);
        $this->assertSame(200, $this->controller->getResponse()->code());
    }

    // ─── changePasswordAsLoggedUser ──────────────────────────────────

    #[Test]
    public function changePassword_returns_rate_limit_response(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter->method('checkAndIncrement')
            ->willReturn($rateLimitedResponse);

        $this->controller->changePasswordAsLoggedUser();

        $this->assertSame(429, $this->controller->getResponse()->code());
        $this->assertFalse($this->controller->broadcastLogoutCalled);
    }

    #[Test]
    public function changePassword_succeeds_and_broadcasts_logout(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $this->request->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'old_password' => 'OldPass!123xx',
                'password' => 'NewValid!Pass1',
                'password_confirmation' => 'NewValid!Pass1',
                default => null,
            };
        });

        $cpModel = $this->createStub(ChangePasswordModel::class);
        $this->controller->mockChangePasswordModel = $cpModel;

        $this->controller->changePasswordAsLoggedUser();

        $this->assertTrue($this->controller->broadcastLogoutCalled);
        $this->assertSame(200, $this->controller->getResponse()->code());
    }

    #[Test]
    public function changePassword_throws_when_passwords_dont_match(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $this->request->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'old_password' => 'OldPass!123xx',
                'password' => 'NewValid!Pass1',
                'password_confirmation' => 'Different!Pass2',
                default => null,
            };
        });

        $cpModel = $this->createStub(ChangePasswordModel::class);
        $this->controller->mockChangePasswordModel = $cpModel;

        $this->expectException(ValidationError::class);
        $this->controller->changePasswordAsLoggedUser();
    }

    #[Test]
    public function changePassword_throws_when_password_too_short(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $this->request->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'old_password' => 'old',
                'password' => 'Short!1',
                'password_confirmation' => 'Short!1',
                default => null,
            };
        });

        $cpModel = $this->createStub(ChangePasswordModel::class);
        $this->controller->mockChangePasswordModel = $cpModel;

        $this->expectException(ValidationError::class);
        $this->controller->changePasswordAsLoggedUser();
    }

    // ─── afterConstruct ──────────────────────────────────────────────

    #[Test]
    public function afterConstruct_appends_login_validator(): void
    {
        $ref = new ReflectionMethod($this->controller, 'afterConstruct');
        $ref->invoke($this->controller);

        $validatorsProp = new ReflectionProperty(KleinController::class, 'validators');
        $validators = $validatorsProp->getValue($this->controller);

        $this->assertNotEmpty($validators);
        $this->assertInstanceOf(LoginValidator::class, end($validators));
    }
}
