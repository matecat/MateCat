<?php

declare(strict_types=1);

use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\ConflictError;
use Controller\API\Commons\Exceptions\ValidationError;
use Exceptions\BootstrapTerminatedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class BootstrapTest extends AbstractTest
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/bootstrap_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    private function createBootstrapInstance(): Bootstrap
    {
        $ref = new ReflectionClass(Bootstrap::class);
        return $ref->newInstanceWithoutConstructor();
    }

    private function invokePrivateMethod(object $obj, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($obj, $method);
        return $ref->invoke($obj, ...$args);
    }

    private function setStaticProperty(string $property, mixed $value): void
    {
        $ref = new ReflectionProperty(Bootstrap::class, $property);
        $ref->setValue(null, $value);
    }

    private function getStaticProperty(string $property): mixed
    {
        $ref = new ReflectionProperty(Bootstrap::class, $property);
        return $ref->getValue(null);
    }

    // --- loadConfigurationFiles tests ---

    #[Test]
    public function loadConfigurationFiles_throws_when_config_file_missing(): void
    {
        $instance = $this->createBootstrapInstance();
        $this->setStaticProperty('_ROOT', $this->tmpDir);

        $missingFile = new SplFileInfo($this->tmpDir . '/nonexistent.ini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configuration file not found');
        $this->invokePrivateMethod($instance, 'loadConfigurationFiles', [$missingFile, null]);
    }

    #[Test]
    public function loadConfigurationFiles_throws_when_task_runner_config_missing(): void
    {
        $instance = $this->createBootstrapInstance();
        $this->setStaticProperty('_ROOT', $this->tmpDir);

        $configFile = $this->tmpDir . '/config.ini';
        file_put_contents($configFile, "[production]\nDB_SERVER=localhost\n");

        $missingTaskRunner = new SplFileInfo($this->tmpDir . '/nonexistent_task.ini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Task Manager Configuration file not found');
        $this->invokePrivateMethod($instance, 'loadConfigurationFiles', [
            new SplFileInfo($configFile),
            $missingTaskRunner,
        ]);
    }

    #[Test]
    public function loadConfigurationFiles_throws_when_version_file_missing(): void
    {
        $instance = $this->createBootstrapInstance();
        $this->setStaticProperty('_ROOT', $this->tmpDir);

        file_put_contents($this->tmpDir . '/config.ini', "[production]\nDB_SERVER=localhost\n");
        file_put_contents($this->tmpDir . '/task_manager_config.ini', "[queue]\nworkers=2\n");

        // version.ini missing — no inc/ subdir
        mkdir($this->tmpDir . '/inc', 0755, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MateCat version file not found');
        $this->invokePrivateMethod($instance, 'loadConfigurationFiles', [
            new SplFileInfo($this->tmpDir . '/config.ini'),
            new SplFileInfo($this->tmpDir . '/task_manager_config.ini'),
        ]);
    }

    #[Test]
    public function loadConfigurationFiles_parses_valid_files(): void
    {
        $instance = $this->createBootstrapInstance();
        $incDir = $this->tmpDir . '/inc';
        mkdir($incDir, 0755, true);
        $this->setStaticProperty('_ROOT', $this->tmpDir);

        file_put_contents($this->tmpDir . '/config.ini', "[production]\nDB_SERVER=localhost\n");
        file_put_contents($this->tmpDir . '/task_manager_config.ini', "[queue]\nworkers=2\n");
        file_put_contents($incDir . '/version.ini', "version=2.5.0\n");

        $this->invokePrivateMethod($instance, 'loadConfigurationFiles', [
            new SplFileInfo($this->tmpDir . '/config.ini'),
            new SplFileInfo($this->tmpDir . '/task_manager_config.ini'),
        ]);

        $config = $this->getStaticProperty('CONFIG');
        $this->assertArrayHasKey('production', $config);
        $this->assertSame('localhost', $config['production']['DB_SERVER']);

        $taskConfig = $this->getStaticProperty('TASK_RUNNER_CONFIG');
        $this->assertArrayHasKey('queue', $taskConfig);

        $version = $this->getStaticProperty('_INI_VERSION');
        $this->assertSame('2.5.0', $version);
    }

    // --- getConfigurationForEnvironment tests ---

    #[Test]
    public function getConfigurationForEnvironment_returns_env_section(): void
    {
        $instance = $this->createBootstrapInstance();
        $this->setStaticProperty('CONFIG', [
            'ENV' => 'production',
            'production' => ['DB_SERVER' => 'prod-db', 'ENABLE_OUTSOURCE' => true],
        ]);

        $oldEnv = getenv('ENV');
        putenv('ENV');
        putenv('ENABLE_OUTSOURCE');

        $result = $this->invokePrivateMethod($instance, 'getConfigurationForEnvironment');

        $this->assertSame('prod-db', $result['DB_SERVER']);

        if ($oldEnv !== false) {
            putenv("ENV=$oldEnv");
        }
    }

    #[Test]
    public function getConfigurationForEnvironment_respects_env_override(): void
    {
        $instance = $this->createBootstrapInstance();
        $this->setStaticProperty('CONFIG', [
            'ENV' => 'production',
            'production' => ['DB_SERVER' => 'prod-db'],
            'testing' => ['DB_SERVER' => 'test-db'],
        ]);

        $oldEnv = getenv('ENV');
        putenv('ENV=testing');

        $result = $this->invokePrivateMethod($instance, 'getConfigurationForEnvironment');
        $this->assertSame('test-db', $result['DB_SERVER']);

        if ($oldEnv !== false) {
            putenv("ENV=$oldEnv");
        } else {
            putenv('ENV');
        }
    }

    #[Test]
    public function getConfigurationForEnvironment_disables_outsource_from_env(): void
    {
        $instance = $this->createBootstrapInstance();
        $this->setStaticProperty('CONFIG', [
            'ENV' => 'production',
            'production' => ['ENABLE_OUTSOURCE' => true],
        ]);

        $oldEnv = getenv('ENV');
        $oldOutsource = getenv('ENABLE_OUTSOURCE');
        putenv('ENV');
        putenv('ENABLE_OUTSOURCE=false');

        $result = $this->invokePrivateMethod($instance, 'getConfigurationForEnvironment');
        $this->assertFalse($result['ENABLE_OUTSOURCE']);

        if ($oldEnv !== false) {
            putenv("ENV=$oldEnv");
        }
        if ($oldOutsource !== false) {
            putenv("ENABLE_OUTSOURCE=$oldOutsource");
        } else {
            putenv('ENABLE_OUTSOURCE');
        }
    }

    // --- createSystemDirectories tests ---

    #[Test]
    public function createSystemDirectories_creates_missing_dirs(): void
    {
        $instance = $this->createBootstrapInstance();
        $subDir = $this->tmpDir . '/storage';

        $oldStorage = AppConfig::$STORAGE_DIR;
        $oldLog = AppConfig::$LOG_REPOSITORY;

        AppConfig::$STORAGE_DIR = $subDir;
        AppConfig::$LOG_REPOSITORY = $subDir . '/logs';

        $this->invokePrivateMethod($instance, 'createSystemDirectories');

        $this->assertDirectoryExists($subDir);
        $this->assertDirectoryExists($subDir . '/logs');

        // cleanup
        rmdir($subDir . '/logs');
        rmdir($subDir);
        AppConfig::$STORAGE_DIR = $oldStorage;
        AppConfig::$LOG_REPOSITORY = $oldLog;
    }

    #[Test]
    public function createSystemDirectories_skips_null_dirs(): void
    {
        $instance = $this->createBootstrapInstance();

        $oldStorage = AppConfig::$STORAGE_DIR;
        $oldLog = AppConfig::$LOG_REPOSITORY;

        AppConfig::$STORAGE_DIR = null;
        AppConfig::$LOG_REPOSITORY = null;

        // should not throw
        $this->invokePrivateMethod($instance, 'createSystemDirectories');
        $this->assertTrue(true);

        AppConfig::$STORAGE_DIR = $oldStorage;
        AppConfig::$LOG_REPOSITORY = $oldLog;
    }

    // --- setErrorReporting tests ---

    #[Test]
    public function setErrorReporting_sets_log_when_print_errors_enabled(): void
    {
        $instance = $this->createBootstrapInstance();

        $oldPrint = AppConfig::$PRINT_ERRORS;
        $oldStorage = AppConfig::$STORAGE_DIR;
        AppConfig::$PRINT_ERRORS = true;
        AppConfig::$STORAGE_DIR = '/tmp/matecat_test';

        $this->invokePrivateMethod($instance, 'setErrorReporting');

        $this->assertSame('/tmp/matecat_test/log_archive/php_errors.txt', ini_get('error_log'));

        AppConfig::$PRINT_ERRORS = $oldPrint;
        AppConfig::$STORAGE_DIR = $oldStorage;
    }

    #[Test]
    public function setErrorReporting_sets_log_when_env_is_develop(): void
    {
        $instance = $this->createBootstrapInstance();

        $oldPrint = AppConfig::$PRINT_ERRORS;
        $oldEnv = AppConfig::$ENV;
        $oldStorage = AppConfig::$STORAGE_DIR;
        AppConfig::$PRINT_ERRORS = false;
        AppConfig::$ENV = 'development';
        AppConfig::$STORAGE_DIR = '/tmp/matecat_test';

        $this->invokePrivateMethod($instance, 'setErrorReporting');

        $this->assertSame('/tmp/matecat_test/log_archive/php_errors.txt', ini_get('error_log'));

        AppConfig::$PRINT_ERRORS = $oldPrint;
        AppConfig::$ENV = $oldEnv;
        AppConfig::$STORAGE_DIR = $oldStorage;
    }

    #[Test]
    public function setErrorReporting_skips_when_env_null_and_no_print(): void
    {
        $instance = $this->createBootstrapInstance();

        $oldPrint = AppConfig::$PRINT_ERRORS;
        $oldEnv = AppConfig::$ENV;
        $oldLog = ini_get('error_log');
        AppConfig::$PRINT_ERRORS = false;
        AppConfig::$ENV = null;

        ini_set('error_log', '/tmp/original_log.txt');
        $this->invokePrivateMethod($instance, 'setErrorReporting');

        $this->assertSame('/tmp/original_log.txt', ini_get('error_log'));

        AppConfig::$PRINT_ERRORS = $oldPrint;
        AppConfig::$ENV = $oldEnv;
        ini_set('error_log', $oldLog ?: '');
    }

    // --- unsetVariables tests ---

    #[Test]
    public function unsetVariables_clears_config(): void
    {
        $instance = $this->createBootstrapInstance();
        $this->setStaticProperty('CONFIG', ['key' => 'value']);
        $this->setStaticProperty('TASK_RUNNER_CONFIG', ['key' => 'value']);

        $this->invokePrivateMethod($instance, 'unsetVariables');

        $this->assertSame([], $this->getStaticProperty('CONFIG'));
        $this->assertSame([], $this->getStaticProperty('TASK_RUNNER_CONFIG'));
    }

    // --- sessionClose tests ---

    #[Test]
    public function sessionClose_does_not_throw(): void
    {
        Bootstrap::sessionClose();
        $this->assertTrue(true);
    }

    // --- exceptionHandler tests ---

    /**
     * @return array<string, array{Throwable, int}>
     */
    public static function exceptionToStatusCodeProvider(): array
    {
        return [
            'AuthenticationError → 401' => [new AuthenticationError('auth failed'), 401],
            'InvalidArgumentException → 400' => [new \InvalidArgumentException('bad arg'), 400],
            'ValidationError (API) → 400' => [new ValidationError('invalid'), 400],
            'DomainException → 400' => [new \DomainException('domain'), 400],
            'UnexpectedValueException → 400' => [new \UnexpectedValueException('unexpected'), 400],
            'ValidationError (Model) → 400' => [new \Model\Exceptions\ValidationError('model invalid'), 400],
            'NotFoundException (Model) → 404' => [new \Model\Exceptions\NotFoundException('not found'), 404],
            'NotFoundException (API) → 404' => [new \Controller\API\Commons\Exceptions\NotFoundException('not found'), 404],
            'AuthorizationError (Model) → 403' => [new \Model\Exceptions\AuthorizationError('forbidden'), 403],
            'AuthorizationError (API) → 403' => [new \Controller\API\Commons\Exceptions\AuthorizationError('forbidden'), 403],
            'ConflictError → 409' => [new ConflictError('conflict'), 409],
            'PDOException → 503' => [new \PDOException('db down'), 503],
            'RuntimeException (default) → 500' => [new \RuntimeException('unknown'), 500],
            'Exception (default) → 500' => [new \Exception('generic'), 500],
        ];
    }

    #[Test]
    #[DataProvider('exceptionToStatusCodeProvider')]
    public function exceptionHandler_maps_exception_to_correct_status_code(Throwable $exception, int $expectedCode): void
    {
        $oldEnv = AppConfig::$ENV;
        AppConfig::$ENV = 'testing';

        $_SERVER['REQUEST_URI'] = '/test/endpoint';

        ob_start();
        try {
            Bootstrap::exceptionHandler($exception);
            $this->fail('Expected BootstrapTerminatedException');
        } catch (BootstrapTerminatedException $e) {
            $this->assertSame($expectedCode, $e->httpStatusCode);
        } finally {
            ob_end_clean();
            AppConfig::$ENV = $oldEnv;
        }
    }

    // --- handleFatalError tests ---

    #[Test]
    public function handleFatalError_does_nothing_on_null(): void
    {
        Bootstrap::handleFatalError(null);
        $this->assertTrue(true);
    }

    #[Test]
    public function handleFatalError_does_nothing_on_non_fatal_error(): void
    {
        Bootstrap::handleFatalError([
            'type' => E_WARNING,
            'message' => 'some warning',
            'file' => '/tmp/test.php',
            'line' => 1,
        ]);
        $this->assertTrue(true);
    }

    /**
     * @return array<string, array{array{type: int, message: string, file: string, line: int}}>
     */
    public static function fatalErrorProvider(): array
    {
        return [
            'E_CORE_ERROR' => [['type' => E_CORE_ERROR, 'message' => 'core error', 'file' => '/tmp/test.php', 'line' => 1]],
            'E_COMPILE_ERROR' => [['type' => E_COMPILE_ERROR, 'message' => 'compile error', 'file' => '/tmp/test.php', 'line' => 2]],
            'E_ERROR' => [['type' => E_ERROR, 'message' => 'fatal error', 'file' => '/tmp/test.php', 'line' => 3]],
            'E_USER_ERROR' => [['type' => E_USER_ERROR, 'message' => 'user error', 'file' => '/tmp/test.php', 'line' => 4]],
            'E_RECOVERABLE_ERROR' => [['type' => E_RECOVERABLE_ERROR, 'message' => 'recoverable error', 'file' => '/tmp/test.php', 'line' => 5]],
        ];
    }

    #[Test]
    #[DataProvider('fatalErrorProvider')]
    public function handleFatalError_throws_on_fatal_error_types(array $error): void
    {
        $oldEnv = AppConfig::$ENV;
        AppConfig::$ENV = 'testing';

        ob_start();
        try {
            Bootstrap::handleFatalError($error);
            $this->fail('Expected BootstrapTerminatedException');
        } catch (BootstrapTerminatedException $e) {
            $this->assertSame(500, $e->httpStatusCode);
        } finally {
            ob_end_clean();
            AppConfig::$ENV = $oldEnv;
        }
    }

    #[Test]
    public function handleFatalError_does_nothing_on_deprecated(): void
    {
        Bootstrap::handleFatalError([
            'type' => E_DEPRECATED,
            'message' => 'deprecated thing',
            'file' => '/tmp/test.php',
            'line' => 1,
        ]);
        $this->assertTrue(true);
    }
}
