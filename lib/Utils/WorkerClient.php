<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/11/16
 * Time: 12:42 PM
 */

use TaskRunner\Commons\ContextList;
use TaskRunner\Commons\QueueElement;

class WorkerClient {

    public static $_QUEUES;
    /**
     * @var AMQHandler
     */
    public static $_HANDLER;


    /**
     * The handler is passed to have a more correct dependency injection
     *
     * @param AMQHandler|null $handler
     * @throws Exception
     */
    public static function init( AMQHandler $handler = null ) {
        if ( \INIT::$TASK_RUNNER_CONFIG ) {
            $contextList = ContextList::get( \INIT::$TASK_RUNNER_CONFIG['context_definitions'] );
            self::$_QUEUES  = $contextList->list;
            if( !is_null( $handler ) ){
                self::$_HANDLER = $handler;
            } else {
                self::$_HANDLER = new AMQHandler();
            }
        }
        else {
            throw new Exception('Missing task runner config'); 
        }
    }

    /**
     * @param $queue
     * @param $class_name
     * @param $data
     * @param $options
     *
     * @throws Exception
     *
     */
    public static function enqueue( $queue, $class_name, $data, $options = [] ) {

        if ( !isset( $options['persistent'] ) ) {
            $options['persistent'] = self::$_HANDLER->persistent ;
        }

        $element            = new QueueElement();
        $element->params    = $data;
        $element->classLoad = $class_name;

        $queue_name = self::$_QUEUES[ $queue ]->queue_name ;

        if ( empty( $queue_name ) ) {
            throw new InvalidArgumentException( 'Empty queue_name: ' . var_export( self::$_QUEUES, true ) . "\n" . var_export( $queue, true )
            ) ;
        }

        self::$_HANDLER->send( $queue_name, $element, $options );
    }

}