<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 23/10/2018
 * Time: 11:36
 */

namespace Features\ReviewExtended;

use LQA\EntryDao;

class TranslationIssueModel extends \Features\ReviewImproved\TranslationIssueModel
{


    /**
     * Deletes the entry and subtracts penalty potins.
     * Penalty points are not subtracted if deletion is coming from a review and the issue is rebutted, because in that
     * case we could end up with negative sum of penalty points
     *
     */

    public function delete() {
        EntryDao::deleteEntry($this->issue);

        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        $chunk_review_model->subtractPenaltyPoints( $this->issue->penalty_points );

    }

}