<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Chunks_ChunkDao;
use Constants_TranslationStatus;
use Database;
use Exception;
use Features;
use Features\ReviewExtended\BatchReviewProcessor;
use Features\ReviewExtended\ReviewUtils;
use Features\TranslationEvents\Model\TranslationEvent;
use Features\TranslationEvents\TranslationEventsHandler;
use Jobs_JobDao;
use Log;
use Translations_SegmentTranslationDao;
use WordCount\CounterModel;

class CopyAllSourceToTargetController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @return \Klein\Response
     * @throws \Exceptions\NotFoundException
     * @throws \ReflectionException
     */
    public function copy()
    {
        $pass = filter_var( $this->request->param( 'pass' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $revision_number = filter_var( $this->request->param( 'revision_number' ), FILTER_SANITIZE_NUMBER_INT );

        Log::doJsonLog( "Requested massive copy-source-to-target for job $id_job." );

        if ( empty( $id_job ) ) {
            return $this->return400Error(-1, "Empty id job");

        }
        if ( empty( $pass ) ) {
            return $this->return400Error(-2, "Empty job password");
        }

        $job_data = Jobs_JobDao::getByIdAndPassword( $id_job, $pass );

        if ( empty( $job_data ) ) {
            $this->return400Error(-3, "Wrong id_job-password couple. Job not found");
        }

        return $this->saveEventsAndUpdateTranslations( $job_data->id, $job_data->password, $revision_number);
    }

    /**
     * @param $job_id
     * @param $password
     * @param $revision_number
     * @return \Klein\Response
     * @throws \Exceptions\NotFoundException
     */
    private function saveEventsAndUpdateTranslations($job_id, $password, $revision_number)
    {
        // BEGIN TRANSACTION
        $database = Database::obtain();
        $database->begin();

        $chunk    = Chunks_ChunkDao::getByIdAndPassword( $job_id, $password );
        $features = $chunk->getProject()->getFeaturesSet();

        $batchEventCreator = new TranslationEventsHandler( $chunk );
        $batchEventCreator->setFeatureSet( $features );
        $batchEventCreator->setProject( $chunk->getProject() );

        $source_page = ReviewUtils::revisionNumberToSourcePage( $revision_number );
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

                return $this->return400Error(-4, $e->getMessage());
            }

            if ( $chunk->getProject()->hasFeature( Features::TRANSLATION_VERSIONS ) ) {
                $segmentTranslationEventModel = new TranslationEvent( $old_translation, $new_translation, $this->user, $source_page );
                $batchEventCreator->addEvent( $segmentTranslationEventModel );
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

        Log::doJsonLog( 'Segment Translation events saved completed' );
        Log::doJsonLog( $data );

        $database->commit();

        return $this->response->json([
            'data' => $data
        ]);
    }
}

