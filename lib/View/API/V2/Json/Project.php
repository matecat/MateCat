<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/01/17
 * Time: 16.41
 *
 */

namespace View\API\V2\Json;

use Exception;
use Model\Analysis\Status;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use ReflectionException;
use Utils\Constants\JobStatus;
use Utils\Tools\Utils;

class Project
{

    /**
     * @var Job
     */
    protected Job $jRenderer;

    /**
     * @var ProjectStruct[]
     */
    protected array $data = [];

    /**
     * @var string|null
     */
    protected ?string $status = null;

    /**
     * @var bool
     */
    protected bool $called_from_api = false;

    /**
     * @var ?UserStruct
     */
    protected ?UserStruct $user = null;

    /**
     * @param UserStruct $user
     *
     * @return $this
     */
    public function setUser(UserStruct $user): Project
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param bool $called_from_api
     *
     * @return $this
     */
    public function setCalledFromApi(bool $called_from_api): Project
    {
        $this->called_from_api = $called_from_api;

        return $this;
    }

    /**
     * @var MetadataDao|null
     */
    protected ?MetadataDao $metadataDao = null;

    /**
     * @var ProjectDao|null
     */
    protected ?ProjectDao $projectDao = null;

    /**
     * Project constructor.
     *
     * @param ProjectStruct[] $data
     * @param string|null $search_status
     * @param MetadataDao|null $metadataDao
     * @param ProjectDao|null $projectDao
     */
    public function __construct(
        array $data = [],
        ?string $search_status = null,
        ?MetadataDao $metadataDao = null,
        ?ProjectDao $projectDao = null
    ) {
        $this->data = $data;
        $this->status = $search_status;
        $this->metadataDao = $metadataDao;
        $this->projectDao = $projectDao;
        $jRendered = new Job();

        if ($search_status) {
            $jRendered->setStatus($search_status);
        }

        $this->jRenderer = $jRendered;
    }

    /**
     * @param ProjectStruct $project
     *
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws Exception
     * @throws \TypeError
     */
    public function renderItem(ProjectStruct $project): array
    {
        $featureSet = $project->getFeaturesSet();
        $jobs = $project->getJobs(60 * 10); //cached

        $jobJSONs = [];
        $jobStatuses = [];
        if (!empty($jobs)) {
            /** @var Job $jobJSON */
            $jobJSON = new $this->jRenderer();

            if (!empty($this->user)) {
                $jobJSON->setUser($this->user);
            }

            if (!empty($this->status)) {
                $jobJSON->setStatus($this->status);
            }

            if ($this->called_from_api) {
                $jobJSON->setCalledFromApi(true);
            }

            foreach ($jobs as $job) {
                // if status is set, then filter off the jobs by owner_status
                if ($this->status) {
                    if ($job->status_owner === $this->status and !$job->isDeleted()) {
                        $jobJSONs[] = $jobJSON->renderItem(new JobStruct($job->getArrayCopy()), $project, $featureSet);
                        $jobStatuses[] = $job->status_owner;
                    }
                } elseif (!$job->isDeleted()) {
                    $jobJSONs[] = $jobJSON->renderItem(new JobStruct($job->getArrayCopy()), $project, $featureSet);
                    $jobStatuses[] = $job->status_owner;
                }
            }
        }

        $this->metadataDao ??= new MetadataDao();
        $projectInfo = $this->metadataDao->setCacheTTL(60)->get((int)$project->id, 'project_info');
        $fromApi = $this->metadataDao->setCacheTTL(60)->get((int)$project->id, ProjectsMetadataMarshaller::FROM_API->value);

        $this->projectDao ??= new ProjectDao();
        $_project_data = $this->projectDao->getProjectAndJobData((int)$project->id);
        $analysisStatus = new Status($_project_data, $featureSet, $this->user);

        return [
            'id' => (int)$project->id,
            'password' => $project->password,
            'name' => $project->name,
            'id_team' => (int)$project->id_team,
            'id_assignee' => (int)$project->id_assignee,
            'from_api' => ($fromApi->value ?? 0) == 1,
            'analysis' => $analysisStatus->fetchData()->getResult(),
            'create_date' => $project->create_date,
            'fast_analysis_wc' => (int)$project->fast_analysis_wc,
            'standard_analysis_wc' => (int)$project->standard_analysis_wc,
            'tm_analysis_wc' => $project->tm_analysis_wc,
            'project_slug' => Utils::friendlySlug($project->name),
            'jobs' => $jobJSONs,
            'features' => implode(",", $featureSet->getCodes()),
            'is_cancelled' => (in_array(JobStatus::STATUS_CANCELLED, $jobStatuses)),
            'is_archived' => (in_array(JobStatus::STATUS_ARCHIVED, $jobStatuses)),
            'remote_file_service' => $this->projectDao->setCacheTTL(60 * 60 * 24 * 7)->getRemoteFileServiceName([(int) $project->id])[0] ?? null,
            'due_date' => Utils::api_timestamp($project->due_date),
            'project_info' => (null !== $projectInfo) ? $projectInfo->value : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     * @throws ReflectionException
     * @throws Exception
     * @throws \TypeError
     */
    public function render(): array
    {
        $out = [];
        foreach ($this->data as $project) {
            $out[] = $this->renderItem($project);
        }

        return $out;
    }

}