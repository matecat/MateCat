<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 17/08/18
 * Time: 12.29
 *
 */

namespace Utils\Engines\Traits;


use Exception;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use ReflectionException;
use TypeError;
use Utils\Redis\RedisHandler;

trait HotSwap
{

    /**
     * To use this trait, in a plugin call this method in
     *
     * <code>
     *     public function beforeInsertJobStruct( \JobStruct $jobStruct ){
     *             $this->swapOn( $jobStruct );
     *     }
     *</code>
     *
     * @throws ReflectionException
     * @throws Exception
     */
    protected function swapOn(RedisHandler $redisHandler, JobStruct $jobStruct, int $newMT = 1, int $newTM = 1): void
    { // 1 == MyMemory
        $redisConn = $redisHandler->getConnection();

        if ($redisConn->setnx("_old_mt_engine:" . $jobStruct->id_project . ":" . $jobStruct->password, $jobStruct->id_mt_engine)) {
            $redisConn->expire("_old_mt_engine:" . $jobStruct->id_project . ":" . $jobStruct->password, 60 * 60 * 24);
            $jobStruct->id_mt_engine = $newMT;
        }

        if ($redisConn->setnx("_old_tms_engine:" . $jobStruct->id_project . ":" . $jobStruct->password, $jobStruct->id_tms)) {
            $redisConn->expire("_old_tms_engine:" . $jobStruct->id_project . ":" . $jobStruct->password, 60 * 60 * 24);
            $jobStruct->id_tms = $newTM;
        }
    }

    /**
     * To use this trait, in a plugin call this method in
     *
     * <code>
     *     public function afterTMAnalysisCloseProject( $project_id, $_analyzed_report ) {
     *             $this->swapOff( $project_id );
     *     }
     *</code>
     *
     * @param int $project_id
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    protected function swapOff(int $project_id, ?JobDao $jobDao = null, ?RedisHandler $redisHandler = null): void
    {
        //There should be more than one job per project, to be generic use a foreach
        $jobDao = $jobDao ?? new JobDao();
        $jobStructs = $jobDao->getNotDeletedByProjectId($project_id, 60);

        $redisConn = ($redisHandler ?? new RedisHandler())->getConnection();
        foreach ($jobStructs as $jobStruct) {
            $update = false;

            $old_mt_engine = $redisConn->get("_old_mt_engine:" . $jobStruct->id_project . ":" . $jobStruct->password);
            if ($redisConn->del("_old_mt_engine:" . $jobStruct->id_project . ":" . $jobStruct->password)) {
                $jobStruct->id_mt_engine = (int) $old_mt_engine;
                $update = true;
            }

            $old_tms_engine = $redisConn->get("_old_tms_engine:" . $jobStruct->id_project . ":" . $jobStruct->password);
            if ($redisConn->del("_old_tms_engine:" . $jobStruct->id_project . ":" . $jobStruct->password)) {
                $jobStruct->id_tms = (int) $old_tms_engine;
                $update = true;
            }

            if ($update) {
                $jobDao->updateStruct($jobStruct, [
                    'fields' => [
                        'id_tms',
                        'id_mt_engine'
                    ]
                ]);
            }
        }
    }

}