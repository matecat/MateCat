<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/24/16
 * Time: 10:21 AM
 */

namespace Features\ReviewExtended;

use Features\ReviewImproved\ChunkReviewModel;
use LQA\ChunkReviewDao;
use \Features\ReviewImproved\SegmentTranslationModel as ReviewImprovedSegmentTranslationModel;

class SegmentTranslationModel extends ReviewImprovedSegmentTranslationModel {


    public function recountPenaltyPoints() {

        //TODO Vincenzo: Create another method in ChunkReviewDao and change the query
        $penaltyPoints                      = ChunkReviewDao::getPenaltyPointsForChunk( $this->chunk );
        $this->chunk_review->penalty_points = $penaltyPoints;

        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        //TODO Vincenzo: Check this Method
        $chunk_review_model->updatePassFailResult();
    }

}