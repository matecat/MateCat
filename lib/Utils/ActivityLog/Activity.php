<?php

namespace ActivityLog;

use Exception;
use Log;
use Utils;
use WorkerClient;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 13/06/16
 * Time: 12:04
 */
class Activity {

    public static function save( ActivityLogStruct $activityLog ){

        try{
            WorkerClient::enqueue( 'ACTIVITYLOG', '\AsyncTasks\Workers\ActivityLogWorker', $activityLog, array( 'persistent' => WorkerClient::$_HANDLER->persistent ) );
        } catch ( Exception $e ){

            # Handle the error, logging, ...
            $output  = "**** Activity Log failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $activityLog, true );
            Log::doJsonLog( $output );
            Utils::sendErrMailReport( $output );

        }
    }
    
}