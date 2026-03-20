<?php

namespace unit\Model\ProjectCreation;

use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\ProjectCreation\JobCreationService;
use Model\ProjectCreation\ProjectStructure;
use ReflectionClass;
use ReflectionException;

/**
 * A testable subclass of JobCreationService that allows injecting a mock JobsMetadataDao
 * and overriding static calls for unit testing.
 */
class TestableJobCreationService extends JobCreationService
{
    private ?JobsMetadataDao $jobsMetadataDaoOverride = null;

    /** @var ?array Injectable chunk results for getChunksByJobId */
    private ?array $chunksByJobIdResult = null;

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

    /**
     * Inject chunk results for getChunksByJobId.
     *
     * @param JobStruct[] $chunks
     */
    public function setChunksByJobIdResult(array $chunks): void
    {
        $this->chunksByJobIdResult = $chunks;
    }

    /**
     * Override to return injected chunks instead of hitting the DB.
     */
    protected function getChunksByJobId(int $jobId): array
    {
        return $this->chunksByJobIdResult ?? parent::getChunksByJobId($jobId);
    }

    protected function insertFilesJob(int $jobId, int $fid): void
    {
        $this->insertFilesJobCalls[] = [$jobId, $fid];
    }

    /**
     * Public wrapper to invoke the private saveJobsMetadata().
     * @throws ReflectionException
     */
    public function callSaveJobsMetadata(JobStruct $job, ProjectStructure $projectStructure): void
    {
        $ref = new ReflectionClass(JobCreationService::class);
        $method = $ref->getMethod('saveJobsMetadata');
        $method->invoke($this, $job, $projectStructure);
    }
}
