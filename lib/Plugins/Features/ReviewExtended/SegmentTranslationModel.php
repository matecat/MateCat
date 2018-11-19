<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/24/16
 * Time: 10:21 AM
 */

namespace Features\ReviewExtended;

use Features\ReviewExtended\Model\ChunkReviewDao;
use \Features\ReviewImproved\SegmentTranslationModel as ReviewImprovedSegmentTranslationModel;

class SegmentTranslationModel extends ReviewImprovedSegmentTranslationModel {


    protected function checkReviewedStateTransition() {

        if ( $this->model->entersReviewedState() ) {
            $this->addCount();
        } elseif ( $this->model->exitsReviewedState() ) {
            $this->subtractCount();
        }

    }

    public function recountPenaltyPoints() {

        $penaltyPoints                      = ChunkReviewDao::getPenaltyPointsForChunk( $this->chunk );
        $this->chunk_review->penalty_points = $penaltyPoints;

        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        $chunk_review_model->updatePassFailResult();
    }

}