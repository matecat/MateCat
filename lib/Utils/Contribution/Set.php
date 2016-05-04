<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 17.49
 *
 */

namespace Contribution;
use \Jobs_JobStruct,
        \Engine,
        \Engines_AbstractEngine,
        \TmKeyManagement_TmKeyManagement,
        \TmKeyManagement_Filter,
        \Exception,
        \Log,
        \WorkerClient,
        \Utils,
        \CatUtils,
        \INIT
    ;

use TaskRunner\Commons\ContextList;

/**
 * Class Set
 * @package Contribution
 *
 */
class Set {

    /**
     * @param ContributionStruct $contribution
     *
     * @throws \Exception
     */
    public static function contribution( ContributionStruct $contribution ){

        try{
            WorkerClient::enqueue( 'CONTRIBUTION', '\AsyncTasks\Workers\SetContributionWorker', $contribution, array( 'persistent' => WorkerClient::$_HANDLER->persistent ) );
        } catch ( Exception $e ){

            # Handle the error, logging, ...
            $output  = "**** SetContribution failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            Log::doLog( $output );
            throw $e;

        }

    }

    public static function contributionMT( ContributionStruct $contribution ){

    }

}