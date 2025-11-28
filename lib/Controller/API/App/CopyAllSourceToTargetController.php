<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureCodes;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Translations\SegmentTranslationDao;
use Model\WordCount\CounterModel;
use Plugins\Features\ReviewExtended\BatchReviewProcessor;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use ReflectionException;
use RuntimeException;
use Utils\Constants\TranslationStatus;

class CopyAllSourceToTargetController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function copy(): void
    {
        $request         = $this->validateTheRequest();
        $revision_number = $request[ 'revision_number' ];
        $job_data        = $request[ 'job_data' ];

        $data = $this->saveEventsAndUpdateTranslations($job_data, (int)$revision_number);
        $this->response->json($data);
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $pass            = filter_var($this->request->param('pass'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $id_job          = filter_var($this->request->param('id_job'), FILTER_SANITIZE_NUMBER_INT);
        $revision_number = filter_var($this->request->param('revision_number'), FILTER_SANITIZE_NUMBER_INT);

        $this->logger->debug("Requested massive copy-source-to-target for job $id_job.");

        if (empty($id_job)) {
            throw new InvalidArgumentException("Empty id job", -1);
        }
        if (empty($pass)) {
            throw new InvalidArgumentException("Empty job password", -2);
        }

        $job_data = JobDao::getByIdAndPassword($id_job, $pass);

        if (empty($job_data)) {
            throw new InvalidArgumentException("Wrong id_job-password couple. Job not found", -3);
        }

        return [
                'id_job'          => $id_job,
                'pass'            => $pass,
                'revision_number' => $revision_number,
                'job_data'        => $job_data,
        ];
    }

    /**
     * @param JobStruct $chunk
     * @param int       $revision_number
     *
     * @return array
     * @throws ReflectionException
     * @throws Exception
     */
    private function saveEventsAndUpdateTranslations(JobStruct $chunk, int $revision_number): array
    {
        // BEGIN TRANSACTION
        $database = Database::obtain();
        $database->begin();

        $features = $chunk->getProject()->getFeaturesSet();

        $batchEventCreator = new TranslationEventsHandler($chunk);
        $batchEventCreator->setFeatureSet($features);
        $batchEventCreator->setProject($chunk->getProject());

        $source_page = ReviewUtils::revisionNumberToSourcePage($revision_number);
        $segments    = $chunk->getSegments();

        $affected_rows = 0;

        foreach ($segments as $segment) {
            $segment_id = $segment->id;
            $chunk_id   = (int)$chunk->id;

            $old_translation = SegmentTranslationDao::findBySegmentAndJob($segment_id, $chunk_id);

            if (empty($old_translation) || ($old_translation->status !== TranslationStatus::STATUS_NEW)) {
                //no segment found
                continue;
            }

            $new_translation                   = clone $old_translation;
            $new_translation->translation      = $segment->segment;
            $new_translation->status           = TranslationStatus::STATUS_DRAFT;
            $new_translation->translation_date = date("Y-m-d H:i:s");

            try {
                $affected_rows += SegmentTranslationDao::updateTranslationAndStatusAndDate($new_translation);
            } catch (Exception $e) {
                $database->rollback();

                throw new RuntimeException($e->getMessage(), -4);
            }

            if ($chunk->getProject()->hasFeature(FeatureCodes::TRANSLATION_VERSIONS)) {
                try {
                    $segmentTranslationEventModel = new TranslationEvent($old_translation, $new_translation, $this->user, $source_page);
                    $batchEventCreator->addEvent($segmentTranslationEventModel);
                } catch (Exception) {
                    $database->rollback();

                    throw new RuntimeException("Job archived or deleted", -5);
                }
            }
        }

        // save all events
        $batchEventCreator->save(new BatchReviewProcessor());

        if (!empty($params[ 'segment_ids' ])) {
            $counter = new CounterModel();
            $counter->initializeJobWordCount($chunk->id, $chunk->password);
        }

        $data = [
                'code'              => 1,
                'segments_modified' => $affected_rows
        ];

        $this->logger->debug('Segment Translation events saved completed');
        $this->logger->debug($data);

        $database->commit(); // COMMIT TRANSACTION

        return $data;
    }
}

