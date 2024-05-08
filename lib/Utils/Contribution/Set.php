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
use WorkerClient;

/**
 * Class Set
 * @package Contribution
 *
 */
class Set {

    /**
     * @param ContributionSetStruct $contribution
     *
     * @throws Exception
     */
    public static function contribution( ContributionSetStruct $contribution ){

        try{
            WorkerClient::enqueue( 'CONTRIBUTION', '\AsyncTasks\Workers\SetContributionWorker', $contribution, array( 'persistent' => WorkerClient::$_HANDLER->persistent ) );
        } catch ( Exception $e ){

            # Handle the error, logging, ...
            $output  = "**** SetContribution failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $contribution, true );
            Log::doJsonLog( $output );
            throw $e;

        }

    }

    /**
     * @throws Exception
     */
    public static function contributionMT( ContributionSetStruct $contribution = null ){
        try{

            if ( empty( $contribution ) ) return;

            WorkerClient::enqueue( 'CONTRIBUTION_MT', '\AsyncTasks\Workers\SetContributionMTWorker', $contribution, array( 'persistent' => WorkerClient::$_HANDLER->persistent ) );
        } catch ( Exception $e ){

            # Handle the error, logging, ...
            $output  = "**** SetContribution failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $contribution, true );
            Log::doJsonLog( $output );
            throw $e;

        }
    }

}