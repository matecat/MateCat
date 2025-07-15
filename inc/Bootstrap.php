<?php

use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\Views\CustomPageView;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\PluginsLoader;
use Utils\ActiveMQ\WorkerClient;
use Utils\Logger\Log;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/05/15
 * Time: 10.54
 *
 */
class Bootstrap {

    private static    $_INI_VERSION;
    private static $CONFIG;
    private static $_ROOT;

    /**
     * @var FeatureSet
     */
    private FeatureSet $autoLoadedFeatureSet;

    public static function start( SplFileInfo $config_file = null, SplFileInfo $task_runner_config_file = null ) {
        new self( $config_file, $task_runner_config_file );
    }

    private function __construct( SplFileInfo $config_file = null, SplFileInfo $task_runner_config_file = null ) {

        self::$_ROOT = realpath( dirname( __FILE__ ) . '/../' );

        if ( $config_file != null ) {
            self::$CONFIG = parse_ini_file( $config_file->getRealPath(), true );
        } else {
            self::$CONFIG = parse_ini_file( self::$_ROOT . DIRECTORY_SEPARATOR . 'inc/config.ini', true );
        }

        register_shutdown_function( [ 'Bootstrap', 'shutdownFunctionHandler' ] );
        set_exception_handler( [ 'Bootstrap', 'exceptionHandler' ] );

        $mv                 = parse_ini_file( 'version.ini' );
        self::$_INI_VERSION = $mv[ 'version' ];

        $this->_setIncludePath();
        include_once self::$_ROOT . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        // Overridable defaults
        AppConfig::$ROOT                        = self::$_ROOT; // Accessible by Apache/PHP
        AppConfig::$BASEURL                     = "/"; // Accessible by the browser
        AppConfig::$DEFAULT_NUM_RESULTS_FROM_TM = 3;
        AppConfig::$TRACKING_CODES_VIEW_PATH    = AppConfig::$ROOT . "/lib/View/templates";

        //get the environment configuration
        self::initConfig();
        PluginsLoader::setIncludePath();

        if ( $task_runner_config_file != null ) {
            AppConfig::$TASK_RUNNER_CONFIG = parse_ini_file( $task_runner_config_file->getRealPath(), true );
        } else {
            AppConfig::$TASK_RUNNER_CONFIG = parse_ini_file( self::$_ROOT . DIRECTORY_SEPARATOR . 'inc/task_manager_config.ini', true );
        }

        ini_set( 'display_errors', false );

        if ( empty( AppConfig::$STORAGE_DIR ) ) {
            AppConfig::$STORAGE_DIR = AppConfig::$ROOT . "/local_storage";
        }

        if ( AppConfig::$PRINT_ERRORS || stripos( AppConfig::$ENV, 'develop' ) !== false ) {
            ini_set( 'error_log', AppConfig::$STORAGE_DIR . "/log_archive/php_errors.txt" );
            ini_set( 'error_reporting', E_ALL );
        }

        date_default_timezone_set( AppConfig::$TIME_ZONE );

        AppConfig::$LOG_REPOSITORY              = AppConfig::$STORAGE_DIR . "/log_archive";
        AppConfig::$UPLOAD_REPOSITORY           = AppConfig::$STORAGE_DIR . "/upload";
        AppConfig::$FILES_REPOSITORY            = AppConfig::$STORAGE_DIR . "/files_storage/files";
        AppConfig::$CACHE_REPOSITORY            = AppConfig::$STORAGE_DIR . "/files_storage/cache";
        AppConfig::$ZIP_REPOSITORY              = AppConfig::$STORAGE_DIR . "/files_storage/originalZip";
        AppConfig::$ANALYSIS_FILES_REPOSITORY   = AppConfig::$STORAGE_DIR . "/files_storage/fastAnalysis";
        AppConfig::$QUEUE_PROJECT_REPOSITORY    = AppConfig::$STORAGE_DIR . "/files_storage/queueProjects";
        AppConfig::$CONVERSIONERRORS_REPOSITORY = AppConfig::$STORAGE_DIR . "/conversion_errors";
        AppConfig::$TMP_DOWNLOAD                = AppConfig::$STORAGE_DIR . "/tmp_download";
        AppConfig::$TEMPLATE_ROOT               = AppConfig::$ROOT . "/lib/View";
        AppConfig::$UTILS_ROOT                  = AppConfig::$ROOT . '/lib/Utils';

        $OAUTH_CONFIG            = @parse_ini_file( self::$_ROOT . DIRECTORY_SEPARATOR . 'inc/oauth_config.ini', true );
        AppConfig::$OAUTH_CONFIG = $OAUTH_CONFIG;

        try {
            Log::$uniqID = ( isset( $_COOKIE[ AppConfig::$PHP_SESSION_NAME ] ) ? substr( $_COOKIE[ AppConfig::$PHP_SESSION_NAME ], 0, 13 ) : uniqid() );
            WorkerClient::init();
            Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE );
        } catch ( Exception $e ) {
            Log::doJsonLog( $e->getMessage() );
        }

        $directories = [
                AppConfig::$STORAGE_DIR,
                AppConfig::$LOG_REPOSITORY,
                AppConfig::$UPLOAD_REPOSITORY,
                AppConfig::$FILES_REPOSITORY,
                AppConfig::$CACHE_REPOSITORY,
                AppConfig::$ANALYSIS_FILES_REPOSITORY,
                AppConfig::$ZIP_REPOSITORY,
                AppConfig::$CONVERSIONERRORS_REPOSITORY,
                AppConfig::$TMP_DOWNLOAD,
                AppConfig::$QUEUE_PROJECT_REPOSITORY,
        ];

        foreach ( $directories as $directory ) {
            if ( !is_dir( $directory ) ) {
                mkdir( $directory, 0755, true );
            }
        }

        //auth sections
        AppConfig::$AUTHSECRET_PATH = AppConfig::$ROOT . '/inc/login_secret.dat';
        //if a secret is set in file
        if ( file_exists( AppConfig::$AUTHSECRET_PATH ) ) {
            //fetch it
            AppConfig::$AUTHSECRET = file_get_contents( AppConfig::$AUTHSECRET_PATH );
        } else {
            //try creating the file and the fetch it
            //generates pass
            $secret = Utils::randomString( 512, true );
            //put the file
            file_put_contents( AppConfig::$AUTHSECRET_PATH, $secret );
            //if put succeeds
            if ( file_exists( AppConfig::$AUTHSECRET_PATH ) ) {
                //restrict permissions
                chmod( AppConfig::$AUTHSECRET_PATH, 0400 );
            } else {
                //if we couldn't create due to permissions, use default secret
                AppConfig::$AUTHSECRET = 'ScavengerOfHumanSorrow';
            }
        }

        $this->initMandatoryPlugins();
        $this->notifyBootCompleted();

    }

    private function initMandatoryPlugins() {
        $this->autoLoadedFeatureSet = new FeatureSet();
    }

    private function notifyBootCompleted() {
        $this->autoLoadedFeatureSet->run( 'bootstrapCompleted' );
    }

    private static function exceptionHandler( Throwable $exception ) {

        Log::setLogFileName( 'fatal_errors.txt' );

        switch ( get_class( $exception ) ) {
            case AuthenticationError::class: // authentication requested
                $code = 401;
                Log::doJsonLog( [ "error" => 'Authentication error for URI: ' . $_SERVER[ 'REQUEST_URI' ] . " - " . "{$exception->getMessage()} ", "trace" => $exception->getTrace() ] );
                break;
            case InvalidArgumentException::class:
            case ValidationError:: class:
            case Model\Exceptions\ValidationError::class:
                $code = 400;
                Log::doJsonLog( [ "error" => 'Bad request error for URI: ' . $_SERVER[ 'REQUEST_URI' ] . " - " . "{$exception->getMessage()} ", "trace" => $exception->getTrace() ] );
                break;
            case Model\Exceptions\NotFoundException:: class:
            case Controller\API\Commons\Exceptions\NotFoundException::class:
                $code = 404;
                Log::doJsonLog( [ "error" => 'Record Not found error for URI: ' . $_SERVER[ 'REQUEST_URI' ] . " - " . "{$exception->getMessage()} ", "trace" => $exception->getTrace() ] );
                break;
            case Model\Exceptions\AuthorizationError::class:
            case Controller\API\Commons\Exceptions\AuthorizationError::class:
                $code = 403;
                Log::doJsonLog( [ "error" => 'Access not allowed error for URI: ' . $_SERVER[ 'REQUEST_URI' ] . " - " . "{$exception->getMessage()} ", "trace" => $exception->getTrace() ] );
                break;
            case PDOException::class:
                $code = 503;
                Log::doJsonLog( json_encode( ( new View\API\Commons\Error( $exception ) )->render( true ) ) );
                break;
            default:
                $code = 500;
                Log::doJsonLog( json_encode( ( new View\API\Commons\Error( $exception ) )->render( true ) ) );
                break;
        }

        self::formatOutputExceptions( $code, $exception );
        die(); // do not complete the response and set the header

    }

    private static function formatOutputExceptions( int $httpStatusCode, Throwable $exception ) {

        if ( stripos( PHP_SAPI, 'cli' ) === false ) {

            if ( AppConfig::$PRINT_ERRORS ) {
                $report = [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTraceAsString(),
                ];
            }

            $controllerInstance = new CustomPageView();
            try {
                $controllerInstance->setView( $httpStatusCode . '.html', $report ?? [], $httpStatusCode );
            } catch ( Exception $ignore ) {

            }

            $controllerInstance->render();

        } else {
            echo $exception->getMessage() . "\n";
            echo $exception->getTraceAsString() . "\n";
        }

    }

    private static function shutdownFunctionHandler() {

        $errorType = [
                E_CORE_ERROR        => 'E_CORE_ERROR',
                E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
                E_ERROR             => 'E_ERROR',
                E_USER_ERROR        => 'E_USER_ERROR',
                E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                E_DEPRECATED        => 'DEPRECATION_NOTICE', //From PHP 5.3
        ];

        # Getting the last error
        $error = error_get_last();

        # Checking if the last error is a fatal error
        if ( isset( $error[ 'type' ] ) )
            switch ( $error[ 'type' ] ) {
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_ERROR:
                case E_USER_ERROR:
                case E_RECOVERABLE_ERROR:

                    Log::setLogFileName( 'fatal_errors.txt' );
                    $exception = new Exception( $errorType[ $error[ 'type' ] ] . " " . $error[ 'message' ] );

                    try {
                        $reflector = new ReflectionProperty( $exception, 'trace' );
                        $reflector->setAccessible( true );
                        $error[ 'type' ] = $errorType[ $error[ 'type' ] ];
                        $reflector->setValue( $exception, [ $error ] );
                    } catch ( ReflectionException $e ) {

                    }

                    Log::doJsonLog( $exception->getTrace() );
                    self::formatOutputExceptions( 500, $exception );
                    die();

            }

    }

    public static function sessionClose() {
        @session_write_close();
    }

    /**
     * @throws Exception
     */
    public static function sessionStart() {
        $session_status = session_status();
        if ( $session_status == PHP_SESSION_NONE ) {
            session_start();
        } elseif ( $session_status == PHP_SESSION_DISABLED ) {
            throw new Exception( "MateCat needs to have sessions. Sessions must be enabled." );
        }
    }

    private static function _setIncludePath( $custom_paths = null ) {
        $def_path = [
                self::$_ROOT . "/lib"
        ];
        if ( !empty( $custom_paths ) ) {
            $def_path = array_merge( $def_path, $custom_paths );
        }

        set_include_path(
                implode( PATH_SEPARATOR, $def_path ) . PATH_SEPARATOR . get_include_path()
        );

    }

    /**
     * Returns an array of configuration params as parsed from the config.ini file.
     * The returned array only returns entries that match the current environment.
     *
     */
    private static function getEnvConfig() {

        if ( getenv( 'ENV' ) !== false ) {
            self::$CONFIG[ 'ENV' ] = getenv( 'ENV' );
        }

        $env = self::$CONFIG[ self::$CONFIG[ 'ENV' ] ];

        // check if outsource is disabled by the environment
        $enable_outsource = getenv( 'ENABLE_OUTSOURCE' );

        if ( $enable_outsource == "false" ) {
            $env[ "ENABLE_OUTSOURCE" ] = false;
            Log::doJsonLog( "DISABLED OUTSOURCE" );
        }

        return $env;
    }
    
    /**
     * TODO: move this to a private instance method on a singleton of this class.
     *
     * This function initializes the configuration performing all required checks to be sure
     * that configuration is safe.
     *
     * If any sanity check is to be done, this is the right place to do it.
     */
    private static function initConfig() {

        AppConfig::$ENV          = self::$CONFIG[ 'ENV' ];
        AppConfig::$BUILD_NUMBER = self::$_INI_VERSION;

        $env = self::getEnvConfig();

        foreach ( $env as $KEY => $value ) {
            if ( property_exists( AppConfig::class, $KEY ) ) {
                AppConfig::${$KEY} = $value;
            }
        }

        if ( stripos( PHP_SAPI, 'cli' ) === false ) {

            register_shutdown_function( [ Bootstrap::class, 'sessionClose' ] );

            // Get HTTPS server status
            // Override if the header is set from load balancer
            $localProto = 'http';
            foreach ( [ 'HTTPS', 'HTTP_X_FORWARDED_PROTO' ] as $_key ) {
                if ( isset( $_SERVER[ $_key ] ) ) {
                    $localProto = 'https';
                    break;
                }
            }

            AppConfig::$PROTOCOL = $localProto;
            ini_set( 'session.name', AppConfig::$PHP_SESSION_NAME );
            ini_set( 'session.cookie_domain', '.' . AppConfig::$COOKIE_DOMAIN );
            ini_set( 'session.cookie_secure', true );
            ini_set( 'session.cookie_httponly', true );

        }

        AppConfig::$HTTPHOST = AppConfig::$CLI_HTTP_HOST;

        AppConfig::init(); //load configurations

    }

    /**
     * Check if all mandatory keys are present
     *
     * @return bool true if all mandatory keys are present, false otherwise
     */
    public static function areMandatoryKeysPresent(): bool {
        $merged_config = array_merge( self::$CONFIG, self::$CONFIG[ AppConfig::$ENV ] );

        foreach ( AppConfig::$MANDATORY_KEYS as $key ) {
            if ( !array_key_exists( $key, $merged_config ) || $merged_config[ $key ] === null ) {
                return false;
            }
        }

        return true;
    }

    public static function isGDriveConfigured(): bool {
        if ( empty( AppConfig::$GOOGLE_OAUTH_CLIENT_ID ) || empty( AppConfig::$GOOGLE_OAUTH_BROWSER_API_KEY ) ) {
            return false;
        }

        return true;
    }

}

return true;
