<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 20.55
 *
 */

namespace TaskRunner\Commons;

use Exception;
use INIT;
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

    use SignalHandlerTrait;

    /**
     * Flag for control the instance running status. Setting to false cause the daemon to stop.
     *
     * @var bool
     */
    public bool $RUNNING = true;

    /**
     * The process id of the daemon
     *
     * @var int
     */
    public int $myProcessPid;

    /**
     * Sleep time in seconds. To be used as a parameter of sleep()
     * @var int
     */
    protected static int $sleepTime = 2;

    /**
     * AbstractDaemon constructor.
     *
     * @param ?string $configFile
     * @param ?string $contextIndex
     */
    protected function __construct( string $configFile = null, string $contextIndex = null ) {
        $this->myProcessPid = posix_getpid();
    }

    /**
     * Singleton Pattern, Unique Instance of This  ( Concrete class )
     *
     * @param $config_file mixed
     * @param $queueIndex
     *
     * @return static
     */
    public static function getInstance( $config_file = null, $queueIndex = null ): AbstractDaemon {

        $__INSTANCE = new static( $config_file, $queueIndex );
        $__INSTANCE->installHandler();

        return $__INSTANCE;
    }

    /**
     * Log method
     *
     * @param $msg
     */
    protected function _logTimeStampedMsg( $msg ) {
        if ( INIT::$DEBUG ) {
            echo "[" . date( DATE_RFC822 ) . "] " . $msg . "\n";
        }
        Log::doJsonLog( $msg );
    }

    /**
     * The starting method
     *
     * @param array|null $args
     *
     * @return mixed
     */
    abstract public function main( array $args = null );

    /**
     * Needed to be abstract and static despite the strict standards because it will be called from signal handler
     * @return mixed
     */
    abstract public function cleanShutDown();

    /**
     * Every cycle reload and update Daemon configuration.
     * @return void
     * @throws Exception
     */
    abstract protected function _updateConfiguration(): void;

}