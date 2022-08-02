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
 * Context definition for the Executors
 *
 * @package TaskRunner\Commons
 */
class Context {

    /**
     * The name for the queue on AMQ
     * @var string
     */
    public $queue_name;

    /**
     * Name of the set of processes in which every pid will be put by TaskManager
     * @var string
     */
    public $pid_set_name;

    /**
     * List of numerical process IDs in my list
     * @var array
     */
    public $pid_list = [];

    /**
     * Number of the processes actually tied to that queue
     * @var int
     */
    public $pid_list_len = 0;

    /**
     * Max processes that must to be tied the queue
     * @var int
     */
    public $max_executors = 0;

    /**
     * Default Logger name
     * @var string
     */
    public $loggerName = 'Executor.log';

    /**
     * @var $redis_key
     */
    public $redis_key;

    /**
     * AbstractContext constructor.
     *
     * @param array $queueElement
     */
    protected function __construct( array $queueElement ) {

        foreach ( $queueElement as $key => $values ) {
            $this->$key = $values;
        }

    }

    /**
     * Concrete Static builder method
     *
     * @param array $context
     *
     * @return static
     */
    public static function buildFromArray( array $context ) {
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