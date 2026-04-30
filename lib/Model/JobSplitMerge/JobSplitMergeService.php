<?php

namespace Model\JobSplitMerge;

use ArrayObject;
use Exception;
use Model\Analysis\AnalysisDao;
use Model\Concerns\LogsMessages;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\PostJobMergedEvent;
use Model\FeaturesBase\Hook\Event\Run\PostJobSplittedEvent;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Model\Translators\TranslatorsModel;
use Model\Users\UserDao;
use Model\WordCount\CounterModel;
use ReflectionException;
use RuntimeException;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\JobsWorker;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\Shop\Cart;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\Tools\Utils;

/**
 * Encapsulates job split and merge logic that was previously embedded in
 * {@see ProjectManager}.
 *
 * This class is responsible for:
 *  - Computing split data (chunk boundaries, word counts)
 *  - Applying a split (cloning job rows for each chunk)
 *  - Merging all chunks back into a single job
 *
 * All mutations are performed on the {@see SplitMergeProjectData} DTO passed
 * to the public methods. FeatureSet hooks receive the SplitMergeProjectData
 * directly.
 */
class JobSplitMergeService
{
    use LogsMessages;

    private IDatabase $dbHandler;
    private FeatureSet $features;

    public function __construct(
        IDatabase $dbHandler,
        FeatureSet $features,
        MatecatLogger $logger,
    ) {
        $this->dbHandler = $dbHandler;
        $this->features = $features;
        $this->logger = $logger;
    }

    // ── Factory methods (overridable in tests) ──────────────────────

    /**
     * Create a new JobDao instance.
     */
    protected function createJobDao(): JobDao
    {
        return new JobDao();
    }

    protected function createJobMetadataDao(): MetadataDao
    {
        // init JobsMetadataDao
        return new MetadataDao();
    }

    /**
     * Wrapper around the static JobDao::getByIdAndPassword() — overridable in tests.
     * @throws ReflectionException
     */
    protected function getJobByIdAndPassword(int $id, string $password): ?JobStruct
    {
        return JobDao::getByIdAndPassword($id, $password);
    }

    /**
     * Begin a database transaction using the injected handler.
     */
    protected function beginTransaction(): void
    {
        $this->dbHandler->begin();
    }

    /**
     * Wrapper around Cart static access — overridable in tests.
     */
    protected function getCart(): Cart
    {
        return Cart::getInstance('outsource_to_external_cache');
    }

    /**
     * Wrapper around static AnalysisDao call — overridable in tests.
     * @throws ReflectionException
     */
    protected function destroyAnalysisCacheByProjectId(int $projectId): void
    {
        AnalysisDao::destroyCacheByProjectId($projectId);
    }

    /**
     * Wrapper around static TmKeyManager call — overridable in tests.
     * @param array<int, string> $tmKeys
     * @return array<int, mixed> $tmKeys
     * @throws Exception
     */
    protected function getOwnerKeys(array $tmKeys): array
    {
        return TmKeyManager::getOwnerKeys($tmKeys);
    }

    /**
     * Wrapper around static JobDao::updateForMerge() — overridable in tests.
     * @throws Exception
     */
    protected function updateForMerge(JobStruct $job, string $newPassword): void
    {
        JobDao::updateForMerge($job, $newPassword);
    }

    /**
     * Wrapper around static JobDao::deleteOnMerge() — overridable in tests.
     */
    protected function deleteOnMerge(JobStruct $job): void
    {
        JobDao::deleteOnMerge($job);
    }

    /**
     * Create a new CounterModel instance — overridable in tests.
     */
    protected function createCounterModel(): CounterModel
    {
        return new CounterModel();
    }

    /**
     * Create a new ProjectDao instance — overridable in tests.
     */
    protected function createProjectDao(): ProjectDao
    {
        return new ProjectDao();
    }

    /**
     * Create a new ProjectsMetadataDao instance — overridable in tests.
     */
    protected function createProjectsMetadataDao(): ProjectsMetadataDao
    {
        return new ProjectsMetadataDao();
    }

    /**
     * Create a new TranslatorsModel instance — overridable in tests.
     */
    protected function createTranslatorsModel(JobStruct $job): TranslatorsModel
    {
        return new TranslatorsModel($job);
    }

    /**
     * Create a new UserDao instance — overridable in tests.
     */
    protected function createUserDao(): UserDao
    {
        return new UserDao();
    }

    /**
     * Generate a random string — overridable in tests for deterministic passwords.
     */
    protected function generateRandomString(): string
    {
        return Utils::randomString();
    }

    /**
     * Enqueue a worker job — overridable in tests.
     * @param array<string, mixed> $data
     */
    protected function enqueueWorker(string $queue, string $workerClass, array $data): void
    {
        WorkerClient::enqueue($queue, $workerClass, $data, ['persistent' => WorkerClient::$_HANDLER->persistent]);
    }

    /**
     * Retrieve the ProjectStruct for a given job — used for cache invalidation.
     *
     * Wraps JobStruct::getProject() so tests can override without DB access.
     */
    protected function getProjectForCacheInvalidation(JobStruct $job): ProjectStruct
    {
        return $job->getProject(60 * 10);
    }

    // ── Public API ──────────────────────────────────────────────────

    /**
     * Compute split data: chunk boundaries and word counts.
     *
     * Analyze the job segments and decide how to distribute them across
     * $num_split chunks, either evenly or according to the caller-specified
     * $requestedWordsPerSplit distribution.
     *
     * @param int $num_split Number of chunks (minimum 2)
     * @param array<int, float|int> $requestedWordsPerSplit Optional per-chunk word targets
     * @param string $count_type Which word-count column to use
     *
     * @return ArrayObject<string, mixed> The split result (also stored in $data->splitResult)
     *
     * @throws Exception
     */
    public function getSplitData(
        SplitMergeProjectData $data,
        int $num_split = 2,
        array $requestedWordsPerSplit = [],
        string $count_type = ProjectsMetadataMarshaller::SPLIT_EQUIVALENT_WORD_TYPE->value
    ): ArrayObject {
        if ($num_split < 2) {
            throw new Exception('Minimum Chunk number for split is 2.', -2);
        }

        if (!empty($requestedWordsPerSplit) && count($requestedWordsPerSplit) != $num_split) {
            throw new Exception("Requested words per chunk and Number of chunks not consistent.", -3);
        }

        if (!empty($requestedWordsPerSplit) && !AppConfig::$VOLUME_ANALYSIS_ENABLED) {
            throw new Exception("Requested words per chunk available only for Matecat PRO version", -4);
        }

        $rows = $this->createJobDao()->getSplitData((int)$data->jobToSplit, (string)$data->jobToSplitPass);

        if (empty($rows)) {
            throw new Exception('No segments found for job ' . $data->jobToSplit, -5);
        }

        $row_totals = array_pop($rows); //get the last row (ROLLUP)
        unset($row_totals['id']);

        if (empty($row_totals['job_first_segment']) || empty($row_totals['job_last_segment'])) {
            throw new Exception('Wrong job id or password. Job segment range not found.', -6);
        }

        $total_words = $row_totals[$count_type];

        // if the requested $count_type is empty (for example, equivalent raw count = 0),
        // switch to the other one
        if ($total_words < $num_split) {
            $new_count_type = ($count_type === ProjectsMetadataMarshaller::SPLIT_EQUIVALENT_WORD_TYPE->value) ? ProjectsMetadataMarshaller::SPLIT_RAW_WORD_TYPE->value : ProjectsMetadataMarshaller::SPLIT_EQUIVALENT_WORD_TYPE->value;
            $total_words = $row_totals[$new_count_type];
            $count_type = $new_count_type;
        }

        // if the total number of words is < the number of chunks, throw an exception
        if ($total_words < $num_split) {
            throw new Exception('The number of words is insufficient for the requested amount of chunks.', -6);
        }

        if (empty($requestedWordsPerSplit)) {
            /*
             * Simple Split with a pretty equivalent number of words per chunk
             */
            $words_per_job = array_fill(0, $num_split, round($total_words / $num_split));
        } else {
            /*
             * User defined words per chunk, needs some checks and control structures
             */
            $words_per_job = $requestedWordsPerSplit;
        }

        $counter = [];
        $chunk = 0;

        $reverse_count = ['standard_word_count' => 0, 'eq_word_count' => 0, 'raw_word_count' => 0];

        foreach ($rows as $row) {
            if (!array_key_exists($chunk, $counter)) {
                $counter[$chunk] = [
                    'standard_word_count' => 0,
                    'eq_word_count' => 0,
                    'raw_word_count' => 0,
                    'segment_start' => $row['id'],
                    'segment_end' => 0,
                    'last_opened_segment' => 0,
                ];
            }

            $counter[$chunk]['standard_word_count'] += $row['standard_word_count'];
            $counter[$chunk]['eq_word_count'] += $row['eq_word_count'];
            $counter[$chunk]['raw_word_count'] += $row['raw_word_count'];
            $counter[$chunk]['segment_end'] = $row['id'];

            //if the last_opened segment is not set and if that segment can be shown in cattool
            //set that segment as the default last visited
            ($counter[$chunk]['last_opened_segment'] == 0 && $row['show_in_cattool'] == 1 ? $counter[$chunk]['last_opened_segment'] = $row['id'] : null);

            //check for wanted words per job.
            //create a chunk when we reach the requested number of words,
            //and we are below the requested number of splits.
            //in this manner, we add to the last chunk all rests
            if ($counter[$chunk][$count_type] >= $words_per_job[$chunk] && $chunk < $num_split - 1 /* chunk is zero-based */) {
                $counter[$chunk]['standard_word_count'] = (int)$counter[$chunk]['standard_word_count'];
                $counter[$chunk]['eq_word_count'] = (int)$counter[$chunk]['eq_word_count'];
                $counter[$chunk]['raw_word_count'] = (int)$counter[$chunk]['raw_word_count'];

                $reverse_count['standard_word_count'] += (int)$counter[$chunk]['standard_word_count'];
                $reverse_count['eq_word_count'] += (int)$counter[$chunk]['eq_word_count'];
                $reverse_count['raw_word_count'] += (int)$counter[$chunk]['raw_word_count'];

                $chunk++;
            }
        }

        if ($total_words > $reverse_count[$count_type]) {
            if (!empty($counter[$chunk])) {
                $counter[$chunk]['standard_word_count'] = round($row_totals['standard_word_count'] - $reverse_count['standard_word_count']);
                $counter[$chunk]['eq_word_count'] = round($row_totals['eq_word_count'] - $reverse_count['eq_word_count']);
                $counter[$chunk]['raw_word_count'] = round($row_totals['raw_word_count'] - $reverse_count['raw_word_count']);
            } else {
                $counter[$chunk - 1]['standard_word_count'] += round($row_totals['standard_word_count'] - $reverse_count['standard_word_count']);
                $counter[$chunk - 1]['eq_word_count'] = ($counter[$chunk - 1]['eq_word_count'] ?? 0) + round(($row_totals['eq_word_count'] ?? 0) - $reverse_count['eq_word_count']);
                $counter[$chunk - 1]['raw_word_count'] = ($counter[$chunk - 1]['raw_word_count'] ?? 0) + round(($row_totals['raw_word_count'] ?? 0) - $reverse_count['raw_word_count']);
            }
        }

        if (count($counter) < 2) {
            throw new Exception('The requested number of words for the first chunk is too large. I cannot create 2 chunks.', -7);
        }

        $chunk = $this->getJobByIdAndPassword((int)$data->jobToSplit, (string)$data->jobToSplitPass);
        if ($chunk === null) {
            throw new Exception('Job not found for id ' . $data->jobToSplit, -5);
        }
        $row_totals['standard_analysis_count'] = $chunk->standard_analysis_wc;

        $result = array_merge($row_totals->getArrayCopy(), ['chunks' => $counter]);

        $data->splitResult = new ArrayObject($result);

        return $data->splitResult;
    }

    /**
     * Apply a new structure of the job: empty cart, begin transaction, split, commit.
     *
     * @param int|null $uid The user ID performing the split (nullable)
     *
     * @throws Exception
     */
    public function applySplit(SplitMergeProjectData $data, ?int $uid = null): void
    {
        $this->getCart()->emptyCart();

        $this->beginTransaction();
        $this->splitJob($data, $uid);
        $this->dbHandler->commit();
    }

    /**
     * Do the split based on previous getSplitData analysis.
     * It clones the original job in the right number of chunks and fills these rows with:
     * first/last segments of every chunk, last opened segment as the first segment of the new job,
     * and the timestamp of creation.
     *
     * @param int|null $uid The user ID performing the split
     *
     * @throws Exception
     */
    public function splitJob(SplitMergeProjectData $data, ?int $uid = null): void
    {
        // init JobDao
        $jobDao = $this->createJobDao();

        // job to split
        $jobToSplit = $this->getJobByIdAndPassword((int)$data->jobToSplit, (string)$data->jobToSplitPass);
        if ($jobToSplit === null) {
            throw new Exception('Job not found for id ' . $data->jobToSplit, -8);
        }

        $translatorModel = $this->createTranslatorsModel($jobToSplit);
        $jTranslatorStruct = $translatorModel->getTranslator(0); // no cache
        if (!empty($jTranslatorStruct) && !empty($uid)) {
            $userStruct = $this->createUserDao()->setCacheTTL(60 * 60)->getByUid($uid);
            if ($userStruct === null) {
                throw new Exception('User not found for uid ' . $uid, -8);
            }
            $translatorModel
                ->setUserInvite($userStruct)
                ->setDeliveryDate($jTranslatorStruct->delivery_date)
                ->setJobOwnerTimezone($jTranslatorStruct->job_owner_timezone)
                ->setEmail($jTranslatorStruct->email)
                ->setNewJobPassword($this->generateRandomString());

            $translatorModel->update();
        }

        if ($data->splitResult === null) {
            throw new Exception('Split result not available. Call getSplitData() first.', -8);
        }

        $chunks = $data->splitResult['chunks'];

        // update the first chunk of the job to split
        $jobDao->updateStdWcAndTotalWc((int)$jobToSplit->id, $chunks[0]['standard_word_count'], $chunks[0]['raw_word_count']);

        $jobsMetadataDao = $this->createJobMetadataDao();

        $newJobList = [];

        // create the other chunks of the job to split
        foreach ($chunks as $contents) {
            $newJob = clone $jobToSplit;

            //IF THIS IS NOT the original job, UPDATE relevant fields
            if ($contents['segment_start'] != $data->splitResult['job_first_segment']) {
                //next insert
                $newJob['password'] = $this->generateRandomString();
                $newJob['create_date'] = date('Y-m-d H:i:s');
                $newJob['avg_post_editing_effort'] = 0;
                $newJob['total_time_to_edit'] = 0;
            }

            $newJob['last_opened_segment'] = $contents['last_opened_segment'];
            $newJob['job_first_segment'] = $contents['segment_start'];
            $newJob['job_last_segment'] = $contents['segment_end'];
            $newJob['standard_analysis_wc'] = $contents['standard_word_count'];
            $newJob['total_raw_wc'] = $contents['raw_word_count'];

            $stmt = $jobDao->getSplitJobPreparedStatement($newJob);
            $stmt->execute();

            $wCountManager = $this->createCounterModel();
            $wCountManager->initializeJobWordCount((int)$newJob->id, (string)$newJob->password);

            if ($this->dbHandler->rowCount() == 0) {
                $msg = "Failed to split job into " . count($data->splitResult['chunks']) . " chunks\n";
                $msg .= "Tried to perform SQL: \n" . print_r($stmt->queryString, true) . " \n\n";
                $msg .= "Failed Statement is: \n" . print_r($newJob, true) . "\n";
                $this->log($msg);
                throw new Exception('Failed to insert job chunk, project damaged.', -8);
            }

            $newJobList[] = $newJob;

            // duplicate character_counter_count_tags, character_counter_mode, subfiltering_handlers metadata
            $metadata = [
                JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value,
                JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value,
                JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value,
            ];

             foreach ($metadata as $key) {
                 $_data = $jobsMetadataDao->get($jobToSplit->id, $jobToSplit->password, $key);

                 if (!empty($_data)) {
                     $jobsMetadataDao->set($newJob->id, $newJob->password, $key, $_data->value);
                     $jobsMetadataDao->destroyCacheByJobAndPasswordAndKey($jobToSplit->id ?? throw new RuntimeException('Missing job id'), $jobToSplit->password ?? throw new RuntimeException('Missing job password'), $key);
                 }
             }

            $stmt->closeCursor();
            unset($stmt);

            //add here the job id to list
            $data->jobList->append((int)$data->jobToSplit);
            //add here passwords to list
            $data->jobPass->append($newJob['password']);

            $data->jobSegments->offsetSet($data->jobToSplit . "-" . $newJob['password'], new ArrayObject([
                $contents['segment_start'],
                $contents['segment_end']
            ]));
        }

        foreach ($newJobList as $job) {
            /**
             * Async worker to re-count avg-PEE and total-TTE for split jobs
             */
            try {
                $this->enqueueWorker('JOBS', JobsWorker::class, $job->getArrayCopy());
            } catch (Exception $e) {
                # Handle the error, logging, ...
                $output = "**** Job Split PEE recount request failed. AMQ Connection Error. ****\n\t";
                $output .= "{$e->getMessage()}";
                $output .= var_export($job, true);
                $this->log($output, $e);
            }
        }

         $this->createJobDao()->destroyCacheByProjectId($data->idProject);

         $projectStruct = $this->getProjectForCacheInvalidation($jobToSplit);
         $this->createProjectDao()->destroyCacheForProjectData($projectStruct->id ?? throw new RuntimeException('Missing project id'), $projectStruct->password);
        $this->destroyAnalysisCacheByProjectId($data->idProject);

        $this->getCart()->deleteCart();

        $this->features->dispatchRun(new PostJobSplittedEvent($data));
    }

    /**
     * Merge all job chunks back into a single job.
     *
     * @param JobStruct[] $jobStructs
     *
     * @throws Exception
     */
    public function mergeALL(SplitMergeProjectData $data, array $jobStructs): void
    {
        $jobsMetadataDao = $this->createJobMetadataDao();

        //get the min and
        $first_job = reset($jobStructs);
        if ($first_job === false) {
            throw new Exception('No job chunks to merge.', -9);
        }

        //the max segment from the job list
        $last_job = end($jobStructs);
        if ($last_job === false) {
            throw new Exception('No job chunks to merge.', -9);
        }
        $job_last_segment = $last_job['job_last_segment'];

        //change values of the first job
        $first_job['job_last_segment'] = $job_last_segment;

        //get the min and
        $total_raw_wc = 0;
        $standard_word_count = 0;

        //merge TM keys: preserve only owner's keys
        $tm_keys = [];
        foreach ($jobStructs as $chunk_info) {
            $tm_keys[] = $chunk_info['tm_keys'];
            $total_raw_wc = $total_raw_wc + $chunk_info['total_raw_wc'];
            $standard_word_count = $standard_word_count + $chunk_info['standard_analysis_wc'];
        }

        try {
            $owner_tm_keys = $this->getOwnerKeys($tm_keys);

            foreach ($owner_tm_keys as $i => $owner_key) {
                $owner_key->complete_format = true;
                $owner_tm_keys[$i] = $owner_key->toArray();
            }

            $first_job['tm_keys'] = json_encode($owner_tm_keys);
        } catch (Exception $e) {
            $this->log(__METHOD__ . " -> Merge Jobs error - TM key problem", $e);
        }

        $totalAvgPee = 0;
        $totalTimeToEdit = 0;

        foreach ($jobStructs as $i => $_jStruct) {
            $totalAvgPee += $_jStruct->avg_post_editing_effort;
            $totalTimeToEdit += $_jStruct->total_time_to_edit;

            if ($i > 0) {
                // delete character_counter_count_tags, character_counter_mode, subfiltering_handlers metadata (not from the first job)
                $metadata = [
                    JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value,
                    JobsMetadataMarshaller::CHARACTER_COUNTER_MODE->value,
                    JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value,
                ];

                 foreach ($metadata as $key) {
                     $jobsMetadataDao->delete($_jStruct->id ?? throw new RuntimeException('Missing job id'), $_jStruct->password ?? throw new RuntimeException('Missing job password'), $key);
                     $jobsMetadataDao->destroyCacheByJobAndPasswordAndKey($_jStruct->id ?? throw new RuntimeException('Missing job id'), $_jStruct->password ?? throw new RuntimeException('Missing job password'), $key);
                 }
            }
        }

        $first_job['avg_post_editing_effort'] = $totalAvgPee;
        $first_job['total_time_to_edit'] = $totalTimeToEdit;

        $this->beginTransaction();

        if ($first_job->getTranslator()) {
            //Update the password in the struct and in the database for the first job
            $this->updateForMerge($first_job, $this->generateRandomString());
            $this->getCart()->emptyCart();
        } else {
            $this->updateForMerge($first_job, '');
        }

        $this->deleteOnMerge($first_job);

        $wCountManager = $this->createCounterModel();
        $wCountManager->initializeJobWordCount((int)$first_job['id'], (string)$first_job['password']);

        $chunk = new JobStruct($first_job->toArray());
        $this->features->dispatchRun(new PostJobMergedEvent($data, $chunk));

        $jobDao = $this->createJobDao();

        $jobDao->updateStdWcAndTotalWc((int)$first_job['id'], $standard_word_count, $total_raw_wc);

        $this->dbHandler->commit();

        $jobDao->destroyCacheByProjectId($data->idProject);
        $this->destroyAnalysisCacheByProjectId($data->idProject);

         $projectStruct = $this->getProjectForCacheInvalidation($jobStructs[0]);
         $this->createProjectDao()->destroyCacheForProjectData($projectStruct->id ?? throw new RuntimeException('Missing project id'), $projectStruct->password);
    }
}
