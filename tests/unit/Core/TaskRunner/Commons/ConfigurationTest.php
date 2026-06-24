<?php

namespace Matecat\Core\TaskRunner\Commons;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\TaskRunner\Commons\Configuration;
use Utils\TaskRunner\Commons\ContextList;

class ConfigurationTest extends AbstractTest
{
    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function writeTempConfig(array $queues = [], string $loggerName = 'test.log'): string
    {
        $content = "loggerName = \"{$loggerName}\"\n\n[context_definitions]\n";
        foreach ($queues as $name => $max) {
            $content .= "{$name}[queue_name] = \"{$name}\"\n";
            $content .= "{$name}[max_executors] = {$max}\n";
        }
        $path = tempnam(sys_get_temp_dir(), 'cfg_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    // ─── Constructor: valid file ─────────────────────────────────────────────────

    #[Test]
    public function constructor_with_valid_file_sets_context_list(): void
    {
        $path = $this->writeTempConfig(['q1' => 2]);

        $config = new Configuration($path);

        $this->assertInstanceOf(ContextList::class, $config->getContextList());
    }

    #[Test]
    public function constructor_with_valid_file_sets_logger_name(): void
    {
        $path = $this->writeTempConfig(['q1' => 2], 'my.logger');

        $config = new Configuration($path);

        $this->assertSame('my.logger', $config->getLoggerName());
    }

    #[Test]
    public function constructor_with_valid_file_populates_raw(): void
    {
        $path = $this->writeTempConfig(['q1' => 2]);

        $config = new Configuration($path);
        $raw = $config->getRaw();

        $this->assertIsArray($raw);
        $this->assertArrayHasKey('loggerName', $raw);
        $this->assertArrayHasKey('context_definitions', $raw);
    }

    // ─── Constructor: context_definitions ────────────────────────────────────────

    #[Test]
    public function constructor_builds_context_list_with_all_queues(): void
    {
        $path = $this->writeTempConfig(['q1' => 2, 'q2' => 5]);

        $config = new Configuration($path);
        $list = $config->getContextList();

        $this->assertCount(2, $list->list);
        $this->assertArrayHasKey('q1', $list->list);
        $this->assertArrayHasKey('q2', $list->list);
    }

    #[Test]
    public function constructor_without_context_index_builds_full_list(): void
    {
        $path = $this->writeTempConfig(['q1' => 2, 'q2' => 5]);

        $config = new Configuration($path);
        $list = $config->getContextList();

        $this->assertInstanceOf(ContextList::class, $list);
        $this->assertCount(2, $list->list);
    }

    // ─── Constructor: invalid / empty file ───────────────────────────────────────

    #[Test]
    public function constructor_with_empty_string_throws_throwable(): void
    {
        $this->expectException(\Throwable::class);

        new Configuration('');
    }

    #[Test]
    public function constructor_with_missing_context_definitions_throws_exception(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cfg_bad_');
        file_put_contents($path, "loggerName = \"test.log\"\n");
        $this->tempFiles[] = $path;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Wrong configuration file provided.');

        new Configuration($path);
    }

    #[Test]
    public function constructor_with_empty_context_definitions_throws_exception(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cfg_empty_');
        file_put_contents($path, "loggerName = \"test.log\"\n\n[context_definitions]\n");
        $this->tempFiles[] = $path;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Wrong configuration file provided.');

        new Configuration($path);
    }

    // ─── getRaw() ────────────────────────────────────────────────────────────────

    #[Test]
    public function get_raw_returns_full_parsed_ini_array(): void
    {
        $path = $this->writeTempConfig(['q1' => 3], 'raw.logger');

        $config = new Configuration($path);
        $raw = $config->getRaw();

        $this->assertSame('raw.logger', $raw['loggerName']);
        $this->assertIsArray($raw['context_definitions']);
        $this->assertSame('q1', $raw['context_definitions']['q1']['queue_name']);
        $this->assertSame('3', $raw['context_definitions']['q1']['max_executors']);
    }

    // ─── getLoggerName() ─────────────────────────────────────────────────────────

    #[Test]
    public function get_logger_name_returns_configured_value(): void
    {
        $path = $this->writeTempConfig(['q1' => 1], 'daemon.log');

        $config = new Configuration($path);

        $this->assertSame('daemon.log', $config->getLoggerName());
    }

    // ─── getContextList() ────────────────────────────────────────────────────────

    #[Test]
    public function get_context_list_returns_context_list_instance(): void
    {
        $path = $this->writeTempConfig(['q1' => 4]);

        $config = new Configuration($path);

        $this->assertInstanceOf(ContextList::class, $config->getContextList());
    }
}
