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

    public $redis_key;
    public $queue_name;
    public $pid_list_name;
    public $pid_list = array();

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
