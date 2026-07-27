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
     */
    private function saveEventsAndUpdateTranslations(JobStruct $chunk): array
    {
        // BEGIN TRANSACTION
        $database = $this->getDatabase();
        $database->begin();

        $features = FeatureSet::forProject($chunk->getProject(new ProjectDao($this->getDatabase())), $this->getDatabase());

        $batchEventCreator = new TranslationEventsHandler($chunk, new TranslationEventDao($this->getDatabase()));
        $batchEventCreator->setFeatureSet($features);
        $batchEventCreator->setProject($chunk->getProject(new ProjectDao($this->getDatabase())));
        $segments = $chunk->getSegments(new SegmentDao($this->getDatabase()));

        $affected_rows = 0;

        foreach ($segments as $segment) {
            $segment_id = $segment->id;
            $chunk_id = (int)$chunk->id;

            $segmentTranslationDao = new SegmentTranslationDao($this->getDatabase());
            $old_translation = $segmentTranslationDao->findBySegmentAndJob($segment_id, $chunk_id);

            if (empty($old_translation) || ($old_translation->status !== TranslationStatus::STATUS_NEW)) {
                //no segment found
                continue;
            }

            $new_translation = clone $old_translation;
            $new_translation->translation = $segment->segment;
            $new_translation->status = TranslationStatus::STATUS_DRAFT;
            $new_translation->translation_date = date("Y-m-d H:i:s");

            try {
                $affected_rows += $segmentTranslationDao->updateTranslationAndStatusAndDate($new_translation);
            } catch (Exception $e) {
                $database->rollback();

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
                        new TranslationEventDao($this->getDatabase()),
                        new SegmentDao($this->getDatabase())
                    );
                    $batchEventCreator->addEvent($segmentTranslationEventModel);
                } catch (Exception) {
                    $database->rollback();

                    throw new RuntimeException("Job archived or deleted", -5);
                }
            }
        }

        // save all events
        $batchEventCreator->save(new BatchReviewProcessor(new ChunkReviewDao($this->getDatabase())));

        $data = [
            'code' => 1,
            'segments_modified' => $affected_rows
        ];

        $this->logger->debug('Segment Translation events saved completed');
        $this->logger->debug($data);

        $database->commit(); // COMMIT TRANSACTION

        return $data;
    }
}

