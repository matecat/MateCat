<?php

use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\Views\CustomPageView;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\PluginsLoader;
use Utils\ActiveMQ\WorkerClient;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/05/15
 * Time: 10.54
 *
 */
class Bootstrap
{

    private static string $_INI_VERSION;
    private static array $CONFIG = [];
    private static array $TASK_RUNNER_CONFIG = [];
    private static string $_ROOT;

    /**
     * @var FeatureSet
     */
    private FeatureSet $autoLoadedFeatureSet;

    /**
     * @throws Exception
     */
    public static function start(SplFileInfo $config_file = null, SplFileInfo $task_runner_config_file = null): void
    {
        new self($config_file, $task_runner_config_file);
    }

    /**
     * @throws Exception
     */
    private function __construct(SplFileInfo $config_file = null, SplFileInfo $task_runner_config_file = null)
    {
        ini_set('display_errors', false);
        self::$_ROOT = realpath(dirname(__FILE__) . '/../');
        include_once self::$_ROOT . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        //get the environment configuration
        $this->loadConfigurationFiles($config_file, $task_runner_config_file);
        $this->initRegistryClass();
        $this->createSystemDirectories();
        $this->setLoggers();

        set_exception_handler([Bootstrap::class, 'exceptionHandler']);
        register_shutdown_function([Bootstrap::class, 'shutdownFunctionHandler']);

        $this->setErrorReporting();

        $this->configureSessionCookies();

        PluginsLoader::setIncludePath();

        date_default_timezone_set(AppConfig::$TIME_ZONE);

        $this->installApplicationSingletons();


        $this->initMandatoryPlugins();
        $this->notifyBootCompleted();
        $this->unsetVariables();
    }

    /**
     * @throws Exception
     */
    private function setLoggers(): void
    {
        LoggerFactory::$uniqID = (isset($_COOKIE[AppConfig::$PHP_SESSION_NAME]) ? substr($_COOKIE[AppConfig::$PHP_SESSION_NAME], 0, 13) : uniqid());
        LoggerFactory::getLogger('exception_handler', 'fatal_errors.txt');
        LoggerFactory::getLogger('dao', 'dao.log');
        LoggerFactory::getLogger('query_cache', 'query_cache.log');
        LoggerFactory::getLogger("conversion", "conversion.log");
        LoggerFactory::getLogger("downloads", "downloads.log");
        LoggerFactory::getLogger('login_exceptions', 'login_exceptions.log');
        LoggerFactory::getLogger('login_cookie_cache', 'login_cookie_cache.log');
        LoggerFactory::getLogger('tag_projection', 'tag_projection.log');
        LoggerFactory::getLogger('outsource', 'outsource.log');
        LoggerFactory::getLogger('decorators', 'decorators.log');
        LoggerFactory::getLogger('feature_set', 'feature_set.log');
        LoggerFactory::getLogger('project_manager', 'project_manager.log');
        LoggerFactory::getLogger("upload_handler", "upload.log");
        LoggerFactory::getLogger("tos_external_call", "tos.log");
        LoggerFactory::getLogger('engines', 'app_engines_call.log'); //default handler for engines called by web node
        LoggerFactory::getLogger('files', 'files_storage.log');
    }

    /**
     * @throws Exception
     */
    private function installApplicationSingletons(): void
    {
        WorkerClient::init();
        Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
    }

    /**
     * Loads configuration files for the application.
     *
     * This method is responsible for loading the main configuration file and the
     * task runner configuration file.
     * If the files aren't provided as arguments,
     * it uses default paths.
     * It also loads the app version from a separate
     * `version.ini` file.
     *
     * @param SplFileInfo|null $config_file The main configuration file. If null, a default path is used.
     * @param SplFileInfo|null $task_runner_config_file The task runner configuration file. If null, a default path is used.
     *
     * @throws RuntimeException If any of the required configuration files are not found.
     */
    private function loadConfigurationFiles(SplFileInfo $config_file = null, SplFileInfo $task_runner_config_file = null): void
    {
        // Use the provided config file or default to 'inc/config.ini'
        $config_file = $config_file ?? new SplFileInfo(self::$_ROOT . DIRECTORY_SEPARATOR . 'inc/config.ini');

        // Check if the main configuration file exists and parse it
        if ($config_file->isFile()) {
            self::$CONFIG = parse_ini_file($config_file->getRealPath(), true);
        } else {
            throw new RuntimeException("Configuration file not found: " . $config_file->getPathname());
        }

        // Use the provided task runner config file or default to 'inc/task_manager_config.ini'
        $task_runner_config_file = $task_runner_config_file ?? new SplFileInfo(self::$_ROOT . DIRECTORY_SEPARATOR . 'inc/task_manager_config.ini');

        // Check if the task runner configuration file exists and parse it
        if ($task_runner_config_file->isFile()) {
            self::$TASK_RUNNER_CONFIG = parse_ini_file($task_runner_config_file->getRealPath(), true);
        } else {
            throw new RuntimeException("Task Manager Configuration file not found: " . $task_runner_config_file->getPathname());
        }

        // Load the app version from 'version.ini'
        $matecatVersionFile = new SplFileInfo(self::$_ROOT . DIRECTORY_SEPARATOR . 'inc/version.ini');
        if ($matecatVersionFile->isFile()) {
            $mv = parse_ini_file($matecatVersionFile->getRealPath());
        } else {
            throw new RuntimeException("MateCat version file not found: " . $matecatVersionFile->getPathname());
        }
        self::$_INI_VERSION = $mv['version'];
    }

    private function initMandatoryPlugins(): void
    {
        $this->autoLoadedFeatureSet = new FeatureSet();
    }

    private function notifyBootCompleted(): void
    {
        $this->autoLoadedFeatureSet->run('bootstrapCompleted');
    }

    /**
     * @throws Exception
     */
    public static function exceptionHandler(Throwable $exception): never
    {
        $logger = LoggerFactory::getLogger('exception_handler');

        switch (get_class($exception)) {
            case AuthenticationError::class: // authentication requested
                $code = 401;
                $logger->debug(["error" => 'Authentication error for URI: ' . $_SERVER['REQUEST_URI'] . " - " . "{$exception->getMessage()} ", "trace" => $exception->getTrace()]);
                break;
            case InvalidArgumentException::class:
            case ValidationError:: class:
            case DomainException::class:
            case UnexpectedValueException::class:
            case Model\Exceptions\ValidationError::class:
                $code = 400;
                $logger->debug(["error" => 'Bad request error for URI: ' . $_SERVER['REQUEST_URI'] . " - " . "{$exception->getMessage()} ", "trace" => $exception->getTrace()]);
                break;
            case Model\Exceptions\NotFoundException:: class:
            case Controller\API\Commons\Exceptions\NotFoundException::class:
                $code = 404;
                $logger->debug(["error" => 'Record Not found error for URI: ' . $_SERVER['REQUEST_URI'] . " - " . "{$exception->getMessage()} ", "trace" => $exception->getTrace()]);
                break;
            case Model\Exceptions\AuthorizationError::class:
            case Controller\API\Commons\Exceptions\AuthorizationError::class:
                $code = 403;
                $logger->debug(["error" => 'Access not allowed error for URI: ' . $_SERVER['REQUEST_URI'] . " - " . "{$exception->getMessage()} ", "trace" => $exception->getTrace()]);
                break;
            case PDOException::class:
                $code = 503;
                $logger->debug((new View\API\Commons\Error($exception))->render(true));
                break;
            default:
                $code = 500;
                $logger->debug((new View\API\Commons\Error($exception))->render(true));
                break;
        }

        self::formatOutputExceptions($code, $exception);
        die(); // do not complete the response and set the header

    }

    private static function formatOutputExceptions(int $httpStatusCode, Throwable $exception): void
    {
        if (stripos(PHP_SAPI, 'cli') === false) {
            if (AppConfig::$PRINT_ERRORS) {
                $report = [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            $controllerInstance = new CustomPageView();
            try {
                $controllerInstance->setView($httpStatusCode . '.html', $report ?? [], $httpStatusCode);
            } catch (Exception) {
            }

            $controllerInstance->render();
        } else {
            echo $exception->getMessage() . "\n";
            echo $exception->getTraceAsString() . "\n";
        }
    }

    /**
     * @throws Exception
     */
    public static function shutdownFunctionHandler(): never
    {
        $logger = LoggerFactory::getLogger('exception_handler');
        $logger->debug("ShutDown");

        $errorType = [
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_ERROR => 'E_ERROR',
            E_USER_ERROR => 'E_USER_ERROR',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATION_NOTICE', //From PHP 5.3
        ];

        # Getting the last error
        $error = error_get_last();

        # Checking if the last error is a fatal error
        if (isset($error['type'])) {
            switch ($error['type']) {
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_ERROR:
                case E_USER_ERROR:
                case E_RECOVERABLE_ERROR:

                    $logger = LoggerFactory::getLogger('exception_handler');

                    $exception = new Exception($errorType[$error['type']] . " " . $error['message']);

                    try {
                        $reflector = new ReflectionProperty($exception, 'trace');
                        $error['type'] = $errorType[$error['type']];
                        $reflector->setValue($exception, [$error]);
                    } catch (ReflectionException) {
                    }

                    $logger->debug($exception->getTrace());
                    self::formatOutputExceptions(500, $exception);
                    break;
            }
        }

        die();
    }

    public static function sessionClose(): void
    {
        @session_write_close();
    }

    /**
     * Returns an array of configuration params as parsed from the config.ini file.
     * The returned array only returns entries that match the current environment.
     *
     */
    private function getConfigurationForEnvironment()
    {
        if (getenv('ENV') !== false) {
            self::$CONFIG['ENV'] = getenv('ENV');
        }

        $configuration = self::$CONFIG[self::$CONFIG['ENV']];

        // check if outsource is disabled by the environment
        $enable_outsource = getenv('ENABLE_OUTSOURCE');

        if ($enable_outsource == "false") {
            $configuration["ENABLE_OUTSOURCE"] = false;
        }

        return $configuration;
    }

    /**
     *
     * This function initializes the configuration performing all required checks to be sure
     * that configuration is safe.
     *
     */
    private function initRegistryClass(): void
    {
        AppConfig::init(
            self::$_ROOT,
            self::$CONFIG['ENV'],
            self::$_INI_VERSION,
            // Retrieves environment-specific settings (e.g., DB credentials) and handles overrides (e.g., enabling/disabling outsourcing)
            $this->getConfigurationForEnvironment(),
            self::$TASK_RUNNER_CONFIG,
        ); // Initializes the application configuration singleton and sets up static properties (paths, OAuth, storage)
    }

    private function createSystemDirectories(): void
    {
        $directories = [
            AppConfig::$STORAGE_DIR,
            AppConfig::$LOG_REPOSITORY,
            AppConfig::$UPLOAD_REPOSITORY,
            AppConfig::$FILES_REPOSITORY,
            AppConfig::$CACHE_REPOSITORY,
            AppConfig::$ANALYSIS_FILES_REPOSITORY,
            AppConfig::$ZIP_REPOSITORY,
            AppConfig::$CONVERSION_ERRORS_REPOSITORY,
            AppConfig::$TMP_DOWNLOAD,
            AppConfig::$QUEUE_PROJECT_REPOSITORY,
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }

    private function setErrorReporting(): void
    {
        if (AppConfig::$PRINT_ERRORS || stripos(AppConfig::$ENV, 'develop') !== false) {
            ini_set('error_log', AppConfig::$STORAGE_DIR . "/log_archive/php_errors.txt");
            ini_set('error_reporting', E_ALL);
        }
    }

    private function configureSessionCookies(): void
    {
        if (stripos(PHP_SAPI, 'cli') === false) {
            register_shutdown_function([Bootstrap::class, 'sessionClose']);

            ini_set('session.name', AppConfig::$PHP_SESSION_NAME);
            ini_set('session.cookie_domain', '.' . AppConfig::$COOKIE_DOMAIN);
            ini_set('session.cookie_secure', true);
            ini_set('session.cookie_httponly', true);
        }
    }

    private function unsetVariables(): void
    {
        self::$CONFIG = [];
        self::$TASK_RUNNER_CONFIG = [];
    }

}

return true;
