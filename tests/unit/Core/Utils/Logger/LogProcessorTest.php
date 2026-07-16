<?php

namespace Matecat\Core\Utils\Logger;

use Matecat\TestHelpers\AbstractTest;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\LoggerFactory;
use Utils\Logger\LogProcessor;

class LogProcessorTest extends AbstractTest
{
    #[Test]
    public function invokeAddsExtraFields(): void
    {
        LoggerFactory::$uniqID = 'test-token-123';
        $processor = new LogProcessor(Level::Debug, []);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug,
            message: 'test message',
        );

        $result = $processor($record);

        $this->assertArrayHasKey('ip', $result['extra']);
        $this->assertSame('test-token-123', $result['extra']['token_hash']);
        $this->assertArrayHasKey('time', $result['extra']);
        $this->assertArrayHasKey('date', $result['extra']);
        $this->assertArrayHasKey('context', $result['extra']);
    }
}
