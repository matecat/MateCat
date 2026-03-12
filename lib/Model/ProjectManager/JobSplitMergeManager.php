<?php

namespace Model\ProjectManager;

use ArrayObject;
use Exception;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectStruct;
use Utils\Collections\RecursiveArrayObject;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;

/**
 * Top-level manager for job split and merge operations.
 *
 * This class replaces the split/merge responsibilities that were previously
 * handled by {@see ProjectManager}. It provides a lightweight entry point
 * that does NOT pull in the heavy project-creation infrastructure (file
 * storage, segment extraction, TMS, MateCatFilter, etc.).
 *
 * Usage:
 *   $manager = new JobSplitMergeManager();
 *   $manager->loadProject($projectStruct);
 *   $pStruct = $manager->getProjectStructure();
 *   $manager->getSplitData($pStruct, 3);
 *   $manager->applySplit($pStruct);
 *   // or
 *   $manager->mergeALL($pStruct, $jobStructs);
 */
class JobSplitMergeManager
{
    use LogsMessages;

    protected FeatureSet $features;

    protected ProjectStruct $project;

    protected ArrayObject $projectStructure;

    protected ?JobSplitMergeService $jobSplitMergeService = null;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->logger = LoggerFactory::getLogger('job_split_merge_manager');

        $this->projectStructure = new RecursiveArrayObject([
            'id_project'   => null,
            'id_customer'  => null,
            'uid'          => null,
            'array_jobs'   => [
                'job_list'     => [],
                'job_pass'     => [],
                'job_segments' => [],
            ],
            'split_result'        => null,
            'job_to_split'        => null,
            'job_to_split_pass'   => null,
            'job_to_merge'        => null,
        ]);

        $this->features = new FeatureSet();
    }

    /**
     * Load a project and reload its feature set.
     *
     * This is the equivalent of the old
     * {@see ProjectManager::setProjectAndReLoadFeatures()} for the
     * split/merge workflow.
     *
     * @throws Exception
     */
    public function loadProject(ProjectStruct $project): void
    {
        $this->project = $project;
        $this->projectStructure['id_project']  = $project->id;
        $this->projectStructure['id_customer'] = $project->id_customer;

        $this->features = new FeatureSet();
        $this->features->loadForProject($this->project);
    }

    /**
     * @return RecursiveArrayObject|ArrayObject
     */
    public function getProjectStructure(): RecursiveArrayObject|ArrayObject
    {
        return $this->projectStructure;
    }

    /**
     * Build a job split structure, minimum split value are 2 chunks.
     *
     * Delegates to {@see JobSplitMergeService::getSplitData()}.
     *
     * @param ArrayObject $projectStructure
     * @param int $num_split
     * @param array $requestedWordsPerSplit Matecat Equivalent Words (Only valid for Pro Version)
     * @param string $count_type
     *
     * @return ArrayObject
     *
     * @throws Exception
     */
    public function getSplitData(
        ArrayObject $projectStructure,
        int $num_split = 2,
        array $requestedWordsPerSplit = [],
        string $count_type = ProjectsMetadataDao::SPLIT_EQUIVALENT_WORD_TYPE
    ): ArrayObject {
        return $this->getJobSplitMergeService()->getSplitData($projectStructure, $num_split, $requestedWordsPerSplit, $count_type);
    }

    /**
     * Apply the new job structure.
     *
     * Delegates to {@see JobSplitMergeService::applySplit()}.
     *
     * @param ArrayObject $projectStructure
     *
     * @throws Exception
     */
    public function applySplit(ArrayObject $projectStructure): void
    {
        $uid = $this->projectStructure['uid'] ?? null;
        $this->getJobSplitMergeService()->applySplit($projectStructure, $uid);
    }

    /**
     * Merge all job chunks back into a single job.
     *
     * Delegates to {@see JobSplitMergeService::mergeALL()}.
     *
     * @param ArrayObject $projectStructure
     * @param JobStruct[] $jobStructs
     *
     * @throws Exception
     */
    public function mergeALL(ArrayObject $projectStructure, array $jobStructs): void
    {
        $this->getJobSplitMergeService()->mergeALL($projectStructure, $jobStructs);
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
