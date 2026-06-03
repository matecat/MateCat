<?php

namespace Matecat\Core\Utils\Logger;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class LoggerFactoryTest extends AbstractTest
{
    /** @var array<string, mixed> */
    private array $originalHandlers;
    private string $originalLogRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalHandlers = AppConfig::$MONOLOG_HANDLERS;
        $this->originalLogRepo = AppConfig::$LOG_REPOSITORY;

        AppConfig::$MONOLOG_HANDLERS = ['StreamHandlerProvider' => []];
        AppConfig::$LOG_REPOSITORY = sys_get_temp_dir();

        $ref = new \ReflectionProperty(LoggerFactory::class, 'loggersMap');
        $ref->setValue(null, []);
    }

    protected function tearDown(): void
    {
        AppConfig::$MONOLOG_HANDLERS = $this->originalHandlers;
        AppConfig::$LOG_REPOSITORY = $this->originalLogRepo;

        $ref = new \ReflectionProperty(LoggerFactory::class, 'loggersMap');
        $ref->setValue(null, []);

        parent::tearDown();
    }

    #[Test]
    public function getFileNamePathReturnsFullPath(): void
    {
        AppConfig::$LOG_REPOSITORY = '/var/log';
        $this->assertSame('/var/log/test.log', LoggerFactory::getFileNamePath('test.log'));
    }

    #[Test]
    public function getLoggerReturnsMatecatLogger(): void
    {
        $logger = LoggerFactory::getLogger('test-logger', 'test-factory.log');
        $this->assertInstanceOf(MatecatLogger::class, $logger);
    }

    #[Test]
    public function getLoggerReturnsSameInstanceForSameName(): void
    {
        $a = LoggerFactory::getLogger('same-name', 'test.log');
        $b = LoggerFactory::getLogger('same-name', 'test.log');
        $this->assertSame($a, $b);
    }

    #[Test]
    public function getRequestIdReturnsConsistentValue(): void
    {
        LoggerFactory::$requestID = null;
        $id1 = LoggerFactory::getRequestID();
        $id2 = LoggerFactory::getRequestID();

        $this->assertSame($id1, $id2);
        $this->assertNotEmpty($id1);
    }

    #[Test]
    public function doJsonLogDoesNotThrow(): void
    {
        LoggerFactory::doJsonLog('test content', 'unit-test.log');
        $this->assertTrue(true);
    }

    #[Test]
    public function setAliasesCreatesNamedLoggers(): void
    {
        $logger = LoggerFactory::getLogger('original', 'test.log');
        LoggerFactory::setAliases(['alias1', 'alias2'], $logger);

        $alias1 = LoggerFactory::getLogger('alias1', 'test.log');
        $this->assertInstanceOf(MatecatLogger::class, $alias1);
    }
}
