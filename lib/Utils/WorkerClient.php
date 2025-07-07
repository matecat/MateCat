<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/11/16
 * Time: 12:42 PM
 */

use Stomp\Transport\Message;
use Utils\TaskRunner\Commons\ContextList;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;

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
     *
     * @throws Exception
     */
    public static function init( AMQHandler $handler = null ) {
        if ( INIT::$TASK_RUNNER_CONFIG ) {
            $contextList   = ContextList::get( INIT::$TASK_RUNNER_CONFIG[ 'context_definitions' ] );
            self::$_QUEUES = $contextList->list;
            if ( !is_null( $handler ) ) {
                self::$_HANDLER = $handler;
            } else {
                self::$_HANDLER = new AMQHandler();
            }
        } else {
            throw new Exception( 'Missing task runner config' );
        }
    }

    /**
     * WARNING this method should never be used in a daemon context
     *
     * @param string $queue
     * @param string $class_name
     * @param array  $data
     * @param array  $options
     *
     */
    public static function enqueue( string $queue, string $class_name, array $data, array $options = [] ) {
        static::enqueueWithClient( self::$_HANDLER, $queue, $class_name, $data, $options );
    }

    /**
     * @param AMQHandler $handler
     * @param string     $queue
     * @param string     $class_name
     * @param array      $data
     * @param array      $options
     */
    public static function enqueueWithClient( AMQHandler $handler, string $queue, string $class_name, array $data, array $options = [] ) {

        if ( !isset( $options[ 'persistent' ] ) ) {
            $options[ 'persistent' ] = $handler->persistent;
        }

        $element            = new QueueElement();
        $element->params    = new Params( $data );
        $element->classLoad = $class_name;

        $queue_name = self::$_QUEUES[ $queue ]->queue_name;

        if ( empty( $queue_name ) ) {
            throw new InvalidArgumentException( 'Empty queue_name: ' . var_export( self::$_QUEUES, true ) . "\n" . var_export( $queue, true ) );
        }

        $handler->publishToQueues( $queue_name, new Message( strval( $element ), $options ) );
        $handler->close();

    }

}