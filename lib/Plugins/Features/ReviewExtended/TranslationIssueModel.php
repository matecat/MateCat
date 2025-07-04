<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 23/10/2018
 * Time: 11:36
 */

namespace Features\ReviewExtended;

use Exception;
use Features\TranslationEvents\Model\TranslationEventDao;
use Features\TranslationVersions\Model\TranslationVersionDao;
use Features\TranslationVersions\Model\TranslationVersionStruct;
use Model\Exceptions\ValidationError;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryDao;
use Model\LQA\EntryStruct;
use Model\Projects\ProjectStruct;
use Utils;

class TranslationIssueModel {

    /**
     * @var ProjectStruct
     */
    protected $project;

    private $diff;

    /**
     * @var EntryStruct
     */
    protected $issue;

    /**
     * @var ChunkReviewStruct
     */
    protected $chunk_review;

    /**
     * @var \Model\Jobs\JobStruct
     */
    protected $chunk;

    /**
     * @param             $id_job
     * @param             $password
     * @param EntryStruct $issue
     */
    public function __construct( $id_job, $password, EntryStruct $issue ) {
        $this->issue = $issue;

        $review = ChunkReviewDao::findByReviewPasswordAndJobId( $password, $id_job );

        $this->chunk_review = $review;
        $this->chunk        = $this->chunk_review->getChunk();
        $this->project      = $this->chunk->getProject();

    }

    /**
     * This method optionally saves the diff between versions if this is being received from the post params.
     * This change was introduced for the new revision, in which issues have to come with a diff object because
     * selection is referred to the difference between segments.
     */
    public function setDiff( $diff ) {
        $this->diff = $diff;
    }


    /**
     * Inserts the struct in database and updates review result
     *
     * @return EntryStruct
     * @throws ValidationError
     * @throws Exception
     */
    public function save() {
        $this->setDefaultIssueValues();

        if ( !empty( $this->diff ) ) {
            $this->saveDiff();
        }

        EntryDao::createEntry( $this->issue );

        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        $chunk_review_model->addPenaltyPoints( $this->issue->penalty_points, $this->project );

        return $this->issue;
    }

    /**
     *
     */
    private function setDefaultIssueValues() {
        if ( is_null( $this->issue->start_node ) ) {
            $this->issue->start_node = 0;
        }

        if ( is_null( $this->issue->end_node ) ) {
            $this->issue->end_node = 0;
        }
    }

    private function saveDiff() {
        $string_to_save = json_encode( $this->diff );

        /**
         * in order to save diff we need to lookup for current version in segment_translations.
         */
        $struct                 = new TranslationVersionStruct();
        $struct->id_job         = $this->issue->id_job;
        $struct->id_segment     = $this->issue->id_segment;
        $struct->creation_date  = Utils::mysqlTimestamp( time() );
        $struct->is_review      = true;
        $struct->version_number = $this->issue->translation_version;
        $struct->raw_diff       = $string_to_save;

        $version_record = ( new TranslationVersionDao() )->getVersionNumberForTranslation(
                $struct->id_job, $struct->id_segment, $struct->version_number
        );

        if ( !$version_record ) {
            $insert = TranslationVersionDao::insertStruct( $struct );
        } else {
            // in case the record exists, we have to update it with the diff anyway
            $version_record->raw_diff = $string_to_save;
            $update                   = TranslationVersionDao::updateStruct( $version_record, [ 'fields' => [ 'raw_diff' ] ] );
        }

    }

    /**
     * @throws Exception
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
        $final_revision = ( new TranslationEventDao() )->getFinalRevisionForSegmentAndSourcePage(
                $chunkReview->id_job,
                $this->issue->id_segment,
                $this->issue->source_page
        );

        if ( $final_revision ) {
            $chunk_review_model = new ChunkReviewModel( $chunkReview );
            $this->subtractPenaltyPoints( $chunk_review_model );
        }
    }

    /**
     * Check if penalty points are >= 0
     * to avoid to persist negative values
     *
     * @param ChunkReviewModel $chunk_review_model
     *
     * @throws Exception
     */
    protected function subtractPenaltyPoints( ChunkReviewModel $chunk_review_model ) {
        if ( ( $chunk_review_model->getPenaltyPoints() - $this->issue->penalty_points ) >= 0 ) {
            $chunk_review_model->subtractPenaltyPoints( $this->issue->penalty_points, $this->project );
        }
    }
}