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
     * To use this trait, in a plugin call this method in
     *
     * <code>
     *     public function beforeInsertJobStruct( \Jobs_JobStruct $jobStruct ){
     *             $this->swapOn( $jobStruct );
     *     }
     *</code>
     *
     * @param Jobs_JobStruct $jobStruct
     *
     * @param int            $newMT
     *
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    protected function swapOn( Jobs_JobStruct $jobStruct, $newMT = 1 ){ // 1 == MyMemory
        $redisConn = ( new RedisHandler() )->getConnection();
        if( $redisConn->setnx( "_old_mt_engine:" . $jobStruct->id_project . ":" . $jobStruct->password, $jobStruct->id_mt_engine ) ){
            $redisConn->expire( "_old_mt_engine:" . $jobStruct->id_project . ":" . $jobStruct->password, 60 * 60 * 24 );
            $jobStruct->id_mt_engine = $newMT;
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
        foreach ( $jobStructs as $jobStruct ){
            $old_mt_engine = $redisConn->get( "_old_mt_engine:" . $jobStruct->id_project . ":" . $jobStruct->password ); //Get the old mt engine value
            if( $redisConn->del( "_old_mt_engine:" . $jobStruct->id_project . ":" . $jobStruct->password ) ) { //avoid race conditions from plugins ( delete is atomic )
                $jobStruct->id_mt_engine = $old_mt_engine;
                $jobDao->updateStruct( $jobStruct );
            }
        }

    }

}