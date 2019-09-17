<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/06/2019
 * Time: 18:51
 */

namespace Features\SecondPassReview;


use Features\SecondPassReview\Model\ChunkReviewModel;
use Features\SecondPassReview\Model\SegmentTranslationEventDao;
use LQA\EntryDao;

class TranslationIssueModel extends \Features\ReviewExtended\TranslationIssueModel {

    /**
     * @throws \Exception
     */
    public function delete() {
        EntryDao::deleteEntry($this->issue);

        $final_revision = ( new SegmentTranslationEventDao())
                ->getFinalRevisionForSegmentAndSourcePage(
                        $this->chunk_review->id_job,
                        $this->issue->id_segment,
                        $this->issue->source_page );

        if ( $final_revision ) {
            $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
            $chunk_review_model->subtractPenaltyPoints( $this->issue->penalty_points, $this->project );
        }

    }

}