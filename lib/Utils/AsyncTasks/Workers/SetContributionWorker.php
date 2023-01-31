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
use INIT;
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
     * @return null
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

        $this->_loadEngine( $contributionStruct );

        $config             = $this->_engine->getConfigStruct();
        $config[ 'source' ] = $jobStruct->source;
        $config[ 'target' ] = $jobStruct->target;
        $config[ 'email' ]  = $contributionStruct->api_key;

        $config = array_merge( $config, $this->_extractAvailableKeysForUser( $contributionStruct, $jobStruct ) );

        try {

            $this->_update( $config, $contributionStruct );
            $this->_doLog( "Key UPDATE -- Job: $contributionStruct->id_job, Segment: $contributionStruct->id_segment " );

        } catch ( ReQueueException $e ) {
            $this->_doLog( $e->getMessage() );
            throw $e;
        }

    }

    /**
     * !Important Refresh the engine ID for each queueElement received
     * to avoid set contributions on the wrong engine ID
     *
     * @param ContributionSetStruct $contributionStruct
     *
     * @throws Exception
     * @throws ValidationError
     */
    protected function _loadEngine( ContributionSetStruct $contributionStruct ) {

        $jobStruct = $contributionStruct->getJobStruct();
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
     */
    protected function _set( array $config, ContributionSetStruct $contributionStruct ) {

        $config[ 'segment' ]        = $contributionStruct->segment;
        $config[ 'translation' ]    = $contributionStruct->translation;
        $config[ 'context_after' ]  = $contributionStruct->context_after;
        $config[ 'context_before' ] = $contributionStruct->context_before;

        //get the Props
        $config[ 'prop' ] = json_encode( $contributionStruct->getProp() );

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->set( $config );
        if ( !$res ) {
            //reset the engine
            $this->_raiseException( 'Set', $config );
        }

    }

    /**
     * @param array                 $config
     * @param ContributionSetStruct $contributionStruct
     *
     * @throws ReQueueException
     * @throws ValidationError
     */
    protected function _update( array $config, ContributionSetStruct $contributionStruct ) {

        // update the contribution for every key in the job belonging to the user
        $config[ 'segment' ]        = $contributionStruct->oldSegment;
        $config[ 'translation' ]    = $contributionStruct->oldTranslation;
        $config[ 'context_after' ]  = $contributionStruct->context_after;
        $config[ 'context_before' ] = $contributionStruct->context_before;
        $config[ 'prop' ]           = json_encode( $contributionStruct->getProp() );

        $config[ 'newsegment' ]     = $contributionStruct->segment;
        $config[ 'newtranslation' ] = $contributionStruct->translation;

        $res = $this->_engine->update( $config );
        if ( !$res ) {
            //reset the engine
            $this->_raiseException( 'Update', $config );
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
            foreach ( $tm_keys as $i => $tm_info ) {
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
    protected function _raiseException( $type, array $config ) {
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

}