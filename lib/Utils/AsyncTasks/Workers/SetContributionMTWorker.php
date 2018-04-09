<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/16
 * Time: 20.36
 *
 */

namespace AsyncTasks\Workers;

use Contribution\ContributionStruct,
        Engine,
        TaskRunner\Exceptions\EndQueueException,
        Jobs_JobStruct;
use Exception;
use TmKeyManagement_TmKeyManagement;

class SetContributionMTWorker extends SetContributionWorker {

    const REDIS_PROPAGATED_ID_KEY = "mt_j:%s:s:%s";

    /**
     * @see SetContributionWorker::_loadEngine
     * @param ContributionStruct $contributionStruct
     *
     * @throws EndQueueException
     */
    protected function _loadEngine( ContributionStruct $contributionStruct ){

        try {
            $this->_engine = Engine::getInstance( $contributionStruct->id_mt ); //Load MT Adaptive Engine
        } catch( Exception $e ){
            throw new EndQueueException( $e->getMessage(), self::ERR_NO_TM_ENGINE );
        }

    }

    protected function _set( Array $config, ContributionStruct $contributionStruct ){

        $config[ 'segment' ]     = $contributionStruct->segment;
        $config[ 'translation' ] = $contributionStruct->translation;

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->set( $config );

        if ( !$res ) {
            $this->_raiseException( 'Set', $config );
        }

    }

    protected function _extractAvailableKeysForUser( ContributionStruct $contributionStruct, Jobs_JobStruct $jobStruct ){

        //find all the job's TMs with write grants and make a contribution to them
        $tm_keys = TmKeyManagement_TmKeyManagement::getOwnerKeys( [ $jobStruct->tm_keys ], 'w' );

        $config = [];
        $config[ 'keys' ] = array_map( function( $tm_key ){
            return $tm_key->key;
        }, $tm_keys );

        return $config;

    }

}