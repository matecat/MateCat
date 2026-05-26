<?php

namespace unit\Controllers;

use Controller\API\App\Authentication\LaraAuthController;
use Controller\Services\RateLimiterService;
use Klein\Request;
use Klein\Response;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Testable subclass that bypasses the parent constructor (session, DB, validators)
 * to allow isolated unit testing of the auth() method logic.
 */
class TestableLaraAuthController extends LaraAuthController
{
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
        $ref = new ReflectionClass(LaraAuthController::class);
        $parentRef = $ref->getParentClass()->getParentClass(); // KleinController

        $parentRef->getProperty('request')->setValue($this, $request);
        $parentRef->getProperty('response')->setValue($this, $response);
        $parentRef->getProperty('logger')->setValue($this, $logger);
        $parentRef->getProperty('user')->setValue($this, $user);
        $parentRef->getProperty('userIsLogged')->setValue($this, true);

        $this->setRateLimiterService($rateLimiter);
    }

    public function setChunk(JobStruct $chunk): void
    {
        $ref = new ReflectionClass(LaraAuthController::class);
        $ref->getProperty('chunk')->setValue($this, $chunk);
    }

    public function getResponse(): Response
    {
        $ref = new ReflectionClass(LaraAuthController::class);
        $parentRef = $ref->getParentClass()->getParentClass();

        return $parentRef->getProperty('response')->getValue($this);
    }
}

#[AllowMockObjectsWithoutExpectations]
class LaraAuthControllerTest extends AbstractTest
{
    private TestableLaraAuthController $controller;
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
        $this->user->email = 'test@example.com';

        $this->controller = new TestableLaraAuthController();
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

        $chunk = new JobStruct();
        $chunk->id_mt_engine = 99;
        $chunk->tm_keys = '[]';
        $this->controller->setChunk($chunk);

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

        $chunk = new JobStruct();
        $chunk->id_mt_engine = 99;
        $chunk->tm_keys = '[]';
        $this->controller->setChunk($chunk);

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

        $chunk = new JobStruct();
        $chunk->id_mt_engine = 99;
        $chunk->tm_keys = '[]';

        $controller = $this->getMockBuilder(TestableLaraAuthController::class)
            ->onlyMethods(['performLaraAuth'])
            ->getMock();

        $controller->initWith(
            $this->request,
            $this->response,
            $this->rateLimiter,
            $this->user,
            $this->logger,
        );
        $controller->setChunk($chunk);

        $controller->expects($this->never())
            ->method('performLaraAuth');

        $controller->auth();
    }

    #[Test]
    public function auth_calls_performLaraAuth_with_engine_id_and_tm_keys(): void
    {
        $this->rateLimiter
            ->method('checkAndIncrement')
            ->willReturn(null);

        $chunk = new JobStruct();
        $chunk->id_mt_engine = 42;
        $chunk->tm_keys = '[]';

        $controller = $this->getMockBuilder(TestableLaraAuthController::class)
            ->onlyMethods(['performLaraAuth'])
            ->getMock();

        $controller->initWith(
            $this->request,
            $this->response,
            $this->rateLimiter,
            $this->user,
            $this->logger,
        );
        $controller->setChunk($chunk);

        $controller->expects($this->once())
            ->method('performLaraAuth')
            ->with(42, '');

        $controller->auth();
    }

    #[Test]
    public function auth_returns_200_with_token_on_success(): void
    {
        $this->rateLimiter
            ->method('checkAndIncrement')
            ->willReturn(null);

        $chunk = new JobStruct();
        $chunk->id_mt_engine = 42;
        $chunk->tm_keys = '[]';

        $controller = $this->getMockBuilder(TestableLaraAuthController::class)
            ->onlyMethods(['performLaraAuth'])
            ->getMock();

        $controller->initWith(
            $this->request,
            $this->response,
            $this->rateLimiter,
            $this->user,
            $this->logger,
        );
        $controller->setChunk($chunk);

        $controller->expects($this->once())
            ->method('performLaraAuth')
            ->willReturnCallback(function () use ($controller) {
                $response = $controller->getResponse();
                $response->code(200);
                $response->json(['token' => 'mocked-token-abc123']);
            });

        $controller->auth();

        $this->assertEquals(200, $controller->getResponse()->code());
    }

    #[Test]
    public function auth_allows_30_requests_then_returns_429_on_31st(): void
    {
        // Clean Redis keys for this test to ensure isolation
        $redis = (new \Utils\Redis\RedisHandler())->getConnection();
        $emailKey = md5('test@example.com' . '/api/app/lara/token');
        $ipKey = md5('127.0.0.1' . '/api/app/lara/token');
        $redis->del([$emailKey, $ipKey]);

        $realRateLimiter = new RateLimiterService();

        $chunk = new JobStruct();
        $chunk->id_mt_engine = 42;
        $chunk->tm_keys = '[]';

        // First 30 requests should pass rate-limiting
        for ($i = 1; $i <= 30; $i++) {
            $controller = $this->getMockBuilder(TestableLaraAuthController::class)
                ->onlyMethods(['performLaraAuth'])
                ->getMock();

            $controller->initWith(
                $this->request,
                new Response(),
                $realRateLimiter,
                $this->user,
                $this->logger,
            );
            $controller->setChunk($chunk);

            $controller->expects($this->once())
                ->method('performLaraAuth');

            $controller->auth();
        }

        // 31st request should be rate-limited (429)
        $controller = $this->getMockBuilder(TestableLaraAuthController::class)
            ->onlyMethods(['performLaraAuth'])
            ->getMock();

        $controller->initWith(
            $this->request,
            new Response(),
            $realRateLimiter,
            $this->user,
            $this->logger,
        );
        $controller->setChunk($chunk);

        $controller->expects($this->never())
            ->method('performLaraAuth');

        $controller->auth();

        $this->assertEquals(429, $controller->getResponse()->code());
        $this->assertNotEmpty($controller->getResponse()->headers()->get('Retry-After'));
    }
}
