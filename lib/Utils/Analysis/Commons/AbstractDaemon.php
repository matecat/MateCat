<?php

namespace Analysis\Commons;

use \Log;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 17/09/15
 * Time: 15.29
 */
abstract class AbstractDaemon {

    public static $RUNNING = true;
    public static $tHandlerPID;

    /**
     * @var AbstractDaemon
     */
    protected static $__INSTANCE = null;

    /**
     * @var int sleep time in seconds. To be used as a parameter of sleep()
     */
    protected static $sleeptime = 1;

    protected function __construct() {
        static::$tHandlerPID = posix_getpid();
    }

    /**
     * Singleton Pattern, Unique Instance of This
     */
    public static function getInstance() {

        if ( PHP_SAPI != 'cli' || isset ( $_SERVER [ 'HTTP_HOST' ] ) ) {
            die ( "This script can be run only in CLI Mode.\n\n" );
        }

        declare( ticks = 1 );
        set_time_limit( 0 );

        if ( static::$__INSTANCE === null ) {
            if ( !extension_loaded( "pcntl" ) && (bool)ini_get( "enable_dl" ) ) {
                dl( "pcntl.so" );
            }
            if ( !function_exists( 'pcntl_signal' ) ) {
                $msg = "****** PCNTL EXTENSION NOT LOADED. KILLING THIS PROCESS COULD CAUSE UNPREDICTABLE ERRORS ******";
                static::_TimeStampMsg( $msg );
            } else {
                static::_TimeStampMsg( 'registering signal handlers\n' );

                pcntl_signal( SIGTERM, array( get_called_class(), 'sigSwitch' ) );
                pcntl_signal( SIGINT, array( get_called_class(), 'sigSwitch' ) );
                pcntl_signal( SIGHUP, array( get_called_class(), 'sigSwitch' ) );
                $msg = str_pad( " Signal Handler Installed ", 50, "-", STR_PAD_BOTH );

                static::_TimeStampMsg( "$msg\n" );
            }
            static::$__INSTANCE = new static();
        }

        return static::$__INSTANCE;
    }

    public static function sigSwitch( $sig_no ) {

        static::_TimeStampMsg( "Signo : $sig_no" );

        switch ( $sig_no ) {
            case SIGTERM :
            case SIGINT :
            case SIGHUP :
                static::$RUNNING = false;
                break;
            default :
                break;
        }
    }

    protected static function _TimeStampMsg( $msg ) {
        echo "[" . date( DATE_RFC822 ) . "] " . $msg . "\n";
        Log::doLog( $msg );
    }

    abstract public function main( $args = null );

    abstract public static function cleanShutDown();

}