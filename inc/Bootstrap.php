<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/05/15
 * Time: 10.54
 *
 */
class Bootstrap {

    public static $_INI_VERSION;
    protected static $CONFIG;
    protected static $_ROOT;

    private  $mandatoryFeatureSet ;

    public static function start() {
        new self();
    }

    private function __construct() {

        self::$CONFIG       = parse_ini_file( 'config.ini', true );
        self::$_ROOT        = realpath( dirname( __FILE__ ) . '/../' );
        $OAUTH_CONFIG       = @parse_ini_file( realpath( dirname( __FILE__ ) . '/oauth_config.ini' ), true );
        register_shutdown_function( 'Bootstrap::fatalErrorHandler' );

        $mv = parse_ini_file( 'version.ini' );
        self::$_INI_VERSION = $mv['version'];

        $this->_setIncludePath();
        spl_autoload_register( 'Bootstrap::loadClass' );
        require_once 'Predis/autoload.php';
        @include_once 'vendor/autoload.php';

        if ( stripos( PHP_SAPI, 'cli' ) === false ) {

            register_shutdown_function( 'Bootstrap::sessionClose' );

            INIT::$PROTOCOL = isset( $_SERVER[ 'HTTPS' ] ) ? "https" : "http";
            INIT::$HTTPHOST = INIT::$PROTOCOL . "://" . $_SERVER[ 'HTTP_HOST' ];

        } else {
            if ( INIT::$DEBUG ) {
//                echo "\nDebug: PHP Running in CLI mode.\n\n";
            }
            //Possible CLI configurations. We definitely don't want sessions in our cron scripts
        }

        INIT::$OAUTH_CONFIG = $OAUTH_CONFIG[ 'OAUTH_CONFIG' ];
        INIT::obtain();

        // Overridable defaults
        INIT::$ROOT                           = self::$_ROOT; // Accessible by Apache/PHP
        INIT::$BASEURL                        = "/"; // Accessible by the browser
        INIT::$TIME_TO_EDIT_ENABLED           = false;
        INIT::$DEFAULT_NUM_RESULTS_FROM_TM    = 3;
        INIT::$THRESHOLD_MATCH_TM_NOT_TO_SHOW = 50;
        INIT::$TRACKING_CODES_VIEW_PATH       = INIT::$ROOT . "/lib/View";

        //get the environment configuration
        self::initConfig();

        if ( empty( INIT::$STORAGE_DIR ) ) {
            INIT::$STORAGE_DIR = INIT::$ROOT . "/local_storage" ;
        }

        date_default_timezone_set( INIT::$TIME_ZONE );

        INIT::$LOG_REPOSITORY                  = INIT::$STORAGE_DIR . "/log_archive";
        INIT::$UPLOAD_REPOSITORY               = INIT::$STORAGE_DIR . "/upload";
        INIT::$FILES_REPOSITORY                = INIT::$STORAGE_DIR . "/files_storage/files";
        INIT::$CACHE_REPOSITORY                = INIT::$STORAGE_DIR . "/files_storage/cache";
        INIT::$ZIP_REPOSITORY                  = INIT::$STORAGE_DIR . "/files_storage/originalZip";
        INIT::$CONVERSIONERRORS_REPOSITORY     = INIT::$STORAGE_DIR . "/conversion_errors";
        INIT::$CONVERSIONERRORS_REPOSITORY_WEB = INIT::$BASEURL . "storage/conversion_errors";
        INIT::$TMP_DOWNLOAD                    = INIT::$STORAGE_DIR . "/tmp_download";
        INIT::$REFERENCE_REPOSITORY            = INIT::$STORAGE_DIR . "/reference_files";
        INIT::$TEMPLATE_ROOT                   = INIT::$ROOT . "/lib/View";
        INIT::$MODEL_ROOT                      = INIT::$ROOT . '/lib/Model';
        INIT::$CONTROLLER_ROOT                 = INIT::$ROOT . '/lib/Controller';
        INIT::$UTILS_ROOT                      = INIT::$ROOT . '/lib/Utils';

        INIT::$TASK_RUNNER_CONFIG = @parse_ini_file( 'task_manager_config.ini', true );

        WorkerClient::init();

        Database::obtain ( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        Log::$uniqID = ( isset( $_COOKIE['PHPSESSID'] ) ? substr( $_COOKIE['PHPSESSID'], 0 , 13 ) : uniqid() );

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
        if ( !is_dir( INIT::$ZIP_REPOSITORY ) ) {
            mkdir( INIT::$ZIP_REPOSITORY, 0755, true );
        }
        if ( !is_dir( INIT::$CONVERSIONERRORS_REPOSITORY ) ) {
            mkdir( INIT::$CONVERSIONERRORS_REPOSITORY, 0755, true );
        }
        if ( !is_dir( INIT::$TMP_DOWNLOAD) ) {
            mkdir( INIT::$TMP_DOWNLOAD, 0755, true );
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
            $secret = self::generate_password( 512 );
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
        $this->mandatoryFeatureSet = new FeatureSet();
    }

    private function notifyBootCompleted() {
        $this->mandatoryFeatureSet->run('bootstrapCompleted');
    }

    public static function fatalErrorHandler() {

        $errorType = array(
                E_CORE_ERROR        => 'E_CORE_ERROR',
                E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
                E_ERROR             => 'E_ERROR',
                E_USER_ERROR        => 'E_USER_ERROR',
                E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                E_DEPRECATED        => 'DEPRECATION_NOTICE', //From PHP 5.3
        );

        # Getting last error
        $error = error_get_last();

        # Checking if last error is a fatal error
        switch ( $error[ 'type' ] ) {
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:

                ini_set( 'display_errors', 'Off' );

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
                $output .= " - REQUEST URI: " . print_r( @$_SERVER[ 'REQUEST_URI' ], true ) . "\n";
                $output .= " - REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
                $output .= "\n\t";
                $output .= "Aborting...\n";
                $output .= "</pre>";

                Log::$fileName = 'fatal_errors.txt';
                Log::doLog( $output );
                Utils::sendErrMailReport( $output );

                header( "HTTP/1.1 200 OK" );

                if ( ( isset( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) == 'xmlhttprequest' ) || @$_SERVER[ 'REQUEST_METHOD' ] == 'POST' ) {

                    //json_rersponse
                    if ( INIT::$EXCEPTION_DEBUG ) {
                        echo json_encode( array(
                                "errors" => array( array( "code" => -1000, "message" => $output ) ), "data" => array()
                        ) );
                    } else {
                        echo json_encode( array(
                                "errors"  => array(
                                        array(
                                                "code"    => -1000,
                                                "message" => "Oops we got an Error. Contact <a href='mailto:support@matecat.com'>support@matecat.com</a>"
                                        )
                                ), "data" => array()
                        ) );
                    }

                } elseif ( INIT::$EXCEPTION_DEBUG ) {
                    echo $output;
                }

                break;
        }

    }

    protected static function generate_password( $length = 12 ) {

        $_pwd = md5( uniqid( '', true ) );
        $pwd  = substr( $_pwd, 0, 6 ) . substr( $_pwd, -6, 6 );

        if ( $length > 12 ) {
            while ( strlen( $pwd ) < $length ) {
                $pwd .= self::generate_password();
            }
            $pwd = substr( $pwd, 0, $length );
        }

        return $pwd;

    }

    public static function sessionClose() {
        @session_write_close();
    }

    public static function sessionStart() {
        @session_start();
    }

    protected static function _setIncludePath( $custom_paths = null ) {
        $def_path = array(
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

        );
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
        @include $fileName;

    }

    /**
     * Returns an array of configuration params as parsed from config.ini file.
     * The returned array only return entries that match the current environment.
     *
     */
    public static function getEnvConfig() {

        if ( getenv( 'ENV' ) !== false ) {
            self::$CONFIG['ENV'] = getenv( 'ENV' );
        }

        return self::$CONFIG[ self::$CONFIG['ENV'] ];
    }

    /**
     * Returns a specific key from parsed coniguration file
     *
     * @param $key
     * @return mixed
     */
    public static function getEnvConfigKey( $key ) {
        $config = self::getEnvConfig() ;
        return $config[ $key ] ;
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

        INIT::$ENV = self::$CONFIG['ENV'];
        INIT::$BUILD_NUMBER = self::$_INI_VERSION;

        $env = self::getEnvConfig();

        foreach( $env as $KEY => $value ){
            if ( property_exists( 'INIT', $KEY ) ) {
                INIT::${$KEY} = $value;
            }

        }

        $fileSystem = trim( shell_exec( "df -T " . escapeshellcmd( INIT::$STORAGE_DIR ) . "/files_storage/ | awk '{print $2 }' | sed -n 2p" ) );

        if ( self::$CONFIG['ENV'] == 'production' ) {
            if( stripos( $fileSystem, 'nfs' ) === false && self::$CONFIG['CHECK_FS'] ){
                die( 'Wrong Configuration! You must mount your remote filesystem to the production or change the storage directory.' );
            }
        } else {
            if( stripos( $fileSystem, 'nfs' ) !== false && self::$CONFIG['CHECK_FS'] ){
                die( 'Wrong Configuration! You must un-mount your remote filesystem or change the local directory.' );
            }
        }

        if ( ! empty( INIT::$PLUGIN_LOAD_PATHS )) {
            set_include_path( get_include_path() .
                    PATH_SEPARATOR .
                    implode(PATH_SEPARATOR, INIT::$PLUGIN_LOAD_PATHS )
            );
        }

    }

    /**
     * Check if all mandatory keys are present
     *
     * @return bool true if all mandatory keys are present, false otherwise
     */
    public static function areMandatoryKeysPresent()  {
        $merged_config = array_merge(self::$CONFIG, self::$CONFIG[ INIT::$ENV ] );

        foreach ( INIT::$MANDATORY_KEYS as $key ) {
            if ( !array_key_exists($key, $merged_config) || $merged_config[ $key ] === null ) {
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
        if( empty( INIT::$OAUTH_CLIENT_ID ) ) {
            return false;
        }

        if( empty( INIT::$OAUTH_CLIENT_SECRET ) ) {
            return false;
        }

        if( empty( INIT::$OAUTH_CLIENT_APP_NAME ) ) {
            return false;
        }

        return true;
    }

}

return true;
