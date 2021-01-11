<?php

namespace Url;

use Features\ReviewExtended\Model\ChunkReviewDao;
use Jobs_JobStruct;
use LQA\ChunkReviewStruct;
use Projects_ProjectDao;

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
     * @param Jobs_JobStruct $job
     * @param array          $options
     *
     * @return JobUrlStruct
     * @throws \Exception
     */
    public static function createFromJobStruct( Jobs_JobStruct $job, $options = [] ) {

        // 2. find the correlated project
        $project = Projects_ProjectDao::findById( $job->id_project );
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
            $passwords[ $label ] = self::getPassword( $job, $sourcePage );
        }

        // 4. httpHost
        $httpHost = ( isset( $options[ 'http_host' ] ) ) ? $options[ 'http_host' ] : null;

        // 5. add segment id only if belongs to the job
        $segmentId = null;
        if ( isset( $options[ 'id_segment' ] ) ) {
            if ( ( $job->job_first_segment <= $options[ 'id_segment' ] ) and ( $options[ 'id_segment' ] <= $job->job_last_segment ) ) {
                $segmentId = $options[ 'id_segment' ];
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

    /**
     * Build the job url from job id/password
     *
     * Optional parameters:
     * - id_segment
     * - httphost
     *
     * Returns null in case of wrong parameters
     *
     * @param int    $jobId
     * @param string $jobPassword
     * @param array  $options
     *
     * @return JobUrlStruct
     * @throws \Exception
     */
    public static function createFromCredentials( $jobId, $jobPassword, $options = [] ) {

        // 1. find the job
        $job = self::getJobFromIdAndAnyPassword( $jobId, $jobPassword );
        if ( !$job ) {
            return null;
        }

        return self::createFromJobStruct( $job, $options );
    }

    /**
     * @param $jobId
     * @param $jobPassword
     *
     * @return \DataAccess_IDaoStruct|Jobs_JobStruct
     */
    private static function getJobFromIdAndAnyPassword( $jobId, $jobPassword ) {
        $job = \Jobs_JobDao::getByIdAndPassword( $jobId, $jobPassword );

        if ( !$job ) {
            /** @var ChunkReviewStruct $chunkReview */
            $chunkReview = ChunkReviewDao::findByReviewPasswordAndJobId( $jobPassword, $jobId );

            if ( !$chunkReview ) {
                return null;
            }

            $job = $chunkReview->getChunk();
        }

        return $job;
    }

    /**
     * Get the correct password for job url
     *
     * @param Jobs_JobStruct $job
     * @param int            $sourcePage
     *
     * @return string|null
     */
    private static function getPassword( Jobs_JobStruct $job, $sourcePage ) {
        if ( $sourcePage == 1 ) {
            return $job->password;
        }

        $qa = ChunkReviewDao::findByIdJobAndPasswordAndSourcePage( $job->id, $job->password, $sourcePage );
        if ( !$qa ) {
            return null;
        }

        return $qa->review_password;
    }
}