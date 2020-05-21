<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/06/2019
 * Time: 18:51
 */

namespace Features\SecondPassReview;


use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\SecondPassReview\Model\ChunkReviewModel;
use Features\SecondPassReview\Model\SegmentTranslationEventDao;
use LQA\EntryDao;

class TranslationIssueModel extends \Features\ReviewExtended\TranslationIssueModel {

    /**
     * @throws \Exception
     */
    public function delete() {
        EntryDao::deleteEntry( $this->issue );

        $final_revision = ( new SegmentTranslationEventDao() )
                ->getFinalRevisionForSegmentAndSourcePage(
                        $this->chunk_review->id_job,
                        $this->issue->id_segment,
                        $this->issue->source_page );


        if ( $final_revision ) {
            $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
            $this->subtractPenaltyPoints( $chunk_review_model );
        }

        // If I am in R2 and I am deleting an issue
        // I would add penalty points from R1
        if ( \Utils::getSourcePageFromReferer() === 3 ) {
            $chunkReviews = ChunkReviewDao::findByIdJob( $this->chunk->id );
            foreach ( $chunkReviews as $chunkReview ) {
                if ( $chunkReview->source_page == 2 ) {
                    $chunk_review_model = new ChunkReviewModel( $chunkReview );
                    $this->subtractPenaltyPoints( $chunk_review_model );
                }
            }
        }
    }
}