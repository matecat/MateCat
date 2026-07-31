<?php

declare(strict_types=1);

namespace Matecat\Core\Controller\API\App\Authentication;

use Controller\Abstracts\Authentication\UserStateStore;
use Controller\Abstracts\KleinController;
use Controller\API\App\Authentication\UserController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Services\RateLimiterService;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\DataAccess\XFetchEnvelope;
use Model\Users\Authentication\ChangePasswordModel;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Predis\Client;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

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
        ?IDatabase          $database = null,
    ): void {
        $ref = new ReflectionClass(KleinController::class);
        $ref->getProperty('request')->setValue($this, $request);
        $ref->getProperty('response')->setValue($this, $response);
        $ref->getProperty('user')->setValue($this, $user);
        $ref->getProperty('userIsLogged')->setValue($this, true);
        $ref->getProperty('logger')->setValue($this, $this->createStubLogger());

        if ($database !== null) {
            $ref->getProperty('database')->setValue($this, $database);
        }

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

    /** @var list<array{0: string, 1: array<int, mixed>}> */
    private array $calls = [];

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
        $this->calls = [];
    }

    protected function tearDown(): void
    {
        UserStateStore::setCacheConnection(null);
        parent::tearDown();
    }

    /**
     * A Redis stub that records every command and answers hget from the given field map. Predis
     * dispatches through Client::__call, so that one method is the whole fake.
     *
     * @param array<string, string> $hashFields field name (already md5-ed) => raw stored string
     */
    private function redisStub(array $hashFields = []): Client
    {
        $redis = $this->createStub(Client::class);
        $redis->method('__call')
            ->willReturnCallback(function (string $method, array $args) use ($hashFields) {
                $this->calls[] = [$method, $args];

                if ($method === 'hget') {
                    return $hashFields[$args[1]] ?? null;
                }

                return $method === 'hdel' || $method === 'del' ? 1 : null;
            });

        return $redis;
    }

    /**
     * @return list<array<int, mixed>> the argument lists of every recorded call to $method
     */
    private function callsTo(string $method): array
    {
        $out = [];
        foreach ($this->calls as [$called, $args]) {
            if ($called === $method) {
                $out[] = $args;
            }
        }

        return $out;
    }

    // ─── show ────────────────────────────────────────────────────────

    #[Test]
    public function show_returns_401_when_the_session_has_no_user(): void
    {
        $_SESSION = [];
        $request = new Request();
        $response = new Response();
        $controller = new TestableUserController();
        $controller->initWith($request, $response, $this->user);

        $controller->show();

        $this->assertSame(401, $controller->getResponse()->code());
    }

    /**
     * The guard's real population, and the reason it is a `must` rather than a tidy-up: the api-key
     * branch of authenticate() sets the user and never calls setUserSession(), so an api-key caller
     * arrives here authenticated — LoginValidator passes — with an empty session. /api/app/* is the
     * UI's session-backed surface; a stateless caller has no business reading a session-scoped
     * profile. This had no coverage before.
     */
    #[Test]
    public function show_returns_401_for_an_api_key_caller_carrying_no_session(): void
    {
        $_SESSION = [];

        // What the api-key branch leaves behind: a logged-in user, no session keys at all.
        $this->assertTrue($this->controller->isLoggedIn());

        ob_start();
        try {
            $this->controller->show();
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        $output = ob_get_clean();

        $this->assertSame(401, $this->controller->getResponse()->code());
        $this->assertSame('Invalid login.', json_decode($output, true)['error']);
    }

    #[Test]
    public function show_returns_the_cached_profile_without_rebuilding_it(): void
    {
        $_SESSION = ['user' => $this->user];

        $payload = [
            'user' => ['uid' => 1, 'email' => 'test@example.com'],
            'connected_services' => [],
            'teams' => [],
            'metadata' => null,
        ];

        UserStateStore::setCacheConnection($this->redisStub([
            md5('user_profile:1') => serialize(new XFetchEnvelope([$payload], microtime(true), 1.0)),
        ]));

        ob_start();
        try {
            $this->controller->show();
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        $output = ob_get_clean();

        $this->assertSame($payload, json_decode($output, true));

        // No write means the build did not run. Without this the test would pass just as happily on
        // a rebuild that happened to produce the same payload.
        $this->assertSame([], $this->callsTo('hset'));
    }

    #[Test]
    public function show_builds_the_profile_and_stores_it_on_a_miss(): void
    {
        $_SESSION = ['user' => $this->user];

        // An empty hash: every hget misses, so this is the cold path.
        UserStateStore::setCacheConnection($this->redisStub());

        $controller = new TestableUserController();
        $controller->initWith(new Request(), new Response(), $this->user, null, obtainTestDatabase());

        ob_start();
        try {
            $controller->show();
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        $decoded = json_decode(ob_get_clean(), true);

        // The payload shape the frontend consumes, asserted as a shape rather than as a 200: a
        // response that dropped 'teams' would still be a 200.
        $this->assertSame(
            ['user', 'connected_services', 'teams', 'metadata'],
            array_keys($decoded)
        );
        $this->assertSame(1, $decoded['user']['uid']);

        // Built once, then cached for the next call — this is the whole point of moving it out of
        // the session rather than deleting the cache.
        $hset = $this->callsTo('hset');
        $this->assertCount(1, $hset);
        $this->assertSame('user_state:1', $hset[0][0]);
        $this->assertSame(md5('user_profile:1'), $hset[0][1]);
    }

    /**
     * The store honours the kill switch because it is a cache, unlike SessionTokenStoreHandler,
     * which assigns cacheTTL directly so a token is stored whatever the switch says.
     */
    #[Test]
    public function show_builds_live_and_stores_nothing_when_sql_cache_is_skipped(): void
    {
        $_SESSION = ['user' => $this->user];

        $previous = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        UserStateStore::setCacheConnection($this->redisStub());

        $controller = new TestableUserController();
        $controller->initWith(new Request(), new Response(), $this->user, null, obtainTestDatabase());

        try {
            ob_start();
            try {
                $controller->show();
            } catch (\Klein\Exceptions\ResponseAlreadySentException) {
            }
            $decoded = json_decode(ob_get_clean(), true);
        } finally {
            AppConfig::$SKIP_SQL_CACHE = $previous;
        }

        $this->assertSame(
            ['user', 'connected_services', 'teams', 'metadata'],
            array_keys($decoded)
        );
        $this->assertSame([], $this->callsTo('hset'));
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

    // ─── registerValidators ──────────────────────────────────────────

    #[Test]
    public function afterConstruct_appends_login_validator(): void
    {
        $ref = new ReflectionMethod($this->controller, 'registerValidators');
        $ref->invoke($this->controller);

        $validatorsProp = new ReflectionProperty(KleinController::class, 'validators');
        $validators = $validatorsProp->getValue($this->controller);

        $this->assertNotEmpty($validators);
        $this->assertInstanceOf(LoginValidator::class, end($validators));
    }

    #[Test]
    public function changePassword_hands_both_passwords_to_the_model_unescaped(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $this->request->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'old_password' => 'Old&Pass<word>1',
                'password' => 'New&Pass<word>1',
                'password_confirmation' => 'New&Pass<word>1',
                default => null,
            };
        });

        // Both values matter here: the old one is verified against the stored hash and the new one
        // replaces it, so escaping either would break a password containing one of these characters
        // in a different way.
        $cpModel = $this->createMock(ChangePasswordModel::class);
        $cpModel->expects($this->once())
            ->method('changePassword')
            ->with('Old&Pass<word>1', 'New&Pass<word>1');

        $this->controller->mockChangePasswordModel = $cpModel;

        $this->controller->changePasswordAsLoggedUser();

        $this->assertSame(200, $this->controller->getResponse()->code());
    }

    #[Test]
    public function changePassword_rejects_a_password_containing_a_control_character(): void
    {
        $this->rateLimiter->method('checkAndIncrement')->willReturn(null);

        $this->request->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'old_password' => 'OldPass!123xx',
                'password' => "NewValid!Pass\n1",
                'password_confirmation' => "NewValid!Pass\n1",
                default => null,
            };
        });

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('control characters');

        try {
            $this->controller->changePasswordAsLoggedUser();
        } finally {
            // Refused before the password is written, so existing sessions are left alone.
            $this->assertFalse($this->controller->broadcastLogoutCalled);
        }
    }
}
