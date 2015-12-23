<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 20.55
 *
 */

namespace Analysis\Commons;


abstract class AbstractContext {

    /**
     * The name for the queue on AMQ
     * @var string
     */
    public $queue_name;

    /**
     * Name of the set of processes in which this pid will be put by TaskManager
     * @var string
     */
    public $pid_set_name;

    /**
     * List of numerical process IDs in my list
     * @var array
     */
    public $pid_list     = array();

    /**
     * Number of the processes tied to that queue
     * @var int
     */
    public $pid_list_len = 0;

    /**
     * AbstractContext constructor.
     *
     * @param array $queueElement
     */
    protected function __construct( Array $queueElement ) {

        foreach( $queueElement as $key => $values ){
            if ( property_exists( $this, $key ) ){
                $this->$key = $values;
            }
        }

    }

    /**
     * @param array $context
     *
     * @return static
     */
    public static function buildFromArray( Array $context ) {
        return new static( $context );
    }

}