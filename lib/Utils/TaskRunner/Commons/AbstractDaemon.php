<?php

namespace TaskRunner\Commons;

use \Log;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 20.55
 *
 */
abstract class AbstractDaemon {

    public $RUNNING = true;
    public static $tHandlerPID;

    /**
     * @var static
     */
    protected static $__INSTANCE = null;

    /**
     * @var int sleep time in seconds. To be used as a parameter of sleep()
     */
    protected static $sleepTime = 2;

    protected function __construct( $configFile = null, $contextIndex = null ) {
        static::$tHandlerPID = posix_getpid();
    }

    /**
     * Singleton Pattern, Unique Instance of This
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
                static::_TimeStampMsg( $msg );
            } else {
                static::_TimeStampMsg( 'Registering signal handlers' );

                pcntl_signal( SIGTERM, array( get_called_class(), 'sigSwitch' ) );
                pcntl_signal( SIGINT, array( get_called_class(), 'sigSwitch' ) );
                pcntl_signal( SIGHUP, array( get_called_class(), 'sigSwitch' ) );
                $msg = str_pad( " Signal Handler Installed ", 50, "-", STR_PAD_BOTH );

                static::_TimeStampMsg( "$msg\n" );
            }
            static::$__INSTANCE = new static(  $config_file, $queueIndex );
        }

        return static::$__INSTANCE;
    }

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

    protected static function _TimeStampMsg( $msg ) {
        if ( \INIT::$DEBUG ) echo "[" . date( DATE_RFC822 ) . "] " . $msg . "\n";
        Log::doLog( $msg );
    }

    abstract public function main( $args = null );

    /**
     * Needed to be abstract and static despite the strict standards because it will be called from signal handler
     * @return mixed
     */
    abstract public static function cleanShutDown();

}