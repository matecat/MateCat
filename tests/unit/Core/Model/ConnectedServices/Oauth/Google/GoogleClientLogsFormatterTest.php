<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth\Google;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\Google\GoogleClientLogsFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;

class GoogleClientLogsFormatterTest extends AbstractTest
{
    private GoogleClientLogsFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new GoogleClientLogsFormatter();
    }

    #[Test]
    public function formatReturnsJsonWithNewline(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message'
        );

        $result = $this->formatter->format($record);

        $this->assertStringEndsWith(PHP_EOL, $result);
        $decoded = json_decode(rtrim($result, PHP_EOL), true);
        $this->assertIsArray($decoded);
        $this->assertSame('Test message', $decoded['message']);
    }

    #[Test]
    public function formatBatchFormatsAllRecords(): void
    {
        $records = [
            new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'test',
                level: Level::Info,
                message: 'First'
            ),
            new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'test',
                level: Level::Warning,
                message: 'Second'
            ),
        ];

        $results = $this->formatter->formatBatch($records);

        $this->assertCount(2, $results);
        $this->assertStringContainsString('First', $results[0]);
        $this->assertStringContainsString('Second', $results[1]);
    }
}
