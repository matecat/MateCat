<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Constants_TranslationStatus;
use Database;
use Exception;
use Features;
use Features\ReviewExtended\BatchReviewProcessor;
use Features\ReviewExtended\ReviewUtils;
use Features\TranslationEvents\Model\TranslationEvent;
use Features\TranslationEvents\TranslationEventsHandler;
use InvalidArgumentException;
use Jobs_JobDao;
use Jobs_JobStruct;
use Klein\Response;
use RuntimeException;
use Translations_SegmentTranslationDao;
use WordCount\CounterModel;

class CopyAllSourceToTargetController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @return Response
     */
    public function copy(): Response
    {
        try {
            $request = $this->validateTheRequest();
            $revision_number = $request['revision_number'];
            $job_data = $request['job_data'];

            return $this->saveEventsAndUpdateTranslations( $job_data, $revision_number);
        } catch (Exception $exception){
            $this->returnException($exception);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $pass = filter_var( $this->request->param( 'pass' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $revision_number = filter_var( $this->request->param( 'revision_number' ), FILTER_SANITIZE_NUMBER_INT );

        $this->log( "Requested massive copy-source-to-target for job $id_job." );

        if ( empty( $id_job ) ) {
            throw new InvalidArgumentException("Empty id job", -1);

        }
        if ( empty( $pass ) ) {
            throw new InvalidArgumentException("Empty job password", -2);
        }

        $job_data = Jobs_JobDao::getByIdAndPassword( $id_job, $pass );

        if ( empty( $job_data ) ) {
            throw new InvalidArgumentException("Wrong id_job-password couple. Job not found", -3);
        }

        return [
            'id_job' => $id_job,
            'pass' => $pass,
            'revision_number' => $revision_number,
            'job_data' => $job_data,
        ];
    }

    /**
     * @param Jobs_JobStruct $chunk
     * @param $revision_number
     * @return Response
     * @throws Exception
     */
    private function saveEventsAndUpdateTranslations(Jobs_JobStruct $chunk, $revision_number): Response
    {
        try {
            // BEGIN TRANSACTION
            $database = Database::obtain();
            $database->begin();

            $features = $chunk->getProject()->getFeaturesSet();

            $batchEventCreator = new TranslationEventsHandler( $chunk );
            $batchEventCreator->setFeatureSet( $features );
            $batchEventCreator->setProject( $chunk->getProject() );

            $source_page = ReviewUtils::revisionNumberToSourcePage( (int)$revision_number );
            $segments    = $chunk->getSegments();

            $affected_rows = 0;

            foreach ( $segments as $segment ) {

                $segment_id = (int)$segment->id;
                $chunk_id   = (int)$chunk->id;

                $old_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $segment_id, $chunk_id );

                if ( empty( $old_translation ) || ( $old_translation->status !== Constants_TranslationStatus::STATUS_NEW ) ) {
                    //no segment found
                    continue;
                }

                $new_translation                   = clone $old_translation;
                $new_translation->translation      = $segment->segment;
                $new_translation->status           = Constants_TranslationStatus::STATUS_DRAFT;
                $new_translation->translation_date = date( "Y-m-d H:i:s" );

                try {
                    $affected_rows += Translations_SegmentTranslationDao::updateTranslationAndStatusAndDate( $new_translation );
                } catch ( Exception $e ) {
                    $database->rollback();

                    throw new RuntimeException($e->getMessage(), -4);
                }

                if ( $chunk->getProject()->hasFeature( Features::TRANSLATION_VERSIONS ) ) {
                    try {
                        $segmentTranslationEventModel = new TranslationEvent( $old_translation, $new_translation, $this->user, $source_page );
                        $batchEventCreator->addEvent( $segmentTranslationEventModel );
                    } catch ( Exception $e ) {
                        $database->rollback();

                        throw new RuntimeException("Job archived or deleted", -5);
                    }
                }
            }

            // save all events
            $batchEventCreator->save( new BatchReviewProcessor() );

            if ( !empty( $params[ 'segment_ids' ] ) ) {
                $counter = new CounterModel();
                $counter->initializeJobWordCount( $chunk->id, $chunk->password );
            }

            $data = [
                'code'              => 1,
                'segments_modified' => $affected_rows
            ];;

            $this->log( 'Segment Translation events saved completed' );
            $this->log( $data );

            $database->commit(); // COMMIT TRANSACTION

            return $this->response->json([
                'data' => $data
            ]);
        } catch (Exception $exception){
            $this->returnException($exception);
        }
    }
}

