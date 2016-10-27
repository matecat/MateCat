<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/16
 * Time: 20.36
 *
 */

namespace AsyncTasks\Workers;

use CatUtils,
        Contribution\ContributionStruct,
        Engine,
        Engines_MyMemory,
        TaskRunner\Commons\AbstractWorker,
        TaskRunner\Commons\QueueElement,
        TaskRunner\Exceptions\EndQueueException,
        TaskRunner\Exceptions\ReQueueException,
        TmKeyManagement_Filter,
        TmKeyManagement_TmKeyManagement,
        TaskRunner\Commons\AbstractElement;
use INIT;

class SetContributionWorker extends AbstractWorker {

    const ERR_SET_FAILED = 4;
    const ERR_NO_TM_ENGINE = 5;

    const REDIS_PROPAGATED_ID_KEY = "j:%s:s:%s";

    /**
     * @var Engines_MyMemory
     */
    protected $_tms;

    /**
     * This method is for testing purpose. Set a dependency injection
     *
     * @param \Engines_AbstractEngine $_tms
     */
    public function setEngine( $_tms ){
        $this->_tms = $_tms;
    }

    /**
     * @param AbstractElement $queueElement
     *
     * @throws EndQueueException
     * @throws ReQueueException
     *
     * @return null
     */
    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );

        $contributionStruct = new ContributionStruct( $queueElement->params->toArray() );

        $this->_execContribution( $contributionStruct );

    }

    /**
     * Check how much times the element was re-queued and raise an Exception when the limit is reached ( 100 times )
     *
     * @param QueueElement $queueElement
     *
     * @throws EndQueueException
     */
    protected function _checkForReQueueEnd( QueueElement $queueElement ){

        /**
         *
         * check for loop re-queuing
         */
        if ( isset( $queueElement->reQueueNum ) && $queueElement->reQueueNum >= 100 ) {

            $msg = "\n\n Error Set Contribution  \n\n " . var_export( $queueElement, true );
            \Utils::sendErrMailReport( $msg );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip." );
            throw new EndQueueException( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END );

        } elseif ( isset( $queueElement->reQueueNum ) ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }

    }

    /**
     * @param ContributionStruct $contributionStruct
     *
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws \Exception
     * @throws \Exceptions\ValidationError
     */
    protected function _execContribution( ContributionStruct $contributionStruct ){

        $jobStructList = $contributionStruct->getJobStruct();
        $jobStruct = array_pop( $jobStructList );
//        $userInfoList = $contributionStruct->getUserInfo();
//        $userInfo = array_pop( $userInfoList );

        $id_tms  = $jobStruct->id_tms;

        if ( $id_tms != 0 ) {

            if( empty( $this->_tms ) ){
                $this->_tms = Engine::getInstance( 1 ); //Load MyMemory
            }

            $config = $this->_tms->getConfigStruct();
            $config[ 'source' ]      = $jobStruct->source;
            $config[ 'target' ]      = $jobStruct->target;
            $config[ 'email' ]       = $contributionStruct->api_key;

            $config = array_merge( $config, $this->_extractAvailableKeysForUser( $contributionStruct, $jobStruct ) );

            $redisSetKey = sprintf( self::REDIS_PROPAGATED_ID_KEY, $contributionStruct->id_job, $contributionStruct->id_segment );
            $isANewSet  = $this->_queueHandler->getRedisClient()->setnx( $redisSetKey, 1 );

            if( empty( $isANewSet ) && $contributionStruct->propagationRequest ){
                $this->_update( $config, $contributionStruct );
                $this->_doLog( "Key UPDATE: $redisSetKey, " . var_export( $isANewSet, true ) );
            } else {
                $this->_set( $config, $contributionStruct );
                $this->_doLog( "Key SET: $redisSetKey, " . var_export( $isANewSet, true ) );
            }

            $this->_queueHandler->getRedisClient()->expire(
                    $redisSetKey,
                    60 * 60 * 24 * INIT::JOB_ARCHIVABILITY_THRESHOLD
            ); //TTL 3 months, the time for job archivability

        } else {

            throw new EndQueueException( "No TM engine configured for the job. Skip, OK", self::ERR_NO_TM_ENGINE );
            
        }

    }

    protected function _set( Array $config, ContributionStruct $contributionStruct ){

        $config[ 'segment' ]     = CatUtils::view2rawxliff( $contributionStruct->segment );
        $config[ 'translation' ] = CatUtils::view2rawxliff( $contributionStruct->translation );

        //get the Props
        $config[ 'prop' ]        = json_encode( $contributionStruct->getProp() );

        // set the contribution for every key in the job belonging to the user
        $res = $this->_tms->set( $config );
        if ( !$res ) {
            throw new ReQueueException( "Set failed on " . get_class( $this->_tms ) . ": Values " . var_export( $config, true ), self::ERR_SET_FAILED );
        }

    }

    protected function _update( Array $config, ContributionStruct $contributionStruct ){

        // update the contribution for every key in the job belonging to the user
        $config[ 'segment' ]     = CatUtils::view2rawxliff( $contributionStruct->oldSegment );
        $config[ 'translation' ] = CatUtils::view2rawxliff( $contributionStruct->oldTranslation );

        $config[ 'newsegment' ] = CatUtils::view2rawxliff( $contributionStruct->segment );
        $config[ 'newtranslation' ] = CatUtils::view2rawxliff( $contributionStruct->translation );

        $res = $this->_tms->update( $config );
        if ( !$res ) {
            throw new ReQueueException( "Update failed on " . get_class( $this->_tms ) . ": Values " . var_export( $config, true ), self::ERR_SET_FAILED );
        }

    }

    protected function _extractAvailableKeysForUser( ContributionStruct $contributionStruct, $jobStruct ){

        if ( $contributionStruct->fromRevision ) {
            $userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } else {
            $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
        }

        //find all the job's TMs with write grants and make a contribution to them
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $jobStruct->tm_keys, 'w', 'tm', $contributionStruct->uid, $userRole  );

        $config = [];
        if ( !empty( $tm_keys ) ) {

            $config[ 'id_user' ] = array();
            foreach ( $tm_keys as $i => $tm_info ) {
                $config[ 'id_user' ][] = $tm_info->key;
            }

        }

        return $config;

    }

}