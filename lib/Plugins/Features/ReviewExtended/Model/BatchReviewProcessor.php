<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/06/2019
 * Time: 17:44
 */

namespace Features\ReviewExtended\Model;

use ChunkReviewTransition\UnitOfWork;
use Exception;
use Features\ReviewExtended\Email\BatchReviewProcessorAlertEmail;
use Features\TranslationVersions\Model\BatchEventCreator;
use RevisionFactory;
use SegmentTranslationChangeVector;

class BatchReviewProcessor {

    /**
     * @var BatchEventCreator
     */
    protected $_batchEventCreator;

    public function __construct( BatchEventCreator $eventCreator ) {
        $this->_batchEventCreator = $eventCreator;
    }

    /**
     * @throws Exception
     */
    public function process() {

        $chunk = $this->_batchEventCreator->getChunk();

        //
        // ----------------------------------------------
        // Note 2020-06-24
        // ----------------------------------------------
        // If $chunk is null:
        //
        // log and exit
        //
        if ( null === $chunk ) {
            \Log::doJsonLog( 'This batch review processor has not a associated chunk. Exiting here...' );

            return;
        }

        $project      = $chunk->getProject();
        $chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $chunk );

        //
        // ----------------------------------------------
        // Note 2020-06-24
        // ----------------------------------------------
        // If $chunkReviews is empty:
        //
        // 1) create a chunkReview
        // 2) send an alert email
        //
        if ( empty( $chunkReviews ) ) {

            $data = [
                    'id_project'  => $project->id,
                    'id_job'      => $chunk->id,
                    'password'    => $chunk->password,
                    'source_page' => 2,
            ];

            $chunkReview = ChunkReviewDao::createRecord( $data );
            $project->getFeaturesSet()->run( 'chunkReviewRecordCreated', $chunkReview, $project );
            $chunkReviews[] = $chunkReview;

            \Log::doJsonLog( 'Batch review processor created a new chunkReview (id ' . $chunkReview->id . ') for chunk with id ' . $chunk->id );

            $alertEmail = new BatchReviewProcessorAlertEmail( $chunk, $chunkReview );
            $alertEmail->send();
        }

        $revisionFactory = RevisionFactory::initFromProject( $project );

        $chunkReviewTransitionModels = [];
        $segmentTranslationModels    = [];

        foreach ( $this->_batchEventCreator->getPersistedEvents() as $event ) {
            $translationVector             = new SegmentTranslationChangeVector( $event );
            $segmentTranslationModel       = $revisionFactory->getSegmentTranslationModel( $translationVector, $chunkReviews );
            $chunkReviewTransitionModels[] = $segmentTranslationModel->getChunkReviewTransitionModel();
            $segmentTranslationModels[]    = $segmentTranslationModel;
        }

        // uow
        $uow = new UnitOfWork( $chunkReviewTransitionModels );
        if ( $uow->commit() ) {
            // send notification emails
            foreach ( $segmentTranslationModels as $segmentTranslationModel ) {
                $segmentTranslationModel->sendNotificationEmail();
            }
        }
    }
}