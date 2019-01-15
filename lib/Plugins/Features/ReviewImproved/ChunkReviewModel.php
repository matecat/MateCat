<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/25/16
 * Time: 3:28 PM
 */

namespace Features\ReviewImproved;

use LQA\ChunkReviewDao;

class ChunkReviewModel extends \Features\ReviewExtended\ChunkReviewModel {

    /**
     * This method invokes the recount of reviewed_words_count and
     * penalty_points for the chunk and updates the passfail result.
     */
    public function recountAndUpdatePassFailResult() {
        $chunk = $this->chunk_review->getChunk();

        $this->chunk_review->penalty_points =
                ChunkReviewDao::getPenaltyPointsForChunk( $chunk );

        $this->chunk_review->reviewed_words_count =
                ChunkReviewDao::getReviewedWordsCountForChunk( $chunk );

        $this->updatePassFailResult();
    }

}

