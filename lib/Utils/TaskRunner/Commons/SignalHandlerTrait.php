<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 27/06/24
 * Time: 17:12
 *
 */

namespace TaskRunner\Commons;

use RuntimeException;

trait SignalHandlerTrait {

    /**
     * Singleton Pattern, Unique Instance of This  ( Concrete class )
     *
     * @return void
     */
    public function installHandler(): void {

        if ( PHP_SAPI != 'cli' || isset ( $_SERVER [ 'HTTP_HOST' ] ) ) {
            die ( "This script can be run only in CLI Mode.\n\n" );
        }

        pcntl_async_signals( true );
        set_time_limit( 0 );

        if ( !extension_loaded( "pcntl" ) && ini_get( "enable_dl" ) ) {
            dl( "pcntl.so" );
        }
        if ( !function_exists( 'pcntl_signal' ) ) {
            throw new RuntimeException( "****** PCNTL EXTENSION NOT LOADED. KILLING THIS PROCESS COULD CAUSE UNPREDICTABLE ERRORS ******" );
        }

        pcntl_signal( SIGTERM, [ $this, 'sigSwitch' ] );
        pcntl_signal( SIGINT, [ $this, 'sigSwitch' ] );
        pcntl_signal( SIGHUP, [ $this, 'sigSwitch' ] );
        pcntl_signal( SIGPIPE, [ $this, 'sigSwitch' ] );
        pcntl_signal( SIGQUIT, [ $this, 'sigSwitch' ] );
        pcntl_signal( SIGSEGV, [ $this, 'sigSwitch' ] );
        pcntl_signal( SIGTSTP, [ $this, 'sigSwitch' ] );
        pcntl_signal( SIGUSR1, [ $this, 'sigSwitch' ] );
        pcntl_signal( SIGUSR2, [ $this, 'sigSwitch' ] );

    }

    /**
     * Posix Signal handling method
     *
     * @param $sig_no
     * @param $siginfo
     */
    public function sigSwitch( $sig_no, $siginfo ) {

        switch ( $sig_no ) {
            case SIGTERM :
            case SIGINT :
            case SIGHUP :
            case SIGPIPE:
            case SIGQUIT:
            case SIGSEGV:
            case SIGTSTP:
            case SIGUSR1:
            case SIGUSR2:
                $this->RUNNING = false;
                break;
            default :
                break;
        }

    }

}