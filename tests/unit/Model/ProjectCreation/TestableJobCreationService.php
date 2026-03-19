<?php

namespace unit\Model\ProjectCreation;

use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\ProjectCreation\JobCreationService;
use Model\ProjectCreation\ProjectStructure;
use ReflectionClass;

/**
 * A testable subclass of JobCreationService that allows injecting a mock JobsMetadataDao
 * and overriding static calls for unit testing.
 */
class TestableJobCreationService extends JobCreationService
{
    private ?JobsMetadataDao $jobsMetadataDaoOverride = null;

    /**
     * Collected calls to insertFilesJob. Each entry is [int $jobId, int $fid].
     * @var array<int, array{0: int, 1: int}>
     */
    public array $insertFilesJobCalls = [];

    public function setJobsMetadataDao(JobsMetadataDao $dao): void
    {
        $this->jobsMetadataDaoOverride = $dao;
    }

    protected function getJobsMetadataDao(): JobsMetadataDao
    {
        return $this->jobsMetadataDaoOverride ?? parent::getJobsMetadataDao();
    }

    protected function insertFilesJob(int $jobId, int $fid): void
    {
        $this->insertFilesJobCalls[] = [$jobId, $fid];
    }

    /**
     * Public wrapper to invoke the private saveJobsMetadata().
     */
    public function callSaveJobsMetadata(JobStruct $job, ProjectStructure $projectStructure): void
    {
        $ref = new ReflectionClass(JobCreationService::class);
        $method = $ref->getMethod('saveJobsMetadata');
        $method->invoke($this, $job, $projectStructure);
    }
}
