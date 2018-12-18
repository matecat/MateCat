<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 17.49
 *
 */

namespace Contribution;
use \Exception,
        \Log,
        \WorkerClient
    ;

/**
 * Class Set
 * @package Contribution
 *
 */
class Request {

    /**
     * @param ContributionRequestStruct $contribution
     *
     * @throws Exception
     */
    public static function contribution( ContributionRequestStruct $contribution ){

        try{
            WorkerClient::enqueue( 'CONTRIBUTION_GET', '\AsyncTasks\Workers\GetContributionWorker', $contribution, array( 'persistent' => WorkerClient::$_HANDLER->persistent ) );
        } catch ( Exception $e ){

            # Handle the error, logging, ...
            $output  = "**** SetContribution failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $contribution, true );
            Log::doLog( $output );
            throw $e;

        }

    }

}