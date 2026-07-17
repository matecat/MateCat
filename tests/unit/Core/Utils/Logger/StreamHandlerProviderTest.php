<?php

namespace Matecat\Core\Utils\Logger;

use Matecat\TestHelpers\AbstractTest;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\Handlers\StreamHandlerProvider;

class StreamHandlerProviderTest extends AbstractTest
{
    #[Test]
    public function getHandlerClassNameReturnsStreamHandler(): void
    {
        $provider = new StreamHandlerProvider();
        $this->assertSame(StreamHandler::class, $provider->getHandlerClassName());
    }

    #[Test]
    public function getHandlerParamsReturnsStreamPath(): void
    {
        $provider = new StreamHandlerProvider();
        $params = $provider->getHandlerParams('test.log', []);

        $this->assertArrayHasKey('stream', $params);
        $this->assertStringContainsString('test.log', $params['stream']);
    }

    #[Test]
    public function setFormatterSetsJsonFormatter(): void
    {
        $provider = new StreamHandlerProvider();
        $handler = new StreamHandler('php://memory');

        $provider->setFormatter($handler);

        $this->assertInstanceOf(JsonFormatter::class, $handler->getFormatter());
    }
}
