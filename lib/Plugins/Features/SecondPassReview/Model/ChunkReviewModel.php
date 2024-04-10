<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/06/2019
 * Time: 18:12
 */

namespace Features\SecondPassReview\Model;

use Exception;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Projects_ProjectStruct;

class ChunkReviewModel extends \Features\ReviewExtended\ChunkReviewModel {

    /**
     *
     * Used to recount total in qa_chunk reviews in case of: [ split/merge/chunk record created/disaster recovery ]
     *
     * Used in AbstractRevisionFeature::postJobMerged and AbstractRevisionFeature::postJobSplitted
     *
     * @param Projects_ProjectStruct $project
     *
     * @throws Exception
     */
    public function recountAndUpdatePassFailResult( Projects_ProjectStruct $project ) {

        /**
         * Count penalty points based on this source_page
         */
        $chunkReviewDao = new ChunkReviewDao();
        $this->chunk_review->penalty_points = ChunkReviewDao::getPenaltyPointsForChunk( $this->chunk, $this->chunk_review->source_page ) ;
        $this->chunk_review->reviewed_words_count = $chunkReviewDao->getReviewedWordsCountForSecondPass( $this->chunk, $this->chunk_review->source_page ) ;
        $this->chunk_review->total_tte = $chunkReviewDao->countTimeToEdit( $this->chunk, $this->chunk_review->source_page ) ;

        $this->_updatePassFailResult( $project );
    }

}