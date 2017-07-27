<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/25/16
 * Time: 5:05 PM
 */

namespace Features\ReviewImproved;


use LQA\EntryDao;
use LQA\EntryStruct;

class TranslationIssueModel
{

    /**
     * @var EntryStruct
     */
    private $issue ;

    /**
     * @var \LQA\ChunkReviewStruct
     */
    private $chunk_review ;


    /**
     * @param $id_job
     * @param $password
     * @param EntryStruct $issue
     */
    public function __construct( $id_job, $password, EntryStruct $issue ) {
        $this->issue = $issue;

       $reviews = \LQA\ChunkReviewDao::findChunkReviewsByChunkIds( array(
                array( $id_job, $password)
           ));

        $this->chunk_review = $reviews[0];

    }

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


    /**
     * Inserts the struct in database and updates review result
     *
     * @return EntryStruct
     */
    public function save() {
        $data = $this->issue->attributes();
        $this->issue = EntryDao::createEntry( $data );

        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        $chunk_review_model->addPenaltyPoints( $this->issue->penalty_points );

        return $this->issue;
    }

}