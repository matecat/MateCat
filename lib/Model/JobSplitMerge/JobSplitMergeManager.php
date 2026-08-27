<?php

namespace Model\JobSplitMerge;

use ArrayObject;
use Exception;
use Model\Concerns\LogsMessages;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Throwable;
use Utils\Logger\LoggerFactory;
use Model\Users\UserStruct;
use Utils\Session\SessionStore;

/**
 * Top-level manager for job split and merge operations.
 *
 * This class replaces the split/merge responsibilities that were previously
 * handled by {@see ProjectManager}. It provides a lightweight entry point
 * that does NOT pull in the heavy project-creation infrastructure (file
 * storage, segment extraction, TMS, MateCatFilter, etc.).
 *
 * Usage:
 *   $manager = new JobSplitMergeManager($projectStruct, $database, $sessionStore, $actingUser);
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
     * Backs the outsource quote cart the service invalidates on split and merge. Threaded through
     * rather than reached statically so a stateless caller cannot silently invalidate nothing.
     */
    protected ?SessionStore $session;

    /**
     * @param UserStruct $actingUser Who is running the split or merge. Carried onto the
     *                               PostJobSplitted / PostJobMerged events so listeners attribute
     *                               the resulting chunk-review updates without reading the session,
     *                               and used as the inviter when a split moves the link its
     *                               translator holds.
     *
     * @throws Exception
     */
    public function __construct(ProjectStruct $project, IDatabase $database, ?SessionStore $session, private readonly UserStruct $actingUser)
    {
        $this->session = $session;

        $this->logger  = LoggerFactory::getLogger('job_split_merge_manager');
        $this->project = $project;

        $this->projectData = new SplitMergeProjectData(
            (int)$project->id,
            $project->id_customer,
        );

        $this->features = new FeatureSet($database);
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
     * @throws \TypeError
     * @throws Throwable the split runs inside a transaction scope, which aborts the transaction on
     *                   any throw and re-throws the original, whatever its type
     */
    public function applySplit(SplitMergeProjectData $data): void
    {
        $this->getJobSplitMergeService()->applySplit($data, $this->actingUser);
    }

    /**
     * Merge all job chunks back into a single job.
     *
     * Delegates to {@see JobSplitMergeService::mergeALL()}.
     *
     * @param JobStruct[] $jobStructs
     *
     * @throws Exception
     * @throws \TypeError
     * @throws Throwable the merge runs inside a transaction scope, which aborts the transaction on
     *                   any throw and re-throws the original, whatever its type
     */
    public function mergeALL(SplitMergeProjectData $data, array $jobStructs): void
    {
        $this->getJobSplitMergeService()->mergeALL($data, $jobStructs, $this->actingUser);
    }

    /**
     * Get or lazily create the JobSplitMergeService instance.
     */
    protected function getJobSplitMergeService(): JobSplitMergeService
    {
        if ($this->jobSplitMergeService === null) {
            $this->jobSplitMergeService = new JobSplitMergeService(
                $this->features->getDatabase(),
                $this->features,
                $this->logger,
                $this->session,
            );
        }

        return $this->jobSplitMergeService;
    }
}
