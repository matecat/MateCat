<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 17.49
 *
 */

namespace Utils\Contribution;

use Exception;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\SetContributionMTWorker;
use Utils\AsyncTasks\Workers\SetContributionWorker;
use Utils\Logger\Log;

/**
 * Class Set
 * @package Contribution
 *
 */
class Set {

    /**
     * @param SetContributionRequest $contribution
     *
     * @throws Exception
     */
    public static function contribution( SetContributionRequest $contribution ) {

        try {
            WorkerClient::enqueue( 'CONTRIBUTION', SetContributionWorker::class, $contribution->getArrayCopy(), [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );
        } catch ( Exception $e ) {

            # Handle the error, logging, ...
            $output = "**** SetContribution failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $contribution, true );
            Log::doJsonLog( $output );
            throw $e;

        }

    }

    /**
     * @throws Exception
     */
    public static function contributionMT( SetContributionRequest $contribution = null ) {
        try {

            if ( empty( $contribution ) ) {
                return;
            }

            WorkerClient::enqueue( 'CONTRIBUTION_MT', SetContributionMTWorker::class, $contribution->getArrayCopy(), [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );
        } catch ( Exception $e ) {

            # Handle the error, logging, ...
            $output = "**** SetContribution failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $contribution, true );
            Log::doJsonLog( $output );
            throw $e;

        }
    }

}