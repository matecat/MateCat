<?php

namespace Model\ActivityLog;

use Exception;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\ActivityLogWorker;
use Utils\Logger\Log;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 13/06/16
 * Time: 12:04
 */
class Activity {

    public static function save( ActivityLogStruct $activityLog ) {

        try {
            WorkerClient::enqueue( 'ACTIVITYLOG', ActivityLogWorker::class, $activityLog->getArrayCopy(), [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );
        } catch ( Exception $e ) {

            # Handle the error, logging, ...
            $output = "**** Activity Log failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $activityLog, true );
            Log::doJsonLog( $output );

        }
    }

}