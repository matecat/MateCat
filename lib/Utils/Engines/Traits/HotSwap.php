<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 17/08/18
 * Time: 12.29
 *
 */

namespace Engines\Traits;


use Jobs_JobStruct;
use RedisHandler;

trait HotSwap {

    /**
     * @param Jobs_JobStruct $jobStruct
     *
     * @param int            $newMT
     *
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    protected function swapOn( Jobs_JobStruct $jobStruct, $newMT = 1 ){ // 1 == MyMemory
        $redisConn = ( new RedisHandler() )->getConnection();
        if( $redisConn->setnx( "_old_mt_engine:" . $jobStruct->id_project, $jobStruct->id_mt_engine ) ){
            $redisConn->expire( "_old_mt_engine:" . $jobStruct->id_project, 60 * 60 * 24 );
            $jobStruct->id_mt_engine = $newMT;
        }
    }

    /**
     * @param $project_id
     *
     * @throws \Exceptions\ValidationError
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    protected function swapOff( $project_id ){

        //There should be more than one job per project, to be generic use a foreach
        $jobDao = new \Jobs_JobDao();
        $jobStructs = $jobDao->getByProjectId( $project_id, 60 );

        $redisConn = ( new RedisHandler() )->getConnection();
        $old_mt_engine = $redisConn->get( "_old_mt_engine:" . $jobStructs[0]->id_project ); //Keep only the first one in the list ( all jobs have the same mt )
        if( $redisConn->del( "_old_mt_engine:" . $jobStructs[0]->id_project ) ){ //avoid race conditions from plugins
            foreach ( $jobStructs as $jobStruct ){
                $jobStruct->id_mt_engine = $old_mt_engine;
                $jobDao->updateStruct( $jobStruct );
            }
        }

    }

}