<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 23/10/2018
 * Time: 11:36
 */

namespace Features\ReviewExtended;

use Chunks_ChunkStruct;
use LQA\ChunkReviewDao;
use LQA\EntryDao;
use LQA\EntryStruct;
use Translations_TranslationVersionDao;
use Translations_TranslationVersionStruct;
use Utils;

class TranslationIssueModel {

    /**
     * @var \Projects_ProjectStruct
     */
    protected $project;

    private   $diff;

    /**
     * @var EntryStruct
     */
    protected $issue ;

    /**
     * @var \LQA\ChunkReviewStruct
     */
    protected $chunk_review ;

    /**
     * @var \Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @param $id_job
     * @param $password
     * @param EntryStruct $issue
     */
    public function __construct( $id_job, $password, EntryStruct $issue ) {
        $this->issue = $issue;

        $review = ( new ChunkReviewDao() )->findChunkReviewsForSourcePage( new Chunks_ChunkStruct( [ 'id' => $id_job, 'password' => $password ] ) , $issue->source_page )[ 0 ];

        $this->chunk_review = $review;
        $this->chunk = $this->chunk_review->getChunk();
        $this->project = $this->chunk->getProject();

    }

    /**
     * This method optionally saves the diff between versions if this is being received from the post params.
     * This change was introduced for the new revision, in which issues have to come with a diff object because
     * selection is referred to the difference between segments.
     */
    public function setDiff( $diff ) {
        $this->diff = $diff ;
    }


    /**
     * Inserts the struct in database and updates review result
     *
     * @return EntryStruct
     * @throws \Exceptions\ValidationError
     * @throws \Exception
     */
    public function save() {
        $this->setDefaultIssueValues();
        $data = $this->issue->attributes();

        if ( !empty( $this->diff ) ) {
            $this->saveDiff();
        }

        $this->issue = EntryDao::createEntry( $data );

        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        $chunk_review_model->addPenaltyPoints( $this->issue->penalty_points, $this->project );

        return $this->issue;
    }

    /**
     *
     */
    private function setDefaultIssueValues() {
        if ( is_null( $this->issue->start_node ) ) {
            $this->issue->start_node = 0 ;
        }

        if ( is_null( $this->issue->end_node ) ) {
            $this->issue->end_node = 0 ;
        }
    }

    private function saveDiff() {
        $string_to_save = json_encode( $this->diff ) ;

        /**
         * in order to save diff we need to lookup for current version in segment_translations.
         */
        $struct                 = new Translations_TranslationVersionStruct() ;
        $struct->id_job         = $this->issue->id_job ;
        $struct->id_segment     = $this->issue->id_segment ;
        $struct->creation_date  = Utils::mysqlTimestamp( time() ) ;
        $struct->is_review      = true ;
        $struct->version_number = $this->issue->translation_version ;
        $struct->raw_diff       = $string_to_save ;

        $version_record = ( new Translations_TranslationVersionDao())->getVersionNumberForTranslation(
                $struct->id_job, $struct->id_segment, $struct->version_number
        );

        if ( !$version_record ) {
            $insert = Translations_TranslationVersionDao::insertStruct( $struct ) ;
        }
        else {
            // in case the record exists, we have to update it with the diff anyway
            $version_record->raw_diff = $string_to_save ;
            $update = Translations_TranslationVersionDao::updateStruct( $version_record, ['fields' => ['raw_diff']]  );
        }

    }

    /**
     * Deletes the entry and subtracts penalty potins.
     * Penalty points are not subtracted if deletion is coming from a review and the issue is rebutted, because in that
     * case we could end up with negative sum of penalty points
     *
     * @throws \Exception
     */
    public function delete() {
        EntryDao::deleteEntry($this->issue);

        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        $chunk_review_model->subtractPenaltyPoints( $this->issue->penalty_points, $this->project );
    }

}