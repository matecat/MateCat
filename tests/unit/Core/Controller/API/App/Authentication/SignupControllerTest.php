<?php

declare(strict_types=1);

namespace Matecat\Core\Controller\API\App\Authentication;

use Utils\Session\ArraySessionStore;
use Utils\Session\SessionStore;

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

    public function setDatabase(\Model\DataAccess\IDatabase $database): void
    {
        (new ReflectionClass(KleinController::class))->getProperty('database')->setValue($this, $database);
    }

    public function checkAndIncrementRateLimit(Response $response, string $identifier, string $route, int $maxRetries = 10, ?RateLimiterService $limiterService = null, int $weight = 1): ?Response
    {
        return parent::checkAndIncrementRateLimit($response, $identifier, $route, $maxRetries, $limiterService ?? $this->injectedRateLimiter);
    }

    protected function createSignupModel(array $params, SessionStore $session): SignupModel
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

    protected function createRedeemableProject(\Model\Users\UserStruct $user, SessionStore $session): \Model\Users\RedeemableProject
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
    private ArraySessionStore $sessionStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createStub(Request::class);
        $this->response = new Response();
        $this->rateLimiter = $this->createStub(RateLimiterService::class);

        $this->controller = new TestableSignupController();
        $this->controller->initWith($this->request, $this->response, $this->rateLimiter);

        // The double skips the constructor that builds the store; a fresh one per case also replaces
        // resetting $_SESSION here.
        $this->sessionStore = $this->injectSessionStore($this->controller);
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

        $this->sessionStore->set('invited_to_team', ['team_id' => 42]);
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

    // ─── real collaborator construction (uncovered helper bodies) ─────

    #[Test]
    public function createSignupModel_real_builds_signup_model_with_user_struct(): void
    {
        $this->controller->setDatabase(obtainTestDatabase());

        $method = new ReflectionMethod(SignupController::class, 'createSignupModel');
        $session = new ArraySessionStore();
        $params = ['email' => 'realbuild@example.com'];

        $signupModel = $method->invoke($this->controller, $params, $session);

        $this->assertInstanceOf(SignupModel::class, $signupModel);
        $this->assertSame($params, $signupModel->getParams());
    }

    #[Test]
    public function createInvitedUser_real_builds_invited_user(): void
    {
        $this->controller->setDatabase(obtainTestDatabase());

        $method = new ReflectionMethod(SignupController::class, 'createInvitedUser');

        $invitedUser = $method->invoke($this->controller);

        $this->assertInstanceOf(\Model\Teams\InvitedUser::class, $invitedUser);
        $this->assertFalse($invitedUser->hasPendingInvitations());
    }

    #[Test]
    public function createRedeemableProject_real_builds_redeemable_project(): void
    {
        $this->controller->setDatabase(obtainTestDatabase());

        $user = new \Model\Users\UserStruct();
        $user->uid = 1;
        $method = new ReflectionMethod(SignupController::class, 'createRedeemableProject');
        $session = new ArraySessionStore();

        $project = $method->invoke($this->controller, $user, $session);

        $this->assertInstanceOf(\Model\Users\RedeemableProject::class, $project);
    }

    // ─── validateCreationRequest wanted_url callback branches ─────────

    #[Test]
    public function validateCreationRequest_falls_back_to_httphost_when_wanted_url_unparsable(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Valid!Password1',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'http://a:b:c/path',
        ]);

        $user = $this->invokeValidateCreationRequest();

        $this->assertSame(\Utils\Registry\AppConfig::$HTTPHOST, $user['wanted_url']);
    }

    #[Test]
    public function validateCreationRequest_falls_back_to_httphost_when_wanted_url_host_differs(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Valid!Password1',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://evil-external-host.example.org/phish',
        ]);

        $user = $this->invokeValidateCreationRequest();

        $this->assertSame(\Utils\Registry\AppConfig::$HTTPHOST, $user['wanted_url']);
    }

    // ─── resendConfirmationEmail success path (no rate limit hit) ─────

    #[Test]
    public function resendConfirmationEmail_completes_with_no_matching_user(): void
    {
        $this->controller->setDatabase(obtainTestDatabase());

        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);
        $this->request->method('param')->willReturn('no-such-user-9975000@example.com');

        $this->controller->resendConfirmationEmail();

        $this->assertSame(200, $this->controller->getResponse()->code());
    }

    // ─── control characters in the password ───────────────────────────

    #[Test]
    public function validateCreationRequest_throws_when_password_contains_a_control_character(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => "Valid!Pass\tword1",
            'password_confirmation' => "Valid!Pass\tword1",
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        // A tab reaches validation encoded as &#9; by the sanitising filter, so the generic illegal
        // character rule already rejects it, but only as a side effect of that encoding and with a
        // message that never says what was wrong. The rule has to be explicit about it.
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('control characters');
        $this->invokeValidateCreationRequest();
    }

    #[Test]
    public function validateCreationRequest_throws_when_password_contains_a_null_byte(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => "Valid!Password1\0",
            'password_confirmation' => "Valid!Password1\0",
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('control characters');
        $this->invokeValidateCreationRequest();
    }

    #[Test]
    public function validateCreationRequest_accepts_a_password_of_printable_characters(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid!Password1',
            'password_confirmation' => 'Valid!Password1',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        // The guard must not narrow what a legitimate user may choose: every printable character is
        // still acceptable, and the value must survive validation unaltered.
        $user = $this->invokeValidateCreationRequest();

        $this->assertSame('Valid!Password1', $user['password']);
    }

    #[Test]
    public function validateCreationRequest_accepts_a_password_containing_html_special_characters(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => 'Valid&Pass<word>1',
            'password_confirmation' => 'Valid&Pass<word>1',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        // These five characters are the ones the sanitising filter rewrites, and the special-character
        // rule has always advertised them as valid choices. A password is compared against a hash and
        // never rendered, so escaping it only shrinks the usable character set and has to reach the
        // hash exactly as the user typed it.
        $user = $this->invokeValidateCreationRequest();

        $this->assertSame('Valid&Pass<word>1', $user['password']);
    }

    #[Test]
    public function validateCreationRequest_measures_the_minimum_length_in_characters_not_bytes(): void
    {
        $this->request->method('param')->willReturn([
            'email' => 'test@example.com',
            'password' => '密碼密!碼碼碼',
            'password_confirmation' => '密碼密!碼碼碼',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'wanted_url' => 'https://example.com',
        ]);

        // Seven characters, nineteen bytes. Measured in bytes this clears a twelve character minimum
        // while being far shorter than the rule intends, and the shortfall grows with every non-Latin
        // alphabet.
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('at least 12 characters');
        $this->invokeValidateCreationRequest();
    }
}
