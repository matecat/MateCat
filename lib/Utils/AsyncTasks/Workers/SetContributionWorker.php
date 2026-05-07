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

    const int ERR_SET_FAILED = 4;
    const int ERR_UPDATE_FAILED = 6;
    const int ERR_NO_TM_ENGINE = 5;

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
     */
    public function process(AbstractElement $queueElement): void
    {
        if (!$queueElement instanceof QueueElement) {
            throw new EndQueueException('Invalid queue element type for SetContributionWorker::process');
        }

        $this->_checkForReQueueEnd($queueElement);

        $this->_checkDatabaseConnection();

        $contributionStruct = $this->toSetContributionRequest($queueElement);

        $this->setEngine(
            $this->_loadEngine($contributionStruct->getJobStruct())
        );

        $this->_execContribution($contributionStruct);
    }

    /**
     * @param SetContributionRequest $contributionStruct
     *
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws Exception
     */
    protected function _execContribution(SetContributionRequest $contributionStruct): void
    {
        $jobStruct = $contributionStruct->getJobStruct();
        $engine = $this->requireEngine();

        /**
         * @see AbstractEngine::$_isAdaptiveMT
         */
        if (!$engine->isAdaptiveMT() && !$engine->isTMS()) {
            return;
        }

        $config = $engine->getConfigStruct();
        $config['source'] = $jobStruct->source;
        $config['target'] = $jobStruct->target;
        $config['email'] = $contributionStruct->api_key;

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
     * @return AbstractEngine
     * @throws Exception
     */
    protected function _loadEngine(JobStruct $jobStruct): AbstractEngine
    {
        try {
            $engine = EnginesFactory::getInstance($jobStruct->id_tms, AbstractEngine::class); //Load MyMemory

            return $engine;
        } catch (Exception $e) {
            throw new EndQueueException($e->getMessage(), self::ERR_NO_TM_ENGINE);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param SetContributionRequest $contributionStruct
     *
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws \LogicException
     */
    protected function _set(array $config, SetContributionRequest $contributionStruct): void
    {
        $engine = $this->requireEngine();
        $jobStruct = $contributionStruct->getJobStruct();

        $config['uid'] = $contributionStruct->uid;
        $config['segment'] = $contributionStruct->segment;
        $config['translation'] = $contributionStruct->translation;
        $config['context_after'] = $contributionStruct->context_after;
        $config['context_before'] = $contributionStruct->context_before;
        $config['set_mt'] = !(($jobStruct->id_mt_engine != 1));

        //get the Props
        $config['prop'] = json_encode($contributionStruct->getProp());

        // set the contribution for every key in the job belonging to the user
        $res = $engine->set($config);
        $responseStatus = (is_object($res) && isset($res->responseStatus) && is_numeric($res->responseStatus)) ? (int)$res->responseStatus : null;

        if ($responseStatus !== null && $responseStatus >= 200 && $responseStatus < 300) {
            $this->_doLog("Update complete");
        } elseif ($responseStatus !== null && $responseStatus >= 400 && $responseStatus < 500) {
            $this->_raiseEndQueueException('Update', $config);
        } else {
            $this->_raiseReQueueException('Update', $config);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param SetContributionRequest $contributionStruct
     * @param int $id_mt_engine
     *
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws \LogicException
     */
    protected function _update(array $config, SetContributionRequest $contributionStruct, int $id_mt_engine = 1): void
    {
        $engine = $this->requireEngine();
        // update the contribution for every key in the job belonging to the user
        $config['uid'] = $contributionStruct->uid;
        $config['segment'] = $contributionStruct->oldSegment;
        $config['translation'] = $contributionStruct->oldTranslation;
        $config['context_after'] = $contributionStruct->context_after;
        $config['context_before'] = $contributionStruct->context_before;
        $config['prop'] = json_encode(
            array_merge(
                $contributionStruct->getProp(),
                (new Headers($contributionStruct->id_job . ":" . $contributionStruct->id_segment, $contributionStruct->translation_origin))->getArrayCopy()
            )
        );
        $config['set_mt'] = !(($id_mt_engine != 1));

        $config['newsegment'] = $contributionStruct->segment;
        $config['newtranslation'] = $contributionStruct->translation;
        $config['spiceMatch'] = $contributionStruct->contextIsSpice;

        $this->_doLog("Executing Update on " . get_class($engine));

        $res = $engine->update($config);
        $responseStatus = (is_object($res) && isset($res->responseStatus) && is_numeric($res->responseStatus)) ? (int)$res->responseStatus : null;

        if ($responseStatus !== null && $responseStatus >= 200 && $responseStatus < 300) {
            $this->_doLog("Update complete");
        } elseif ($responseStatus !== null && $responseStatus >= 400 && $responseStatus < 500) {
            $this->_raiseEndQueueException('Update', $config);
        } else {
            $this->_raiseReQueueException('Update', $config);
        }
    }

    /**
     * @param SetContributionRequest $contributionStruct
     * @param JobStruct $jobStruct
     *
     * @return array<string, mixed>
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
            $config['keys'] = [];
            foreach ($tm_keys as $tm_info) {
                $config['id_user'][] = $tm_info->key;
            }
        }

        return $config;
    }

    /**
     * @param string $type
     * @param array<string, mixed> $config
     *
     * @throws ReQueueException
     */
    protected function _raiseReQueueException(string $type, array $config): never
    {
        //reset the engine
        $engineName = $this->_engine !== null ? get_class($this->_engine) : 'unknown';
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
     * @param array<string, mixed> $config
     *
     * @throws EndQueueException
     */
    protected function _raiseEndQueueException(string $type, array $config): never
    {
        //reset the engine
        $engineName = $this->_engine !== null ? get_class($this->_engine) : 'unknown';
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

    /**
     * @throws \LogicException
     */
    protected function requireEngine(): EngineInterface
    {
        if ($this->_engine === null) {
            throw new \LogicException('TM engine is not initialized');
        }

        return $this->_engine;
    }

}
