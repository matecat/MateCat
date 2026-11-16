<?php

namespace Matecat\TestHelpers;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\After;
use ReflectionProperty;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;

/**
 * Lets a test read what the code wrote to one or more named loggers.
 *
 * captureLog() routes the loggers to an in-memory handler. After the test, the LoggerFactory map is
 * put back as it was, so the capture never leaks into the next test.
 *
 * Example:
 *     $log = $this->captureLog('files');
 *     Utils::deleteDir($dir);
 *     $this->assertTrue($log->hasWarningThatContains('fail deleting'));
 */
trait CapturesNamedLogs
{
    /** @var array<string, MatecatLogger>|null The logger map before the first capture; null when nothing was captured. */
    private ?array $loggersBeforeCapture = null;

    /**
     * Route the named loggers to a new TestHandler and return it.
     */
    protected function captureLog(string ...$names): TestHandler
    {
        $this->loggersBeforeCapture ??= (new ReflectionProperty(LoggerFactory::class, 'loggersMap'))->getValue();

        $handler = new TestHandler();
        LoggerFactory::setAliases($names, new MatecatLogger(new Logger('test', [$handler])));

        return $handler;
    }

    /**
     * Put back the logger map taken by the first captureLog() call of the test.
     */
    #[After]
    protected function restoreCapturedLogs(): void
    {
        if ($this->loggersBeforeCapture === null) {
            return;
        }

        (new ReflectionProperty(LoggerFactory::class, 'loggersMap'))->setValue(null, $this->loggersBeforeCapture);
        $this->loggersBeforeCapture = null;
    }
}
