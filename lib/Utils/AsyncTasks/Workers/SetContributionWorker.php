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
use Engines_EngineInterface;
use Exception;
use Exceptions\ValidationError;
use Jobs_JobStruct;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
use TmKeyManagement_Filter;
use TmKeyManagement_TmKeyManagement;

class SetContributionWorker extends AbstractWorker {

    const ERR_SET_FAILED    = 4;
    const ERR_UPDATE_FAILED = 6;
    const ERR_NO_TM_ENGINE  = 5;

    const REDIS_PROPAGATED_ID_KEY = "j:%s:s:%s";

    /**
     * @var Engines_EngineInterface
     */
    protected $_engine;

    /**
     * This method is for testing purpose. Set a dependency injection
     *
     * @param Engines_EngineInterface $_tms
     */
    public function setEngine( $_tms ) {
        $this->_engine = $_tms;
    }

    /**
     * @param AbstractElement $queueElement
     *
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws Exception
     * @throws ValidationError
     */
    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );

        $contributionStruct = new ContributionSetStruct( $queueElement->params->toArray() );

        $this->_checkDatabaseConnection();

        $this->_execContribution( $contributionStruct );

    }

    /**
     * @param ContributionSetStruct $contributionStruct
     *
     * @throws ReQueueException
     * @throws Exception
     * @throws ValidationError
     */
    protected function _execContribution( ContributionSetStruct $contributionStruct ) {

        $jobStruct = $contributionStruct->getJobStruct();

        if ( empty( $jobStruct ) ) {
            throw new Exception( "Job not found. Password changed?" );
        }

        $this->_loadEngine( $jobStruct );

        $config             = $this->_engine->getConfigStruct();
        $config[ 'source' ] = $jobStruct->source;
        $config[ 'target' ] = $jobStruct->target;
        $config[ 'email' ]  = $contributionStruct->api_key;

        $config = array_merge( $config, $this->_extractAvailableKeysForUser( $contributionStruct, $jobStruct ) );

        try {

            $this->_update( $config, $contributionStruct, $jobStruct->id_mt_engine );
            $this->_doLog( "Key UPDATE -- Job: $contributionStruct->id_job, Segment: $contributionStruct->id_segment " );

        } catch ( ReQueueException $e ) {
            $this->_doLog( $e->getMessage() );
            throw $e;
        }

    }

    /**
     * !Important Refresh the engine ID for each queueElement received
     * to avoid set contributions to the wrong engine ID
     *
     * @param Jobs_JobStruct $jobStruct
     *
     * @throws Exception
     * @throws ValidationError
     */
    protected function _loadEngine( Jobs_JobStruct $jobStruct ) {

        if ( empty( $this->_engine ) ) {
            $this->_engine = Engine::getInstance( $jobStruct->id_tms ); //Load MyMemory
        }

    }

    /**
     * @param array                 $config
     * @param ContributionSetStruct $contributionStruct
     *
     * @throws ReQueueException
     * @throws ValidationError
     * @throws EndQueueException
     */
    protected function _set( array $config, ContributionSetStruct $contributionStruct ) {

        $jobStruct = $contributionStruct->getJobStruct();

        $config[ 'uid' ]            = $contributionStruct->uid;
        $config[ 'segment' ]        = $contributionStruct->segment;
        $config[ 'translation' ]    = $contributionStruct->translation;
        $config[ 'context_after' ]  = $contributionStruct->context_after;
        $config[ 'context_before' ] = $contributionStruct->context_before;
        $config[ 'set_mt' ]         = ( $jobStruct->id_mt_engine != 1 ) ? false : true;

        //get the Props
        $config[ 'prop' ] = json_encode( $contributionStruct->getProp() );

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->set( $config );
        if ( $res->responseStatus >= 400 && $res->responseStatus < 500 ) {
            $this->_raiseEndQueueException( 'Set', $config );
        } elseif ( $res->responseStatus != 200 ) {
            $this->_raiseReQueueException( 'Set', $config );
        } else {
            $this->_doLog( "Set complete" );
        }

    }

    /**
     * @param array                 $config
     * @param ContributionSetStruct $contributionStruct
     * @param int                   $id_mt_engine
     *
     * @throws ReQueueException
     * @throws ValidationError
     * @throws EndQueueException
     */
    protected function _update( array $config, ContributionSetStruct $contributionStruct, $id_mt_engine = 1 ) {

        // update the contribution for every key in the job belonging to the user
        $config[ 'uid' ]            = $contributionStruct->uid;
        $config[ 'segment' ]        = $contributionStruct->oldSegment;
        $config[ 'translation' ]    = $contributionStruct->oldTranslation;
        $config[ 'context_after' ]  = $contributionStruct->context_after;
        $config[ 'context_before' ] = $contributionStruct->context_before;
        $config[ 'prop' ]           = json_encode( $contributionStruct->getProp() );
        $config[ 'set_mt' ]         = ($id_mt_engine != 1) ? false : true;

        $config[ 'newsegment' ]     = $contributionStruct->segment;
        $config[ 'newtranslation' ] = $contributionStruct->translation;

        $this->_doLog( "Executing Update on " . get_class( $this->_engine ) );
        $res = $this->_engine->update( $config );

        if ( $res->responseStatus >= 400 && $res->responseStatus < 500 ) {
            $this->_raiseEndQueueException( 'Update', $config );
        } elseif ( $res->responseStatus != 200 ) {
            $this->_raiseReQueueException( 'Update', $config );
        } else {
            $this->_doLog( "Update complete" );
        }

    }

    /**
     * @param ContributionSetStruct $contributionStruct
     * @param Jobs_JobStruct        $jobStruct
     *
     * @return array
     * @throws Exception
     */
    protected function _extractAvailableKeysForUser( ContributionSetStruct $contributionStruct, Jobs_JobStruct $jobStruct ) {

        if ( $contributionStruct->fromRevision ) {
            $userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } else {
            $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
        }

        //find all the job's TMs with write grants and make a contribution to them
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $jobStruct->tm_keys, 'w', 'tm', $contributionStruct->uid, $userRole );

        $config = [];
        if ( !empty( $tm_keys ) ) {

            $config[ 'keys' ] = [];
            foreach ( $tm_keys as $tm_info ) {
                $config[ 'id_user' ][] = $tm_info->key;
            }

        }

        return $config;

    }

    /**
     * @param       $type
     * @param array $config
     *
     * @throws ReQueueException
     */
    protected function _raiseReQueueException( $type, array $config ) {
        //reset the engine
        $engineName    = get_class( $this->_engine );
        $this->_engine = null;

        switch ( strtolower( $type ) ) {
            case 'update':
                $errNum = self::ERR_UPDATE_FAILED;
                break;
            case 'set':
            default:
                $errNum = self::ERR_SET_FAILED;
                break;
        }

        throw new ReQueueException( "$type failed on " . $engineName . ": Values " . var_export( $config, true ), $errNum );
    }

    /**
     * @param string $type
     * @param array  $config
     *
     * @throws EndQueueException
     */
    protected function _raiseEndQueueException( $type, array $config ) {
        //reset the engine
        $engineName    = get_class( $this->_engine );
        $this->_engine = null;

        switch ( strtolower( $type ) ) {
            case 'update':
                $errNum = self::ERR_UPDATE_FAILED;
                break;
            case 'set':
            default:
                $errNum = self::ERR_SET_FAILED;
                break;
        }

        throw new EndQueueException( "$type failed on " . $engineName . ": Values " . var_export( $config, true ), $errNum );
    }

}