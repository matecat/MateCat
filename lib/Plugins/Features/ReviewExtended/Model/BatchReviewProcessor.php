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

        $chunkReviews    = ( new ChunkReviewDao() )->findChunkReviews( $this->_batchEventCreator->getChunk() );
        $project         = $chunkReviews[ 0 ]->getChunk()->getProject();
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
        if($uow->commit()){
            // send notification emails
            foreach ( $segmentTranslationModels as $segmentTranslationModel ) {
                $segmentTranslationModel->sendNotificationEmail();
            }
        }
    }
}