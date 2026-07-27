<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use DomainException;
use Exception;
use InvalidArgumentException;
use Matecat\Finder\WholeTextFinder;
use Matecat\SubFiltering\MateCatFilter;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\Hook\Event\Run\PostAddSegmentTranslationEvent;
use Model\FeaturesBase\Hook\Event\Run\SetTranslationCommittedEvent;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Projects\ProjectDao;
use Model\Search\ReplaceEventStruct;
use Model\Search\SearchModel;
use Model\Search\SearchQueryParamsStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\TranslationVersions;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Constants\TranslationStatus;
use Utils\Registry\AppConfig;
use Utils\Search\ReplaceHistory;
use Utils\Search\ReplaceHistoryFactory;
use Utils\Tools\Utils;

class GetSearchController extends AbstractStatefulKleinController
{

    private JobStruct $chunk;

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $Validator = new ChunkPasswordValidator($this);
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk = $Validator->getChunk();
        });
        $this->appendValidator($Validator);
    }

    /**
     * @throws InvalidArgumentException
     * @throws DomainException
     * @throws RuntimeException
     * @throws TypeError
     */
    public function search(): void
    {
        $request = $this->validateTheRequest();
        $res = $this->doSearch($request);

        $this->response->json([
            'data' => [],
            'errors' => [],
            'token' => $request['token'],
            'total' => $res['count'],
            'segments' => $res['sid_list'],
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws TypeError
     * @throws Exception
     */
    public function replaceAll(): void
    {
        $request = $this->validateTheRequest();
        $res = $this->doSearch($request);
        $search_results = [];

        // and then hydrate the $search_results array
        foreach ($res['sid_list'] as $segmentId) {
            $segmentTranslation = (new SegmentTranslationDao($this->getDatabase()))->findBySegmentAndJob($segmentId, (int)$this->chunk->id);
            if ($segmentTranslation === null) {
                continue;
            }
            $search_results[] = $segmentTranslation->toArray();
        }

        // set the replacement in queryParams
        $request['queryParams']['replacement'] = $request['replace'];

        // update segment translations
        $committed = $this->updateSegments($search_results, (int)$this->chunk->id, $request['queryParams']);

        // Persist the replace history for the committed segments only. The whole batch shares one
        // version so a single undo reverts it atomically; the cursor advances once, after the batch.
        if (!empty($committed)) {
            $srh = $this->getReplaceHistory((int)$this->chunk->id);
            $replace_version = (string)($srh->getCursor() + 1);
            foreach ($committed as $tRow) {
                $this->saveReplacementEvent($replace_version, $tRow, $srh, $request['queryParams']);
            }
            $srh->updateIndex((int)$replace_version);
        }

        $this->response->json([
            "errors" => [],
            "data" => [],
            "token" => $request['token'] ?? null,
            "total" => $res['count'] ?? 0,
            "segments" => $res['sid_list']
        ]);
    }

    /**
     * @throws TypeError
     * @throws Exception
     */
    public function undoReplaceAll(): void
    {
        $request = $this->validateTheRequest();
        $shr = $this->getReplaceHistory((int)$this->chunk->id);
        $search_results = $this->getSegmentForUndoReplaceAll($shr);
        $this->updateSegments($search_results, (int)$this->chunk->id, $request['queryParams'], true);
        $shr->undo();

        $this->response->json([
            'success' => true
        ]);
    }

    /**
     * @return array{
     *     job: int,
     *     token: string|false,
     *     source: string|false,
     *     target: string|false,
     *     status: string|false,
     *     replace: string|false,
     *     password: string|false,
     *     isMatchCaseRequested: bool,
     *     isExactMatchRequested: bool,
     *     inCurrentChunkOnly: bool,
     *     revisionNumber: string|false|null,
     *     queryParams: SearchQueryParamsStruct
     * }
     *
     * @throws InvalidArgumentException
     */
    private function validateTheRequest(): array
    {
        $job = filter_var($this->request->param('id_job'), FILTER_SANITIZE_NUMBER_INT);
        $token = filter_var($this->request->param('token'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $source = filter_var($this->request->param('source'), FILTER_UNSAFE_RAW);
        $target = filter_var($this->request->param('target'), FILTER_UNSAFE_RAW);
        $status = filter_var($this->request->param('status'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);
        $replace = filter_var($this->request->param('replace'), FILTER_UNSAFE_RAW);
        $password = filter_var($this->request->param('password'), FILTER_UNSAFE_RAW);
        $isMatchCaseRequested = filter_var($this->request->param('matchcase'), FILTER_VALIDATE_BOOLEAN);
        $isExactMatchRequested = filter_var($this->request->param('exactmatch'), FILTER_VALIDATE_BOOLEAN);
        $inCurrentChunkOnly = filter_var($this->request->param('inCurrentChunkOnly'), FILTER_VALIDATE_BOOLEAN);
        $revision_number = filter_var(
            $this->request->param('revision_number'),
            FILTER_SANITIZE_NUMBER_INT,
            ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => null]]
        );

        if (empty($job)) {
            throw new InvalidArgumentException("missing id job", -2);
        }

        if (empty($password)) {
            throw new InvalidArgumentException("missing job password", -3);
        }

        $job = (int)$job;

        switch ($status) {
            case 'translated':
            case 'approved':
            case 'approved2':
            case 'rejected':
            case 'draft':
            case 'new':
                break;
            default:
                $status = "all";
                break;
        }

        $queryParams = new SearchQueryParamsStruct([
            'job' => $this->chunk->id,
            'password' => $this->chunk->password,
            'key' => null,
            'src' => null,
            'trg' => null,
            'status' => $status,
            'replacement' => $replace,
            'isMatchCaseRequested' => $isMatchCaseRequested,
            'isExactMatchRequested' => $isExactMatchRequested,
            'inCurrentChunkOnly' => $inCurrentChunkOnly,
        ]);

        return [
            'job' => $job,
            'token' => $token,
            'source' => $source,
            'target' => $target,
            'status' => $status,
            'replace' => $replace,
            'password' => $password,
            'isMatchCaseRequested' => $isMatchCaseRequested,
            'isExactMatchRequested' => $isExactMatchRequested,
            'inCurrentChunkOnly' => $inCurrentChunkOnly,
            'revisionNumber' => $revision_number,
            'queryParams' => $queryParams,
        ];
    }

    /**
     * @throws Exception
     */
    private function getReplaceHistory(int $job_id): ReplaceHistory
    {
        // ReplaceHistory init
        $srh_driver = ('' !== AppConfig::$REPLACE_HISTORY_DRIVER) ? AppConfig::$REPLACE_HISTORY_DRIVER : 'redis';
        $srh_ttl = (0 !== AppConfig::$REPLACE_HISTORY_TTL) ? AppConfig::$REPLACE_HISTORY_TTL : 300;

        return ReplaceHistoryFactory::create($job_id, $srh_driver, $srh_ttl, $this->getDatabase());
    }

    /**
     * @param SearchQueryParamsStruct $queryParams
     * @param JobStruct $jobStruct
     * @return SearchModel
     * @throws RuntimeException
     * @throws TypeError
     */
    private function getSearchModel(SearchQueryParamsStruct $queryParams, JobStruct $jobStruct): SearchModel
    {
        $metadata = new MetadataDao($this->getDatabase());

        if ($jobStruct->id === null || $jobStruct->password === null) {
            throw new RuntimeException("Job struct has null id or password");
        }

        $filter = MateCatFilter::getInstance($this->getFeatureSet(), $jobStruct->source, $jobStruct->target, [], $metadata->getSubfilteringCustomHandlers($jobStruct->id, $jobStruct->password));

        if (!$filter instanceof MateCatFilter) {
            throw new RuntimeException("Expected MateCatFilter instance");
        }

        return new SearchModel($queryParams, $filter, $this->getDatabase());
    }

    /**
     * @return array<int, array{id_segment: int, id_job: int, translation: string|null, status: string}>
     */
    private function getSegmentForUndoReplaceAll(ReplaceHistory $srh): array
    {
        $results = [];
        $cursor = $srh->getCursor();

        if ($cursor === 0) {
            return $results; // nothing applied yet, nothing to undo
        }

        // Undo reverts the CURRENT version: restore each segment's pre-replacement text and the status
        // it had before that replace (both captured in the replace event when it was applied).
        $events = $srh->get($cursor);

        foreach ($events as $event) {
            $results[] = [
                'id_segment' => $event->id_segment,
                'id_job' => $event->id_job,
                'translation' => $event->translation_before_replacement,
                'status' => $event->status,
            ];
        }

        return $results;
    }

    /**
     * @param array{
     *     queryParams: SearchQueryParamsStruct,
     *     source?: string|false,
     *     target?: string|false,
     *     status?: string|false,
     *     status_only?: bool,
     *     inCurrentChunkOnly?: bool,
     *     job: int,
     *     password: string|false
     * } $request
     *
     * @return array<string, mixed>
     *
     * @throws DomainException
     * @throws RuntimeException
     * @throws TypeError
     */
    private function doSearch(array $request): array
    {
        $queryParams = $request['queryParams'] instanceof SearchQueryParamsStruct
            ? $request['queryParams']
            : new SearchQueryParamsStruct($request['queryParams']);

        if (!empty($request['source']) and !empty($request['target'])) {
            $queryParams['key'] = 'coupled';
            $queryParams['src'] = html_entity_decode((string)$request['source']); // source strings are not escaped as html entites in DB. Example: &lt; must be decoded to <
            $queryParams['trg'] = (string)$request['target'];
        } elseif (!empty($request['source'])) {
            $queryParams['key'] = 'source';
            $queryParams['src'] = html_entity_decode((string)$request['source']); // source strings are not escaped as html entites in DB. Example: &lt; must be decoded to <
        } elseif (!empty($request['target'])) {
            $queryParams['key'] = 'target';
            $queryParams['trg'] = (string)$request['target'];
        } else {
            $queryParams['key'] = 'status_only';
        }

        try {
            $inCurrentChunkOnly = $queryParams['inCurrentChunkOnly'];
            $searchModel = $this->getSearchModel($queryParams, $this->chunk);

            return $searchModel->search($inCurrentChunkOnly);
        } catch (Exception) {
            throw new RuntimeException("internal error: see the log", -1000);
        }
    }

    /**
     * Applies the translation change to each segment and returns the rows that were committed
     * (segments whose translation/segment could not be loaded are skipped). Replace-history writing
     * is intentionally NOT done here — it belongs to the forward replaceAll() path only, so undo/redo
     * (which also call this method) do not advance the replace cursor or emit replace events.
     *
     * When $isHistoryReplay is true (undo/redo), each row already carries the FINAL text and status to
     * persist (the historical values from the replace event), so no find-and-replace or status ladder is
     * applied — the segment is restored exactly. Because the reviewed-word/advancement/pass-fail counters
     * are driven purely by the status transition, restoring the historical status also moves the counters
     * back correctly. When false (forward replace), the replacement text is computed from $queryParams and
     * the status from the review ladder.
     *
     * @param array<int, array<string, mixed>> $search_results
     *
     * @return array<int, array<string, mixed>> committed rows (subset of $search_results)
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws TypeError
     * @throws Exception
     */
    private function updateSegments(
        array $search_results,
        int $id_job,
        SearchQueryParamsStruct $queryParams,
        bool $isHistoryReplay = false
    ): array
    {
        $db = $this->getDatabase();

        $revisionNumber = ReviewUtils::sourcePageToRevisionNumber($this->chunk->getSourcePage());
        $project = (new ProjectDao($this->getDatabase()))->findByJobId($id_job);

        if ($project === null) {
            throw new NotFoundException("Project not found for job $id_job");
        }

        // loop all segments to replace
        $committed = [];
        foreach ($search_results as $tRow) {
            // start the transaction
            $db->begin();

            $versionsHandler = TranslationVersions::getVersionHandlerNewInstance($this->chunk, $this->user, $project, (int)$tRow['id_segment'], $this->getDatabase());

            $segmentTranslationDao = new SegmentTranslationDao($this->getDatabase());
            $old_translation = $segmentTranslationDao->findBySegmentAndJob((int)$tRow['id_segment'], (int)$tRow['id_job']);
            $segment = (new SegmentDao($this->getDatabase()))->fetchById((int)$tRow['id_segment'], SegmentStruct::class);

            if ($old_translation === null || $segment === null) {
                $db->rollback();
                continue;
            }

            // Propagation
            $propagationTotal = [
                'propagated_ids' => []
            ];

            if ($isHistoryReplay) {
                // Undo/redo: the row already holds the exact historical text to restore.
                $replacedTranslation = Utils::stripBOM((string)($tRow['translation'] ?? ''));
            } else {
                $filter = MateCatFilter::getInstance($this->getFeatureSet(), $this->chunk->source, $this->chunk->target);
                $replacedTranslation = $filter->fromLayer1ToLayer0($this->getReplacedSegmentTranslation((string)($tRow['translation'] ?? ''), $queryParams));
                $replacedTranslation = Utils::stripBOM($replacedTranslation);
            }

            // Setup $new_translation
            $new_translation = new SegmentTranslationStruct();
            $new_translation->id_segment = $tRow['id_segment'];
            $new_translation->id_job = $id_job;
            // Undo/redo restore the exact historical status; forward applies the review ladder. Because
            // all reviewed-word/advancement/pass-fail counters are driven purely by the status
            // transition, restoring the historical status moves the counters back correctly too.
            $new_translation->status = $isHistoryReplay ? (string)$tRow['status'] : $this->getNewStatus($old_translation, $revisionNumber);
            $new_translation->time_to_edit = $old_translation->time_to_edit;
            $new_translation->segment_hash = $segment->segment_hash;
            $new_translation->translation = $replacedTranslation;
            $new_translation->serialized_errors_list = $old_translation->serialized_errors_list;
            $new_translation->suggestion_position = $old_translation->suggestion_position;
            $new_translation->warning = $old_translation->warning;
            $new_translation->translation_date = date("Y-m-d H:i:s");

            // commit the transaction
            try {
                // Save version. saveVersionAndIncrement() sets $new_translation->version_number
                // (old + 1 when the text changed, else old), so no manual pre-increment is needed here.
                $versionsHandler->saveVersionAndIncrement($new_translation, $old_translation);

                // Write the translation row first, then persist the version/review event, mirroring
                // SetTranslationController. Keeping storeTranslationEvent inside this try means a
                // handler failure rolls back the whole segment instead of abandoning the open
                // transaction (which previously skipped both the event and the translation write).
                $segmentTranslationDao->updateTranslationAndStatusAndDate($new_translation);

                // preSetTranslationCommitted
                $versionsHandler->storeTranslationEvent([
                    'translation' => $new_translation,
                    'old_translation' => $old_translation,
                    'propagation' => $propagationTotal,
                    'chunk' => $this->chunk,
                    'user' => $this->user,
                    'source_page_code' => $this->chunk->getSourcePage(),
                    'features' => $this->featureSet,
                    'project' => $project
                ]);

                $db->commit();
                $committed[] = $tRow;
            } catch (Exception $e) {
                $this->logger->debug("Lock: Transaction Aborted. " . $e->getMessage());
                $db->rollback();

                throw new RuntimeException("A fatal error occurred during saving of segments");
            }

            // setTranslationCommitted
            try {
                $this->featureSet->dispatch(new SetTranslationCommittedEvent([
                    'translation' => $new_translation,
                    'old_translation' => $old_translation,
                    'propagated_ids' => $propagationTotal['propagated_ids'],
                    'chunk' => $this->chunk,
                    'segment' => $segment,
                    'user' => $this->user,
                    'source_page_code' => $this->chunk->getSourcePage()
                ]));
            } catch (Exception $e) {
                $this->logger->debug("Exception in setTranslationCommitted callback . " . $e->getMessage() . "\n" . $e->getTraceAsString());

                throw new RuntimeException("Exception in setTranslationCommitted callback");
            }
        }

        // Refresh chunk completion once for the whole batch (mirrors SetTranslationController): the
        // per-segment SetTranslationCommittedEvent above does not update ProjectCompletion's
        // chunk_completion tracking — PostAddSegmentTranslationEvent does. Only emit it when at least
        // one segment was committed. Applies to undo/redo replays too, which also change statuses.
        if (!empty($committed)) {
            $this->featureSet->dispatch(new PostAddSegmentTranslationEvent([
                'chunk' => $this->chunk,
                'is_review' => $this->chunk->isReview(),
                'logged_user' => $this->user,
            ]));
        }

        return $committed;
    }

    /**
     * @param SegmentTranslationStruct $translationStruct
     * @param int|null $revisionNumber
     * @return string
     */
    private function getNewStatus(SegmentTranslationStruct $translationStruct, ?int $revisionNumber = null): string
    {
        // A replace-all is an automated, unseen change, so the current editor must re-review it: the
        // touched segment is demoted to one tier BELOW the actor's review level, and never higher than
        // the segment's own current tier (a replace can only demote, never promote). The actor is
        // identified by $revisionNumber: null = translator, 1 = R1, 2 = R2.
        //
        //   ceiling (one tier below the actor):
        //     translator -> DRAFT, R1 -> TRANSLATED, R2 -> APPROVED
        //   result = min(currentTier, ceilingTier), mapped back to a status:
        //     translator:  APPROVED2/APPROVED/TRANSLATED -> DRAFT
        //     R1:          APPROVED2/APPROVED/TRANSLATED -> TRANSLATED
        //     R2:          APPROVED2 -> APPROVED, APPROVED stays APPROVED, TRANSLATED stays TRANSLATED
        $tierOfStatus = [
            TranslationStatus::STATUS_NEW        => 0,
            TranslationStatus::STATUS_DRAFT      => 0,
            TranslationStatus::STATUS_REJECTED   => 0,
            TranslationStatus::STATUS_TRANSLATED => 1,
            TranslationStatus::STATUS_APPROVED   => 2,
            TranslationStatus::STATUS_APPROVED2  => 3,
        ];
        $statusOfTier = [
            0 => TranslationStatus::STATUS_DRAFT,
            1 => TranslationStatus::STATUS_TRANSLATED,
            2 => TranslationStatus::STATUS_APPROVED,
        ];

        $ceilingTier = max(0, min(2, $revisionNumber ?? 0));
        $currentTier = $tierOfStatus[$translationStruct->status] ?? 0;

        return $statusOfTier[min($currentTier, $ceilingTier)];
    }

    /**
     * @param string $translation
     * @param SearchQueryParamsStruct $queryParams
     * @return string
     */
    private function getReplacedSegmentTranslation(string $translation, SearchQueryParamsStruct $queryParams): string
    {
        $replacedSegmentTranslation = WholeTextFinder::findAndReplace(
            $translation,
            $queryParams->target ?? '',
            $queryParams->replacement ?? '',
            true,
            $queryParams->isExactMatchRequested,
            $queryParams->isMatchCaseRequested,
            true
        );

        return (!empty($replacedSegmentTranslation)) ? $replacedSegmentTranslation['replacement'] : $translation;
    }

    /**
     * @param array<string, mixed> $tRow
     *
     * @throws DomainException
     * @throws TypeError
     * @throws Exception
     */
    private function saveReplacementEvent(string $replace_version, array $tRow, ReplaceHistory $srh, SearchQueryParamsStruct $queryParams): void
    {
        $event = new ReplaceEventStruct();
        $event->replace_version = $replace_version;
        $event->id_segment = $tRow['id_segment'];
        $event->id_job = $queryParams['job'];
        $event->job_password = $queryParams['password'];
        $event->source = $queryParams['source'];
        $event->target = $queryParams['target'];
        $event->replacement = $queryParams['replacement'];
        $event->translation_before_replacement = $tRow['translation'];
        $event->translation_after_replacement = $this->getReplacedSegmentTranslation((string)($tRow['translation'] ?? ''), $queryParams);
        $event->status = $tRow['status'];

        $srh->save($event);
        // NOTE: the undo-cursor advance moved to updateSegments(), once after the batch loop, so
        // the whole replace-all lands atomically in history. See updateSegments().

        $this->logger->debug('Replacement event for segment #' . $tRow['id_segment'] . ' correctly saved.');
    }
}
