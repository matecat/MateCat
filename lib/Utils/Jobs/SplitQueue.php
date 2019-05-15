<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 11/09/17
 * Time: 16.43
 *
 */

namespace Jobs;


use Exception;
use Jobs_JobStruct;
use Log;
use Utils;
use WorkerClient;

class SplitQueue {

    public static function recount( Jobs_JobStruct $jobStruct ){

        try{
            WorkerClient::enqueue( 'JOBS', '\AsyncTasks\Workers\JobsWorker', $jobStruct, [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );
        } catch ( Exception $e ){

            # Handle the error, logging, ...
            $output  = "**** Job Split PEE recount request failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $jobStruct, true );
            Log::doJsonLog( $output );
            Utils::sendErrMailReport( $output );

        }
    }

}