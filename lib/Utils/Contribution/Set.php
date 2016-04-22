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
     * @param \AMQHandler|null   $handler
     *
     * @throws \Exception
     */
    public static function contribution( ContributionStruct $contribution, \AMQHandler $handler = null ){

        //dependency Injection purpose
        if ( empty( $handler ) ){
           $handler = new \AMQHandler();
        }

        //First Execution, load build object
        $contextList = ContextList::get( \INIT::$TASK_RUNNER['context_definitions'] );
        try{
            $handler->send( $contextList->list['CONTRIBUTION']->queue_name, $contribution, array( 'persistent' => $handler->persistent ) );
        } catch ( \Exception $e ){

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