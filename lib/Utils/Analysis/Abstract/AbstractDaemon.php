<?php

set_time_limit(0);

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 17/09/15
 * Time: 15.29
 */
abstract class Analysis_Abstract_AbstractDaemon
{
    public static $RUNNING = true;
    public static $tHandlerPID;
    protected static $__INSTANCE = null;

    /**
     * @var int sleep time in seconds. To be used as a parameter of sleep()
     */
    protected static $sleeptime = 1;

    protected function __construct( ){
        static::$tHandlerPID = posix_getpid();
    }

    /**
     * Singleton Pattern, Unique Instance of This
     */
    public static function getInstance() {
        if ( static::$__INSTANCE === null ) {
            if( !extension_loaded("pcntl") && (bool)ini_get( "enable_dl" ) ){
                dl("pcntl.so");
            }
            if (! function_exists ( 'pcntl_signal' )) {
                $msg = "****** PCNTL EXTENSION NOT LOADED. KILLING THIS PROCESS COULD CAUSE UNPREDICTABLE ERRORS ******";
                Log::doLog( $msg );
                static::_TimeStampMsg( $msg."\n" );
            } else {
                Log::doLog('registering signal handlers');
                static::_TimeStampMsg('registering signal handlers\n');

                pcntl_signal( SIGTERM, array ( get_called_class(), 'sigSwitch' ) );
                pcntl_signal( SIGINT,  array ( get_called_class(), 'sigSwitch' ) );
                pcntl_signal( SIGHUP,  array ( get_called_class(), 'sigSwitch' ) );
                $msg = str_pad( " Signal Handler Installed ", 50, "-", STR_PAD_BOTH );

                Log::doLog( $msg );
                static::_TimeStampMsg( "$msg\n" );
            }
            static::$__INSTANCE = new static();
        }
        return static::$__INSTANCE;
    }

    public static function sigSwitch($signo) {
        Log::doLog("Signo : $signo");
        static::_TimeStampMsg("Signo : $signo" . "\n");

        switch ($signo) {
            case SIGTERM :
            case SIGINT :
            case SIGHUP :
                static::$RUNNING = false;
                break;
            default :
                break;
        }
    }

    protected static function _TimeStampMsg($msg) {
        echo "[" . date( DATE_RFC822 ) . "] " . $msg;
    }

    abstract function main($args);
}