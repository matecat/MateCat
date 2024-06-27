<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 20.55
 *
 */

namespace TaskRunner\Commons;

/**
 * Class Context
 *
 * Context definition for the Executors
 *
 * @package TaskRunner\Commons
 */
class Context {

    /**
     * The name for the queue on AMQ
     * @var string
     */
    public string $queue_name;

    /**
     * Name of the set of processes in which every pid will be put by TaskManager
     * @var string
     */
    public string $pid_set_name;

    /**
     * Number of the processes actually tied to that queue
     * @var int
     */
    public int $pid_list_len = 0;

    /**
     * Max processes that must be tied the queue
     * @var int
     */
    public int $max_executors = 0;

    /**
     * Default Logger name
     * @var string
     */
    public string $loggerName = 'Executor.log';

    /**
     * @var string $redis_key
     */
    public string $redis_key;

    /**
     * AbstractContext constructor.
     *
     * @param array $queueElement
     */
    protected function __construct( array $queueElement ) {

        $this->queue_name    = $queueElement[ 'queue_name' ];
        $this->pid_set_name  = $queueElement[ 'queue_name' ] . '_pid_set';
        $this->max_executors = $queueElement[ 'max_executors' ];
        $this->redis_key     = $queueElement[ 'queue_name' ] . '_redis_key';
        $this->loggerName    = $queueElement[ 'queue_name' ] . '.log';

    }

    /**
     * Concrete Static builder method
     *
     * @param array $context
     *
     * @return static
     */
    public static function buildFromArray( array $context ): Context {
        return new static( $context );
    }

    /**
     * Magic to string method
     *
     * @return string
     */
    public function __toString() {
        return json_encode( $this );
    }

}