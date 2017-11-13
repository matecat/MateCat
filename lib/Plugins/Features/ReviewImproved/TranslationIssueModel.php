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
use Translations\TranslationVersionDataDao;
use Translations_TranslationVersionDao;
use Translations_TranslationVersionStruct;
use Utils;

class TranslationIssueModel
{

    private $diff;

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
     * This method optionally saves the diff between versions if this is being received from the post params.
     * This change was introduced for the new revision, in which issues have to come with a diff object because
     * selection is referred to the difference between segments.
     */
    public function setDiff( $diff ) {
        $this->diff = $diff ;
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
        $this->setDefaultIssueValues();
        $data = $this->issue->attributes();

        $this->diff = [
                [0,"||| |||"],
                [-1," Prova"],
                [0," UNTRANSLATED_CONTENT_START&lt;g id=\"1\"&gt;ci sono innumerevoli&lt;/g&gt;&lt;g id=\"2\"&gt; variazioni &lt;g id=\"3\"&gt;passaggi&lt;/g&gt; il &lt;g id=\"4\"&gt;Lorem Ipsum&lt;/g&gt;, &lt;g id=\"5\"&gt;ma la maggior parte &lt;/g&gt;&lt;/g&gt;||| ||| UNTRANSLATED_CONTENT_END"]
        ];

        if ( !empty( $this->diff ) && $this->issue->translation_version == 0 ) {
            $this->saveDiff();
        }

        $this->issue = EntryDao::createEntry( $data );

        $chunk_review_model = new ChunkReviewModel( $this->chunk_review );
        $chunk_review_model->addPenaltyPoints( $this->issue->penalty_points );

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

    }

}