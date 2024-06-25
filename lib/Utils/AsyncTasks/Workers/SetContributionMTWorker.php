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
use Jobs_JobStruct;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
use TmKeyManagement_TmKeyManagement;

class SetContributionMTWorker extends SetContributionWorker {

    const REDIS_PROPAGATED_ID_KEY = "mt_j:%s:s:%s";

    /**
     * @param Jobs_JobStruct $jobStruct
     *
     * @throws EndQueueException
     * @see SetContributionWorker::_loadEngine
     *
     */
    protected function _loadEngine( Jobs_JobStruct $jobStruct ) {

        try {
            $this->_engine = Engine::getInstance( $jobStruct->id_mt ); //Load MT Adaptive Engine
        } catch ( Exception $e ) {
            throw new EndQueueException( $e->getMessage(), self::ERR_NO_TM_ENGINE );
        }

    }

    /**
     * @param array $config
     * @param ContributionSetStruct $contributionStruct
     * @throws Exception
     */
    protected function _set( array $config, ContributionSetStruct $contributionStruct ) {

        $jobStruct = $contributionStruct->getJobStruct();

        $config[ 'segment' ]     = $contributionStruct->segment;
        $config[ 'translation' ] = $contributionStruct->translation;
        $config[ 'session' ]     = $contributionStruct->getSessionId();
        $config[ 'uid' ]         = $contributionStruct->uid;
        $config[ 'set_mt' ]      = ($jobStruct->id_mt_engine != 1) ? false : true;

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->set( $config );

        if ( !$res ) {
            $this->_raiseReQueueException( 'set', $config );
        }

    }

    /**
     * @param array $config
     * @param ContributionSetStruct $contributionStruct
     * @throws Exception
     */
    protected function _update( array $config, ContributionSetStruct $contributionStruct, $id_mt_engine = 0 ) {

        $config[ 'segment' ]     = $contributionStruct->segment;
        $config[ 'translation' ] = $contributionStruct->translation;
        $config[ 'tuid' ]        = $contributionStruct->id_job . ":" . $contributionStruct->id_segment;
        $config[ 'session' ]     = $contributionStruct->getSessionId();
        $config[ 'set_mt' ]      = ($id_mt_engine != 1) ? false : true;

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->update( $config );

        if ( !$res ) {
            $this->_raiseReQueueException( 'update', $config );
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