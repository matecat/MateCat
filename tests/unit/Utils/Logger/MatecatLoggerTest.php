<?php

namespace Tests\unit\Utils\Logger;

use Exception;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

#[CoversClass(MatecatLogger::class)]
class MatecatLoggerTest extends TestCase
{
    private TestHandler $handler;
    private MatecatLogger $logger;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $monolog       = new Logger('test');
        $monolog->pushHandler($this->handler);
        $this->logger = new MatecatLogger($monolog);
    }

    public function testConstructorCreatesInstance(): void
    {
        $this->assertInstanceOf(MatecatLogger::class, $this->logger);
    }

    public function testDebugLogsAtDebugLevel(): void
    {
        $this->logger->debug('test message');
        $this->assertTrue($this->handler->hasDebugRecords());
        $this->assertTrue($this->handler->hasDebug(['message' => 'test message']));
    }

    public function testInfoLogsAtInfoLevel(): void
    {
        $this->logger->info('info message');
        $this->assertTrue($this->handler->hasInfoRecords());
        $this->assertTrue($this->handler->hasInfo(['message' => 'info message']));
    }

    public function testNoticeLogsAtNoticeLevel(): void
    {
        $this->logger->notice('notice message');
        $this->assertTrue($this->handler->hasNoticeRecords());
    }

    public function testWarningLogsAtWarningLevel(): void
    {
        $this->logger->warning('warning message');
        $this->assertTrue($this->handler->hasWarningRecords());
    }

    public function testErrorLogsAtErrorLevel(): void
    {
        $this->logger->error('error message');
        $this->assertTrue($this->handler->hasErrorRecords());
    }

    public function testCriticalLogsAtCriticalLevel(): void
    {
        $this->logger->critical('critical message');
        $this->assertTrue($this->handler->hasCriticalRecords());
    }

    public function testAlertLogsAtAlertLevel(): void
    {
        $this->logger->alert('alert message');
        $this->assertTrue($this->handler->hasAlertRecords());
    }

    public function testEmergencyLogsAtEmergencyLevel(): void
    {
        $this->logger->emergency('emergency message');
        $this->assertTrue($this->handler->hasEmergencyRecords());
    }

    public function testLogWithStringMessagePassesMessageDirectly(): void
    {
        $this->logger->log(Level::Info, 'simple string');
        $record = $this->handler->getRecords()[0];
        $this->assertSame('simple string', $record->message);
        $this->assertSame([], $record->context);
    }

    public function testLogWithStringMessageAndContextPassesBoth(): void
    {
        $this->logger->log(Level::Info, 'with context', ['key' => 'value']);
        $record = $this->handler->getRecords()[0];
        $this->assertSame('with context', $record->message);
        $this->assertSame(['key' => 'value'], $record->context);
    }

    public function testLogWithArrayMessageFormatsAsStructured(): void
    {
        $this->logger->log(Level::Info, ['foo' => 'bar', 'baz' => 123]);
        $record = $this->handler->getRecords()[0];
        $this->assertSame('Log Entry:', $record->message);
        $this->assertSame(['foo' => 'bar', 'baz' => 123], $record->context);
    }

    public function testLogWithArrayMessageMergesContext(): void
    {
        $this->logger->log(Level::Info, ['foo' => 'bar'], ['extra' => 'data']);
        $record = $this->handler->getRecords()[0];
        $this->assertSame('Log Entry:', $record->message);
        $this->assertSame(['foo' => 'bar', 'extra' => 'data'], $record->context);
    }

    public function testLogWithObjectMessageFormatsAsStructured(): void
    {
        $obj      = new \stdClass();
        $obj->key = 'value';
        $this->logger->log(Level::Warning, $obj);
        $record = $this->handler->getRecords()[0];
        $this->assertSame('Log Entry:', $record->message);
        $this->assertSame(['key' => 'value'], $record->context);
    }

    public function testLogWithObjectMessageMergesContext(): void
    {
        $obj      = new \stdClass();
        $obj->key = 'value';
        $this->logger->log(Level::Warning, $obj, ['extra' => 'info']);
        $record = $this->handler->getRecords()[0];
        $this->assertSame('Log Entry:', $record->message);
        $this->assertSame(['key' => 'value', 'extra' => 'info'], $record->context);
    }

    public function testLogFallsBackToFileOnException(): void
    {
        $tmpDir                   = sys_get_temp_dir() . '/matecat_logger_test_' . uniqid();
        mkdir($tmpDir, 0777, true);
        AppConfig::$LOG_REPOSITORY = $tmpDir;

        $mockMonolog = $this->createStub(Logger::class);
        $mockMonolog->method('log')
            ->willThrowException(new Exception('Logging failed'));

        $logger = new MatecatLogger($mockMonolog);
        $logger->log(Level::Error, 'fallback test');

        $fallbackFile = $tmpDir . '/logging_configuration_exception.log';
        $this->assertFileExists($fallbackFile);
        $content = file_get_contents($fallbackFile);
        $decoded = json_decode(trim($content), true);
        $this->assertSame('fallback test', $decoded['message']);
        $this->assertSame([], $decoded['context']);

        unlink($fallbackFile);
        rmdir($tmpDir);
    }

    public function testWithNameReturnsNewInstance(): void
    {
        $newLogger = $this->logger->withName('new-channel');
        $this->assertInstanceOf(MatecatLogger::class, $newLogger);
        $this->assertNotSame($this->logger, $newLogger);
    }

    public function testPushHandlerAddsHandler(): void
    {
        $newHandler = new TestHandler();
        $this->logger->pushHandler($newHandler);
        $this->logger->info('dual handler test');
        $this->assertTrue($this->handler->hasInfoRecords());
        $this->assertTrue($newHandler->hasInfoRecords());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function logLevelMethodProvider(): array
    {
        return [
            'debug'     => ['debug'],
            'info'      => ['info'],
            'notice'    => ['notice'],
            'warning'   => ['warning'],
            'error'     => ['error'],
            'critical'  => ['critical'],
            'alert'     => ['alert'],
            'emergency' => ['emergency'],
        ];
    }

    #[DataProvider('logLevelMethodProvider')]
    public function testAllLevelMethodsAcceptContext(string $method): void
    {
        $this->logger->$method('msg', ['ctx_key' => 'ctx_val']);
        $record = $this->handler->getRecords()[0];
        $this->assertSame(['ctx_key' => 'ctx_val'], $record->context);
    }
}
