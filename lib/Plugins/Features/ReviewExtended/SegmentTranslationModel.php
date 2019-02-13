<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/24/16
 * Time: 10:21 AM
 */

namespace Features\ReviewExtended;

use Features\ISegmentTranslationModel;
use Features\ReviewExtended\Model\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use SegmentTranslationChangeVector;

class SegmentTranslationModel  implements  ISegmentTranslationModel {
    /**
     * @var SegmentTranslationChangeVector
     */
    protected $model;

    /**
     * @var \Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @var ChunkReviewStruct
     */
    protected $chunk_review;

    public function __construct( SegmentTranslationChangeVector $model ) {

        $this->model = $model;
        $this->chunk = \Chunks_ChunkDao::getBySegmentTranslation( $this->model->getTranslation() );

        $reviews = ChunkReviewDao::findChunkReviewsByChunkIds( [
                [
                        $this->chunk->id, $this->chunk->password
                ]
        ] );

        return $this->chunk_review = $reviews[ 0 ];

    }

    public function recountPenaltyPoints() {
        $penaltyPoints                      = ChunkReviewDao::getPenaltyPointsForChunk( $this->chunk );
        $this->chunk_review->penalty_points = $penaltyPoints;

        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        $chunk_review_model->updatePassFailResult();
    }

    /**
     * addOrSubtractCachedReviewedWordsCount
     */

    public function addOrSubtractCachedReviewedWordsCount() {
        /**
         * If this model triggers a new version, then we can jump to
         * the check for reviewed state transition directly, because translation
         * issues are bound to a specific version. So when a new version is created
         * it's useless to check for previous translation issues.
         *
         * When a new version is not triggered instead we must check translation
         * issues exist instead. If they do, then the reviewed word count was already
         * added to the cached sum.
         *
         */

        if ( $this->model->isEnteringReviewedState() ) {
            $this->addCount();
        } elseif ( $this->model->isExitingReviewedState() ) {
            $this->subtractCount();
        }
    }

    /**
     * @return \LQA\ChunkReviewStruct
     */
    public function getChunkReview() {
        return $this->chunk_review;
    }

    protected function addCount() {
        $segment = $this->model->getSegmentStruct();
        $model   = new ChunkReviewModel( $this->chunk_review );
        $model->addWordsCount( $this->getWordCountWithPropagation( $segment->raw_word_count ) );
    }

    protected function subtractCount() {
        $segment = $this->model->getSegmentStruct();
        $model   = new ChunkReviewModel( $this->chunk_review );
        $model->subtractWordsCount( $this->getWordCountWithPropagation( $segment->raw_word_count ) );
    }

    protected function getWordCountWithPropagation( $count ) {
        if ( $this->model->didPropagate() ) {
            return $count + ( $count * count( $this->model->getPropagatedIds() ) ) ;
        }
        else {
            return $count ;
        }
    }

}