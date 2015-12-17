<?php

namespace Analysis\Queue;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/12/15
 * Time: 16.59
 *
 */
class Info {

    /**
     * The key of the project list
     * ( needed to know the decremental counter for the number of elements. Will be read from the web page )
     * @var string
     */
    public $redis_key;

    /**
     * The name for the queue on AMQ
     * @var string
     */
    public $queue_name;

    /**
     * Name of the set of processes in which this pid will be put by TMManager
     * @var string
     */
    public $pid_set_name;

    /**
     * List of numerical process IDs in my list
     * @var array
     */
    public $pid_list     = array();

    /**
     * Number of elements in the queue on AMQ
     * @var int
     */
    public $queue_length = 0;

    /**
     * The breakdown percentage on which balance processes over the queues
     * @var int
     */
    public $pid_set_perc_break = 0;

    /**
     * Analysis_Queue_Info constructor.
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
     * @param array $queueInfo
     *
     * @return Info
     */
    public static function build( Array $queueInfo ) {
        return new self( $queueInfo );
    }
}
