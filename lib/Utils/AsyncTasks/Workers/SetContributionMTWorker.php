<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/16
 * Time: 20.36
 *
 */

namespace AsyncTasks\Workers;

use Contribution\ContributionSetStruct;
use Engine;
use Exception;
use Exceptions\ValidationError;
use Jobs_JobStruct;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
use TmKeyManagement_TmKeyManagement;

class SetContributionMTWorker extends SetContributionWorker {

    const REDIS_PROPAGATED_ID_KEY = "mt_j:%s:s:%s";

    /**
     * @param ContributionSetStruct $contributionStruct
     *
     * @throws EndQueueException
     * @see SetContributionWorker::_loadEngine
     *
     */
    protected function _loadEngine( ContributionSetStruct $contributionStruct ) {

        try {
            $this->_engine = Engine::getInstance( $contributionStruct->id_mt ); //Load MT Adaptive Engine
        } catch ( Exception $e ) {
            throw new EndQueueException( $e->getMessage(), self::ERR_NO_TM_ENGINE );
        }

    }

    protected function _set( array $config, ContributionSetStruct $contributionStruct ) {

        $config[ 'segment' ]     = $contributionStruct->segment;
        $config[ 'translation' ] = $contributionStruct->translation;

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->set( $config );

        if ( !$res ) {
            $this->_raiseException( 'set', $config );
        }

    }

    /**
     * @param array                 $config
     * @param ContributionSetStruct $contributionStruct
     *
     * @throws ReQueueException
     */
    protected function _update( array $config, ContributionSetStruct $contributionStruct ) {

        $config[ 'segment' ]     = $contributionStruct->segment;
        $config[ 'translation' ] = $contributionStruct->translation;
        $config[ 'tuid' ]        = $contributionStruct->id_job . ":" . $contributionStruct->id_segment;

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->update( $config );

        if ( !$res ) {
            $this->_raiseException( 'update', $config );
        }

    }

    protected function _extractAvailableKeysForUser( ContributionSetStruct $contributionStruct, Jobs_JobStruct $jobStruct ) {

        //find all the job's TMs with write grants and make a contribution to them
        $tm_keys = TmKeyManagement_TmKeyManagement::getOwnerKeys( [ $jobStruct->tm_keys ], 'w' );

        $config           = [];
        $config[ 'keys' ] = array_map( function ( $tm_key ) {
            return $tm_key->key;
        }, $tm_keys );

        return $config;

    }

}