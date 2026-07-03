<?php

declare(strict_types=1);

namespace Matecat\Core\Controller\API\App\Authentication;

use Controller\Abstracts\KleinController;
use Controller\API\App\Authentication\ForgotPasswordController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\Services\RateLimiterService;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\Authentication\PasswordResetModel;
use Model\Users\Authentication\SignupModel;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;
use Utils\Logger\MatecatLogger;

class TestableForgotPasswordController extends ForgotPasswordController
{
    public ?SignupModel $mockSignupModel = null;
    public ?PasswordResetModel $mockPasswordResetModel = null;
    private ?RateLimiterService $injectedRateLimiter = null;
    public bool $broadcastLogoutCalled = false;

    public function __construct()
    {
    }

    public function initWith(
        Request          $request,
        Response         $response,
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

    protected function createSignupModel(array $params, array &$session): SignupModel
    {
        return $this->mockSignupModel ?? parent::createSignupModel($params, $session);
    }

    protected function createPasswordResetModel(array &$session, ?string $token = null): PasswordResetModel
    {
        return $this->mockPasswordResetModel ?? parent::createPasswordResetModel($session, $token);
    }

    public function broadcastLogout(?\Utils\ActiveMQ\AMQHandler $amqHandler = null): void
    {
        $this->broadcastLogoutCalled = true;
    }

    public function getResponse(): Response
    {
        return (new ReflectionClass(KleinController::class))->getProperty('response')->getValue($this);
    }

    public function setUser(UserStruct $user): void
    {
        (new ReflectionClass(KleinController::class))->getProperty('user')->setValue($this, $user);
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

class ForgotPasswordControllerTest extends AbstractTest
{
    private TestableForgotPasswordController $controller;
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

        $this->controller = new TestableForgotPasswordController();
        $this->controller->initWith($this->request, $this->response, $this->rateLimiter);
    }

    // ─── doForgotPassword (private, via reflection) ──────────────────

    #[Test]
    public function doForgotPassword_returns_errors_when_email_missing(): void
    {
        $signupModel = $this->createStub(SignupModel::class);
        $signupModel->method('getParams')->willReturn(['email' => '', 'wanted_url' => 'https://example.com']);

        $result = $this->invokeDoForgotPassword($signupModel);

        $this->assertSame(400, $result['code']);
        $this->assertContains('email is a mandatory field.', $result['errors']);
    }

    #[Test]
    public function doForgotPassword_returns_errors_when_wanted_url_missing(): void
    {
        $signupModel = $this->createStub(SignupModel::class);
        $signupModel->method('getParams')->willReturn(['email' => 'test@example.com', 'wanted_url' => '']);

        $result = $this->invokeDoForgotPassword($signupModel);

        $this->assertSame(400, $result['code']);
        $this->assertContains('wanted_url is a mandatory field.', $result['errors']);
    }

    #[Test]
    public function doForgotPassword_returns_errors_when_email_invalid(): void
    {
        $signupModel = $this->createStub(SignupModel::class);
        $signupModel->method('getParams')->willReturn(['email' => 'not-an-email', 'wanted_url' => 'https://example.com']);

        $result = $this->invokeDoForgotPassword($signupModel);

        $this->assertSame(400, $result['code']);
        $this->assertContains('email is not valid.', $result['errors']);
    }

    #[Test]
    public function doForgotPassword_returns_errors_when_wanted_url_invalid(): void
    {
        $signupModel = $this->createStub(SignupModel::class);
        $signupModel->method('getParams')->willReturn(['email' => 'test@example.com', 'wanted_url' => 'not-a-url']);

        $result = $this->invokeDoForgotPassword($signupModel);

        $this->assertSame(400, $result['code']);
        $this->assertContains('wanted_url is not a valid URL.', $result['errors']);
    }

    #[Test]
    public function doForgotPassword_returns_multiple_errors(): void
    {
        $signupModel = $this->createStub(SignupModel::class);
        $signupModel->method('getParams')->willReturn(['email' => '', 'wanted_url' => '']);

        $result = $this->invokeDoForgotPassword($signupModel);

        $this->assertSame(400, $result['code']);
        $this->assertGreaterThanOrEqual(2, count($result['errors']));
    }

    #[Test]
    public function doForgotPassword_calls_forgotPassword_when_valid(): void
    {
        $signupModel = $this->createMock(SignupModel::class);
        $signupModel->method('getParams')->willReturn(['email' => 'test@example.com', 'wanted_url' => 'https://example.com/reset']);
        $signupModel->expects($this->once())->method('forgotPassword');

        $result = $this->invokeDoForgotPassword($signupModel);

        $this->assertSame(200, $result['code']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function doForgotPassword_returns_correct_array_shape(): void
    {
        $signupModel = $this->createStub(SignupModel::class);
        $signupModel->method('getParams')->willReturn(['email' => 'test@example.com', 'wanted_url' => 'https://example.com']);

        $result = $this->invokeDoForgotPassword($signupModel);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('code', $result);
        $this->assertIsArray($result['errors']);
        $this->assertIsInt($result['code']);
    }

    // ─── forgotPassword (rate limiting paths) ────────────────────────

    #[Test]
    public function forgotPassword_returns_rate_limit_response_on_ip(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter->method('checkAndIncrement')
            ->willReturn($rateLimitedResponse);

        $this->request->method('param')->willReturn('test@example.com');

        $this->controller->forgotPassword();

        $this->assertSame(429, $this->controller->getResponse()->code());
    }

    #[Test]
    public function forgotPassword_returns_rate_limit_on_email(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter->method('checkAndIncrement')
            ->willReturn($rateLimitedResponse);

        $this->request->method('param')->willReturn('test@example.com');

        $this->controller->forgotPassword();

        $this->assertSame(429, $this->controller->getResponse()->code());
    }

    #[Test]
    public function forgotPassword_processes_valid_request(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $this->request->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'email' => 'test@example.com',
                'wanted_url' => 'https://example.com/reset',
                default => null,
            };
        });

        $signupModel = $this->createMock(SignupModel::class);
        $signupModel->method('getParams')->willReturn([
            'email' => 'test@example.com',
            'wanted_url' => 'https://example.com/reset',
        ]);
        $signupModel->expects($this->once())->method('forgotPassword');

        $this->controller->mockSignupModel = $signupModel;

        ob_start();
        try {
            $this->controller->forgotPassword();
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        ob_get_clean();

        $this->assertSame(200, $this->controller->getResponse()->code());
    }

    // ─── authForPasswordReset ────────────────────────────────────────

    #[Test]
    public function authForPasswordReset_returns_rate_limit_response(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter->method('checkAndIncrement')
            ->willReturn($rateLimitedResponse);

        $this->controller->authForPasswordReset();

        $this->assertSame(429, $this->controller->getResponse()->code());
    }

    #[Test]
    public function authForPasswordReset_redirects_on_valid_token(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $resetModel = $this->createStub(PasswordResetModel::class);
        $resetModel->method('flushWantedURL')->willReturn('https://example.com/dashboard');

        $this->controller->mockPasswordResetModel = $resetModel;

        $this->controller->authForPasswordReset();

        $headers = $this->controller->getResponse()->headers();
        $this->assertNotNull($headers->get('Location'));
    }

    #[Test]
    public function authForPasswordReset_redirects_to_root_on_validation_error(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $resetModel = $this->createStub(PasswordResetModel::class);
        $resetModel->method('validateUser')->willThrowException(new ValidationError('Invalid token'));

        $this->controller->mockPasswordResetModel = $resetModel;

        $this->controller->authForPasswordReset();

        $headers = $this->controller->getResponse()->headers();
        $this->assertNotNull($headers->get('Location'));
    }

    // ─── setNewPassword ──────────────────────────────────────────────

    #[Test]
    public function setNewPassword_resets_password_and_broadcasts_logout(): void
    {
        $this->request->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'password' => 'Valid!Password1',
                'password_confirmation' => 'Valid!Password1',
                default => null,
            };
        });

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.com';

        $resetModel = $this->createStub(PasswordResetModel::class);
        $resetModel->method('getUser')->willReturn($user);

        $this->controller->mockPasswordResetModel = $resetModel;

        $this->controller->setNewPassword();

        $this->assertTrue($this->controller->broadcastLogoutCalled);
        $this->assertSame(200, $this->controller->getResponse()->code());
    }

    #[Test]
    public function setNewPassword_throws_when_passwords_dont_match(): void
    {
        $this->request->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'password' => 'Valid!Password1',
                'password_confirmation' => 'DifferentPass!1',
                default => null,
            };
        });

        $resetModel = $this->createStub(PasswordResetModel::class);
        $this->controller->mockPasswordResetModel = $resetModel;

        $this->expectException(ValidationError::class);
        $this->controller->setNewPassword();
    }

    #[Test]
    public function setNewPassword_throws_when_user_null(): void
    {
        $this->request->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'password' => 'Valid!Password1',
                'password_confirmation' => 'Valid!Password1',
                default => null,
            };
        });

        $resetModel = $this->createStub(PasswordResetModel::class);
        $resetModel->method('getUser')->willReturn(null);

        $this->controller->mockPasswordResetModel = $resetModel;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not found after password reset');
        $this->controller->setNewPassword();
    }

    /**
     * @return array{errors: list<string>, code: int}
     */
    private function invokeDoForgotPassword(SignupModel $signupModel): array
    {
        $method = new ReflectionMethod(ForgotPasswordController::class, 'doForgotPassword');

        return $method->invoke($this->controller, $signupModel);
    }
}
