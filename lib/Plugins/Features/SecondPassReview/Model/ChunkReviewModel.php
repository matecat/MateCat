<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/06/2019
 * Time: 18:12
 */

namespace Features\SecondPassReview\Model;

class ChunkReviewModel extends \Features\ReviewExtended\ChunkReviewModel {

    public function recountAndUpdatePassFailResult() {
        $chunk = $this->chunk_review->getChunk();

        /**
         * Count penalty points based on this source_page
         */
        $this->chunk_review->penalty_points = ChunkReviewDao::getPenaltyPointsForChunk( $chunk, $this->chunk_review->source_page ) ;
        $this->chunk_review->reviewed_words_count =
                ( new ChunkReviewDao() )
                        ->getReviewedWordsCountForSecondPass( $chunk, $this->chunk_review->source_page ) ;

        $this->chunk_review->advancement_wc = ( new ChunkReviewDao($this->chunk_review))->recountAdvancementWords(
                $chunk, $this->chunk_review->source_page
        ) ;

        $this->updatePassFailResult();
    }

}