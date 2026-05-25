<?php

namespace unit\Controllers;

use Controller\API\App\Authentication\LaraAuthStandaloneController;
use Controller\Services\RateLimiterInterface;
use Controller\Services\RateLimiterService;
use Klein\Request;
use Klein\Response;
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
class TestableLaraAuthStandaloneController extends LaraAuthStandaloneController
{
    public function __construct()
    {
        // Skip parent constructor entirely
    }

    public function initWith(
        Request $request,
        Response $response,
        RateLimiterInterface $rateLimiter,
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

        $ref->getProperty('rateLimiter')->setValue($this, $rateLimiter);
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
    private RateLimiterInterface|MockObject $rateLimiter;
    private UserStruct $user;
    private MatecatLogger|MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createStub(Request::class);
        $this->response = new Response();
        $this->rateLimiter = $this->createMock(RateLimiterInterface::class);
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

        $controller->auth();

        $this->assertEquals(200, $controller->getResponse()->code());
    }
}
