<?php

namespace Plugins\Features\TranslationVersions\Handlers;

use Exception;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDao;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use Plugins\Features\ReviewExtended\BatchReviewProcessor;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use Plugins\Features\TranslationVersions\Model\TranslationVersionStruct;
use Plugins\Features\TranslationVersions\VersionHandlerInterface;
use RuntimeException;
use Utils\Constants\TranslationStatus;

/**
 * Class TranslationVersionsHandler
 *
 */
class TranslationVersionsHandler implements VersionHandlerInterface
{

    /**
     * @var TranslationVersionDao
     */
    private TranslationVersionDao $dao;

    /**
     * @var JobStruct
     */
    private JobStruct $chunkStruct;

    /**
     * @var int
     */
    private int $id_segment;

    private ProjectStruct $projectStruct;

    private SegmentTranslationDao $segmentTranslationDao;
    private JobDao $jobDao;
    private ProjectDao $projectDao;
    private IDatabase $database;

    /**
     * TranslationVersionsHandler constructor.
     *
     * @param JobStruct $chunkStruct
     * @param int|null $id_segment
     * @param ProjectStruct $projectStruct
     * @param IDatabase $database
     *
     * @throws RuntimeException
     */
    public function __construct(
        JobStruct $chunkStruct,
        ?int $id_segment,
        ProjectStruct $projectStruct,
        IDatabase $database,
    ) {
        if ($chunkStruct->id === null) {
            throw new RuntimeException('Job id is required');
        }
        $this->chunkStruct = $chunkStruct;
        $this->id_segment = $id_segment ?? throw new RuntimeException('Segment id is required');
        $this->projectStruct = $projectStruct;
        $this->database = $database;
        $this->dao = new TranslationVersionDao($database);
        $this->segmentTranslationDao = new SegmentTranslationDao($database);
        $this->jobDao = new JobDao($database);
        $this->projectDao = new ProjectDao($database);
    }

    /**
     * Save the current version and perform up-count
     *
     * If returns true it means that a new version of the parent segment was persisted
     *
     * @param SegmentTranslationStruct $new_translation
     * @param SegmentTranslationStruct $old_translation
     *
     * @return bool
     * @throws \TypeError
     * @throws \PDOException
     */
    public function saveVersionAndIncrement(SegmentTranslationStruct $new_translation, SegmentTranslationStruct $old_translation): bool
    {
        $version_saved = $this->saveVersion($new_translation, $old_translation);

        if ($version_saved) {
            $new_translation->version_number = $old_translation->version_number + 1;
        } else {
            $new_translation->version_number = $old_translation->version_number ?? 0;
        }

        return $version_saved;
    }

    /**
     * @throws Exception
     */
    public function propagateTranslation(SegmentTranslationStruct $translationStruct): array
    {
        return $this->segmentTranslationDao->propagateTranslation(
            $translationStruct,
            $this->chunkStruct,
            $this->id_segment,
            $this->projectStruct,
        );
    }

    /**
     * Evaluates the need to save a new translation version to the database.
     *
     * @param SegmentTranslationStruct $new_translation
     * @param SegmentTranslationStruct $old_translation
     *
     * @return bool
     * @throws \TypeError
     * @throws \PDOException
     */
    private function saveVersion(
        SegmentTranslationStruct $new_translation,
        SegmentTranslationStruct $old_translation
    ): bool {
        if ($new_translation->translation == ($old_translation->translation ?? '')) {
            return false;
        }

        // avoid version_number null error
        if ($new_translation->version_number === null) {
            $new_translation->version_number = 0;
        }

        // avoid version_number null error
        if ($old_translation->version_number === null) {
            $old_translation->version_number = 0;
        }

        $new_version = new TranslationVersionStruct($old_translation->toArray());
        $new_version->old_status = TranslationStatus::$DB_STATUSES_MAP[$old_translation->status];
        $new_version->new_status = TranslationStatus::$DB_STATUSES_MAP[$new_translation->status];

        // segment_translation_versions has no unique constraint on (id_job, id_segment,
        // version_number), so a blind insert can duplicate a row already written for this key —
        // most commonly version 0, which ReviewExtended\TranslationIssueModel::saveDiff() may
        // already have written (raw_diff set, translation NULL) before the translator ever
        // saves. Reconcile onto that row instead of inserting a second one.
        //
        // Unlike the previous check-then-update flow, the return value never depends on
        // updateVersion()'s row count: we already established above that the translation text
        // changed, so a version was genuinely saved whether this ends up as an insert or update.
        $existing_version = $this->dao->getVersionNumberForTranslation(
            $new_version->id_job,
            $new_version->id_segment,
            $new_version->version_number
        );

        if ($existing_version) {
            $this->dao->updateVersion($new_version);
        } else {
            $this->dao->insertVersion($new_version);
        }

        return true;
    }


    /**
     * @throws Exception
     * @throws \TypeError
     */
    public function storeTranslationEvent(array $params): void
    {
        // evaluate if the record is to be created, either the
        // status changed, or the translation changed
        $user = $params['user'];

        /** @var SegmentTranslationStruct $translation */
        $translation = $params['translation'];

        /** @var SegmentTranslationStruct $old_translation */
        $old_translation = $params['old_translation'];

        $source_page_code = $params['source_page_code'];

        /** @var JobStruct $chunk */
        $chunk = $params['chunk'];

        /** @var FeatureSet $features */
        $features = $params['features'];

        /** @var ProjectStruct $project */
        $project = $params['project'];

        $sourceEvent = $this->createTranslationEvent(
            $old_translation,
            $translation,
            $user,
            $source_page_code,
            $chunk,
        );

        $translationEventsHandler = $this->createTranslationEventsHandler($chunk);
        $translationEventsHandler->setFeatureSet($features);
        $translationEventsHandler->addEvent($sourceEvent);
        $translationEventsHandler->setProject($project);

        // If propagated segments exist, start cycle here
        // There is no logic here, the version_number is simply got from $segmentTranslationBeforeChange and saved as is in translation events
        if (isset($params['propagation']['segments_for_propagation']['propagated']) and !empty($params['propagation']['segments_for_propagation']['propagated'])) {
            $segments_for_propagation = $params['propagation']['segments_for_propagation']['propagated'];
            $segmentTranslations = [];

            if (!empty($segments_for_propagation['not_ice'])) {
                $segmentTranslations = array_merge($segmentTranslations, $segments_for_propagation['not_ice']['object']);
            }

            if (!empty($segments_for_propagation['ice'])) {
                $segmentTranslations = array_merge($segmentTranslations, $segments_for_propagation['ice']['object']);
            }

            foreach ($segmentTranslations as $segmentTranslationBeforeChange) {
                /** @var SegmentTranslationStruct $propagatedSegmentAfterChange */
                $propagatedSegmentAfterChange = clone $segmentTranslationBeforeChange;
                $propagatedSegmentAfterChange->translation = $translation->translation;
                $propagatedSegmentAfterChange->status = $translation->status;
                $propagatedSegmentAfterChange->autopropagated_from = $translation->id_segment; // nullable
                $propagatedSegmentAfterChange->time_to_edit = 0;

                $propagatedEvent = $this->createTranslationEvent(
                    $segmentTranslationBeforeChange,
                    $propagatedSegmentAfterChange,
                    $user,
                    $source_page_code,
                    $chunk,
                );

                $propagatedEvent->setPropagationSource(false);
                $translationEventsHandler->addEvent($propagatedEvent);
            }
        }

        try {
            $translationEventsHandler->save($this->createBatchReviewProcessor());
            $this->jobDao->destroyCacheByProjectId($chunk->id_project);
            $this->projectDao->destroyFetchByIdCache($chunk->id_project, ProjectStruct::class);
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), -2000, $e);
        }
    }

    /**
     * @throws RuntimeException
     */
    protected function createTranslationEvent(
        SegmentTranslationStruct $old_translation,
        SegmentTranslationStruct $translation,
        ?UserStruct $user,
        int $source_page_code,
        JobStruct $chunk,
    ): TranslationEvent {
        return new TranslationEvent(
            $old_translation,
            $translation,
            $user,
            $source_page_code,
            $chunk,
            new TranslationEventDao($this->database),
            new SegmentDao($this->database),
        );
    }

    protected function createTranslationEventsHandler(JobStruct $chunk): TranslationEventsHandler
    {
        return new TranslationEventsHandler($chunk, new TranslationEventDao($this->database));
    }

    protected function createBatchReviewProcessor(): BatchReviewProcessor
    {
        return new BatchReviewProcessor(new ChunkReviewDao($this->database));
    }

}
