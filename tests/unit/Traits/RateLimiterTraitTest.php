<?php

namespace unit\Traits;

use Controller\Services\RateLimiterService;
use Controller\Traits\RateLimiterTrait;
use Klein\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TestHelpers\AbstractTest;

/**
 * Concrete class that uses RateLimiterTrait for testing.
 */
class RateLimiterTraitConsumer
{
    use RateLimiterTrait;

    public function setRateLimiterService(RateLimiterService $limiterService): void
    {
        $this->limiterService = $limiterService;
    }
}

#[AllowMockObjectsWithoutExpectations]
class RateLimiterTraitTest extends AbstractTest
{
    private RateLimiterTraitConsumer $consumer;
    private RateLimiterService|MockObject $mockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockService = $this->createMock(RateLimiterService::class);
        $this->consumer = new RateLimiterTraitConsumer();
        $this->consumer->setRateLimiterService($this->mockService);
    }

    // ─── Delegation tests ───────────────────────────────────────────

    #[Test]
    public function checkAndIncrementRateLimitDelegatesToService(): void
    {
        $response = $this->createStub(Response::class);

        $this->mockService
            ->expects($this->once())
            ->method('checkAndIncrement')
            ->with($response, 'user@test.com', '/api/route', 10)
            ->willReturn(null);

        $result = $this->consumer->checkAndIncrementRateLimit($response, 'user@test.com', '/api/route', 10);

        $this->assertNull($result);
    }

    #[Test]
    public function checkAndIncrementRateLimitReturnsServiceResponseWhenRateLimited(): void
    {
        $response = $this->createMock(Response::class);

        $this->mockService
            ->expects($this->once())
            ->method('checkAndIncrement')
            ->willReturn($response);

        $result = $this->consumer->checkAndIncrementRateLimit($response, 'user@test.com', '/api/route', 5);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function checkAndIncrementRateLimitUsesDefaultMaxRetries(): void
    {
        $response = $this->createStub(Response::class);

        $this->mockService
            ->expects($this->once())
            ->method('checkAndIncrement')
            ->with($response, 'id', '/route', 10);

        $this->consumer->checkAndIncrementRateLimit($response, 'id', '/route');
    }

    // ─── getRateLimiterService tests ────────────────────────────────

    #[Test]
    public function getRateLimiterServiceReturnsInjectedService(): void
    {
        $service = new RateLimiterService();
        $consumer = new RateLimiterTraitConsumer();
        $consumer->setRateLimiterService($service);

        $ref = new \ReflectionMethod($consumer, 'getRateLimiterService');
        $result = $ref->invoke($consumer);

        $this->assertSame($service, $result);
    }

    #[Test]
    public function getRateLimiterServiceCreatesDefaultServiceWhenNotInjected(): void
    {
        $consumer = new RateLimiterTraitConsumer();

        $ref = new \ReflectionMethod($consumer, 'getRateLimiterService');
        $result = $ref->invoke($consumer);

        $this->assertInstanceOf(RateLimiterService::class, $result);
    }
}
