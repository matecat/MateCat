<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 20.55
 *
 */

namespace TaskRunner\Commons;

use Log;

/**
 * The abstract Daemon definition.
 * Extended by every concrete running daemon class
 *
 * - Singleton pattern for the concrete daemon
 * - Install signal handlers for the concrete class
 * - Provide log method for the concrete classes
 *
 */
abstract class AbstractDaemon {

    /**
     * Flag for control the instance running status. Setting to false cause the daemon to stop.
     *
     * @var bool
     */
    public $RUNNING = true;

    /**
     * The process id of the daemon
     *
     * @var int
     */
    public static $tHandlerPID;

    /**
     * Static reference for the singleton
     *
     * @var static
     */
    protected static $__INSTANCE = null;

    /**
     * Sleep time in seconds. To be used as a parameter of sleep()
     * @var int
     */
    protected static $sleepTime = 2;

    /**
     * AbstractDaemon constructor.
     *
     * @param null $configFile
     * @param null $contextIndex
     */
    protected function __construct( $configFile = null, $contextIndex = null ) {
        static::$tHandlerPID = posix_getpid();
    }

    /**
     * Singleton Pattern, Unique Instance of This  ( Concrete class )
     *
     * @param $config_file mixed
     * @param $queueIndex
     *
     * @return static
     */
    public static function getInstance( $config_file = null, $queueIndex = null ) {

        if ( PHP_SAPI != 'cli' || isset ( $_SERVER [ 'HTTP_HOST' ] ) ) {
            die ( "This script can be run only in CLI Mode.\n\n" );
        }

        declare( ticks = 10 );
        set_time_limit( 0 );

        if ( static::$__INSTANCE === null ) {
            if ( !extension_loaded( "pcntl" ) && (bool)ini_get( "enable_dl" ) ) {
                dl( "pcntl.so" );
            }
            if ( !function_exists( 'pcntl_signal' ) ) {
                $msg = "****** PCNTL EXTENSION NOT LOADED. KILLING THIS PROCESS COULD CAUSE UNPREDICTABLE ERRORS ******";
                //static::_TimeStampMsg( $msg );
            } else {
                //static::_TimeStampMsg( str_pad( " Registering signal handlers ", 60, "*", STR_PAD_BOTH ) );

                pcntl_signal( SIGTERM, array( get_called_class(), 'sigSwitch' ) );
                pcntl_signal( SIGINT, array( get_called_class(), 'sigSwitch' ) );
                pcntl_signal( SIGHUP, array( get_called_class(), 'sigSwitch' ) );
                $msg = str_pad( " Signal Handler Installed ", 60, "-", STR_PAD_BOTH );

                //static::_TimeStampMsg( "$msg" );
            }
            static::$__INSTANCE = new static(  $config_file, $queueIndex );
        }

        return static::$__INSTANCE;
    }

    /**
     * Posix Signal handling method
     *
     * @param $sig_no
     */
    public static function sigSwitch( $sig_no ) {

        static::_TimeStampMsg( "Signo : $sig_no" );

        switch ( $sig_no ) {
            case SIGTERM :
            case SIGINT :
            case SIGHUP :
                static::$__INSTANCE->RUNNING = false;
                break;
            default :
                break;
        }
    }

    /**
     * Log method
     *
     * @param $msg
     */
    protected static function _TimeStampMsg( $msg ) {
        if ( \INIT::$DEBUG ) echo "[" . date( DATE_RFC822 ) . "] " . $msg . "\n";
        Log::doJsonLog( $msg );
    }

    /**
     * The starting method
     *
     * @param array|null $args
     *
     * @return mixed
     */
    abstract public function main( $args = null );

    /**
     * Needed to be abstract and static despite the strict standards because it will be called from signal handler
     * @return mixed
     */
    abstract public static function cleanShutDown();

    /**
     * Every cycle reload and update Daemon configuration.
     * @return void
     */
    abstract protected function _updateConfiguration();

}