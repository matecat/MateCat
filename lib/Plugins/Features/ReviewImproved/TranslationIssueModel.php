<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/25/16
 * Time: 5:05 PM
 */

namespace Features\ReviewImproved;


use LQA\EntryDao;

class TranslationIssueModel extends \Features\ReviewExtended\TranslationIssueModel  {

    /**
     * Deletes the entry and subtracts penalty potins.
     * Penalty points are not subtracted if deletion is coming from a review and the issue is rebutted, because in that
     * case we could end up with negative sum of penalty points
     *
     */

    public function delete() {
        EntryDao::deleteEntry($this->issue);

        if ( is_null( $this->issue->rebutted_at ) ) {
            $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
            $chunk_review_model->subtractPenaltyPoints( $this->issue->penalty_points );
        }
    }


}