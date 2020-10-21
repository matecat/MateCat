<?php

namespace Url;

use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\ReviewExtended\ReviewUtils;
use Jobs_JobStruct;
use Projects_ProjectDao;

class UrlBuilder {

    /**
     * Build the job url from job id/password
     *
     * Optional parameters:
     * - segment id
     * - revision number (could be 1 or 2)
     *
     * Returns null in case of wrong parameters
     *
     * @param int    $jobId
     * @param string $jobPassword
     * @param null   $segmentId
     * @param null   $revisionNumber
     *
     * @return string
     */
    public static function getJobUrl( $jobId, $jobPassword, $segmentId = null, $revisionNumber = null ) {

        // 1. find job
        $job = \Jobs_JobDao::getByIdAndPassword( $jobId, $jobPassword );
        if(!$job){
            return null;
        }

        // 2. find the correlated project
        $project = Projects_ProjectDao::findById( $job->id_project );
        if(!$project){
            return null;
        }

        // 3. get jobType and password
        $sourcePage = ReviewUtils::revisionNumberToSourcePage( $revisionNumber );
        $jobType    = self::getJobType( $sourcePage );
        $password   = self::getPassword( $job, $sourcePage );

        if(!$jobType or !$password){
            return null;
        }

        $url = \INIT::$HTTPHOST;
        $url .= DIRECTORY_SEPARATOR;
        $url .= $jobType;
        $url .= DIRECTORY_SEPARATOR;
        $url .= $project->name;
        $url .= DIRECTORY_SEPARATOR;
        $url .= $job->source . '-' . $job->target;
        $url .= DIRECTORY_SEPARATOR;
        $url .= $job->id . '-' . $password;

        if ( $segmentId ) {
            $url .= '#' . $segmentId;
        }

        return $url;
    }

    /**
     * Get the job type:
     *
     * - translate
     * - revise
     * - revise(n)
     *
     * @param $sourcePage
     *
     * @return string|null
     */
    private static function getJobType( $sourcePage ) {
        if ( $sourcePage == 1 ) {
            return 'translate';
        }

        if ( $sourcePage == 2 ) {
            return 'revise';
        }

        if ( $sourcePage > 2 ) {
            return 'revise' . ( $sourcePage - 1 );
        }

        return null;
    }

    /**
     * @param Jobs_JobStruct $jobs_JobStruct
     * @param                $sourcePage
     *
     * @return string|null
     */
    private static function getPassword( Jobs_JobStruct $jobs_JobStruct, $sourcePage ) {
        if ( $sourcePage == 1 ) {
            return $jobs_JobStruct->password;
        }

        $qa = ChunkReviewDao::findByIdJobAndPasswordAndSourcePage( $jobs_JobStruct->id, $jobs_JobStruct->password, $sourcePage );
        if(!$qa){
            return null;
        }

        return $qa->review_password;
    }
}