<?php

namespace unit\Utils\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\Handlers\StreamHandlerProvider;
use Utils\Registry\AppConfig;

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
