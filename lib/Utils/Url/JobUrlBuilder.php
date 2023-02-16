<?php

namespace Url;

use Jobs_JobStruct;
use Projects_ProjectDao;
use Projects_ProjectStruct;

class JobUrlBuilder {

    /**
     * Build the job url from Jobs_JobStruct
     *
     * Optional parameters:
     * - id_segment
     * - httphost
     *
     * Returns null in case of wrong parameters
     *
     * @param Jobs_JobStruct              $job
     * @param array                       $options
     * @param Projects_ProjectStruct|null $project
     *
     * @return JobUrlStruct
     */
    public static function createFromJobStruct( Jobs_JobStruct $job, $options = [], Projects_ProjectStruct $project = null ) {

        // 1. if project is passed we gain a query
        if( $project == null ){
            // 2. find the correlated project, if not passed
            $project = Projects_ProjectDao::findById( $job->id_project );
        }

        if ( !$project ) {
            return null;
        }

        // 3. get passwords array
        $passwords   = [];
        $sourcePages = [
                JobUrlStruct::LABEL_T  => 1,
                JobUrlStruct::LABEL_R1 => 2,
                JobUrlStruct::LABEL_R2 => 3
        ];

        foreach ( $sourcePages as $label => $sourcePage ) {
            $passwords[ $label ] = \CatUtils::getJobPassword( $job, $sourcePage );
        }

        // 4. httpHost
        $httpHost = ( isset( $options[ 'http_host' ] ) ) ? $options[ 'http_host' ] : null;

        // 5. add segment id only if belongs to the job
        $segmentId = null;
        if(isset( $options[ 'id_segment' ] )){
            if(isset( $options[ 'skip_check_segment' ] ) and isset( $options[ 'skip_check_segment' ] ) === true){
                $segmentId = (isset( $options[ 'id_segment' ] ) ) ? $options[ 'id_segment' ] : null;
            } else {
                if ( ( $job->job_first_segment <= $options[ 'id_segment' ] ) and ( $options[ 'id_segment' ] <= $job->job_last_segment ) ) {
                    $segmentId = $options[ 'id_segment' ];
                }
            }
        }

        return new JobUrlStruct(
                $job->id,
                $project->name,
                $job->source,
                $job->target,
                $passwords,
                $httpHost,
                $segmentId
        );
    }
}