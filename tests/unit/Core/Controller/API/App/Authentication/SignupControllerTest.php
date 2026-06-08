<?php

declare(strict_types=1);

namespace Matecat\Core\Controller\API\App\Authentication;

use Controller\Abstracts\KleinController;
use Controller\API\App\Authentication\SignupController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\Services\RateLimiterService;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\Authentication\SignupModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;
use Utils\Logger\MatecatLogger;

class TestableSignupController extends SignupController
{
    public ?SignupModel $mockSignupModel = null;
    public ?\Model\Teams\InvitedUser $mockInvitedUser = null;
    public ?\Model\Users\RedeemableProject $mockRedeemableProject = null;
    public bool $authenticateCalled = false;
    public bool $renderErrorCalled = false;
    private ?RateLimiterService $injectedRateLimiter = null;

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

    protected function createSignupModel(array $params, array &$session): SignupModel
    {
        return $this->mockSignupModel ?? parent::createSignupModel($params, $session);
    }

    protected function authenticateConfirmedUser(\Model\Users\UserStruct $user): void
    {
        $this->authenticateCalled = true;
    }

    protected function createInvitedUser(): \Model\Teams\InvitedUser
    {
        return $this->mockInvitedUser ?? parent::createInvitedUser();
    }

    protected function createRedeemableProject(\Model\Users\UserStruct $user, array &$session): \Model\Users\RedeemableProject
    {
        return $this->mockRedeemableProject ?? parent::createRedeemableProject($user, $session);
    }

    protected function renderErrorPage(): void
    {
        $this->renderErrorCalled = true;
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

class SignupControllerTest extends AbstractTest
{
    private TestableSignupController $controller;
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

        $this->controller = new TestableSignupController();
        $this->controller->initWith($this->request, $this->response, $this->rateLimiter);
    }

    // ─── validateCreationRequest (private, via reflection) ───────────

    #[Test]
    public function validateCreationRequest_throws_when_email_missing(): void
    {
        $this->request->method('param')->willReturn([
            'email' => '',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Valid!Password1',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Missing email');
        $this->invokeValidateCreationRequest();
    }

    #[Test]
    public function validateCreationRequest_throws_when_first_name_missing(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Valid!Password1',
            'first_name' => '',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('First name');
        $this->invokeValidateCreationRequest();
    }

    #[Test]
    public function validateCreationRequest_throws_when_last_name_missing(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Valid!Password1',
            'first_name' => 'John',
            'last_name' => '',
            'wanted_url' => 'https://example.com',
        ]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Last name');
        $this->invokeValidateCreationRequest();
    }

    #[Test]
    public function validateCreationRequest_throws_when_passwords_dont_match(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Different!Pass2',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        $this->expectException(ValidationError::class);
        $this->invokeValidateCreationRequest();
    }

    #[Test]
    public function validateCreationRequest_returns_filtered_array_on_valid_input(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Valid!Password1',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        $result = $this->invokeValidateCreationRequest();

        $this->assertSame('test@example.com', $result['email']);
        $this->assertSame('John', $result['first_name']);
        $this->assertSame('Doe', $result['last_name']);
    }

    // ─── create (rate limiting) ──────────────────────────────────────

    #[Test]
    public function create_returns_rate_limit_on_email(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter->method('checkAndIncrement')
            ->willReturn($rateLimitedResponse);

        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Valid!Password1',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        $this->controller->create();

        $this->assertSame(429, $this->controller->getResponse()->code());
    }

    #[Test]
    public function create_returns_rate_limit_on_ip(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter->method('checkAndIncrement')
            ->willReturnOnConsecutiveCalls(null, $rateLimitedResponse);

        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Valid!Password1',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        $this->controller->create();

        $this->assertSame(429, $this->controller->getResponse()->code());
    }

    #[Test]
    public function create_processes_valid_signup(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Valid!Password1',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        $signupModel = $this->createMock(SignupModel::class);
        $signupModel->expects($this->once())->method('processSignup');
        $this->controller->mockSignupModel = $signupModel;

        $this->controller->create();

        $this->assertSame(200, $this->controller->getResponse()->code());
    }

    // ─── resendConfirmationEmail ─────────────────────────────────────

    // ─── confirm ──────────────────────────────────────────────────────

    #[Test]
    public function confirm_authenticates_and_redirects_on_success(): void
    {
        $user = new \Model\Users\UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.com';

        $signupModel = $this->createStub(SignupModel::class);
        $signupModel->method('confirm')->willReturn($user);
        $signupModel->method('flushWantedURL')->willReturn('https://example.com/dashboard');

        $this->controller->mockSignupModel = $signupModel;

        $invitedUser = $this->createStub(\Model\Teams\InvitedUser::class);
        $invitedUser->method('hasPendingInvitations')->willReturn(false);
        $this->controller->mockInvitedUser = $invitedUser;

        $project = $this->createStub(\Model\Users\RedeemableProject::class);
        $project->method('getDestinationURL')->willReturn(null);
        $this->controller->mockRedeemableProject = $project;

        $this->request->method('param')->willReturn('valid-token');

        $this->controller->confirm();

        $this->assertTrue($this->controller->authenticateCalled);
        $headers = $this->controller->getResponse()->headers();
        $this->assertNotNull($headers->get('Location'));
    }

    #[Test]
    public function confirm_redirects_to_project_url_when_redeemable(): void
    {
        $user = new \Model\Users\UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.com';

        $signupModel = $this->createStub(SignupModel::class);
        $signupModel->method('confirm')->willReturn($user);

        $this->controller->mockSignupModel = $signupModel;

        $invitedUser = $this->createStub(\Model\Teams\InvitedUser::class);
        $invitedUser->method('hasPendingInvitations')->willReturn(false);
        $this->controller->mockInvitedUser = $invitedUser;

        $project = $this->createStub(\Model\Users\RedeemableProject::class);
        $project->method('getDestinationURL')->willReturn('https://example.com/project/123');
        $this->controller->mockRedeemableProject = $project;

        $this->request->method('param')->willReturn('valid-token');

        $this->controller->confirm();

        $location = $this->controller->getResponse()->headers()->get('Location');
        $this->assertSame('https://example.com/project/123', $location);
    }

    #[Test]
    public function confirm_renders_error_page_on_exception(): void
    {
        $signupModel = $this->createStub(SignupModel::class);
        $signupModel->method('confirm')->willThrowException(new \Exception('Invalid token'));

        $this->controller->mockSignupModel = $signupModel;
        $this->request->method('param')->willReturn('bad-token');

        $this->controller->confirm();

        $this->assertTrue($this->controller->renderErrorCalled);
        $this->assertFalse($this->controller->authenticateCalled);
    }

    #[Test]
    public function confirm_completes_team_signup_for_invited_user(): void
    {
        $user = new \Model\Users\UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.com';

        $signupModel = $this->createStub(SignupModel::class);
        $signupModel->method('confirm')->willReturn($user);
        $signupModel->method('flushWantedURL')->willReturn('https://example.com');

        $this->controller->mockSignupModel = $signupModel;

        $invitedUser = $this->createMock(\Model\Teams\InvitedUser::class);
        $invitedUser->method('hasPendingInvitations')->willReturn(true);
        $invitedUser->expects($this->once())->method('completeTeamSignUp');
        $this->controller->mockInvitedUser = $invitedUser;

        $project = $this->createStub(\Model\Users\RedeemableProject::class);
        $project->method('getDestinationURL')->willReturn(null);
        $this->controller->mockRedeemableProject = $project;

        $_SESSION['invited_to_team'] = ['team_id' => 42];
        $this->request->method('param')->willReturn('valid-token');

        $this->controller->confirm();

        $this->assertTrue($this->controller->authenticateCalled);
    }

    // ─── resendConfirmationEmail ─────────────────────────────────────

    #[Test]
    public function resendConfirmationEmail_returns_rate_limit_on_ip(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter->method('checkAndIncrement')
            ->willReturnOnConsecutiveCalls(null, $rateLimitedResponse);

        $this->request->method('param')->willReturn('test@example.com');

        $this->controller->resendConfirmationEmail();

        $this->assertSame(429, $this->controller->getResponse()->code());
    }

    #[Test]
    public function resendConfirmationEmail_returns_rate_limit_on_email(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter->method('checkAndIncrement')
            ->willReturn($rateLimitedResponse);

        $this->request->method('param')->willReturn('test@example.com');

        $this->controller->resendConfirmationEmail();

        $this->assertSame(429, $this->controller->getResponse()->code());
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeValidateCreationRequest(): array
    {
        $method = new ReflectionMethod(SignupController::class, 'validateCreationRequest');

        return $method->invoke($this->controller);
    }
}
