<?php

namespace unit\Utils\Logger;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\HandlersProviderFactory;
use Utils\Registry\AppConfig;

class HandlersProviderFactoryTest extends AbstractTest
{
    /** @var array<string, mixed> */
    private array $originalHandlers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalHandlers = AppConfig::$MONOLOG_HANDLERS;
    }

    protected function tearDown(): void
    {
        AppConfig::$MONOLOG_HANDLERS = $this->originalHandlers;
        parent::tearDown();
    }

    #[Test]
    public function loadWithNameReturnsEmptyWhenNoHandlersConfigured(): void
    {
        AppConfig::$MONOLOG_HANDLERS = [];

        $handlers = HandlersProviderFactory::loadWithName('test.log');

        $this->assertSame([], $handlers);
    }

    #[Test]
    public function loadWithNameLoadsStreamHandler(): void
    {
        AppConfig::$MONOLOG_HANDLERS = [
            'StreamHandlerProvider' => [],
        ];

        $handlers = HandlersProviderFactory::loadWithName('test.log');

        $this->assertNotEmpty($handlers);
        $this->assertContainsOnlyInstancesOf(\Monolog\Handler\AbstractProcessingHandler::class, $handlers);
    }

    #[Test]
    public function loadWithNameSkipsUnknownProviders(): void
    {
        AppConfig::$MONOLOG_HANDLERS = [
            'NonExistentProvider' => [],
        ];

        $handlers = HandlersProviderFactory::loadWithName('test.log');

        $this->assertSame([], $handlers);
    }
}
