<?php

namespace Model\ProjectCreation;

use Exception;
use Model\Analysis\PayableRates;
use Model\ConnectedServices\GDrive\Session;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\FeaturesBase\FeatureSet;
use Model\Files\FileDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\PayableRates\CustomPayableRateDao;
use Model\PayableRates\CustomPayableRateStruct;
use ReflectionException;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\Tools\Utils;
use View\API\Commons\Error;

class JobCreationService
{
    public function __construct(
        private readonly FeatureSet $features,
        private readonly MatecatLogger $logger,
    ) {
    }

    /**
     * Resolve the payable rates and optional template for a target language.
     *
     * Priority: mt_qe_workflow → payable_rate_model (object) → payable_rate_model_id (DB lookup) → default.
     *
     * @return array{0: string, 1: ?CustomPayableRateStruct} [$ratesJson, $template]
     * @throws Exception
     */
    private function resolvePayableRates(ProjectStructure $projectStructure, string $target): array
    {
        // Branch 1: mt_qe_workflow payable rate takes the highest priority
        if ($projectStructure->mt_qe_workflow_payable_rate) {
            return [
                (string)json_encode($projectStructure->mt_qe_workflow_payable_rate),
                null,
            ];
        }

        // Branch 2: payable_rate_model provided as object/array
        if (!empty($projectStructure->payable_rate_model)) {
            $template = new CustomPayableRateStruct();
            $template->hydrateFromJSON((string)json_encode($projectStructure->payable_rate_model));
            $rates = $template->getPayableRates((string)$projectStructure->source_language, $target);

            return [(string)json_encode($rates), $template];
        }

        // Branch 3: payable_rate_model_id — look up from DB
        if (!empty($projectStructure->payable_rate_model_id)) {
            $template = CustomPayableRateDao::getById($projectStructure->payable_rate_model_id);
            if ($template === null) {
                throw new Exception("Payable rate model not found: $projectStructure->payable_rate_model_id");
            }
            $rates = $template->getPayableRates((string)$projectStructure->source_language, $target);

            return [(string)json_encode($rates), $template];
        }

        // Branch 4: default — use static PayableRates with feature filtering
        $rates = PayableRates::getPayableRates((string)$projectStructure->source_language, $target);

        return [
            (string)json_encode($this->features->filter('filterPayableRates', $rates, $projectStructure->source_language, $target)),
            null,
        ];
    }

    /**
     * Build a JSON string of TM keys from the project's private_tm_key array.
     * Replaces the {{pid}} placeholder with the actual project ID.
     */
    private function buildTmKeysJson(ProjectStructure $projectStructure): string
    {
        $this->logger->debug('Private TM keys', ['keys' => $projectStructure->private_tm_key]);

        $tmKeys = [];

        if (!empty($projectStructure->private_tm_key)) {
            foreach ($projectStructure->private_tm_key as $tmKeyObj) {
                $newTmKey = TmKeyManager::getTmKeyStructure();
                $newTmKey->complete_format = true;
                $newTmKey->tm = true;
                $newTmKey->glos = true;
                $newTmKey->owner = true;
                $newTmKey->penalty = $tmKeyObj['penalty'] ?? 0;
                $newTmKey->name = $tmKeyObj['name'];
                $newTmKey->key = $tmKeyObj['key'];
                $newTmKey->r = $tmKeyObj['r'];
                $newTmKey->w = $tmKeyObj['w'];

                $tmKeys[] = $newTmKey;
            }
        }

        $json = (string)json_encode($tmKeys);

        // Replace {{pid}} with project ID for new keys created with an empty name
        return str_replace('{{pid}}', (string)$projectStructure->id_project, $json);
    }

    /**
     * Build a JobStruct with all fields populated from project data.
     *
     * @param array<string, int> $minMaxSegmentsId
     */
    private function buildJobStruct(
        ProjectStructure $projectStructure,
        string $target,
        string $payableRates,
        string $tmKeysJson,
        array $minMaxSegmentsId,
        int $filesWordCount,
    ): JobStruct {
        $job = new JobStruct();
        $job->password = Utils::randomString();
        $job->id_project = (int)$projectStructure->id_project;
        $job->source = (string)$projectStructure->source_language;
        $job->target = $target;
        $job->id_tms = $projectStructure->tms_engine ?? 1;
        $job->id_mt_engine = $projectStructure->target_language_mt_engine_association[$target];
        $job->create_date = date('Y-m-d H:i:s');
        $job->last_update = date('Y-m-d H:i:s');
        $job->subject = $projectStructure->job_subject;
        $job->owner = $projectStructure->owner;
        $job->job_first_segment = $minMaxSegmentsId['job_first_segment'];
        $job->job_last_segment = $minMaxSegmentsId['job_last_segment'];
        $job->tm_keys = $tmKeysJson;
        $job->payable_rates = $payableRates;
        $job->total_raw_wc = $filesWordCount;
        $job->only_private_tm = $projectStructure->only_private;

        return $job;
    }

    /**
     * Populate the array_jobs tracking arrays on ProjectStructure after a job is inserted.
     *
     * @param array<string, int> $minMaxSegmentsId
     */
    private function updateJobTracking(
        ProjectStructure $projectStructure,
        JobStruct $job,
        string $payableRates,
        array $minMaxSegmentsId,
    ): void {
        $projectStructure->array_jobs['job_list'][] = $job->id;
        $projectStructure->array_jobs['job_pass'][] = $job->password;
        $projectStructure->array_jobs['job_segments'][$job->id . '-' . $job->password] = $minMaxSegmentsId;
        $projectStructure->array_jobs['job_languages'][$job->id] = $job->id . ':' . $job->target;
        $projectStructure->array_jobs['payable_rates'][$job->id] = $payableRates;
    }

    /**
     * Persist job-level metadata via JobsMetadataDao.
     *
     * Moved from ProjectManager::saveJobsMetadata().
     *
     * @throws ReflectionException
     */
    private function saveJobsMetadata(JobStruct $job, ProjectStructure $projectStructure): void
    {
        $metadata = [];

        if (isset($projectStructure->public_tm_penalty)) {
            $metadata[JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value] = (string)$projectStructure->public_tm_penalty;
        }
        if ($projectStructure->character_counter_count_tags !== null) {
            $metadata[JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value] = (string)($projectStructure->character_counter_count_tags ? 1 : 0);
        }
        if ($projectStructure->character_counter_mode !== null) {
            $metadata[JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value] = $projectStructure->character_counter_mode;
        }
        if ($projectStructure->tm_prioritization !== null) {
            $metadata[JobsMetadataMarshaller::TM_PRIORITIZATION->value] = (string)($projectStructure->tm_prioritization ? 1 : 0);
        }

        if ($projectStructure->dialect_strict !== null) {
            foreach ($projectStructure->dialect_strict as $lang => $value) {
                if (trim($lang) === trim($job->target)) {
                    $metadata[JobsMetadataMarshaller::DIALECT_STRICT->value] = (string)(int)$value;
                }
            }
        }

        if (!empty($projectStructure->subfiltering_handlers)) {
            $metadata[JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value] = $projectStructure->subfiltering_handlers;
        }

        $this->getJobsMetadataDao()->bulkSet((int)$job->id, (string)$job->password, $metadata);
    }

    /**
     * Get a JobsMetadataDao instance — overridable in tests.
     */
    protected function getJobsMetadataDao(): JobsMetadataDao
    {
        return new JobsMetadataDao();
    }

    /**
     * Associate a payable rate model with a job if both model ID and template exist.
     * Wrapped in try-catch to preserve the original error handling behavior.
     */
    private function associatePayableRateModel(
        JobStruct $job,
        ProjectStructure $projectStructure,
        ?CustomPayableRateStruct $template,
    ): void {
        if (empty($projectStructure->payable_rate_model_id) || $template === null) {
            return;
        }

        try {
            CustomPayableRateDao::assocModelToJob(
                $projectStructure->payable_rate_model_id,
                (int)$job->id,
                $template->version,
                $template->name
            );
        } catch (Exception $e) {
            $this->logger->error("Failed to associate payable rate model to job $job->id", ['exception' => $e]);
        }
    }

    /**
     * Create one job per target language. Returns the created jobs with
     * DB-assigned IDs. Populates $projectStructure->array_jobs as a side effect.
     *
     * @param array<string, int> $minMaxSegmentsId
     * @return list<JobStruct>
     * @throws Exception
     */
    public function createJobsForTargetLanguages(
        ProjectStructure $projectStructure,
        array $minMaxSegmentsId,
        int $filesWordCount,
    ): array {
        $createdJobs = [];

        // Validate segments exist before creating any jobs.
        // Intentionally moved outside the loop (was inside in the original code)
        // because minMaxSegmentsId does not change between iterations.
        if (!isset($minMaxSegmentsId['job_first_segment']) || !isset($minMaxSegmentsId['job_last_segment'])) {
            throw new Exception('Job cannot be created. No segments found!');
        }

        foreach ($projectStructure->target_language ?? [] as $target) {
            [$payableRates, $template] = $this->resolvePayableRates($projectStructure, $target);
            $tmKeysJson = $this->buildTmKeysJson($projectStructure);
            $job = $this->buildJobStruct($projectStructure, $target, $payableRates, $tmKeysJson, $minMaxSegmentsId, $filesWordCount);

            $this->features->run('validateJobCreation', $job, $projectStructure);
            $job = $this->insertJob($job);

            $this->updateJobTracking($projectStructure, $job, $payableRates, $minMaxSegmentsId);
            $this->saveJobsMetadata($job, $projectStructure);
            $this->associatePayableRateModel($job, $projectStructure, $template);

            $createdJobs[] = $job;
        }

        return $createdJobs;
    }

    /**
     * Insert a job into the database. Extracted for testability.
     * @throws ReflectionException
     */
    protected function insertJob(JobStruct $job): JobStruct
    {
        return JobDao::createFromStruct($job);
    }

    /**
     * Insert file-job association. Extracted for testability.
     * @throws Exception
     */
    protected function insertFilesJob(int $jobId, int $fid): void
    {
        FileDao::insertFilesJob($jobId, $fid);
    }

    /**
     * For each created job, link project files and insert any pre-translations.
     *
     * @param list<JobStruct> $jobs
     * @throws Exception
     */
    public function linkFilesAndInsertPreTranslations(
        array $jobs,
        ProjectStructure $projectStructure,
        ?Session $gdriveSession,
        SegmentStorageService $segmentStorageService,
        QAProcessor $qaProcessor,
    ): void {
        foreach ($jobs as $job) {
            $this->linkFilesToJob($job, $projectStructure, $gdriveSession);
            $this->insertPreTranslations($job, $projectStructure, $segmentStorageService, $qaProcessor);
        }
    }

    /**
     * Link all project files to a job and create GDrive remote copies if applicable.
     * @throws Exception
     */
    private function linkFilesToJob(JobStruct $job, ProjectStructure $projectStructure, ?Session $gdriveSession): void
    {
        foreach ($projectStructure->file_id_list as $fid) {
            $this->insertFilesJob((int)$job->id, $fid);

            if ($gdriveSession && $gdriveSession->hasFiles()) {
                $client = GoogleProvider::getClient(AppConfig::$HTTPHOST . '/gdrive/oauth/response');
                $gdriveSession->createRemoteCopiesWhereToSaveTranslation($fid, (int)$job->id, $client);
            }
        }
    }

    /**
     * Insert pre-translations for a job.
     *
     * Failures are logged and an error email is sent, but the exception is
     * re-thrown so the caller can abort project creation cleanly.
     *
     * @throws Exception
     */
    private function insertPreTranslations(
        JobStruct $job,
        ProjectStructure $projectStructure,
        SegmentStorageService $segmentStorageService,
        QAProcessor $qaProcessor,
    ): void {
        if (empty($projectStructure->translations)) {
            return;
        }

        try {
            // Use the in-memory $job directly instead of re-querying via getChunksByJobId().
            // The job was just created by createFromStruct() and already carries source/target.
            // Re-querying through ProxySQL risks hitting a read replica that hasn't replicated
            // the INSERT yet, causing a spurious "No Job found" error.
            $qaProcessor->process($projectStructure, $job->source, $job->target);
            $segmentStorageService->insertPreTranslations($job, $projectStructure);
        } catch (Exception $e) {
            $msg = "\n\n Error, pre-translations lost, project should be re-created. \n\n " . var_export($e->getMessage(), true);
            Utils::sendErrMailReport($msg);
            $this->logger->debug("Pre-translation insertion failed for job $job->id", (new Error($e))->render(true));
            $projectStructure->addError(
                (int)$e->getCode(),
                "Pre-translations lost for job $job->id: " . $e->getMessage() . ". The project should be re-created."
            );
            throw $e;
        }
    }
}
