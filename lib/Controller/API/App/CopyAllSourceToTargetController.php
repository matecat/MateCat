<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use DomainException;
use Exception;
use Model\FeaturesBase\FeatureCodes;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectDao;
use Model\Segments\SegmentDao;
use Model\Translations\SegmentTranslationDao;
use Plugins\Features\ReviewExtended\BatchReviewProcessor;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use ReflectionException;
use RuntimeException;
use Throwable;
use TypeError;
use Utils\Constants\TranslationStatus;

class CopyAllSourceToTargetController extends KleinController
{
    private JobStruct $chunk;

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $Validator = new ChunkPasswordValidator($this);
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk = $Validator->getChunk();
            if ($this->chunk->isReview()) {
                throw new DomainException('The source cannot be fully copied to the target while in the revision phase.');
            }
        });
        $this->appendValidator($Validator);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function copy(): void
    {
        $data = $this->saveEventsAndUpdateTranslations($this->chunk);
        $this->response->json($data);
    }

    /**
     * @param JobStruct $chunk
     *
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws TypeError
     * @throws Exception
     * @throws Throwable the write runs inside a transaction scope, which aborts the transaction
     *                   on any throw and re-throws the original, whatever its type
     */
    private function saveEventsAndUpdateTranslations(JobStruct $chunk): array
    {
        $database = $this->getDatabase();

        return $database->transaction(function () use ($chunk, $database): array {
            $project = $chunk->getProject(new ProjectDao($database));
            $features = FeatureSet::forProject($project, $database);

            // Built once and reused for every segment. Constructing them inside the loop bought
            // nothing and made a chunk-sized copy allocate three DAOs per segment.
            $segmentTranslationDao = new SegmentTranslationDao($database);
            $translationEventDao = new TranslationEventDao($database);
            $segmentDao = new SegmentDao($database);

            $batchEventCreator = new TranslationEventsHandler($chunk, $translationEventDao);
            $batchEventCreator->setFeatureSet($features);
            $batchEventCreator->setProject($project);
            $segments = $chunk->getSegments($segmentDao);

            $chunk_id = (int)$chunk->id;

            // One read for the whole job instead of one SELECT per segment. The per-segment call
            // this replaces keyed on (id_segment, id_job), so this is the same row set indexed by
            // segment id; a job split into chunks returns its siblings' rows too, and the lookup
            // below simply never asks for them.
            $translations = [];
            foreach ($segmentTranslationDao->getByJobId($chunk_id) as $translation) {
                $translations[(int)$translation->id_segment] = $translation;
            }

            $affected_rows = 0;

            foreach ($segments as $segment) {
                $old_translation = $translations[(int)$segment->id] ?? null;

                if (empty($old_translation) || ($old_translation->status !== TranslationStatus::STATUS_NEW)) {
                    //no segment found
                    continue;
                }

                $new_translation = clone $old_translation;
                $new_translation->translation = $segment->segment;
                $new_translation->status = TranslationStatus::STATUS_DRAFT;
                $new_translation->translation_date = date("Y-m-d H:i:s");

                try {
                    // One UPDATE per row, deliberately: updateTranslationAndStatusAndDateByList()
                    // is not this statement in bulk. It is an INSERT .. ON DUPLICATE KEY UPDATE,
                    // so it writes a row where this UPDATE matches none — and it never binds
                    // segment_hash, which is NOT NULL with no default. The read above snapshots
                    // the rows once; DatabaseCleanTask erases segment_translations by id_job in
                    // autocommit batches, so a job wiped mid-copy leaves this loop holding rows
                    // that no longer exist. That is a no-op here. Batched, it recreates each one
                    // with an empty segment_hash, which is the key TM propagation matches on.
                    $affected_rows += $segmentTranslationDao->updateTranslationAndStatusAndDate($new_translation);
                } catch (Exception $e) {
                    // Re-thrown rather than rolled back: the scope owns the undo and re-throws this.
                    throw new RuntimeException($e->getMessage(), -4);
                }

                if ($features->hasFeature(FeatureCodes::TRANSLATION_VERSIONS)) {
                    try {
                        $segmentTranslationEventModel = new TranslationEvent(
                            $old_translation,
                            $new_translation,
                            $this->user,
                            $this->chunk->getSourcePage(),
                            null,
                            $translationEventDao,
                            $segmentDao
                        );
                        $batchEventCreator->addEvent($segmentTranslationEventModel);
                    } catch (Exception) {
                        throw new RuntimeException("Job archived or deleted", -5);
                    }
                }
            }

            // save all events
            $batchEventCreator->save(new BatchReviewProcessor(new ChunkReviewDao($database), $this->user));

            $data = [
                'code' => 1,
                'segments_modified' => $affected_rows
            ];

            $this->logger->debug('Segment Translation events saved completed');
            $this->logger->debug($data);

            return $data;
        });
    }
}

