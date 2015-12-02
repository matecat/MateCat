<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/12/15
 * Time: 16.59
 *
 */
class Analysis_Queue_Info {

    public $redis_key;
    public $queue_name;

    protected function __construct( Array $queueElement ) {

        foreach( $queueElement as $key => $values ){
            if ( property_exists( $this, $key ) ){
                $this->$key = $values;
            }
        }

    }

    public static function build( Array $queueInfo ) {
        return new self( $queueInfo );
    }
}
