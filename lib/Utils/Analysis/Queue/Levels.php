<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/12/15
 * Time: 17.00
 *
 */
class Analysis_Queue_Levels {

    protected function __construct( Array $queue_info ) {
        foreach ( $queue_info as $level => $values ) {
            $this->$level = Analysis_Queue_Info::build( $values );
        }
    }

    public static function build( Array $queue_info ) {
        return new static( $queue_info );
    }

}