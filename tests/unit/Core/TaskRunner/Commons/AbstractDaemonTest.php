<?php

namespace Matecat\Core\TaskRunner\Commons;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use UnexpectedValueException;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\AbstractDaemon;
use Utils\TaskRunner\Commons\Configuration;
use Utils\TaskRunner\Commons\ContextList;

/**
 * Concrete subclass used for all tests.
 * Defined at file scope so ReflectionClass can target it consistently.
 */
class ConcreteTestDaemon extends AbstractDaemon
{
    /** Runs as the daemon's own check of a configuration it read; null: every configuration is accepted. */
    public ?\Closure $validate = null;

    public function main(array $args = null): void {}
    public function cleanShutDown(): void {}
    protected function _updateConfiguration(): void {}

    protected function _validateConfiguration(Configuration $configuration): void
    {
        if ($this->validate !== null) {
            ($this->validate)($configuration);
        }
    }
}

class AbstractDaemonTest extends AbstractTest
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

    /**
     * Create a ConcreteTestDaemon without running its constructor,
     * then wire properties directly — same pattern as TaskManagerTest.
     */
    private function makeDaemon(string $configFile = '', ?string $contextIndex = null): ConcreteTestDaemon
    {
        $ref = new ReflectionClass(ConcreteTestDaemon::class);
        /** @var ConcreteTestDaemon $daemon */
        $daemon = $ref->newInstanceWithoutConstructor();

        $ref->getProperty('_configFile')->setValue($daemon, $configFile);
        $ref->getProperty('_contextIndex')->setValue($daemon, $contextIndex);
        $ref->getProperty('myProcessPid')->setValue($daemon, posix_getpid());
        $ref->getProperty('RUNNING')->setValue($daemon, true);

        return $daemon;
    }

    /**
     * Invoke the protected AbstractDaemon constructor on an already-created
     * instance via the parent class reflection (avoids visibility errors).
     */
    private function invokeConstructor(ConcreteTestDaemon $daemon, string $configFile = '', ?string $contextIndex = null): void
    {
        $parentRef = new ReflectionClass(AbstractDaemon::class);
        $ctor = $parentRef->getConstructor();
        $ctor->setAccessible(true);
        $ctor->invoke($daemon, $configFile, $contextIndex);
    }

    private function writeTempConfig(array $queues): string
    {
        $path = tempnam(sys_get_temp_dir(), 'daemon_test_');
        $this->rewriteConfig($path, $queues);
        $this->tempFiles[] = $path;
        return $path;
    }

    // ─── getInstance() ──────────────────────────────────────────────────────────

    #[Test]
    public function getInstance_returns_concrete_instance(): void
    {
        $daemon = ConcreteTestDaemon::getInstance();

        $this->assertInstanceOf(ConcreteTestDaemon::class, $daemon);
        $this->assertInstanceOf(AbstractDaemon::class, $daemon);
    }

    #[Test]
    public function getInstance_sets_pid_on_returned_instance(): void
    {
        $daemon = ConcreteTestDaemon::getInstance();

        $this->assertSame(posix_getpid(), $daemon->myProcessPid);
    }

    // ─── Constructor tests ───────────────────────────────────────────────────────

    #[Test]
    public function constructor_sets_process_pid(): void
    {
        $ref = new ReflectionClass(ConcreteTestDaemon::class);
        /** @var ConcreteTestDaemon $daemon */
        $daemon = $ref->newInstanceWithoutConstructor();

        $this->invokeConstructor($daemon, '');

        $this->assertSame(posix_getpid(), $daemon->myProcessPid);
    }

    #[Test]
    public function constructor_sets_config_file_property(): void
    {
        $ref = new ReflectionClass(ConcreteTestDaemon::class);
        /** @var ConcreteTestDaemon $daemon */
        $daemon = $ref->newInstanceWithoutConstructor();

        $this->invokeConstructor($daemon, '/some/config.ini', null);

        $this->assertSame('/some/config.ini', $ref->getProperty('_configFile')->getValue($daemon));
    }

    #[Test]
    public function constructor_sets_context_index_property(): void
    {
        $ref = new ReflectionClass(ConcreteTestDaemon::class);
        /** @var ConcreteTestDaemon $daemon */
        $daemon = $ref->newInstanceWithoutConstructor();

        $this->invokeConstructor($daemon, '', 'some_context');

        $this->assertSame('some_context', $ref->getProperty('_contextIndex')->getValue($daemon));
    }

    #[Test]
    public function constructor_sets_null_context_index_when_omitted(): void
    {
        $ref = new ReflectionClass(ConcreteTestDaemon::class);
        /** @var ConcreteTestDaemon $daemon */
        $daemon = $ref->newInstanceWithoutConstructor();

        $this->invokeConstructor($daemon, '/config.ini');

        $this->assertNull($ref->getProperty('_contextIndex')->getValue($daemon));
    }

    #[Test]
    public function constructor_enables_print_errors(): void
    {
        $originalValue = AppConfig::$PRINT_ERRORS;

        $ref = new ReflectionClass(ConcreteTestDaemon::class);
        /** @var ConcreteTestDaemon $daemon */
        $daemon = $ref->newInstanceWithoutConstructor();

        AppConfig::$PRINT_ERRORS = false;
        $this->invokeConstructor($daemon, '');

        $this->assertTrue(AppConfig::$PRINT_ERRORS);

        AppConfig::$PRINT_ERRORS = $originalValue;
    }

    // ─── Default property values (via makeDaemon helper) ────────────────────────

    #[Test]
    public function running_defaults_to_true(): void
    {
        $daemon = $this->makeDaemon();
        $this->assertTrue($daemon->RUNNING);
    }

    #[Test]
    public function my_process_pid_is_set(): void
    {
        $daemon = $this->makeDaemon();
        $this->assertSame(posix_getpid(), $daemon->myProcessPid);
    }

    // ─── getConfiguration() ─────────────────────────────────────────────────────

    #[Test]
    public function getConfiguration_returns_configuration_instance(): void
    {
        $configFile = $this->writeTempConfig(['q1' => 2]);
        $daemon = $this->makeDaemon($configFile);

        $config = $daemon->getConfiguration();

        $this->assertInstanceOf(Configuration::class, $config);
    }

    #[Test]
    public function getConfiguration_returns_correct_context_list(): void
    {
        $configFile = $this->writeTempConfig(['q1' => 2, 'q2' => 3]);
        $daemon = $this->makeDaemon($configFile);

        $config = $daemon->getConfiguration();

        $this->assertInstanceOf(ContextList::class, $config->getContextList());
        $this->assertCount(2, $config->getContextList()->list);
    }

    #[Test]
    public function getConfiguration_throws_on_empty_config_file(): void
    {
        $daemon = $this->makeDaemon('');

        $this->expectException(\Throwable::class);
        $daemon->getConfiguration();
    }

    #[Test]
    public function getConfiguration_throws_on_invalid_config_path(): void
    {
        $daemon = $this->makeDaemon('/nonexistent/path/to/config.ini');

        $this->expectException(Exception::class);
        $daemon->getConfiguration();
    }

    #[Test]
    public function getConfiguration_populates_context_list_entries(): void
    {
        $configFile = $this->writeTempConfig(['myctx' => 4]);
        $daemon = $this->makeDaemon($configFile);

        $config = $daemon->getConfiguration();

        $this->assertArrayHasKey('myctx', $config->getContextList()->list);
        $this->assertSame(4, $config->getContextList()->list['myctx']->max_executors);
    }

    #[Test]
    public function getConfiguration_returns_logger_name(): void
    {
        $configFile = $this->writeTempConfig(['q1' => 1]);
        $daemon = $this->makeDaemon($configFile);

        $config = $daemon->getConfiguration();

        $this->assertSame('test.log', $config->getLoggerName());
    }

    // ─── _readChangedConfiguration() ────────────────────────────────────────────

    /**
     * A daemon on $configFile whose info and error lines go to $logged.
     *
     * @param list<array{0: string, 1: string, 2: array<mixed>}> $logged level, message, context
     */
    private function makeLoggingDaemon(string $configFile, array &$logged): ConcreteTestDaemon
    {
        $daemon = $this->makeDaemon($configFile);
        $logger = $this->createStub(MatecatLogger::class);
        foreach (['info', 'error'] as $level) {
            $logger->method($level)->willReturnCallback(function (mixed $message, array $context = []) use (&$logged, $level): void {
                $logged[] = [$level, (string)$message, $context];
            });
        }
        (new ReflectionClass(AbstractDaemon::class))->getProperty('logger')->setValue($daemon, $logger);

        return $daemon;
    }

    /**
     * The queue keys of the configuration the daemon read now, or null when it read nothing.
     *
     * Example: a file with q1 and q2, read now, gives ['q1', 'q2'].
     *
     * @return list<string>|null
     */
    private function readChanged(ConcreteTestDaemon $daemon): ?array
    {
        /** @var Configuration|null $configuration */
        $configuration = (new ReflectionMethod(AbstractDaemon::class, '_readChangedConfiguration'))->invoke($daemon);

        return $configuration === null ? null : array_keys($configuration->getContextList()->list);
    }

    #[Test]
    public function readChangedConfigurationReadsTheFileAtOnceTheFirstTime(): void
    {
        $logged = [];
        $daemon = $this->makeLoggingDaemon($this->writeTempConfig(['q1' => 1, 'q2' => 1]), $logged);

        $this->assertSame(['q1', 'q2'], $this->readChanged($daemon));
        $this->assertSame([], $logged, 'the first read is the start, not a reload');
    }

    /**
     * @return array<string, array{0: string}> the content of a config that cannot be used, '' for no file
     */
    public static function unusableConfigProvider(): array
    {
        return [
            'no file'                        => [''],
            'no context_definitions section' => ["loggerName = \"test.log\"\n"],
        ];
    }

    #[Test]
    #[DataProvider('unusableConfigProvider')]
    public function readChangedConfigurationThrowsTheFirstTimeWhenTheFileCannotBeUsed(string $content): void
    {
        $path = sys_get_temp_dir() . '/daemon_test_unusable_' . getmypid() . '.ini';
        $this->tempFiles[] = $path;
        if ($content !== '') {
            file_put_contents($path, $content);
        }
        $logged = [];
        $daemon = $this->makeLoggingDaemon($path, $logged);

        // A daemon with no queues cannot work: its constructor stops it
        $this->expectException(Exception::class);
        $this->readChanged($daemon);
    }

    #[Test]
    public function readChangedConfigurationReadsNothingWhileTheFileIsUnchanged(): void
    {
        $logged = [];
        $daemon = $this->makeLoggingDaemon($this->writeTempConfig(['q1' => 1]), $logged);
        $this->readChanged($daemon);

        $this->assertNull($this->readChanged($daemon));
        $this->assertNull($this->readChanged($daemon));
        $this->assertSame([], $logged);
    }

    #[Test]
    public function readChangedConfigurationTakesAChangeOnceTheFileHoldsStillForACycle(): void
    {
        $logged = [];
        $path = $this->writeTempConfig(['q1' => 1]);
        $daemon = $this->makeLoggingDaemon($path, $logged);
        $this->readChanged($daemon);

        $this->rewriteConfig($path, ['q1' => 1, 'q2' => 1]);
        $this->assertNull($this->readChanged($daemon), 'a file seen changed once may still be being written');
        $this->assertSame(['q1', 'q2'], $this->readChanged($daemon));
        $this->assertSame([['info', 'config_reloaded', ['file' => $path, 'queues' => ['q1', 'q2']]]], $logged);
    }

    #[Test]
    public function readChangedConfigurationWaitsWhileTheFileKeepsChanging(): void
    {
        $logged = [];
        $path = $this->writeTempConfig(['q1' => 1]);
        $daemon = $this->makeLoggingDaemon($path, $logged);
        $this->readChanged($daemon);

        // A slow save: the first check sees half of the file, the next one all of it
        $this->rewriteConfig($path, ['q1' => 1]);
        file_put_contents($path, "q2[queue_name] = \"q2\"\n", FILE_APPEND);
        $this->assertNull($this->readChanged($daemon));
        file_put_contents($path, "q2[max_executors] = 1\n", FILE_APPEND);
        $this->assertNull($this->readChanged($daemon));

        $this->assertSame(['q1', 'q2'], $this->readChanged($daemon));
    }

    #[Test]
    public function readChangedConfigurationForgetsAChangeThatWasUndone(): void
    {
        $logged = [];
        $path = $this->writeTempConfig(['q1' => 1]);
        $daemon = $this->makeLoggingDaemon($path, $logged);
        $this->readChanged($daemon);

        $this->rewriteConfig($path, ['q1' => 1, 'q2' => 1]);
        $this->assertNull($this->readChanged($daemon));
        $this->rewriteConfig($path, ['q1' => 1]);
        $this->assertNull($this->readChanged($daemon), 'back to the version in use: nothing to read');

        // The same change made again must hold still for a cycle once more
        $this->rewriteConfig($path, ['q1' => 1, 'q2' => 1]);
        $this->assertNull($this->readChanged($daemon));
        $this->assertSame(['q1', 'q2'], $this->readChanged($daemon));
    }

    #[Test]
    #[DataProvider('unusableConfigProvider')]
    public function readChangedConfigurationSkipsAVersionThatCannotBeUsedAndLogsItOnce(string $content): void
    {
        $logged = [];
        $path = $this->writeTempConfig(['q1' => 1]);
        $daemon = $this->makeLoggingDaemon($path, $logged);
        $this->readChanged($daemon);

        if ($content === '') {
            unlink($path);
        } else {
            file_put_contents($path, $content);
        }
        foreach (range(1, 4) as $cycle) {
            $this->assertNull($this->readChanged($daemon), "cycle $cycle: the daemon keeps the settings it has");
        }
        $this->assertSame(
            [['error', 'config_reload_failed', ['file' => $path, 'error' => 'Wrong configuration file provided.']]],
            $logged
        );

        // Fixed: read once it holds still
        $this->rewriteConfig($path, ['q1' => 2]);
        $this->assertNull($this->readChanged($daemon));
        $this->assertSame(['q1'], $this->readChanged($daemon));
    }

    #[Test]
    public function readChangedConfigurationSkipsAVersionWithNoLoggerName(): void
    {
        $logged = [];
        $path = $this->writeTempConfig(['q1' => 1]);
        $daemon = $this->makeLoggingDaemon($path, $logged);
        $this->readChanged($daemon);

        // Configuration reads loggerName into a string property: a missing one is a TypeError
        file_put_contents($path, "[context_definitions]\nq1[queue_name] = \"q1\"\nq1[max_executors] = 1\n");
        $this->assertNull(@$this->readChanged($daemon));
        $this->assertNull(@$this->readChanged($daemon));

        $this->assertSame(['error', 'config_reload_failed'], array_slice($logged[0], 0, 2));
        $this->assertCount(1, $logged);
    }

    /**
     * A check that refuses a configuration without the queue q1. Example: a file with only q2 is refused.
     */
    private static function requireQ1(): \Closure
    {
        return static function (Configuration $configuration): void {
            if (!isset($configuration->getContextList()->list['q1'])) {
                throw new UnexpectedValueException('No q1 queue');
            }
        };
    }

    #[Test]
    public function readChangedConfigurationThrowsTheFirstTimeWhenTheDaemonRefusesTheFile(): void
    {
        $logged = [];
        $daemon = $this->makeLoggingDaemon($this->writeTempConfig(['q2' => 1]), $logged);
        $daemon->validate = self::requireQ1();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('No q1 queue');
        $this->readChanged($daemon);
    }

    #[Test]
    public function readChangedConfigurationSkipsAVersionTheDaemonRefusesAndLogsItOnce(): void
    {
        $logged = [];
        $path = $this->writeTempConfig(['q1' => 1]);
        $daemon = $this->makeLoggingDaemon($path, $logged);
        $daemon->validate = self::requireQ1();
        $this->assertSame(['q1'], $this->readChanged($daemon));

        $this->rewriteConfig($path, ['q2' => 1]);
        foreach (range(1, 4) as $cycle) {
            $this->assertNull($this->readChanged($daemon), "cycle $cycle: the daemon keeps the settings it has");
        }
        $this->assertSame([['error', 'config_reload_failed', ['file' => $path, 'error' => 'No q1 queue']]], $logged);

        $this->rewriteConfig($path, ['q1' => 1, 'q2' => 1]);
        $this->assertNull($this->readChanged($daemon));
        $this->assertSame(['q1', 'q2'], $this->readChanged($daemon));
    }

    /**
     * Rewrites $path with the given queues and their max_executors. Example: rewriteConfig($p, ['q1' => 2]).
     *
     * @param array<string, int> $queues
     */
    private function rewriteConfig(string $path, array $queues): void
    {
        $content = "loggerName = \"test.log\"\n\n[context_definitions]\n";
        foreach ($queues as $name => $max) {
            $content .= "{$name}[queue_name] = \"{$name}\"\n{$name}[max_executors] = {$max}\n";
        }
        file_put_contents($path, $content);
    }
}
