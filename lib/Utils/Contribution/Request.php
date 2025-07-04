<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 17.49
 *
 */

namespace Contribution;
use Exception;
use Log;
use Utils\AsyncTasks\Workers\GetContributionWorker;
use WorkerClient;

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
            WorkerClient::enqueue( 'CONTRIBUTION_GET', GetContributionWorker::class, $contribution->getArrayCopy(), array( 'persistent' => false ) );
        } catch ( Exception $e ){

            # Handle the error, logging, ...
            $output  = "**** GetContribution failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $contribution, true );
            Log::doJsonLog( $output );
            throw $e;

        }

    }

}