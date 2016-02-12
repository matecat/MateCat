<?php
namespace TaskRunner\Commons;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/12/15
 * Time: 17.00
 *
 */
class ContextList {

    /**
     * @var Context[]
     */
    public $list = array();

    /**
     * QueuesList constructor.
     *
     * @param array $queue_info
     */
    protected function __construct( Array $queue_info ) {

        foreach ( $queue_info as $level => $values ) {
            $this->list[ $level ] = Context::buildFromArray( $values );
        }

    }

    /**
     * @param array $queue_info
     *
     * @return static
     */
    public static function get( Array $queue_info = array() ) {
        return new static( $queue_info );
    }

}