<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/01/2017
 * Time: 17:07
 */

namespace Features\ProjectCompletion;


class ProjectCompletionStatus {

    /**
     * @param \Projects_ProjectStruct $project
     */
    public static function factory( \Jobs_JobStruct $job ) {
        if ( in_array('dqf3', $job->getProject()->getFeatures()->getCodes() ) ) {
            return new JobStatus( $job );
        }
        else {
            return new ChunkStatus( new \Chunks_ChunkStruct( $job->toArray() ) ) ;
        }
    }

}