<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\APISourcePageGuesserTrait;
use Controller\Traits\SegmentDisabledTrait;
use Exception;
use InvalidArgumentException;
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\MessagePatternValidator;
use Matecat\SubFiltering\Filters\CtrlCharsPlaceHoldToAscii;
use Matecat\SubFiltering\MateCatFilter;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\EditLog\EditLogSegmentStruct;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\Hook\Event\Filter\FilterContributionStructOnMTSetEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterContributionStructOnSetTranslationEvent;
use Model\FeaturesBase\Hook\Event\Filter\RewriteContributionContextsEvent;
use Model\FeaturesBase\Hook\Event\Run\PostAddSegmentTranslationEvent;
use Model\FeaturesBase\Hook\Event\Run\SetTranslationCommittedEvent;
use Model\Files\FilesPartsDao;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataMarshaller;
use Model\Segments\SegmentOriginalDataDao;
use Model\Segments\SegmentStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use Model\TranslationsSplit\SegmentSplitStruct;
use Model\TranslationsSplit\SplitDAO;
use Model\WordCount\WordCountStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\TranslationVersions;
use Plugins\Features\TranslationVersions\Handlers\TranslationVersionsHandler;
use Plugins\Features\TranslationVersions\VersionHandlerInterface;
use ReflectionException;
use RuntimeException;
use Utils\Constants\EngineConstants;
use Utils\Constants\JobStatus;
use Utils\Constants\ProjectStatus;
use Utils\Constants\TranslationStatus;
use Utils\Contribution\Set;
use Utils\Contribution\SetContributionRequest;
use Utils\LQA\ICUSourceSegmentChecker;
use Utils\LQA\QA;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;

class SetTranslationController extends AbstractStatefulKleinController
{
    use SegmentDisabledTrait;
    use APISourcePageGuesserTrait;
    use ICUSourceSegmentChecker;

    /**
     * @var array{
     *  id_job: string,
     *  password: string,
     *  received_password: string,
     *  id_segment: string,
     *  time_to_edit: int|string,
     *  id_translator: string,
     *  translation: string,
     *  segment: ?SegmentStruct,
     *  segmentString: string,
     *  version: string|null,
     *  chosen_suggestion_index: int|string|null,
     *  suggestion_array: string|null,
     *  splitStatuses: string|null,
     *  context_before: string,
     *  context_after: string,
     *  id_before: string|null,
     *  id_after: string|null,
     *  revisionNumber: int|null,
     *  guess_tag_used: bool|null,
     *  characters_counter: string|null,
     *  propagate: bool|null,
     *  client_target_version: int|string,
     *  status: string,
     *  split_statuses: array<int, string>,
     *  chunk: JobStruct,
     *  project: ProjectStruct,
     *  id_project: int,
     *  segment_contains_icu: bool,
     *  split_num: string|null,
     *  split_chunk_lengths: array<mixed>|null
     * }
     */
    protected array $data;

    protected ?string $password = null;

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    /**
     * @var SegmentStruct|null
     */
    protected ?SegmentStruct $segment = null;  // this comes from DAO

    /**
     * @var MateCatFilter
     */
    protected MateCatFilter $filter;

    /**
     * @var ?VersionHandlerInterface
     */
    protected ?VersionHandlerInterface $VersionsHandler = null;

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     */
    public function translate(): void
    {
        $db = Database::obtain();

        try {
            $prepared    = $this->prepareTranslation();
            $translation = $prepared['translation'];
            $check       = $prepared['check'];
            $err_json    = $prepared['err_json'];

            /*
             * begin stat counter
             *
             * It works well with default InnoDB Isolation level
             *
             * REPEATABLE-READ offering a row level lock for this id_segment
             *
             */
            $db->begin();

            $translations    = $this->buildNewTranslation($translation, $err_json, $check);
            $new_translation = $translations['new'];
            $old_translation = $translations['old'];

            $propagationTotal = $this->persistTranslation($new_translation, $old_translation, $translation, $err_json, $check);

            $db->commit();

            $result = $this->buildResult($new_translation, $old_translation, $propagationTotal, $check);

            $this->finalizeTranslation($new_translation, $old_translation, $propagationTotal, $result);

            $this->response->json($result);
        } catch (Exception $exception) {
            $db->rollback();
            throw $exception;
        }
    }

    /**
     * Phase 1-3: Validate request, set up filters, run QA checks, and prepare the cleaned translation.
     *
     * @return array{segment: string, translation: string, check: QA, err_json: string}
     * @throws Exception
     */
    private function prepareTranslation(): array
    {
        $this->data = $this->validateTheRequest();
        $this->checkIfSegmentIsNotDisabled();
        $this->setSubFilteringBehavior();
        $this->checkSegmentSplitData();
        $this->initVersionHandler();
        $this->getContexts();

        if ($this->data['segment'] === null) {
            throw new Exception('Segment not found for id_segment: ' . $this->data['id_segment']);
        }

        $segment = $this->filter->fromLayer0ToLayer1($this->data['segment']['segment']);
        $translation = (empty($this->data['translation']) and !is_numeric($this->data['translation'])) ? "" : $this->filter->fromLayer2ToLayer1(
            $this->data['translation']
        );

        $check = $this->setQaChecks($segment, $translation);
        $check->performConsistencyCheck();

        if ($check->thereAreWarnings()) {
            $err_json = $check->getWarningsJSON();
            $translation = $this->filter->fromLayer1ToLayer0($translation);
        } else {
            $err_json = '';
            $targetNormalized = $check->getTrgNormalized();
            $translation = $this->filter->fromLayer1ToLayer0($targetNormalized);
        }

        //PATCH TO FIX BOM INSERTIONS
        $translation = Utils::stripBOM($translation);

        return [
            'segment'     => $segment,
            'translation' => $translation,
            'check'       => $check,
            'err_json'    => $err_json,
        ];
    }

    /**
     * Phase 4-6: Fetch old translation, build new SegmentTranslationStruct, apply suggestion logic.
     *
     * @param string $translation The cleaned translation string
     * @param string $errJson Serialized QA warnings (or empty string)
     * @param QA $check The QA checker instance
     *
     * @return array{new: SegmentTranslationStruct, old: SegmentTranslationStruct}
     * @throws ReflectionException
     */
    private function buildNewTranslation(string $translation, string $errJson, QA $check): array
    {
        $old_translation = $this->getOldTranslation();

        $client_suggestion_array = json_decode($this->data['suggestion_array'] ?? '[]', true);
        $chosenIndex = $this->data['chosen_suggestion_index'] !== null ? (int)$this->data['chosen_suggestion_index'] : null;
        $client_chosen_suggestion_params = ($chosenIndex !== null && isset($client_suggestion_array[$chosenIndex - 1])) ? $client_suggestion_array[$chosenIndex - 1] : [];
        $client_chosen_suggestion = new ShapelessConcreteStruct($client_chosen_suggestion_params);

        $new_translation = new SegmentTranslationStruct();
        $new_translation->id_segment = (int)$this->data['id_segment'];
        $new_translation->id_job = (int)$this->data['id_job'];
        $new_translation->status = $this->data['status'];

        if ($this->data['segment'] === null) {
            throw new RuntimeException('Segment must not be null in buildNewTranslation');
        }
        $new_translation->segment_hash = $this->data['segment']->segment_hash;
        $new_translation->translation = $translation;
        $new_translation->serialized_errors_list = $errJson;
        $new_translation->suggestions_array = ($chosenIndex !== null ? $this->data['suggestion_array'] : $old_translation->suggestions_array);
        $new_translation->suggestion_position = ($chosenIndex !== null ? $chosenIndex : $old_translation->suggestion_position);
        $new_translation->warning = $check->thereAreWarnings();
        $new_translation->translation_date = date("Y-m-d H:i:s");
        $new_translation->suggestion = $old_translation->suggestion; //IMPORTANT: raw_translation is in layer 0 and suggestion too
        $new_translation->suggestion_source = $old_translation->suggestion_source;
        $new_translation->suggestion_match = $old_translation->suggestion_match;

        // update suggestion
        if ($this->canUpdateSuggestion($new_translation, $client_chosen_suggestion)) {
            $new_translation->suggestion = !empty($client_chosen_suggestion->raw_translation) ? $client_chosen_suggestion->raw_translation : $old_translation->suggestion; //IMPORTANT: raw_translation is in layer 0 and suggestion too

            // update suggestion match
            if ($client_chosen_suggestion->match == EngineConstants::MT) {
                /** @var ProjectStruct $project */
                $project = $this->data['project'];
                // case 1. is MT
                $new_translation->suggestion_match = (string)($project->getMetadataValue(ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value) ?? 85);
                $new_translation->suggestion_source = EngineConstants::MT;
            } elseif ($client_chosen_suggestion->match == InternalMatchesConstants::NO_MATCH) {
                // case 2. no match
                $new_translation->suggestion_source = InternalMatchesConstants::NO_MATCH;
            } else {
                // case 3. otherwise is TM
                $new_translation->suggestion_match = (string)(int)$client_chosen_suggestion->match; // cast '71%' to int 71
                $new_translation->suggestion_source = EngineConstants::TM;
            }
        }

        $new_translation->time_to_edit = (int)$this->data['time_to_edit'];

        return [
            'new' => $new_translation,
            'old' => $old_translation,
        ];
    }

    /**
     * Phase 7-15: Persist translation, handle propagation, splits, and version events.
     *
     * @param SegmentTranslationStruct $newTranslation The new translation struct
     * @param SegmentTranslationStruct $oldTranslation The old translation struct
     * @param string $translation The cleaned translation string
     * @param string $errJson Serialized QA warnings (or empty string)
     * @param QA $check The QA checker instance
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    private function persistTranslation(
        SegmentTranslationStruct $newTranslation,
        SegmentTranslationStruct $oldTranslation,
        string $translation,
        string $errJson,
        QA $check
    ): array {
        /**
         * Update Time to Edit and
         *
         * Evaluate new Avg post-editing effort for the job:
         * - get old translation
         * - get suggestion
         * - evaluate $_seg_oldPEE and normalize it on the number of words for this segment
         *
         * - Get a new translation
         * - Evaluate $_seg_newPEE and normalize it on the number of words for this segment
         *
         * - Get $_jobTotalPEE
         * - Evaluate $_jobTotalPEE - $_seg_oldPEE + $_seg_newPEE and save it into the job's row
         */
        $this->updateJobPEE($oldTranslation->toArray(), $newTranslation->toArray());

        // if saveVersionAndIncrement() return true it means that it was persisted a new version of the parent segment
        /** @var VersionHandlerInterface $versionsHandler */
        $versionsHandler = $this->VersionsHandler;
        $versionsHandler->saveVersionAndIncrement($newTranslation, $oldTranslation);

        /**
         * when the status of the translation changes, the auto propagation flag
         * must be removed
         */
        if ($newTranslation->translation != $oldTranslation->translation or
            $this->data['status'] == TranslationStatus::STATUS_TRANSLATED or
            $this->data['status'] == TranslationStatus::STATUS_APPROVED or
            $this->data['status'] == TranslationStatus::STATUS_APPROVED2
        ) {
            $newTranslation->autopropagated_from = null;
        }

        /**
         * Translation is inserted here.
         */
        CatUtils::addSegmentTranslation($newTranslation, (bool)$this->isRevision());

        /**
         * @see ProjectCompletion
         */
        $this->getFeatureSet()->dispatchRun(new PostAddSegmentTranslationEvent([
            'chunk' => $this->data['chunk'],
            'is_review' => (bool)$this->isRevision(),
            'logged_user' => $this->user
        ]));

        $propagationTotal = [
            'totals' => [],
            'propagated_ids' => [],
            'segments_for_propagation' => []
        ];

        if ($this->data['propagate'] && in_array($this->data['status'], [
                TranslationStatus::STATUS_TRANSLATED,
                TranslationStatus::STATUS_APPROVED,
                TranslationStatus::STATUS_APPROVED2,
                TranslationStatus::STATUS_REJECTED
            ])
        ) {
            //propagate translations
            $TPropagation = new SegmentTranslationStruct();
            $TPropagation['status'] = $this->data['status'];
            $TPropagation['id_job'] = $this->data['id_job'];
            $TPropagation['translation'] = $translation;
            $TPropagation['autopropagated_from'] = (int)$this->data['id_segment'];
            $TPropagation['serialized_errors_list'] = $errJson;
            $TPropagation['warning'] = $check->thereAreWarnings();
            $TPropagation['segment_hash'] = $oldTranslation['segment_hash'];
            $TPropagation['translation_date'] = Utils::mysqlTimestamp(time());
            $TPropagation['match_type'] = $oldTranslation['match_type'];
            $TPropagation['locked'] = $oldTranslation['locked'];

            $propagationTotal = $versionsHandler->propagateTranslation($TPropagation);
        }

        if ($this->isSplittedSegment()) {
            /* put the split inside the transaction if they are present */
            $translationStruct = SegmentSplitStruct::getStruct();
            $translationStruct->id_segment = (int)$this->data['id_segment'];
            $translationStruct->id_job = (int)$this->data['id_job'];

            $translationStruct->target_chunk_lengths = [
                'len' => $this->data['split_chunk_lengths'],
                'statuses' => $this->data['split_statuses']
            ];

            $translationDao = new SplitDAO(Database::obtain());
            $translationDao->atomicUpdate($translationStruct);
        }

        //COMMIT THE TRANSACTION
        /*
         * Hooked by TranslationVersions, which manage translation versions
         *
         * This is also the init handler of all R1/R2 handling and Qr score calculation by
         * by TranslationEventsHandler and BatchReviewProcessor
         */
        $versionsHandler->storeTranslationEvent([
            'translation' => $newTranslation,
            'old_translation' => $oldTranslation,
            'propagation' => $propagationTotal,
            'chunk' => $this->chunk,
            'user' => $this->user,
            'source_page_code' => ReviewUtils::revisionNumberToSourcePage($this->data['revisionNumber']),
            'features' => $this->featureSet,
            'project' => $this->data['project']
        ]);

        return $propagationTotal;
    }

    /**
     * Phase 16-18: Build the result array with job stats, translation data, warnings,
     * and run the setTranslationCommitted / filterSetTranslationResult hooks.
     *
     * IMPORTANT: setTranslationCommitted MUST be called BEFORE filterSetTranslationResult.
     *
     * @param SegmentTranslationStruct $newTranslation
     * @param SegmentTranslationStruct $oldTranslation
     * @param array<string, mixed> $propagationTotal
     * @param QA $check
     *
     * @return array<string, mixed>
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws Exception
     */
    private function buildResult(
        SegmentTranslationStruct $newTranslation,
        SegmentTranslationStruct $oldTranslation,
        array $propagationTotal,
        QA $check
    ): array {
        $newTotals = WordCountStruct::loadFromJob($this->data['chunk']);

        $job_stats = CatUtils::getFastStatsForJob($newTotals);
        $job_stats['analysis_complete'] = (
            $this->data['project']['status_analysis'] == ProjectStatus::STATUS_DONE or
            $this->data['project']['status_analysis'] == ProjectStatus::STATUS_NOT_TO_ANALYZE
        );

        $file_stats = [];
        $result = [];

        $result['stats'] = $job_stats;
        $result['file_stats'] = $file_stats;
        $result['code'] = 1;
        $result['data'] = "OK";
        $translationDate = date_create($newTranslation['translation_date']);
        $result['version'] = $translationDate !== false ? $translationDate->getTimestamp() : time();
        $result['translation'] = $this->getTranslationObject($newTranslation);

        /* FIXME: added for code compatibility with front-end. Remove. */
        $_warn = $check->getWarnings();
        $warning = $_warn[0];
        /* */

        $result['warning']['cod'] = $warning->outcome;
        if ($warning->outcome > 0) {
            $result['warning']['id'] = $this->data['id_segment'];
        } else {
            $result['warning']['id'] = 0;
        }

        $this->getFeatureSet()->dispatchRun(new SetTranslationCommittedEvent([
            'translation' => $newTranslation,
            'old_translation' => $oldTranslation,
            'propagated_ids' => $propagationTotal['segments_for_propagation']['propagated_ids'] ?? null,
            'chunk' => $this->data['chunk'],
            'segment' => $this->data['segment'],
            'user' => $this->user,
            'source_page_code' => ReviewUtils::revisionNumberToSourcePage($this->data['revisionNumber'])
        ]));

        return $result;
    }

    /**
     * Phase 19-20: Check Redis job completeness, add propagation totals to result,
     * and evaluate the TM contribution for this translation.
     *
     * @param SegmentTranslationStruct $newTranslation
     * @param SegmentTranslationStruct $oldTranslation
     * @param array<string, mixed> $propagationTotal
     * @param array<string, mixed> $result Passed by reference — adds 'propagation' key
     *
     * @return void
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     */
    private function finalizeTranslation(
        SegmentTranslationStruct $newTranslation,
        SegmentTranslationStruct $oldTranslation,
        array $propagationTotal,
        array &$result
    ): void {
        //EVERY time a user changes a row in his job when the job is completed,
        // a query to do the update is executed...
        // Avoid this by setting a key on redis with a reasonable TTL
        $redisHandler = new RedisHandler();
        $job_status = $redisHandler->getConnection()->get('job_completeness:' . $this->data['id_job']);
        if (
            (
                (
                    $result['stats'][ProjectsMetadataMarshaller::WORD_COUNT_RAW->value]['draft'] +
                    $result['stats'][ProjectsMetadataMarshaller::WORD_COUNT_RAW->value]['new'] == 0
                )
                and empty($job_status)
            )
        ) {
            $redisHandler->getConnection()->setex('job_completeness:' . $this->data['id_job'], 60 * 60 * 24 * 15, true); //15 days

            try {
                JobDao::setJobComplete($this->data['chunk']);
            } catch (Exception) {
                $msg = "\n\n Error setJobCompleteness \n\n " . var_export($this->request->paramsPost()->all(), true);
                $redisHandler->getConnection()->del('job_completeness:' . $this->data['id_job']);
                $this->logger->debug($msg);
            }
        }

        $result['propagation'] = $propagationTotal;
        $this->evalSetContribution($newTranslation, $oldTranslation);
    }

    /**
     * @return array{
     *   id_job: string,
     *   password: string,
     *   received_password: string,
     *   id_segment: string,
     *   time_to_edit: int|string,
     *   id_translator: string,
     *   translation: string,
     *   segment: ?SegmentStruct,
     *   segmentString: string,
     *   version: string|null,
     *   chosen_suggestion_index: int|string|null,
     *   suggestion_array: string|null,
     *   splitStatuses: string|null,
     *   context_before: string,
     *   context_after: string,
     *   id_before: string|null,
     *   id_after: string|null,
     *   revisionNumber: int|null,
     *   guess_tag_used: bool|null,
     *   characters_counter: string|null,
     *   propagate: bool|null,
     *   client_target_version: int|string,
     *   status: string,
     *   split_statuses: array<int, string>,
     *   chunk: JobStruct,
     *   project: ProjectStruct,
     *   id_project: int,
     *   segment_contains_icu: bool,
     *   split_num: string|null,
     *   split_chunk_lengths: array<mixed>|null
     * }
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $id_job = filter_var($this->request->param('id_job'), FILTER_SANITIZE_NUMBER_INT);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $received_password = (string)filter_var($this->request->param('current_password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $propagate = filter_var($this->request->param('propagate'), FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        $id_segment = filter_var($this->request->param('id_segment'), FILTER_SANITIZE_NUMBER_INT); // FILTER_SANITIZE_NUMBER_INT leaves untouched segments id with the split flag. Ex: 123-1
        $time_to_edit = filter_var($this->request->param('time_to_edit'), FILTER_SANITIZE_NUMBER_INT, ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE]
        ) ?? 0;
        $id_translator = (string)filter_var($this->request->param('id_translator'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $translation = (string)filter_var($this->request->param('translation'), FILTER_UNSAFE_RAW);
        $segmentString = (string)filter_var($this->request->param('segment'), FILTER_UNSAFE_RAW);
        $version = filter_var($this->request->param('version'), FILTER_SANITIZE_NUMBER_INT);
        $chosen_suggestion_index = filter_var(
            $this->request->param('chosen_suggestion_index'),
            FILTER_SANITIZE_NUMBER_INT,
            ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE]
        );
        $suggestion_array = filter_var(
            $this->request->param('suggestion_array'),
            FILTER_SANITIZE_SPECIAL_CHARS,
            ['filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_FLAG_EMPTY_STRING_NULL | FILTER_NULL_ON_FAILURE]
        );
        $status = filter_var($this->request->param('status'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $splitStatuses = filter_var($this->request->param('splitStatuses'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $context_before = (string)filter_var($this->request->param('context_before'), FILTER_UNSAFE_RAW);
        $context_after = (string)filter_var($this->request->param('context_after'), FILTER_UNSAFE_RAW);
        $id_before = filter_var($this->request->param('id_before'), FILTER_SANITIZE_NUMBER_INT);
        $id_after = filter_var($this->request->param('id_after'), FILTER_SANITIZE_NUMBER_INT);
        $revisionNumber = filter_var($this->request->param('revision_number'), FILTER_SANITIZE_NUMBER_INT);
        $guess_tag_used = filter_var($this->request->param('guess_tag_used'), FILTER_VALIDATE_BOOLEAN);
        $characters_counter = filter_var($this->request->param('characters_counter'), FILTER_SANITIZE_NUMBER_INT);

        /*
         * set by the client, mandatorily
         * check the propagation flag if it is null the client not sent it, leave default true, otherwise set the value
         */
        $propagate = $propagate ?? null; /* do nothing */
        $client_target_version = $version ?: 0;
        $status = strtoupper((string)$status);
        $split_statuses = explode(",", strtoupper((string)$splitStatuses)); //strtoupper transforms null to ""

        if (empty($id_job)) {
            throw new InvalidArgumentException("Missing id job", -2);
        }

        if (empty($password)) {
            throw new InvalidArgumentException("Missing password", -3);
        }

        if (empty($id_segment)) {
            throw new InvalidArgumentException("Missing id segment", -4);
        }

        //to get Job Info, we need only a row of jobs (split)
        $chunk = ChunkDao::getByIdAndPassword((int)$id_job, $password);
        $this->chunk = $chunk;

        //add check for job status archived.
        if (strtolower($chunk['status']) == JobStatus::STATUS_ARCHIVED) {
            throw new NotFoundException("Job archived", -3);
        }

        //check tag mismatch
        //get the original source segment, first
        $dao = new SegmentDao(Database::obtain());
        $this->segment = $dao->getById((int)$id_segment); // Cast to int to remove eventually split positions. Ex: id_segment = 123-1

        $this->id_job = (int)$id_job;
        $this->password = (string)$password;
        $this->request_password = (string)$received_password;

        $this->sourceContainsIcu($chunk->getProject(), $chunk, $segmentString);

        $data = [
            'id_job' => $id_job,
            'password' => $password,
            'received_password' => $received_password,
            'id_segment' => $id_segment,
            'time_to_edit' => $time_to_edit,
            'id_translator' => $id_translator,
            'translation' => $translation,
            'segment' => $this->segment,
            'segmentString' => $segmentString,
            'version' => $version ?: null,
            'chosen_suggestion_index' => $chosen_suggestion_index,
            'suggestion_array' => $suggestion_array ?: null,
            'splitStatuses' => $splitStatuses ?: null,
            'context_before' => $context_before,
            'context_after' => $context_after,
            'id_before' => $id_before ?: null,
            'id_after' => $id_after ?: null,
            'revisionNumber' => ($revisionNumber !== false && $revisionNumber !== '') ? (int)$revisionNumber : null,
            'guess_tag_used' => $guess_tag_used,
            'characters_counter' => $characters_counter ?: null,
            'propagate' => $propagate,
            'client_target_version' => $client_target_version,
            'status' => $status,
            'split_statuses' => $split_statuses,
            'chunk' => $chunk,
            'project' => $chunk->getProject(),
            'id_project' => $chunk->id_project,
            'segment_contains_icu' => $this->sourceContainsIcu,
            'split_num' => null,
            'split_chunk_lengths' => null,
        ];

        $this->logger->debug($data);

        return $data;
    }

    /**
     * checkIfIsNotDisabled
     *
     * Determines whether the segment associated with a specific job and segment ID
     * is disabled by checking cached information. If the segment is found to be disabled,
     * an exception is thrown.
     *
     * @return void
     * @throws Exception If the segment is disabled.
     */
    private function checkIfSegmentIsNotDisabled(): void
    {
        $id_job = $this->data['id_job'];
        $id_segment = $this->data['id_segment'];

        if ($this->isSegmentDisabled((int)$id_job, (int)$id_segment)) {
            throw new RuntimeException("Segment #".$id_segment." is disabled", -5);
        }
}

    /**
     * @return bool
     */
    private function isSplittedSegment(): bool
    {
        return !empty($this->data['split_statuses'][0]) && !empty($this->data['split_num']);
    }

    /**
     * setStatusForSplittedSegment
     *
     * If split segments have different statuses, we reset the status
     * to draft.
     */
    private function setStatusForSplittedSegment(): void
    {
        if (count(array_unique($this->data['split_statuses'])) == 1) {
            // IF ALL translation chunks are in the same status,
            // we take the status for the entire segment
            $this->data['status'] = $this->data['split_statuses'][0];
        } else {
            $this->data['status'] = TranslationStatus::STATUS_DRAFT;
        }
    }

    /**
     * @throws Exception
     */
    protected function checkSegmentSplitData(): void
    {
        [$__translation, $this->data['split_chunk_lengths']] = CatUtils::parseSegmentSplit($this->data['translation'], '', $this->filter);

        if (is_null($__translation) || $__translation === '') {
            $this->logger->debug("Empty Translation \n\n" . var_export($this->request->paramsPost()->all(), true));
            throw new RuntimeException("Empty Translation \n\n" . var_export($this->request->paramsPost()->all(), true), 0);
        }

        $explodeIdSegment = explode("-", $this->data['id_segment']);
        $this->data['id_segment'] = $explodeIdSegment[0];
        $this->data['split_num'] = $explodeIdSegment[1] ?? null;

        if (empty($this->data['id_segment'])) {
            throw new Exception("missing id_segment", -1);
        }

        if ($this->isSplittedSegment()) {
            $this->setStatusForSplittedSegment();
        }

        $this->checkStatus($this->data['status']);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function setSubFilteringBehavior(): void
    {
        /** @var ProjectStruct $projectStruct */
        $projectStruct = $this->data['project'];
        $featureSet = $this->getFeatureSet();
        $featureSet->loadForProject($projectStruct);

        $metadata = new JobsMetadataDao();
        $filter = MateCatFilter::getInstance(
            $featureSet,
            $this->data['chunk']->source,
            $this->data['chunk']->target,
            SegmentOriginalDataDao::getSegmentDataRefMap((int)$this->data['id_segment']),
            $metadata->getSubfilteringCustomHandlers($this->id_job, $this->password ?? ''),
            $this->sourceContainsIcu
        );

        if (!$filter instanceof MateCatFilter) {
            throw new RuntimeException('Expected MateCatFilter instance from getInstance()');
        }
        $this->filter = $filter;
    }

    /**
     * @param string $segment
     * @param string $translation
     * @return QA
     * @throws Exception
     */
    protected function setQaChecks(string $segment, string $translation): QA
    {
        $check = new QA(
            $segment,
            $translation,
            ($this->icuSourcePatternValidator !== null) ? MessagePatternComparator::fromValidators(
                $this->icuSourcePatternValidator,
                new MessagePatternValidator(
                    $this->data['chunk']->target,
                    // Transform target content: convert control character placeholders back to ASCII control characters
                    (new CtrlCharsPlaceHoldToAscii())->transform($translation)

                )
            ) : null,
            // ICU syntax is enabled for this project, and the translation content must contain valid ICU syntax
            $this->sourceContainsIcu
        ); // Layer 1 here

        $check->setChunk($this->data['chunk']);
        $check->setFeatureSet($this->getFeatureSet());
        $check->setSourceSegLang($this->data['chunk']->source);
        $check->setTargetSegLang($this->data['chunk']->target);

        if (isset($this->data['characters_counter']) and is_numeric($this->data['characters_counter'])) {
            $check->setCharactersCount(
                (int)$this->data['characters_counter'],
                SegmentMetadataDao::get((int)$this->data['id_segment'], SegmentMetadataMarshaller::SIZE_RESTRICTION->value)
            );
        }

        return $check;
    }

    /**
     * Throws exception if status is not valid.
     *
     * @param string $status
     *
     * @throws Exception
     */
    private function checkStatus(string $status): void
    {
        switch ($status) {
            case TranslationStatus::STATUS_TRANSLATED:
            case TranslationStatus::STATUS_APPROVED:
            case TranslationStatus::STATUS_APPROVED2:
            case TranslationStatus::STATUS_REJECTED:
            case TranslationStatus::STATUS_DRAFT:
            case TranslationStatus::STATUS_NEW:
            case TranslationStatus::STATUS_FIXED:
                break;

            default:
                $msg = "Error Hack Status \n\n " . var_export($this->request->paramsPost()->all(), true);
                throw new Exception($msg, -1);
        }
    }

    /**
     * @throws Exception
     */
    private function getContexts(): void
    {
        //Get contexts
        $segmentsList = (new SegmentDao)->setCacheTTL(60 * 60 * 24)->getContextAndSegmentByIDs(
            [
                'id_before' => (int)$this->data['id_before'],
                'id_segment' => (int)$this->data['id_segment'],
                'id_after' => (int)$this->data['id_after']
            ]
        );

        $event = new RewriteContributionContextsEvent($segmentsList, $this->data);
        $this->getFeatureSet()->dispatchFilter($event);
        $segmentsList = $event->getSegmentsList();

        if (isset($segmentsList->id_before->segment)) {
            $this->data['context_before'] = $this->filter->fromLayer0ToLayer1($segmentsList->id_before->segment);
        }

        if (isset($segmentsList->id_after->segment)) {
            $this->data['context_after'] = $this->filter->fromLayer0ToLayer1($segmentsList->id_after->segment);
        }
    }

    /**
     * init VersionHandler
     */
    private function initVersionHandler(): void
    {
        $this->VersionsHandler = TranslationVersions::getVersionHandlerNewInstance($this->data['chunk'], $this->user, $this->data['project'], (int)$this->data['id_segment']);
    }

    /**
     * @return SegmentTranslationStruct
     * @throws ReflectionException
     */
    protected function getOldTranslation(): SegmentTranslationStruct
    {
        $old_translation = SegmentTranslationDao::findBySegmentAndJob((int)$this->data['id_segment'], (int)$this->data['id_job']);

        if (empty($old_translation)) {
            $old_translation = new SegmentTranslationStruct();
        } // $old_translation if `false` sometimes


        // If volume analysis is not enabled and no translation rows exist, create the row
        if (!AppConfig::$VOLUME_ANALYSIS_ENABLED && empty($old_translation['status'])) {

            if ($this->segment === null) {
                throw new Exception('Segment not found');
            }

            $translation = new SegmentTranslationStruct();
            $translation->id_segment = (int)$this->data['id_segment'];
            $translation->id_job = (int)$this->data['id_job'];
            $translation->status = TranslationStatus::STATUS_NEW;

            $translation->segment_hash = $this->segment->segment_hash;
            $translation->translation = $this->segment->segment;
            $translation->standard_word_count = $this->segment->raw_word_count;

            $translation->serialized_errors_list = '';
            $translation->suggestion_position = 0;
            $translation->warning = false;
            $translation->translation_date = date("Y-m-d H:i:s");

            try {
                CatUtils::addSegmentTranslation($translation, (bool)$this->isRevision());
            } catch (Exception $e) {
                Database::obtain()->rollback();
                throw new RuntimeException($e->getMessage());
            }

            $old_translation = $translation;
        }

        return $old_translation;
    }

    /**
     * Update suggestion only if the new state is one of these:
     *      - NEW
     *      - DRAFT
     *      - TRANSLATED
     *
     * @param SegmentTranslationStruct $new_translation
     * @param ShapelessConcreteStruct $old_suggestion
     *
     * @return bool
     */
    private function canUpdateSuggestion(SegmentTranslationStruct $new_translation, ShapelessConcreteStruct $old_suggestion): bool
    {
        if (!in_array($new_translation->status, [
            TranslationStatus::STATUS_NEW,
            TranslationStatus::STATUS_DRAFT,
            TranslationStatus::STATUS_TRANSLATED,
        ])) {
            return false;
        }

        if (
            isset($old_suggestion->raw_translation) and
            isset($old_suggestion->match) and
            isset($old_suggestion->created_by)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $old_translation
     * @param array<string, mixed> $new_translation
     */
    private function updateJobPEE(array $old_translation, array $new_translation): void
    {
        //update total time to edit
        $jobTotalTTEForTranslation = $this->chunk['total_time_to_edit'];
        if (!self::isRevision()) {
            $jobTotalTTEForTranslation += $new_translation['time_to_edit'];
        }

        if ($this->segment === null) {
            throw new RuntimeException('Segment must not be null in updateJobPEE');
        }
        $segmentRawWordCount = $this->segment->raw_word_count;
        $editLogSegmentStruct = new EditLogSegmentStruct(
            [
                'suggestion' => $old_translation['suggestion'],
                'translation' => $old_translation['translation'],
                'raw_word_count' => $segmentRawWordCount,
                'time_to_edit' => $old_translation['time_to_edit'] + $new_translation['time_to_edit'],
                'target_language' => $this->chunk->target
            ]
        );

        $oldSegmentStatus = clone $editLogSegmentStruct;
        $oldSegmentStatus->time_to_edit = $old_translation['time_to_edit'];

        $oldPEE = $editLogSegmentStruct->getPEE();
        $oldPee_weighted = $oldPEE * $segmentRawWordCount;

        $editLogSegmentStruct->translation = $new_translation['translation'];

        $newPEE = $editLogSegmentStruct->getPEE();
        $newPee_weighted = $newPEE * $segmentRawWordCount;

        if ($editLogSegmentStruct->isValidForEditLog()) {
            //if the segment was not valid for editlog, and now it is, then just add the weighted pee
            if (!$oldSegmentStatus->isValidForEditLog()) {
                $newTotalJobPee = ($this->chunk['avg_post_editing_effort'] + $newPee_weighted);
            } //otherwise, evaluate it normally
            else {
                $newTotalJobPee = ($this->chunk['avg_post_editing_effort'] - $oldPee_weighted + $newPee_weighted);
            }

            JobDao::updateFields(

                ['avg_post_editing_effort' => $newTotalJobPee, 'total_time_to_edit' => $jobTotalTTEForTranslation],
                [
                    'id' => $this->id_job,
                    'password' => $this->password
                ]
            );
        } //the segment was valid, but now it is no more valid
        elseif ($oldSegmentStatus->isValidForEditLog()) {
            $newTotalJobPee = ($this->chunk['avg_post_editing_effort'] - $oldPee_weighted);

            JobDao::updateFields(
                ['avg_post_editing_effort' => $newTotalJobPee, 'total_time_to_edit' => $jobTotalTTEForTranslation],
                [
                    'id' => $this->id_job,
                    'password' => $this->password
                ]
            );
        } elseif ($jobTotalTTEForTranslation != 0) {
            JobDao::updateFields(
                ['total_time_to_edit' => $jobTotalTTEForTranslation],
                [
                    'id' => $this->id_job,
                    'password' => $this->password
                ]
            );
        }
    }

    /**
     * This method returns a representation of the saved translation which
     * should be as much as possible compliant with the future API v2.
     *
     * @param SegmentTranslationStruct $saved_translation
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    private function getTranslationObject(SegmentTranslationStruct $saved_translation): array
    {
        return [
            'version_number' => $saved_translation['version_number'] ?? null,
            'sid' => $saved_translation['id_segment'],
            'translation' => $this->filter->fromLayer0ToLayer2($saved_translation['translation']),
            'status' => $saved_translation['status']

        ];
    }

    /**
     * @param SegmentTranslationStruct $_Translation
     * @param SegmentTranslationStruct $old_translation
     *
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     * @throws Exception
     */
    private function evalSetContribution(SegmentTranslationStruct $_Translation, SegmentTranslationStruct $old_translation): void
    {
        if (in_array($this->data['status'], [
            TranslationStatus::STATUS_DRAFT,
            TranslationStatus::STATUS_NEW
        ])) {
            return;
        }

        $ownerUid = JobDao::getOwnerUid((int)$this->data['id_job'], $this->data['password']);
        $filesParts = (new FilesPartsDao())->getBySegmentId((int)$this->data['id_segment']); // Cast to int to remove eventually split positions. Ex: id_segment = 123-1

        if ($this->data['segment'] === null) {
            throw new RuntimeException('Segment must not be null in evalSetContribution');
        }

        /**
         * Set the new contribution in the queue
         */
        $contributionStruct = new SetContributionRequest();
        $contributionStruct->jobStruct = $this->chunk;
        $contributionStruct->fromRevision = (bool)$this->isRevision();
        $contributionStruct->id_file = $filesParts->id_file ?? 0;
        $contributionStruct->id_job = (int)$this->data['id_job'];
        $contributionStruct->job_password = $this->data['password'];
        $contributionStruct->id_segment = (int)$this->data['id_segment'];
        $contributionStruct->segment = $this->filter->fromLayer0ToLayer1($this->data['segment']['segment']);
        $contributionStruct->translation = ($_Translation['translation'] !== null) ? $this->filter->fromLayer0ToLayer1($_Translation['translation']) : "";
        $contributionStruct->api_key = AppConfig::$MYMEMORY_API_KEY;
        $contributionStruct->uid = ($ownerUid !== null) ? $ownerUid : 0;
        $contributionStruct->oldTranslationStatus = $old_translation['status'];
        $contributionStruct->oldSegment = $this->filter->fromLayer0ToLayer1($this->data['segment']['segment']); //
        $contributionStruct->oldTranslation = ($old_translation['translation'] !== null) ? $this->filter->fromLayer0ToLayer1($old_translation['translation']) : "";
        $contributionStruct->translation_origin = $this->getOriginalSuggestionProvider($_Translation, $old_translation);

        /*
         * This parameter is not used by the application, but we use it to for information integrity
         *
         * User choice for propagation.
         *
         * Propagate is false IF:
         * - the segment has no repetitions
         * - the segment has one or more repetitions and the user choose to not propagate it
         * - the segment is already autopropagated ( marked as autopropagated_from ) and it hasn't been changed
         *
         * Propagate is true ( vice versa ) IF:
         * - the segment has one or more repetitions, and its status is NEW/DRAFT
         * - the segment has one or more repetitions and the user chooses to propagate it
         * - the segment has one or more repetitions, it is not modified, it doesn't have translation conflicts and a change status is requested
         */
        $contributionStruct->propagationRequest = (bool)$this->data['propagate'];
        $contributionStruct->id_mt = $this->data['chunk']->id_mt_engine;

        $contributionStruct->context_after = $this->data['context_after'];
        $contributionStruct->context_before = $this->data['context_before'];

        $setTranslationEvent = new FilterContributionStructOnSetTranslationEvent(
            $contributionStruct,
            $this->data['project'],
            $this->data['segment']
        );
        $this->getFeatureSet()->dispatchFilter($setTranslationEvent);
        $contributionStruct = $setTranslationEvent->getContributionStruct();

        //assert there is not an exception by following the flow
        Set::contribution($contributionStruct);

        if ($contributionStruct->id_mt > 1) {
            /**
             * @see Airbnb::filterContributionStructOnMTSet
             */
            $newContributionStructEvent = new FilterContributionStructOnMTSetEvent(
                $contributionStruct,
                $_Translation,
                $this->data['segment'],
                $this->filter
            );
            $this->getFeatureSet()->dispatchFilter($newContributionStructEvent);
            $newContributionStruct = $newContributionStructEvent->getContributionStruct();
            Set::contributionMT($newContributionStruct);
        }

    }

    /**
     * Determines the original suggestion provider for a given segment translation.
     *
     * This method evaluates the suggestion array and position based on the status
     * of the old translation. If the old translation's status is `STATUS_NEW` or
     * `STATUS_DRAFT`, it retrieves the `created_by` field from the suggestion array
     * of the new translation. The provider information is extracted from the
     * `created_by` field.
     *
     * - If the `suggestion_position` is set, the `created_by` field is retrieved
     *   from the corresponding suggestion.
     * - If the `suggestion_position` is not set, the first suggestion in the array
     *   is used as a fallback.
     * - If no valid `created_by` field is found, the default value is `EngineConstants::MT`.
     *
     * @param SegmentTranslationStruct $new_translation The new translation structure containing the suggestion array.
     * @param SegmentTranslationStruct $old_translation The old translation structure used to determine the status.
     *
     * @return string The original translation provider extracted from the `created_by` field, or a default value if unavailable.
     */
    protected function getOriginalSuggestionProvider(SegmentTranslationStruct $new_translation, SegmentTranslationStruct $old_translation): string
    {
        // Default to Translation Memory (TM) as the provider.
        $created_by = EngineConstants::TM;

        // Check if the old translation's status is `STATUS_NEW` or `STATUS_DRAFT`.
        if (in_array($old_translation->status, [TranslationStatus::STATUS_NEW, TranslationStatus::STATUS_DRAFT])) {
            // Decode the suggestion array from the new translation.
            $suggestion_array = json_decode($new_translation->suggestions_array ?? '[]');

            // Retrieve the `created_by` field based on the suggestion position, or use the first suggestion as a fallback.
            if ($new_translation->suggestion_position) {
                $created_by = $suggestion_array[$new_translation->suggestion_position - 1]->created_by ?? EngineConstants::MT;
            } else {
                $created_by = $suggestion_array[0]->created_by ?? EngineConstants::MT;
            }
        }

        // Extract and return the provider information from the `created_by` field.
        return $created_by == EngineConstants::TM ? $created_by : explode('-', $created_by)[1] ?? EngineConstants::MT;
    }

}
