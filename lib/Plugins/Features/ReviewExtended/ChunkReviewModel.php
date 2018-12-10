<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 17/10/2018
 * Time: 18:53
 */


namespace Features\ReviewExtended;


use Features\ReviewExtended\Model\ChunkReviewDao;

class ChunkReviewModel extends \Features\ReviewImproved\ChunkReviewModel
{

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