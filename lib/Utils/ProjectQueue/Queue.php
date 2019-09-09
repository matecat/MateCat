<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 15.15
 *
 */

namespace ProjectQueue;

use AMQHandler;
use ArrayObject;
use Constants_ProjectStatus;
use Exception;
use Log;
use RedisHandler;
use WorkerClient;

/**
 * Class Enqueue
 * @package ProjectQueue
 *
 */
class Queue {

    /**
     * @param ArrayObject $projectStructure
     *
     * @throws Exception
     *
     * //TODO externalize ProjectStruct from @see ProjectManager
     *
     */
    public static function sendProject( ArrayObject $projectStructure ) {

        try {
            WorkerClient::init( new AMQHandler() );
            WorkerClient::enqueue( 'PROJECT_QUEUE', 'AsyncTasks\Workers\ProjectCreationWorker', json_encode( $projectStructure ), [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );
        } catch ( Exception $e ) {

            # Handle the error, logging, ...
            $output = "**** Project Enqueue failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $projectStructure, true );
            Log::doJsonLog( $output );
            throw $e;

        }

    }

    public static function getPublishedResults( $id_project ){

        $redisHandler = ( new RedisHandler() )->getConnection();
        $response = json_decode( $redisHandler->get( sprintf( Constants_ProjectStatus::PROJECT_QUEUE_HASH, $id_project ) ), true );
        $redisHandler->disconnect();
        return $response;

    }

    public static function publishResults( ArrayObject $projectStructure ){

        $hashKey = sprintf( Constants_ProjectStatus::PROJECT_QUEUE_HASH, $projectStructure[ 'id_project' ] );
        return ( new RedisHandler() )->getConnection()->set( $hashKey, json_encode( $projectStructure[ 'result' ], 60 * 60 * 24 * 7 ) ); //store for 7 days

    }

}