<?php

namespace Utils\Url;

use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use ReflectionException;
use Utils\Tools\CatUtils;

class JobUrlBuilder
{

    /**
     * Build the job url from JobStruct
     *
     * Optional parameters:
     * - id_segment
     * - httphost
     *
     * Returns null in case of wrong parameters
     *
     * @param JobStruct $job
     * @param string    $projectName
     * @param array     $options
     *
     * @return JobUrls
     */
    public static function createFromJobStructAndProjectName(JobStruct $job, string $projectName, array $options = []): JobUrls
    {
        // 3. get passwords array
        $passwords   = [];
        $sourcePages = [
                JobUrls::LABEL_T  => 1,
                JobUrls::LABEL_R1 => 2,
                JobUrls::LABEL_R2 => 3
        ];

        foreach ($sourcePages as $label => $sourcePage) {
            $passwords[ $label ] = CatUtils::getJobPassword($job, $sourcePage);
        }

        // 4. httpHost
        $httpHost = (isset($options[ 'http_host' ])) ? $options[ 'http_host' ] : null;

        // 5. add segment id only if belongs to the job
        $segmentId = null;
        if (isset($options[ 'id_segment' ])) {
            if (!empty($options[ 'skip_check_segment' ])) {
                $segmentId = $options[ 'id_segment' ];
            } elseif (($job->job_first_segment <= $options[ 'id_segment' ]) and ($options[ 'id_segment' ] <= $job->job_last_segment)) {
                $segmentId = $options[ 'id_segment' ];
            }
        }

        return new JobUrls(
                $job->id,
                $projectName,
                $job->source,
                $job->target,
                $passwords,
                $httpHost,
                $segmentId
        );
    }

    /**
     * Build the job url from JobStruct
     *
     * Optional parameters:
     * - id_segment
     * - httphost
     *
     * Returns null in case of wrong parameters
     *
     * @param JobStruct          $job
     * @param array              $options
     * @param ProjectStruct|null $project
     *
     * @return JobUrls|null
     * @throws ReflectionException
     */
    public static function createFromJobStruct(JobStruct $job, array $options = [], ProjectStruct $project = null): ?JobUrls
    {
        // 1. if project is passed we gain a query
        if ($project == null) {
            // 2. find the correlated project, if not passed
            $project = ProjectDao::findById($job->id_project, 60 * 10);
        }

        if (!$project) {
            return null;
        }

        return static::createFromJobStructAndProjectName($job, $project->name, $options);
    }
}