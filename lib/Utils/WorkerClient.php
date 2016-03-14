<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/11/16
 * Time: 12:42 PM
 */

use TaskRunner\Commons\ContextList;
use TaskRunner\Commons\QueueElement ;

class WorkerClient {

    public static $_QUEUES;
    /**
     * @var AMQHandler
     */
    public static $_HANDLER;


    public static function init() {
        $task_manager_config = @parse_ini_file( INIT::$UTILS_ROOT . '/Analysis/task_manager_config.ini', true );
        if ( $task_manager_config ) {
            $contextList = ContextList::get( $task_manager_config[ 'context_definitions' ] );
            self::$_QUEUES  = $contextList->list;
            self::$_HANDLER = new AMQHandler();
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
     * TODO: move this in the abstractWorker itself
     */
    public static function enqueue( $queue, $class_name, $data, $options ) {
        $element            = new QueueElement();
        $element->params    = $data;
        $element->classLoad = $class_name;

        self::$_HANDLER->send( self::$_QUEUES[ $queue ]->queue_name, $element, $options );
    }

}