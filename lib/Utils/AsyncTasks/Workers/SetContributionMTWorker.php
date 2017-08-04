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
        TaskRunner\Exceptions\ReQueueException,
        Jobs_JobStruct;
use Exception;
use INIT;

class SetContributionMTWorker extends SetContributionWorker {

    const REDIS_PROPAGATED_ID_KEY = "mt_j:%s:s:%s";

    protected function _loadEngine( Jobs_JobStruct $jobStruct ){
        if( empty( $this->_engine ) ){
            try {
                $this->_engine = Engine::getInstance( $jobStruct->id_mt_engine ); //Load MT Adaptive Engine
            } catch( Exception $e ){
                throw new EndQueueException( $e->getMessage(), self::ERR_NO_TM_ENGINE );
            }
        }
    }

    protected function _set( Array $config, ContributionStruct $contributionStruct ){

        $config[ 'segment' ]     = $contributionStruct->segment;
        $config[ 'translation' ] = $contributionStruct->translation;

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->set( $config );
        if ( !$res ) {
            throw new ReQueueException( "Set failed on " . get_class( $this->_engine ) . ": Values " . var_export( $config, true ), self::ERR_SET_FAILED );
        }

    }

    protected function _update( Array $config, ContributionStruct $contributionStruct ){
        parent::_update( $config, $contributionStruct );
    }

    protected function _extractAvailableKeysForUser( ContributionStruct $contributionStruct, Jobs_JobStruct $jobStruct ){

        $config = [];
        $config[ 'keys' ] = array_map( function( $tm_key ){
            return $tm_key[ 'key' ];
        }, $jobStruct->getOwnerKeys() );

        return $config;

    }

}