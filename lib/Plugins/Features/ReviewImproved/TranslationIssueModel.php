<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/25/16
 * Time: 5:05 PM
 */

namespace Features\ReviewImproved;


class TranslationIssueModel
{

    /**
     * @var \LQA\EntryStruct
     */
    private $issue ;

    /**
     * @var \LQA\ChunkReviewStruct
     */
    private $chunk_review ;


    /**
     * @param $id_job
     * @param $password
     * @param \LQA\EntryStruct $issue
     */
    public function __construct( $id_job, $password, \LQA\EntryStruct $issue ) {
        $this->issue = $issue;

       $reviews = \LQA\ChunkReviewDao::findChunkReviewsByChunkIds( array(
                array( $id_job, $password)
           ));

        $this->chunk_review = $reviews[0];

    }

    /**
     * public deletes the entry and updates the review result
     */

    public function delete() {
        \LQA\EntryDao::deleteEntry($this->issue);
        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        $chunk_review_model->subtractPenaltyPoints( $this->issue->penalty_points );
    }


    /**
     * Inserts the struct in database and updates review result
     *
     * @return \LQA\EntryStruct
     */
    public function save() {
        $data = $this->issue->attributes();
        $this->issue = \LQA\EntryDao::createEntry( $data );

        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        $chunk_review_model->addPenaltyPoints( $this->issue->penalty_points );

        return $this->issue;
    }

}