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
use Model\Jobs\JobStruct;
use Utils\Contribution\SetContributionRequest;
use Utils\Engines\EnginesFactory;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\TmKeyManager;

class SetContributionMTWorker extends SetContributionWorker
{

    /**
     * @param JobStruct $jobStruct
     *
     * @throws EndQueueException
     * @see SetContributionWorker::_loadEngine
     *
     */
    protected function _loadEngine(JobStruct $jobStruct): void
    {
        if (empty($this->_engine) || $jobStruct->id_mt_engine != $this->_engine->getEngineRecord()->id) {
            try {
                $this->_engine = EnginesFactory::getInstance($jobStruct->id_mt_engine); //Load MT Adaptive EnginesFactory
            } catch (Exception $e) {
                throw new EndQueueException($e->getMessage(), self::ERR_NO_TM_ENGINE);
            }
        }
    }

    /**
     * @param array $config
     * @param SetContributionRequest $contributionStruct
     *
     * @throws Exception
     */
    protected function _set(array $config, SetContributionRequest $contributionStruct): void
    {
        $jobStruct = $contributionStruct->getJobStruct();

        $config['segment'] = $contributionStruct->segment;
        $config['translation'] = $contributionStruct->translation;
        $config['session'] = $contributionStruct->getSessionId();
        $config['uid'] = $contributionStruct->uid;
        $config['set_mt'] = $jobStruct->id_mt_engine == 1; // negate, if mt is 1, then is mymemory, and the flag set_mt must be set to true

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->set($config);

        if (!$res) {
            $this->_raiseReQueueException('set', $config);
        }
    }

    /**
     * @param array $config
     * @param SetContributionRequest $contributionStruct
     * @param int $id_mt_engine
     *
     * @throws ReQueueException
     */
    protected function _update(array $config, SetContributionRequest $contributionStruct, int $id_mt_engine = 0): void
    {
        $config['segment'] = $contributionStruct->segment;
        $config['translation'] = $contributionStruct->translation;
        $config['tuid'] = $contributionStruct->id_job . ":" . $contributionStruct->id_segment;
        $config['session'] = $contributionStruct->getSessionId();
        $config['set_mt'] = $id_mt_engine == 1; // negate, if mt is 1, then is mymemory, and the flag set_mt must be set to true
        $config['context_before'] = $contributionStruct->context_before;
        $config['context_after'] = $contributionStruct->context_after;
        $config['translation_origin'] = $contributionStruct->translation_origin;

        // set the contribution for every key in the job belonging to the user
        $res = $this->_engine->update($config);

        if (!$res) {
            $this->_raiseReQueueException('update', $config);
        }
    }

    protected function _extractAvailableKeysForUser(SetContributionRequest $contributionStruct, JobStruct $jobStruct): array
    {
        //find all the job's TMs with write grants and make a contribution to them
        $tm_keys = TmKeyManager::getOwnerKeys([$jobStruct->tm_keys], 'w');

        $config = [];
        $config['keys'] = array_map(function ($tm_key) {
            return $tm_key->key;
        }, $tm_keys);

        return $config;
    }

}