<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/16
 * Time: 20.36
 *
 */

namespace Utils\AsyncTasks\Workers;

use Exception;
use Model\Exceptions\ValidationError;
use Model\Jobs\JobStruct;
use Utils\Contribution\SetContributionRequest;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EngineInterface;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara\Headers;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\Filter;
use Utils\TmKeyManagement\TmKeyManager;

class SetContributionWorker extends AbstractWorker
{

    const int ERR_SET_FAILED    = 4;
    const int ERR_UPDATE_FAILED = 6;
    const int ERR_NO_TM_ENGINE  = 5;

    /**
     * @var ?EngineInterface
     */
    protected ?EngineInterface $_engine = null;

    /**
     * This method is for testing purpose. Set a dependency injection
     *
     * @param EngineInterface $_tms
     */
    public function setEngine(EngineInterface $_tms): void
    {
        $this->_engine = $_tms;
    }

    private function toSetContributionRequest(QueueElement $queueElement): SetContributionRequest
    {
        $queueElement->params->jobStruct = new JobStruct($queueElement->params->jobStruct->toArray());

        return new SetContributionRequest($queueElement->params->toArray());
    }

    /**
     * @param AbstractElement $queueElement
     *
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws Exception
     * @throws ValidationError
     */
    public function process(AbstractElement $queueElement): void
    {
        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd($queueElement);

        $this->_checkDatabaseConnection();

        $this->_execContribution(
                $this->toSetContributionRequest($queueElement)
        );
    }

    /**
     * @param SetContributionRequest $contributionStruct
     *
     * @throws ReQueueException
     * @throws Exception
     * @throws ValidationError
     */
    protected function _execContribution(SetContributionRequest $contributionStruct): void
    {
        $jobStruct = $contributionStruct->getJobStruct();

        $this->_loadEngine($jobStruct);

        /**
         * @see AbstractEngine::$_isAdaptiveMT
         */
        if (!$this->_engine->isAdaptiveMT() && !$this->_engine->isTMS()) {
            return;
        }

        $config             = $this->_engine->getConfigStruct();
        $config[ 'source' ] = $jobStruct->source;
        $config[ 'target' ] = $jobStruct->target;
        $config[ 'email' ]  = $contributionStruct->api_key;

        $config = array_merge($config, $this->_extractAvailableKeysForUser($contributionStruct, $jobStruct));

        try {
            $this->_update($config, $contributionStruct, $jobStruct->id_mt_engine);
            $this->_doLog("Key UPDATE -- Job: $contributionStruct->id_job, Segment: $contributionStruct->id_segment ");
        } catch (ReQueueException $e) {
            $this->_doLog($e->getMessage());
            throw $e;
        }
    }

    /**
     * !Important Refresh the engine ID for each queueElement received
     * to avoid set contributions to the wrong engine ID
     *
     * @param JobStruct $jobStruct
     *
     * @throws Exception
     * @throws ValidationError
     */
    protected function _loadEngine(JobStruct $jobStruct): void
    {
        if (empty($this->_engine) || $jobStruct->id_tms != $this->_engine->getEngineRecord()->id) {
            $this->_engine = EnginesFactory::getInstance($jobStruct->id_tms); //Load MyMemory
        }
    }

    /**
     * @param array                  $config
     * @param SetContributionRequest $contributionStruct
     *
     * @throws ReQueueException
     * @throws ValidationError
     * @throws EndQueueException
     */
    protected function _set(array $config, SetContributionRequest $contributionStruct): void
    {
        $jobStruct = $contributionStruct->getJobStruct();

        $config[ 'uid' ]            = $contributionStruct->uid;
        $config[ 'segment' ]        = $contributionStruct->segment;
        $config[ 'translation' ]    = $contributionStruct->translation;
        $config[ 'context_after' ]  = $contributionStruct->context_after;
        $config[ 'context_before' ] = $contributionStruct->context_before;
        $config[ 'set_mt' ]         = !(($jobStruct->id_mt_engine != 1));

        //get the Props
        $config[ 'prop' ] = json_encode($contributionStruct->getProp());

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->set($config);
        if ($res->responseStatus >= 400 && $res->responseStatus < 500) {
            $this->_raiseEndQueueException('Set', $config);
        } elseif ($res->responseStatus != 200) {
            $this->_raiseReQueueException('Set', $config);
        } else {
            $this->_doLog("Set complete");
        }
    }

    /**
     * @param array $config
     * @param SetContributionRequest $contributionStruct
     * @param int $id_mt_engine
     *
     * @throws EndQueueException
     * @throws ReQueueException
     */
    protected function _update(array $config, SetContributionRequest $contributionStruct, int $id_mt_engine = 1): void
    {
        // update the contribution for every key in the job belonging to the user
        $config[ 'uid' ]            = $contributionStruct->uid;
        $config[ 'segment' ]        = $contributionStruct->oldSegment;
        $config[ 'translation' ]    = $contributionStruct->oldTranslation;
        $config[ 'context_after' ]  = $contributionStruct->context_after;
        $config[ 'context_before' ] = $contributionStruct->context_before;
        $config[ 'prop' ]           = json_encode(
                array_merge(
                        $contributionStruct->getProp(),
                        (new Headers($contributionStruct->id_job . ":" . $contributionStruct->id_segment, $contributionStruct->translation_origin))->getArrayCopy()
                )
        );
        $config[ 'set_mt' ]         = !(($id_mt_engine != 1));

        $config[ 'newsegment' ]     = $contributionStruct->segment;
        $config[ 'newtranslation' ] = $contributionStruct->translation;
        $config[ 'spiceMatch' ]     = $contributionStruct->contextIsSpice;

        $this->_doLog("Executing Update on " . get_class($this->_engine));
        $res = $this->_engine->update($config);

        if ($res->responseStatus >= 400 && $res->responseStatus < 500) {
            $this->_raiseEndQueueException('Update', $config);
        } elseif ($res->responseStatus != 200) {
            $this->_raiseReQueueException('Update', $config);
        } else {
            $this->_doLog("Update complete");
        }
    }

    /**
     * @param SetContributionRequest $contributionStruct
     * @param JobStruct              $jobStruct
     *
     * @return array
     * @throws Exception
     */
    protected function _extractAvailableKeysForUser(SetContributionRequest $contributionStruct, JobStruct $jobStruct): array
    {
        if ($contributionStruct->fromRevision) {
            $userRole = Filter::ROLE_REVISOR;
        } else {
            $userRole = Filter::ROLE_TRANSLATOR;
        }

        //find all the job's TMs with write grants and make a contribution to them
        $tm_keys = TmKeyManager::getJobTmKeys($jobStruct->tm_keys, 'w', 'tm', $contributionStruct->uid, $userRole);

        $config = [];
        if (!empty($tm_keys)) {
            $config[ 'keys' ] = [];
            foreach ($tm_keys as $tm_info) {
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
    protected function _raiseReQueueException($type, array $config)
    {
        //reset the engine
        $engineName    = get_class($this->_engine);
        $this->_engine = null;

        switch (strtolower($type)) {
            case 'update':
                $errNum = self::ERR_UPDATE_FAILED;
                break;
            case 'set':
            default:
                $errNum = self::ERR_SET_FAILED;
                break;
        }

        throw new ReQueueException("$type failed on " . $engineName . ": Values " . var_export($config, true), $errNum);
    }

    /**
     * @param string $type
     * @param array  $config
     *
     * @throws EndQueueException
     */
    protected function _raiseEndQueueException(string $type, array $config)
    {
        //reset the engine
        $engineName    = get_class($this->_engine);
        $this->_engine = null;

        switch (strtolower($type)) {
            case 'update':
                $errNum = self::ERR_UPDATE_FAILED;
                break;
            case 'set':
            default:
                $errNum = self::ERR_SET_FAILED;
                break;
        }

        throw new EndQueueException("$type failed on " . $engineName . ": Values " . var_export($config, true), $errNum);
    }

}