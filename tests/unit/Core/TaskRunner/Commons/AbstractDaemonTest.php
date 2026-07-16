<?php

namespace Matecat\Core\TaskRunner\Commons;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
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
    public function main(array $args = null): void {}
    public function cleanShutDown(): void {}
    protected function _updateConfiguration(): void {}
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
        $content = "loggerName = \"test.log\"\n\n[context_definitions]\n";
        foreach ($queues as $name => $max) {
            $content .= "{$name}[queue_name] = \"{$name}\"\n";
            $content .= "{$name}[max_executors] = {$max}\n";
        }
        $path = tempnam(sys_get_temp_dir(), 'daemon_test_');
        file_put_contents($path, $content);
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
}
