<?php

namespace Url;

use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\ReviewExtended\ReviewUtils;
use Jobs_JobStruct;
use Projects_ProjectDao;

class JobUrlBuilder {

    /**
     * Build the job url from job id/password
     *
     * Optional parameters:
     * - id_segment
     * - revision_number (could be 1 or 2)
     *
     * Returns null in case of wrong parameters
     *
     * @param int    $jobId
     * @param string $jobPassword
     * @param array  $options
     *
     * @return string
     * @throws \Exception
     */
    public static function create( $jobId, $jobPassword, $options = [] ) {

        // 1. find the job
        $job = \Jobs_JobDao::getByIdAndPassword( $jobId, $jobPassword );
        if(!$job){
            return null;
        }

        // 2. find the correlated project
        $project = Projects_ProjectDao::findById( $job->id_project );
        if(!$project){
            return null;
        }

        // 3. get job type and password
        $sourcePage = ReviewUtils::revisionNumberToSourcePage( isset($options['revision_number']) ? $options['revision_number'] : null );
        $jobType    = self::getJobType( $sourcePage );
        $password   = self::getPassword( $job, $sourcePage );

        if(!$jobType or !$password){
            return null;
        }

        $url = self::httpHost($options);
        $url .= DIRECTORY_SEPARATOR;
        $url .= $jobType;
        $url .= DIRECTORY_SEPARATOR;
        $url .= $project->name;
        $url .= DIRECTORY_SEPARATOR;
        $url .= $job->source . '-' . $job->target;
        $url .= DIRECTORY_SEPARATOR;
        $url .= $job->id . '-' . $password;

        // 4. add segment id only if belongs to the job
        if ( isset($options['id_segment']) ) {
            if ( ($job->job_first_segment <= $options['id_segment']) and ($options['id_segment'] <= $job->job_last_segment) ) {
                $url .= '#' . $options['id_segment'];
            }
        }

        return $url;
    }

    /**
     * @param $params
     *
     * @return mixed
     * @throws \Exception
     */
    private static function httpHost( $params ) {
        $host = \INIT::$HTTPHOST;

        if ( !empty( $params[ 'http_host' ] ) ) {
            $host = $params[ 'http_host' ];
        }

        if ( empty( $host ) ) {
            throw new \Exception( 'HTTP_HOST is not set ' );
        }

        return $host;
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
     * Get the correct password for job url
     *
     * @param Jobs_JobStruct $job
     * @param                $sourcePage
     *
     * @return string|null
     */
    private static function getPassword( Jobs_JobStruct $job, $sourcePage ) {
        if ( $sourcePage == 1 ) {
            return $job->password;
        }

        $qa = ChunkReviewDao::findByIdJobAndPasswordAndSourcePage( $job->id, $job->password, $sourcePage );
        if(!$qa){
            return null;
        }

        return $qa->review_password;
    }
}