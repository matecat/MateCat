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
        $this->updateSegments($search_results, (int)$this->chunk->id, $request['queryParams']);

        $this->response->json([
            "errors" => [],
            "data" => [],
            "token" => $request['token'] ?? null,
            "total" => $res['count'] ?? 0,
            "segments" => $res['sid_list']
        ]);
    }

    // not is use

    /**
     * @throws TypeError
     * @throws Exception
     */
    public function redoReplaceAll(): void
    {
        $request = $this->validateTheRequest();
        $shr = $this->getReplaceHistory((int)$this->chunk->id);
        $search_results = $this->getSegmentForRedoReplaceAll($shr);
        $this->updateSegments($search_results, (int)$this->chunk->id, $request['queryParams']);
        $shr->redo();

        $this->response->json([
            'success' => true
        ]);
    }

    // not is use

    /**
     * @throws TypeError
     * @throws Exception
     */
    public function undoReplaceAll(): void
    {
        $request = $this->validateTheRequest();
        $shr = $this->getReplaceHistory((int)$this->chunk->id);
        $search_results = $this->getSegmentForUndoReplaceAll($shr);
        $this->updateSegments($search_results, (int)$this->chunk->id, $request['queryParams']);
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
    private function getSegmentForRedoReplaceAll(ReplaceHistory $srh): array
    {
        $results = [];

        $versionToMove = $srh->getCursor() + 1;
        $events = $srh->get($versionToMove);

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
     * @return array<int, array{id_segment: int, id_job: int, translation: string|null, status: string}>
     */
    private function getSegmentForUndoReplaceAll(ReplaceHistory $srh): array
    {
        $results = [];
        $cursor = $srh->getCursor();

        if ($cursor === 0) {
            $versionToMove = 0;
        } elseif ($cursor === 1) {
            $versionToMove = 1;
        } else {
            $versionToMove = $cursor - 1;
        }

        $events = $srh->get($versionToMove);

        foreach ($events as $event) {
            $results[] = [
                'id_segment' => $event->id_segment,
                'id_job' => $event->id_job,
                'translation' => $event->translation_after_replacement,
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
     * @param array<int, array<string, mixed>> $search_results
     *
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws TypeError
     * @throws Exception
     */
    private function updateSegments(array $search_results, int $id_job, SearchQueryParamsStruct $queryParams): void
    {
        $db = $this->getDatabase();

        $revisionNumber = ReviewUtils::sourcePageToRevisionNumber($this->chunk->getSourcePage());
        $project = (new ProjectDao($this->getDatabase()))->findByJobId($id_job);

        if ($project === null) {
            throw new NotFoundException("Project not found for job $id_job");
        }

        // loop all segments to replace
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

            $filter = MateCatFilter::getInstance($this->getFeatureSet(), $this->chunk->source, $this->chunk->target);
            $replacedTranslation = $filter->fromLayer1ToLayer0($this->getReplacedSegmentTranslation((string)($tRow['translation'] ?? ''), $queryParams));
            $replacedTranslation = Utils::stripBOM($replacedTranslation);

            // Setup $new_translation
            $new_translation = new SegmentTranslationStruct();
            $new_translation->id_segment = $tRow['id_segment'];
            $new_translation->id_job = $id_job;
            $new_translation->status = $this->getNewStatus($old_translation, $revisionNumber);
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

                // and save replace events
                $srh = $this->getReplaceHistory($id_job);
                $replace_version = (string)($srh->getCursor() + 1);

                $this->saveReplacementEvent($replace_version, $tRow, $srh, $queryParams);

                $db->commit();
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
    }

    /**
     * @param SegmentTranslationStruct $translationStruct
     * @param int|null $revisionNumber
     * @return string
     */
    private function getNewStatus(SegmentTranslationStruct $translationStruct, ?int $revisionNumber = null): string
    {
        if (!isset($revisionNumber)) {
            return TranslationStatus::STATUS_DRAFT;
        }

        // On a revision page the replace is a review action: the segment moves to the approval
        // status of that revision level. Returning STATUS_TRANSLATED here would collide with the
        // revision source_page and make TranslationEventsHandler::prepareEventStruct() throw
        // ('Setting translated state from revision is not allowed'), which silently drops the event.
        return match ($translationStruct->status) {
            TranslationStatus::STATUS_APPROVED => TranslationStatus::STATUS_TRANSLATED,
            TranslationStatus::STATUS_APPROVED2 => TranslationStatus::STATUS_APPROVED,
            default => TranslationStatus::STATUS_DRAFT
        };
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
        $srh->updateIndex((int)$replace_version);

        $this->logger->debug('Replacement event for segment #' . $tRow['id_segment'] . ' correctly saved.');
    }
}
