<?php

namespace Model\JobSplitMerge;

use ArrayObject;
use Exception;
use Model\Concerns\LogsMessages;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Utils\Logger\LoggerFactory;

/**
 * Top-level manager for job split and merge operations.
 *
 * This class replaces the split/merge responsibilities that were previously
 * handled by {@see ProjectManager}. It provides a lightweight entry point
 * that does NOT pull in the heavy project-creation infrastructure (file
 * storage, segment extraction, TMS, MateCatFilter, etc.).
 *
 * Usage:
 *   $manager = new JobSplitMergeManager($projectStruct);
 *   $data = $manager->getProjectData();
 *   $manager->getSplitData($data, 3);
 *   $manager->applySplit($data);
 *   // or
 *   $manager->mergeALL($data, $jobStructs);
 */
class JobSplitMergeManager
{
    use LogsMessages;

    protected FeatureSet $features;

    protected ProjectStruct $project;

    protected SplitMergeProjectData $projectData;

    protected ?JobSplitMergeService $jobSplitMergeService = null;

    /**
     * @throws Exception
     */
    public function __construct(ProjectStruct $project)
    {
        $this->logger  = LoggerFactory::getLogger('job_split_merge_manager');
        $this->project = $project;

        $this->projectData = new SplitMergeProjectData(
            (int)$project->id,
            $project->id_customer,
        );

        $this->features = new FeatureSet();
        $this->features->loadForProject($this->project);
    }

    /**
     * Return the typed DTO carrying a split / merge state.
     */
    public function getProjectData(): SplitMergeProjectData
    {
        return $this->projectData;
    }

    /**
     * Build a job split structure, the minimum split value is 2 chunks.
     *
     * Delegates to {@see JobSplitMergeService::getSplitData()}.
     *
     * @param list<int> $requestedWordsPerSplit Matecat Equivalent Words (Only valid for Pro Version)
     *
     * @return ArrayObject<string, mixed>
     *
     * @throws Exception
     */
    public function getSplitData(
        SplitMergeProjectData $data,
        int $num_split = 2,
        array $requestedWordsPerSplit = [],
        string $count_type = ProjectsMetadataMarshaller::SPLIT_EQUIVALENT_WORD_TYPE->value
    ): ArrayObject {
        return $this->getJobSplitMergeService()->getSplitData($data, $num_split, $requestedWordsPerSplit, $count_type);
    }

    /**
     * Apply the new job structure.
     *
     * Delegates to {@see JobSplitMergeService::applySplit()}.
     *
     * @throws Exception
     */
    public function applySplit(SplitMergeProjectData $data): void
    {
        $this->getJobSplitMergeService()->applySplit($data, $data->uid);
    }

    /**
     * Merge all job chunks back into a single job.
     *
     * Delegates to {@see JobSplitMergeService::mergeALL()}.
     *
     * @param JobStruct[] $jobStructs
     *
     * @throws Exception
     */
    public function mergeALL(SplitMergeProjectData $data, array $jobStructs): void
    {
        $this->getJobSplitMergeService()->mergeALL($data, $jobStructs);
    }

    /**
     * Get or lazily create the JobSplitMergeService instance.
     */
    protected function getJobSplitMergeService(): JobSplitMergeService
    {
        if ($this->jobSplitMergeService === null) {
            $this->jobSplitMergeService = new JobSplitMergeService(
                Database::obtain(),
                $this->features,
                $this->logger,
            );
        }

        return $this->jobSplitMergeService;
    }
}
