<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\Authentication\LaraAuthStandaloneController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Services\RateLimiterService;
use DomainException;
use Klein\Request;
use Klein\Response;
use Lara\Internal\HttpClient as LaraSdkHttpClient;
use Matecat\TestHelpers\AbstractTest;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Model\DataAccess\Database;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Utils\Engines\Lara;
use Utils\Engines\Lara\Headers;
use Utils\Engines\Lara\HttpClientInterface as LaraHttpClientInterface;
use Utils\Logger\MatecatLogger;

/**
 * Fake Lara HTTP client used as a test double. Satisfies the intersection type
 * (`HttpClient & HttpClientInterface`) required by Lara::getInternalClient().
 */
class FakeLaraHttpClient extends LaraSdkHttpClient implements LaraHttpClientInterface
{
    public array $extraHeaders = [];
    public mixed $tokenToReturn = 'fake-token';

    /** Skip parent constructor (which requires auth + opens curl). */
    public function __construct()
    {
    }

    public function __destruct()
    {
        // Skip parent destructor (would curl_close() an uninitialized handle).
    }

    public function setExtraHeader($name, $value): void
    {
        $this->extraHeaders[$name] = $value;
    }

    public function authenticate(): string
    {
        return $this->tokenToReturn;
    }
}

/**
 * Testable subclass that bypasses the parent constructor (session, DB, validators)
 * to allow isolated unit testing of the auth() method logic.
 */
class TestableLaraAuthStandaloneController extends LaraAuthStandaloneController
{
    private ?RateLimiterService $injectedRateLimiter = null;
    public ?EngineDAO $mockEngineDAO = null;
    public ?Lara $mockLaraEngine = null;

    public function __construct()
    {
        // Skip parent constructor entirely
    }

    public function initWith(
        Request $request,
        Response $response,
        RateLimiterService $rateLimiter,
        UserStruct $user,
        MatecatLogger $logger,
    ): void {
        $ref = new ReflectionClass(LaraAuthStandaloneController::class);
        $parentRef = $ref->getParentClass()->getParentClass(); // KleinController

        $parentRef->getProperty('request')->setValue($this, $request);
        $parentRef->getProperty('response')->setValue($this, $response);
        $parentRef->getProperty('logger')->setValue($this, $logger);
        $parentRef->getProperty('user')->setValue($this, $user);
        $parentRef->getProperty('userIsLogged')->setValue($this, true);
        $parentRef->getProperty('database')->setValue($this, obtainTestDatabase());

        $this->injectedRateLimiter = $rateLimiter;
    }

    protected function checkRateLimits(?RateLimiterService $limiterService = null): bool
    {
        return parent::checkRateLimits($limiterService ?? $this->injectedRateLimiter);
    }

    protected function getEngineDAO(): EngineDAO
    {
        return $this->mockEngineDAO ?? parent::getEngineDAO();
    }

    protected function resolveLaraEngine(int $engineId): Lara
    {
        return $this->mockLaraEngine ?? parent::resolveLaraEngine($engineId);
    }

    public function getResponse(): Response
    {
        $ref = new ReflectionClass(LaraAuthStandaloneController::class);
        $parentRef = $ref->getParentClass()->getParentClass();

        return $parentRef->getProperty('response')->getValue($this);
    }
}

#[AllowMockObjectsWithoutExpectations]
class LaraAuthStandaloneControllerTest extends AbstractTest
{
    private TestableLaraAuthStandaloneController $controller;
    private Request|MockObject $request;
    private Response $response;
    private RateLimiterService|MockObject $rateLimiter;
    private UserStruct $user;
    private MatecatLogger|MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createStub(Request::class);
        $this->response = new Response();
        $this->rateLimiter = $this->createMock(RateLimiterService::class);
        $this->logger = $this->createStub(MatecatLogger::class);

        $this->user = new UserStruct();
        $this->user->uid = 1;
        $this->user->email = 'standalone@example.com';

        $this->controller = new TestableLaraAuthStandaloneController();
        $this->controller->initWith(
            $this->request,
            $this->response,
            $this->rateLimiter,
            $this->user,
            $this->logger,
        );
    }

    #[Test]
    public function auth_returns_429_when_email_rate_limited(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);
        $rateLimitedResponse->header('Retry-After', '60');

        $this->rateLimiter
            ->expects($this->once())
            ->method('checkAndIncrement')
            ->willReturn($rateLimitedResponse);

        $this->controller->auth();

        $this->assertEquals(429, $this->controller->getResponse()->code());
    }

    #[Test]
    public function auth_returns_429_when_ip_rate_limited(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);
        $rateLimitedResponse->header('Retry-After', '45');

        $this->rateLimiter
            ->expects($this->exactly(2))
            ->method('checkAndIncrement')
            ->willReturnOnConsecutiveCalls(null, $rateLimitedResponse);

        $this->controller->auth();

        $this->assertEquals(429, $this->controller->getResponse()->code());
    }

    #[Test]
    public function auth_does_not_call_performLaraAuth_when_rate_limited(): void
    {
        $rateLimitedResponse = new Response();
        $rateLimitedResponse->code(429);

        $this->rateLimiter
            ->method('checkAndIncrement')
            ->willReturn($rateLimitedResponse);

        $controller = $this->getMockBuilder(TestableLaraAuthStandaloneController::class)
            ->onlyMethods(['performLaraAuth'])
            ->getMock();

        $controller->initWith(
            $this->request,
            $this->response,
            $this->rateLimiter,
            $this->user,
            $this->logger,
        );

        $controller->expects($this->never())
            ->method('performLaraAuth');

        $controller->auth();
    }

    #[Test]
    public function auth_calls_resolveActiveLaraEngineId_when_not_rate_limited(): void
    {
        $this->rateLimiter
            ->method('checkAndIncrement')
            ->willReturn(null);

        $controller = $this->getMockBuilder(TestableLaraAuthStandaloneController::class)
            ->onlyMethods(['performLaraAuth', 'resolveActiveLaraEngineId'])
            ->getMock();

        $controller->initWith(
            $this->request,
            $this->response,
            $this->rateLimiter,
            $this->user,
            $this->logger,
        );

        $controller->expects($this->once())
            ->method('resolveActiveLaraEngineId')
            ->willReturn(10);

        $controller->expects($this->once())
            ->method('performLaraAuth')
            ->with(10, '');

        $controller->auth();
    }

    #[Test]
    public function auth_returns_200_with_token_on_success(): void
    {
        $this->rateLimiter
            ->method('checkAndIncrement')
            ->willReturn(null);

        $controller = $this->getMockBuilder(TestableLaraAuthStandaloneController::class)
            ->onlyMethods(['performLaraAuth', 'resolveActiveLaraEngineId'])
            ->getMock();

        $controller->initWith(
            $this->request,
            $this->response,
            $this->rateLimiter,
            $this->user,
            $this->logger,
        );

        $controller->expects($this->once())
            ->method('resolveActiveLaraEngineId')
            ->willReturn(77);

        $controller->expects($this->once())
            ->method('performLaraAuth')
            ->with(77, '')
            ->willReturnCallback(function () use ($controller) {
                $controller->getResponse()->code(200);
                $controller->getResponse()->json(['token' => 'mocked-standalone-token']);
            });

        // auth() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $controller->auth();
        } finally {
            ob_end_clean();
        }

        $this->assertEquals(200, $controller->getResponse()->code());
    }

    #[Test]
    public function auth_allows_30_requests_then_returns_429_on_31st(): void
    {
        // Clean Redis keys for this test to ensure isolation
        $redis = (new \Utils\Redis\RedisHandler())->getConnection();
        $emailKey = md5('standalone@example.com' . '/api/app/lara/token');
        $ipKey = md5('127.0.0.1' . '/api/app/lara/token');
        $redis->del([$emailKey, $ipKey]);

        $realRateLimiter = new RateLimiterService();

        // First 30 requests should pass rate-limiting
        for ($i = 1; $i <= 30; $i++) {
            $controller = $this->getMockBuilder(TestableLaraAuthStandaloneController::class)
                ->onlyMethods(['performLaraAuth', 'resolveActiveLaraEngineId'])
                ->getMock();

            $controller->initWith(
                $this->request,
                new Response(),
                $realRateLimiter,
                $this->user,
                $this->logger,
            );

            $controller->method('resolveActiveLaraEngineId')->willReturn(1);
            $controller->expects($this->once())->method('performLaraAuth');

            $controller->auth();
        }

        // 31st request should be rate-limited (429)
        $controller = $this->getMockBuilder(TestableLaraAuthStandaloneController::class)
            ->onlyMethods(['performLaraAuth', 'resolveActiveLaraEngineId'])
            ->getMock();

        $controller->initWith(
            $this->request,
            new Response(),
            $realRateLimiter,
            $this->user,
            $this->logger,
        );

        $controller->expects($this->never())->method('performLaraAuth');

        $controller->auth();

        $this->assertEquals(429, $controller->getResponse()->code());
        $this->assertNotEmpty($controller->getResponse()->headers()->get('Retry-After'));
    }

    // ─── resolveActiveLaraEngineId() ──────────────────────────────────

    #[Test]
    public function resolveActiveLaraEngineId_returns_first_engine_id_from_dao(): void
    {
        $struct1 = EngineStruct::getStruct();
        $struct1->id = 42;
        $struct2 = EngineStruct::getStruct();
        $struct2->id = 99;

        $dao = $this->createMock(EngineDAO::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->expects($this->once())
            ->method('read')
            ->willReturn([$struct1, $struct2]);

        $this->controller->mockEngineDAO = $dao;

        $ref = new ReflectionMethod($this->controller, 'resolveActiveLaraEngineId');
        $result = $ref->invoke($this->controller);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function resolveActiveLaraEngineId_throws_DomainException_when_no_engines(): void
    {
        $dao = $this->createMock(EngineDAO::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('read')->willReturn([]);

        $this->controller->mockEngineDAO = $dao;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No active Lara engine found');

        $ref = new ReflectionMethod($this->controller, 'resolveActiveLaraEngineId');
        $ref->invoke($this->controller);
    }

    // ─── performLaraAuth() (LaraAuthTrait coverage) ───────────────────

    #[Test]
    public function performLaraAuth_with_empty_tm_keys_returns_200_and_does_not_set_memories_header(): void
    {
        $client = new FakeLaraHttpClient();
        $client->tokenToReturn = 'token-empty-tm';

        $engine = $this->createMock(Lara::class);
        $engine->method('getInternalClient')->willReturn($client);
        $engine->expects($this->never())->method('reMapKeyList');

        $this->controller->mockLaraEngine = $engine;

        $ref = new ReflectionMethod($this->controller, 'performLaraAuth');
        // performLaraAuth() calls Response::json(), which send()s the body to stdout and
        // pollutes the test-runner output. Swallow that echo; the body is still recorded on
        // the Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $ref->invoke($this->controller, 7, '');
        } finally {
            ob_end_clean();
        }

        $this->assertSame(200, $this->controller->getResponse()->code());
        $this->assertArrayNotHasKey(
            Headers::LARA_MEMORIES_IDS,
            $client->extraHeaders,
            'Memories header must not be set when tmKeys is empty.'
        );
    }

    #[Test]
    public function performLaraAuth_with_tm_keys_remaps_and_sets_memories_header(): void
    {
        $client = new FakeLaraHttpClient();
        $client->tokenToReturn = 'token-with-tm';

        $engine = $this->createMock(Lara::class);
        $engine->method('getInternalClient')->willReturn($client);
        $engine->expects($this->once())
            ->method('reMapKeyList')
            ->with(['k1', 'k2'])
            ->willReturn(['ext_my_k1', 'ext_my_k2']);

        $this->controller->mockLaraEngine = $engine;

        $ref = new ReflectionMethod($this->controller, 'performLaraAuth');
        // performLaraAuth() calls Response::json(), which send()s the body to stdout and
        // pollutes the test-runner output. Swallow that echo; the body is still recorded on
        // the Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $ref->invoke($this->controller, 7, 'k1,k2');
        } finally {
            ob_end_clean();
        }

        $this->assertSame(200, $this->controller->getResponse()->code());
        $this->assertArrayHasKey(Headers::LARA_MEMORIES_IDS, $client->extraHeaders);
        $this->assertSame('ext_my_k1,ext_my_k2', $client->extraHeaders[Headers::LARA_MEMORIES_IDS]);
    }

    // ─── Constructor / wiring seams ───────────────────────────────────

    #[Test]
    public function initLogger_initializes_logger_property_from_context_list(): void
    {
        $controller = new TestableLaraAuthStandaloneController();

        $ref = new ReflectionMethod($controller, 'initLogger');
        $ref->invoke($controller);

        $loggerProp = new ReflectionProperty(LaraAuthStandaloneController::class, 'logger');
        $logger = $loggerProp->getValue($controller);

        $this->assertNotNull($logger, 'Logger must be initialized after initLogger().');
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    #[Test]
    public function registerValidators_appends_a_login_validator(): void
    {
        $ref = new ReflectionMethod($this->controller, 'registerValidators');
        $ref->invoke($this->controller);

        $validatorsProp = new ReflectionProperty(
            \Controller\Abstracts\KleinController::class,
            'validators'
        );
        $validators = $validatorsProp->getValue($this->controller);

        $this->assertNotEmpty($validators, 'registerValidators must append at least one validator.');
        $this->assertInstanceOf(LoginValidator::class, end($validators));
    }

    #[Test]
    public function getEngineDAO_returns_a_real_engine_dao_instance(): void
    {
        // Ensure the override seam falls through to the real implementation.
        $this->controller->mockEngineDAO = null;

        $ref = new ReflectionMethod(LaraAuthStandaloneController::class, 'getEngineDAO');
        $dao = $ref->invoke($this->controller);

        $this->assertInstanceOf(EngineDAO::class, $dao);
    }
}
