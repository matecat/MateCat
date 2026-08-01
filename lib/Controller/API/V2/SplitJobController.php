<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\JobSplitMerge\JobSplitMergeManager;
use Model\JobSplitMerge\SplitMergeProjectData;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use ReflectionException;
use TypeError;
use Utils\Session\SessionStore;

/**
 * Stateless: serves the v2 and v3 api-key routes, which have no session and no outsource quote cart.
 *
 * The UI's three endpoints are served by Controller\API\App\SplitJobController, which subclasses this
 * one and adds only statefulness. That split exists because splitting or merging a job invalidates the
 * cached outsource quote, and that cart lives in the session:
 *
 *  - the 2015 predecessor of this controller opened its own session in its constructor under an
 *    explicit `//SESSION ENABLED` comment — the spelling of `$useSession = true` at the time — so
 *    JobSplitMergeService's cart invalidation would take effect
 *  - the migration to KleinController, which defaults $useSession to false, silently dropped it, and
 *    the emptyCart()/deleteCart() calls have operated on a throwaway array ever since, leaving a
 *    split job's cached quote stale for the life of the user's session
 *
 * Restoring it on this class would have opened a session on every api-key call for nothing. The App
 * subclass restores it only where a session genuinely exists, matching the App-stateful/V3-stateless
 * pairing already used by TmKeyManagementController.
 *
 * @see \Controller\API\App\SplitJobController
 */
class SplitJobController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * The store backing the outsource quote cart, or null when there is no session to hold one.
     *
     * Null here rather than sessionStore(): this class is stateless, so its store refuses every
     * operation, and constructing a Cart over it would throw. The App subclass overrides this with the
     * real store. Expressed as an override rather than a runtime check on $useSession so that which
     * routes invalidate the cart stays readable from the class, not from a conditional.
     */
    protected function outsourceCartStore(): ?SessionStore
    {
        return null;
    }

    /**
     * Merge every chunk of a job back into one.
     *
     * Serves the UI, v2 and v3. The retired JobMergeController used to serve v2 and v3 with an
     * identical merge, differing only in how it reached the same ProjectDao::findByIdAndPassword check
     * — through ProjectPasswordValidator instead of getProjectData(). Its response shape is kept below
     * so retiring it changed nothing for the api-key callers.
     *
     * @throws Exception
     * @throws \TypeError
     */
    public function merge(): void
    {
        $request = $this->validateTheRequest();
        $projectStructure = $this->getProjectData(
            $request['project_id'],
            $request['project_pass'],
            $request['split_raw_words']
        );

        $data = $projectStructure['data'];
        $pManager = $projectStructure['pManager'];
        $project = $projectStructure['project'];

        $jobStructs = $this->checkMergeAccess($request['job_id'], $this->getProjectJobs($project));
        $data->jobToMerge = $request['job_id'];
        $pManager->mergeALL($data, $jobStructs);

        // Not ['data' => $data->splitResult] like check() and apply(): splitResult is only ever
        // populated by getSplitData(), which the merge path never calls, so that key was always null.
        $this->response->json([
            "success" => true
        ]);
    }

    /**
     * @throws Exception
     */
    public function check(): void
    {
        $request = $this->validateTheRequest();

        if (empty($request['job_pass'])) {
            throw new InvalidArgumentException("No job password provided", -4);
        }

        [, $data] = $this->checkSplit($request);

        $this->response->json([
            "data" => $data->splitResult
        ]);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function apply(): void
    {
        $request = $this->validateTheRequest();

        if (empty($request['job_pass'])) {
            throw new InvalidArgumentException("No job password provided", -4);
        }

        [$pManager, $data] = $this->checkSplit($request);
        $pManager->applySplit($data);

        $this->response->json([
            "data" => $data->splitResult
        ]);
    }

    /**
     * @param array{project_id: int, project_pass: string, job_id: int, job_pass: string, split_raw_words: bool, num_split: int, split_values: list<int>} $request
     *
     * @return array{0: JobSplitMergeManager, 1: SplitMergeProjectData}
     *
     * @throws Exception
     */
    private function checkSplit(array $request): array
    {
        $projectStructure = $this->getProjectData(
            $request['project_id'],
            $request['project_pass'],
            $request['split_raw_words']
        );

        $data = $projectStructure['data'];
        $pManager = $projectStructure['pManager'];
        $project = $projectStructure['project'];
        $count_type = $projectStructure['count_type'];

        $this->checkSplitAccess($request['job_id'], $request['job_pass'], $this->getProjectJobs($project));

        $data->jobToSplit = $request['job_id'];
        $data->jobToSplitPass = $request['job_pass'];

        $pManager->getSplitData($data, $request['num_split'], $request['split_values'], $count_type);

        return [$pManager, $data];
    }

    /**
     * Compatibility between the v2/v3 (api_v2_routes.php) API and the internal API obtained through the Elvis operator.
     * This covers the differences in the named parameters.
     *
     * @return array{project_id: int, project_pass: string, job_id: int, job_pass: string|false, split_raw_words: bool, num_split: int, split_values: list<int>}
     *
     * @throws InvalidArgumentException
     */
    private function validateTheRequest(): array
    {
        $project_id = filter_var($this->request->param('project_id'), FILTER_SANITIZE_NUMBER_INT) ?:
            filter_var($this->request->param('id_project'), FILTER_SANITIZE_NUMBER_INT);

        $project_pass = filter_var($this->request->param('project_pass'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]) ?:
            filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);

        $job_id = filter_var($this->request->param('job_id'), FILTER_SANITIZE_NUMBER_INT) ?:
            filter_var($this->request->param('id_job'), FILTER_SANITIZE_NUMBER_INT);

        $job_pass = filter_var($this->request->param('job_pass'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]) ?:
            filter_var($this->request->param('job_password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);

        $split_raw_words = filter_var($this->request->param('split_raw_words'), FILTER_VALIDATE_BOOLEAN) ?? false;
        $num_split = filter_var($this->request->param('num_split'), FILTER_SANITIZE_NUMBER_INT);
        $split_values = is_array($this->request->param('split_values')) ? array_values(array_map('intval', $this->request->param('split_values'))) : [];

        if (empty($project_id)) {
            throw new InvalidArgumentException("No id project provided", -1);
        }

        if (empty($project_pass)) {
            throw new InvalidArgumentException("No project password provided", -2);
        }

        if (empty($job_id)) {
            throw new InvalidArgumentException("No id job provided", -3);
        }

        return [
            'project_id' => (int)$project_id,
            'project_pass' => $project_pass,
            'job_id' => (int)$job_id,
            'job_pass' => $job_pass,
            'split_raw_words' => $split_raw_words,
            'num_split' => (int)$num_split,
            'split_values' => $split_values,
        ];
    }

    /**
     * @return JobStruct[]
     *
     * @throws Exception
     */
    protected function getProjectJobs(ProjectStruct $project): array
    {
        return (new JobDao($this->getDatabase()))->getNotDeletedByProjectId((int) $project->id);
    }

    /**
     * @return array{data: SplitMergeProjectData, pManager: JobSplitMergeManager, count_type: string, project: ProjectStruct}
     *
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     */
    protected function getProjectData(int $project_id, string $project_pass, bool $split_raw_words = false): array
    {
        $count_type = $split_raw_words ? ProjectsMetadataMarshaller::SPLIT_RAW_WORD_TYPE->value : ProjectsMetadataMarshaller::SPLIT_EQUIVALENT_WORD_TYPE->value;
        $project_struct = (new ProjectDao($this->getDatabase()))->findByIdAndPassword($project_id, $project_pass, 60 * 60);

        $pManager = new JobSplitMergeManager($project_struct, $this->getDatabase(), $this->outsourceCartStore());

        $data = $pManager->getProjectData();

        return [
            'data' => $data,
            'pManager' => $pManager,
            'count_type' => $count_type,
            'project' => $project_struct,
        ];
    }

    /**
     * @param int $jid
     * @param JobStruct[] $jobList
     *
     * @return JobStruct[]
     *
     * @throws NotFoundException
     */
    private function checkMergeAccess(int $jid, array $jobList): array
    {
        try {
            return $this->filterJobsById($jid, $jobList);
        } catch (AuthenticationError $e) {
            // 404, not the 401 filterJobsById raises for the split endpoints: a job id that is not in
            // this project is a missing record, and the caller is authenticated. This is also the status
            // the retired JobMergeController returned here, so its api-key callers see no change.
            throw new NotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param JobStruct[] $jobList
     *
     * @throws AuthenticationError
     * @throws InvalidArgumentException
     */
    private function checkSplitAccess(int $jid, string $job_pass, array $jobList): void
    {
        $jobToSplit = $this->filterJobsById($jid, $jobList);

        if ($jobToSplit[0]->password != $job_pass) {
            throw new InvalidArgumentException("Access denied", -10);
        }
    }

    /**
     * @param JobStruct[] $jobList
     *
     * @return JobStruct[]
     *
     * @throws AuthenticationError
     */
    private function filterJobsById(int $jid, array $jobList): array
    {
        $filteredJobs = array_values(array_filter($jobList, function (JobStruct $jobStruct) use ($jid) {
            return $jobStruct->id == $jid and !$jobStruct->isDeleted();
        }));

        if (empty($filteredJobs)) {
            throw new AuthenticationError("Access denied", -10);
        }

        return $filteredJobs;
    }

}
