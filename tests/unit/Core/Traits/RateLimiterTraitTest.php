<?php

namespace Matecat\Core\Traits;

use Controller\Services\RateLimiterService;
use Controller\Traits\RateLimiterTrait;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Concrete class that uses RateLimiterTrait for testing.
 */
class RateLimiterTraitConsumer
{
    use RateLimiterTrait;
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
    }

    #[Test]
    public function checkAndIncrementRateLimitDelegatesToInjectedService(): void
    {
        $response = $this->createStub(Response::class);

        $this->mockService
            ->expects($this->once())
            ->method('checkAndIncrement')
            ->with($response, 'user@test.com', '/api/route', 10)
            ->willReturn(null);

        $result = $this->consumer->checkAndIncrementRateLimit(
            $response,
            'user@test.com',
            '/api/route',
            10,
            $this->mockService
        );

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

        $result = $this->consumer->checkAndIncrementRateLimit(
            $response,
            'user@test.com',
            '/api/route',
            5,
            $this->mockService
        );

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

        $this->consumer->checkAndIncrementRateLimit(
            $response,
            'id',
            '/route',
            10,
            $this->mockService
        );
    }
}
