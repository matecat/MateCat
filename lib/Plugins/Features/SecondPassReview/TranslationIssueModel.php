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
use Features\SecondPassReview\Model\TranslationEventDao;
use LQA\EntryDao;

class TranslationIssueModel extends \Features\ReviewExtended\TranslationIssueModel {

    /**
     * @throws \Exception
     */
    public function delete() {
        EntryDao::deleteEntry( $this->issue );

        //
        // ---------------------------------------------------
        // Note 2020-06-24
        // ---------------------------------------------------
        //
        // $this->chunkReview may not refer to the chunk review associated to issue source page
        //
        $chunkReview    = ChunkReviewDao::findByIdJobAndPasswordAndSourcePage( $this->chunk->id, $this->chunk->password, $this->issue->source_page );
        $final_revision = ( new TranslationEventDao() )
                ->getFinalRevisionForSegmentAndSourcePage(
                        $chunkReview->id_job,
                        $this->issue->id_segment,
                        $this->issue->source_page );

        if ( $final_revision ) {
            $chunk_review_model = new ChunkReviewModel( $chunkReview );
            $this->subtractPenaltyPoints( $chunk_review_model );
        }
    }
}
