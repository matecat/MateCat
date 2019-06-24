<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/06/2019
 * Time: 17:44
 */

namespace Features\ReviewExtended\Model;

use Features\TranslationVersions\Model\BatchEventCreator;
use RevisionFactory;
use SegmentTranslationChangeVector;

class BatchReviewProcessor {

    /**
     * @var BatchEventCreator
     */
    protected $_batchEventCreator;

    public function __construct( BatchEventCreator $eventCreator ) {
        $this->_batchEventCreator = $eventCreator ;
    }

    public function process() {
        $chunkReviews = ( new ChunkReviewDao() )->findAllChunkReviewsByChunkIds(
                [ [ $this->_batchEventCreator->getChunk()->id, $this->_batchEventCreator->getChunk()->password ] ]
        ) ;

        $revisionFactory = RevisionFactory::getInstance() ;

        foreach ( $this->_batchEventCreator->getPersistedEvents() as $event ) {

            $translationVector = new SegmentTranslationChangeVector( $event );

            $segmentTranslationModel = $revisionFactory->getSegmentTranslationModel(
                    $translationVector , $chunkReviews
            ) ;

            $segmentTranslationModel->evaluateChunkReviewTransition() ;
        }

        foreach( $chunkReviews as $chunkReview ) {
            $chunkReviewModel = $revisionFactory->getChunkReviewModel( $chunkReview ) ;
            $chunkReviewModel->updatePassFailResult() ;
        }
    }
}