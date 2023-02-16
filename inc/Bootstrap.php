<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/05/15
 * Time: 10.54
 *
 */
class Bootstrap {

    public static    $_INI_VERSION;
    protected static $CONFIG;
    protected static $_ROOT;

    /**
     * @var FeatureSet
     */
    private $autoLoadedFeatureSet;

    public static function start() {
        new self();
    }

    private function __construct() {

        self::$_ROOT  = realpath( dirname( __FILE__ ) . '/../' );
        self::$CONFIG = parse_ini_file( self::$_ROOT . DIRECTORY_SEPARATOR . 'inc/config.ini', true );
        $OAUTH_CONFIG = @parse_ini_file( self::$_ROOT . DIRECTORY_SEPARATOR . 'inc/oauth_config.ini', true );

        register_shutdown_function( [ 'Bootstrap', 'shutdownFunctionHandler' ] );
        set_exception_handler( [ 'Bootstrap', 'exceptionHandler' ] );

        $mv                 = parse_ini_file( 'version.ini' );
        self::$_INI_VERSION = $mv[ 'version' ];

        $this->_setIncludePath();
        spl_autoload_register( [ 'Bootstrap', 'loadClass' ] );
        require_once 'Predis/autoload.php';
        @include_once 'vendor/autoload.php';

        INIT::$OAUTH_CONFIG = $OAUTH_CONFIG[ 'OAUTH_CONFIG' ];

        // Overridable defaults
        INIT::$ROOT                           = self::$_ROOT; // Accessible by Apache/PHP
        INIT::$BASEURL                        = "/"; // Accessible by the browser
        INIT::$TIME_TO_EDIT_ENABLED           = false;
        INIT::$DEFAULT_NUM_RESULTS_FROM_TM    = 3;
        INIT::$THRESHOLD_MATCH_TM_NOT_TO_SHOW = 50;
        INIT::$TRACKING_CODES_VIEW_PATH       = INIT::$ROOT . "/lib/View";


        //get the environment configuration
        self::initConfig();

        ini_set( 'display_errors', false );
        if ( INIT::$PRINT_ERRORS ) {
            ini_set( 'display_errors', true );
        }

        if ( empty( INIT::$STORAGE_DIR ) ) {
            INIT::$STORAGE_DIR = INIT::$ROOT . "/local_storage";
        }

        date_default_timezone_set( INIT::$TIME_ZONE );

        INIT::$LOG_REPOSITORY                  = INIT::$STORAGE_DIR . "/log_archive";
        INIT::$UPLOAD_REPOSITORY               = INIT::$STORAGE_DIR . "/upload";
        INIT::$FILES_REPOSITORY                = INIT::$STORAGE_DIR . "/files_storage/files";
        INIT::$CACHE_REPOSITORY                = INIT::$STORAGE_DIR . "/files_storage/cache";
        INIT::$ZIP_REPOSITORY                  = INIT::$STORAGE_DIR . "/files_storage/originalZip";
        INIT::$BLACKLIST_REPOSITORY            = INIT::$STORAGE_DIR . "/files_storage/blacklist";
        INIT::$ANALYSIS_FILES_REPOSITORY       = INIT::$STORAGE_DIR . "/files_storage/fastAnalysis";
        INIT::$QUEUE_PROJECT_REPOSITORY        = INIT::$STORAGE_DIR . "/files_storage/queueProjects";
        INIT::$CONVERSIONERRORS_REPOSITORY     = INIT::$STORAGE_DIR . "/conversion_errors";
        INIT::$CONVERSIONERRORS_REPOSITORY_WEB = INIT::$BASEURL . "storage/conversion_errors";
        INIT::$TMP_DOWNLOAD                    = INIT::$STORAGE_DIR . "/tmp_download";
        INIT::$REFERENCE_REPOSITORY            = INIT::$STORAGE_DIR . "/reference_files";
        INIT::$TEMPLATE_ROOT                   = INIT::$ROOT . "/lib/View";
        INIT::$MODEL_ROOT                      = INIT::$ROOT . '/lib/Model';
        INIT::$CONTROLLER_ROOT                 = INIT::$ROOT . '/lib/Controller';
        INIT::$UTILS_ROOT                      = INIT::$ROOT . '/lib/Utils';

        INIT::$TASK_RUNNER_CONFIG = parse_ini_file( self::$_ROOT . DIRECTORY_SEPARATOR . 'inc/task_manager_config.ini', true );

        try {
            Log::$uniqID = ( isset( $_COOKIE[ INIT::$PHP_SESSION_NAME ] ) ? substr( $_COOKIE[ INIT::$PHP_SESSION_NAME ], 0, 13 ) : uniqid() );
            WorkerClient::init();
            Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        } catch ( \Exception $e ) {
            Log::doJsonLog( $e->getMessage() );
        }

        if ( !is_dir( INIT::$STORAGE_DIR ) ) {
            mkdir( INIT::$STORAGE_DIR, 0755, true );
        }
        if ( !is_dir( INIT::$LOG_REPOSITORY ) ) {
            mkdir( INIT::$LOG_REPOSITORY, 0755, true );
        }
        if ( !is_dir( INIT::$UPLOAD_REPOSITORY ) ) {
            mkdir( INIT::$UPLOAD_REPOSITORY, 0755, true );
        }
        if ( !is_dir( INIT::$FILES_REPOSITORY ) ) {
            mkdir( INIT::$FILES_REPOSITORY, 0755, true );
        }
        if ( !is_dir( INIT::$CACHE_REPOSITORY ) ) {
            mkdir( INIT::$CACHE_REPOSITORY, 0755, true );
        }
        if ( !is_dir( INIT::$ANALYSIS_FILES_REPOSITORY ) ) {
            mkdir( INIT::$ANALYSIS_FILES_REPOSITORY, 0755, true );
        }
        if ( !is_dir( INIT::$ZIP_REPOSITORY ) ) {
            mkdir( INIT::$ZIP_REPOSITORY, 0755, true );
        }
        if ( !is_dir( INIT::$CONVERSIONERRORS_REPOSITORY ) ) {
            mkdir( INIT::$CONVERSIONERRORS_REPOSITORY, 0755, true );
        }
        if ( !is_dir( INIT::$TMP_DOWNLOAD ) ) {
            mkdir( INIT::$TMP_DOWNLOAD, 0755, true );
        }
        if ( !is_dir( INIT::$QUEUE_PROJECT_REPOSITORY ) ) {
            mkdir( INIT::$QUEUE_PROJECT_REPOSITORY, 0755, true );
        }

        //auth sections
        INIT::$AUTHSECRET_PATH = INIT::$ROOT . '/inc/login_secret.dat';
        //if secret is set in file
        if ( file_exists( INIT::$AUTHSECRET_PATH ) ) {
            //fetch it
            INIT::$AUTHSECRET = file_get_contents( INIT::$AUTHSECRET_PATH );
        } else {
            //try creating the file and the fetch it
            //generate pass
            $secret = Utils::randomString( 512, true );
            //put file
            file_put_contents( INIT::$AUTHSECRET_PATH, $secret );
            //if put succeed
            if ( file_exists( INIT::$AUTHSECRET_PATH ) ) {
                //restrict permissions
                chmod( INIT::$AUTHSECRET_PATH, 0400 );
            } else {
                //if couldn't create due to permissions, use default secret
                INIT::$AUTHSECRET = 'ScavengerOfHumanSorrow';
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

    public static function exceptionHandler( $exception ) {

        Log::$fileName = 'fatal_errors.txt';

        $response_message = "Oops we got an Error. Contact <a href='mailto:" . INIT::$SUPPORT_MAIL . "'>" . INIT::$SUPPORT_MAIL . "</a>";

        try {
            /**
             * @var $exception Exception
             */
            throw $exception;
        } catch ( InvalidArgumentException $e ) {
            $code    = 400;
            $message = "Bad Request";
        } catch ( Exceptions\NotFoundException $e ) {
            $code    = 404;
            $message = "Not Found";
            \Log::doJsonLog( [ "error" => 'Record Not found error for URI: ' . $_SERVER[ 'REQUEST_URI' ] . " - " . "{$exception->getMessage()} ", "trace" => $exception->getTrace() ] );
        } catch ( Exceptions\AuthorizationError $e ) {
            $code    = 403;
            $message = "Forbidden";
            \Log::doJsonLog( [ "error" => 'Access not allowed error for URI: ' . $_SERVER[ 'REQUEST_URI' ] . " - " . "{$exception->getMessage()} ", "trace" => $exception->getTrace() ] );
        } catch ( Exceptions\ValidationError $e ) {
            $code             = 409;
            $message          = "Conflict";
            $response_message = $exception->getMessage();
            \Log::doJsonLog( [ "error" => 'The request could not be completed due to a conflict with the current state of the resource. - ' . "{$exception->getMessage()} ", "trace" => $exception->getTrace() ] );
        } catch ( \PDOException $e ) {
            $code    = 503;
            $message = "Service Unavailable";
            \Utils::sendErrMailReport( $exception->getMessage() . "" . $exception->getTraceAsString(), 'Generic error' );
            \Log::doJsonLog( [ "error" => $exception->getMessage(), "trace" => $exception->getTrace() ] );
        } catch ( Exception $e ) {
            $code    = 500;
            $message = "Internal Server Error";
            \Utils::sendErrMailReport( $exception->getMessage() . "" . $exception->getTraceAsString(), 'Generic error' );
            \Log::doJsonLog( [ "error" => $exception->getMessage(), "trace" => $exception->getTrace() ] );
        }

        if ( stripos( PHP_SAPI, 'cli' ) === false ) {
            header( "HTTP/1.1 " . $code . " " . $message );
        }

        if ( ( isset( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) == 'xmlhttprequest' ) || @$_SERVER[ 'REQUEST_METHOD' ] == 'POST' ) {

            //json_rersponse
            if ( INIT::$PRINT_ERRORS ) {
                echo json_encode( [
                        "errors" => [ [ "code" => -1000, "message" => $exception->getMessage() ] ], "data" => []
                ] );
            } else {
                echo json_encode( [
                        "errors"  => [
                                [
                                        "code"    => -1000,
                                        "message" => $response_message
                                ]
                        ], "data" => []
                ] );
            }

        } elseif ( INIT::$PRINT_ERRORS ) {
            echo $exception->getMessage() . "\n";
            echo $exception->getTraceAsString() . "\n";
        } else {
            $controllerInstance = new CustomPage();
            $controllerInstance->setTemplate( "$code.html" );
            $controllerInstance->setCode( $code );
            $controllerInstance->doAction();
            die(); // do not complete the response and set the header
        }

    }

    public static function shutdownFunctionHandler() {

        $errorType = [
                E_CORE_ERROR        => 'E_CORE_ERROR',
                E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
                E_ERROR             => 'E_ERROR',
                E_USER_ERROR        => 'E_USER_ERROR',
                E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                E_DEPRECATED        => 'DEPRECATION_NOTICE', //From PHP 5.3
        ];

        # Getting last error
        $error = error_get_last();

        # Checking if last error is a fatal error
        if ( isset( $error[ 'type' ] ) )
            switch ( $error[ 'type' ] ) {
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_ERROR:
                case E_USER_ERROR:
                case E_RECOVERABLE_ERROR:

                    if ( !ob_get_level() ) {
                        ob_start();
                    } else {
                        ob_end_clean();
                        ob_start();
                    }

                    debug_print_backtrace();
                    $output = ob_get_contents();
                    ob_end_clean();

                    # Here we handle the error, displaying HTML, logging, ...
                    $output .= "<pre>\n";
                    $output .= "[ {$errorType[$error['type']]} ]\n\t";
                    $output .= "{$error['message']}\n\t";
                    $output .= "Not Recoverable Error on line {$error['line']} in file " . $error[ 'file' ];
                    $output .= " - PHP " . PHP_VERSION . " (" . PHP_OS . ")\n";
                    $output .= " - REQUEST URI: " . var_export( @$_SERVER[ 'REQUEST_URI' ], true ) . "\n";
                    $output .= " - REQUEST Message: " . var_export( $_REQUEST, true ) . "\n";
                    $output .= "\n\t";
                    $output .= "Aborting...\n";
                    $output .= "</pre>";

                    $isAPI = preg_match( '#/api/*#', @$_SERVER[ 'REQUEST_URI' ] );

                    Log::$fileName = 'fatal_errors.txt';
                    Log::doJsonLog( $output );
                    Utils::sendErrMailReport( $output );

                    if ( stripos( PHP_SAPI, 'cli' ) === false ) {
                        header( "HTTP/1.1 500 Internal Server Error" );
                    }

                    if ( ( isset( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) == 'xmlhttprequest' ) || $isAPI ) {

                        //json_response
                        if ( INIT::$PRINT_ERRORS ) {
                            echo json_encode( [
                                    "errors" => [ [ "code" => -1000, "message" => $output ] ], "data" => []
                            ] );
                        } else {
                            echo json_encode( [
                                    "errors"  => [
                                            [
                                                    "code"    => -1000,
                                                    "message" => "Oops we got an Error. Contact <a href='mailto:" . INIT::$SUPPORT_MAIL . "'>" . INIT::$SUPPORT_MAIL . "</a>"
                                            ]
                                    ], "data" => []
                            ] );
                        }

                    } elseif ( INIT::$PRINT_ERRORS ) {
                        echo $output;
                    }

                    break;
            }

    }

    public static function sessionClose() {
        @session_write_close();
    }

    public static function sessionStart() {
        $session_status = session_status();
        if ( $session_status == PHP_SESSION_NONE ) {
            session_start();
        } elseif ( $session_status == PHP_SESSION_DISABLED ) {
            throw new \Exception( "MateCat needs to have sessions. Sessions must be enabled." );
        }
    }

    protected static function _setIncludePath( $custom_paths = null ) {
        $def_path = [
                self::$_ROOT,
                self::$_ROOT . "/lib/Controller/AbstractControllers",
                self::$_ROOT . "/lib/Controller/API",
                self::$_ROOT . "/lib/Controller",
                self::$_ROOT . "/inc/PHPTAL",
                self::$_ROOT . "/lib/Utils/API",
                self::$_ROOT . "/lib/Utils",
                self::$_ROOT . "/lib/Utils/Predis/src",
                self::$_ROOT . "/lib/Model",
                self::$_ROOT . "/lib/View",
                self::$_ROOT . "/lib/Decorator",
                self::$_ROOT . "/lib/Plugins",

        ];
        if ( !empty( $custom_paths ) ) {
            $def_path = array_merge( $def_path, $custom_paths );
        }

        set_include_path(
                implode( PATH_SEPARATOR, $def_path ) . PATH_SEPARATOR . get_include_path()
        );

    }

    public static function loadClass( $className ) {

        $className = ltrim( $className, '\\' );
        $fileName  = '';
        if ( $lastNsPos = strrpos( $className, '\\' ) ) {
            $namespace = substr( $className, 0, $lastNsPos );
            $className = substr( $className, $lastNsPos + 1 );
            $fileName  = str_replace( '\\', DIRECTORY_SEPARATOR, $namespace ) . DIRECTORY_SEPARATOR;
        }
        $fileName .= str_replace( '_', DIRECTORY_SEPARATOR, $className ) . '.php';
        if ( stream_resolve_include_path( $fileName ) ) {
            include $fileName;
        }

    }

    /**
     * Returns an array of configuration params as parsed from config.ini file.
     * The returned array only return entries that match the current environment.
     *
     */
    public static function getEnvConfig() {

        if ( getenv( 'ENV' ) !== false ) {
            self::$CONFIG[ 'ENV' ] = getenv( 'ENV' );
        }

        $env = self::$CONFIG[ self::$CONFIG[ 'ENV' ] ];

        // check if outsource is disabled by environment
        $enable_outsource = getenv( 'ENABLE_OUTSOURCE' );

        if ( $enable_outsource == "false" ) {
            $env[ "ENABLE_OUTSOURCE" ] = false;
            Log::doJsonLog( "DISABLED OUTSOURCE" );
        }

        return $env;
    }

    /**
     * Returns a specific key from parsed coniguration file
     *
     * @param $key
     *
     * @return mixed
     */
    public static function getEnvConfigKey( $key ) {
        $config = self::getEnvConfig();

        return @$config[ $key ];
    }

    /**
     * TODO: move this to a private instance method on a singleton of this class.
     *
     * This function initializes the configuration peforming all required checks to be sure
     * that configuraiton is safe.
     *
     * If any sanity check is to be done, this is the right place to do it.
     */
    public static function initConfig() {

        INIT::$ENV          = self::$CONFIG[ 'ENV' ];
        INIT::$BUILD_NUMBER = self::$_INI_VERSION;

        $env = self::getEnvConfig();

        foreach ( $env as $KEY => $value ) {
            if ( property_exists( 'INIT', $KEY ) ) {
                INIT::${$KEY} = $value;
            }
        }

        if ( stripos( PHP_SAPI, 'cli' ) === false ) {

            register_shutdown_function( 'Bootstrap::sessionClose' );

            // Get HTTPS server status
            // Override if header is set from load balancer
            $localProto = 'http';
            foreach ( [ 'HTTPS', 'HTTP_X_FORWARDED_PROTO' ] as $_key ) {
                if ( isset( $_SERVER[ $_key ] ) ) {
                    $localProto = 'https';
                    break;
                }
            }

            INIT::$PROTOCOL = $localProto;
            ini_set( 'session.name', INIT::$PHP_SESSION_NAME );
            ini_set( 'session.cookie_domain', '.' . INIT::$COOKIE_DOMAIN );
            ini_set( 'session.cookie_secure', true );

        }

        INIT::$HTTPHOST = INIT::$CLI_HTTP_HOST;

        INIT::obtain(); //load configurations

//        $fileSystem = trim( shell_exec( "df -T " . escapeshellcmd( INIT::$STORAGE_DIR ) . "/files_storage/ | awk '{print $2 }' | sed -n 2p" ) );
//
//        if ( self::$CONFIG['ENV'] == 'production' ) {
//            if( stripos( $fileSystem, 'nfs' ) === false && self::$CONFIG['CHECK_FS'] ){
//                die( 'Wrong Configuration! You must mount your remote filesystem to the production or change the storage directory.' );
//            }
//        } else {
//            if( stripos( $fileSystem, 'nfs' ) !== false && self::$CONFIG['CHECK_FS'] ){
//                die( 'Wrong Configuration! You must un-mount your remote filesystem or change the local directory.' );
//            }
//        }

        Features::setIncludePath();

    }

    /**
     * Check if all mandatory keys are present
     *
     * @return bool true if all mandatory keys are present, false otherwise
     */
    public static function areMandatoryKeysPresent() {
        $merged_config = array_merge( self::$CONFIG, self::$CONFIG[ INIT::$ENV ] );

        foreach ( INIT::$MANDATORY_KEYS as $key ) {
            if ( !array_key_exists( $key, $merged_config ) || $merged_config[ $key ] === null ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the main OAuth keys are present
     *
     * @return bool true if the main OAuth keys are present, false otherwise
     */
    public static function areOauthKeysPresent() {
        if ( empty( INIT::$OAUTH_CLIENT_ID ) ) {
            return false;
        }

        if ( empty( INIT::$OAUTH_CLIENT_SECRET ) ) {
            return false;
        }

        if ( empty( INIT::$OAUTH_CLIENT_APP_NAME ) ) {
            return false;
        }

        return true;
    }

    public static function isGDriveConfigured() {
        if ( empty( INIT::$OAUTH_BROWSER_API_KEY ) ) {
            return false;
        }

        return true;
    }

}

return true;
