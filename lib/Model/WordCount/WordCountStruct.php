<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 24/02/14
 * Time: 18.55
 *
 */

namespace WordCount;

use Jobs_JobStruct;
use JsonSerializable;
use Projects_MetadataDao;

class WordCountStruct implements JsonSerializable {

    protected $id_job;
    protected $job_password;
    protected $new_words        = 0;
    protected $draft_words      = 0;
    protected $translated_words = 0;
    protected $approved_words   = 0;
    protected $rejected_words   = 0;
    protected $approved2_words  = 0;

    protected $new_raw_words        = 0;
    protected $draft_raw_words      = 0;
    protected $translated_raw_words = 0;
    protected $approved_raw_words   = 0;
    protected $approved2_raw_words  = 0;
    protected $rejected_raw_words   = 0;

    protected $id_segment = null;
    protected $old_status = null;
    protected $new_status = null;

    protected $total;

    /**
     * @param Jobs_JobStruct $jobOrChunk
     *
     * @return WordCountStruct
     */
    public static function loadFromJob( Jobs_JobStruct $jobOrChunk ) {
        $wordCountStruct                       = new WordCountStruct();
        $wordCountStruct->id_job               = (int)$jobOrChunk->id;
        $wordCountStruct->job_password         = $jobOrChunk->password;
        $wordCountStruct->new_words            = (float)$jobOrChunk->new_words;
        $wordCountStruct->draft_words          = (float)$jobOrChunk->draft_words;
        $wordCountStruct->translated_words     = (float)$jobOrChunk->translated_words;
        $wordCountStruct->approved_words       = (float)$jobOrChunk->approved_words;
        $wordCountStruct->rejected_words       = (float)$jobOrChunk->rejected_words;
        $wordCountStruct->approved2_words      = (float)$jobOrChunk->approved2_words;
        $wordCountStruct->new_raw_words        = (float)$jobOrChunk->new_raw_words;
        $wordCountStruct->draft_raw_words      = (float)$jobOrChunk->draft_raw_words;
        $wordCountStruct->translated_raw_words = (float)$jobOrChunk->translated_raw_words;
        $wordCountStruct->approved_raw_words   = (float)$jobOrChunk->approved_raw_words;
        $wordCountStruct->approved2_raw_words  = (float)$jobOrChunk->approved2_raw_words;
        $wordCountStruct->rejected_raw_words   = (float)$jobOrChunk->rejected_raw_words;

        return $wordCountStruct;
    }

    /**
     * @param null $id_segment
     *
     * @return $this
     */
    public function setIdSegment( $id_segment ) {
        $this->id_segment = $id_segment;

        return $this;
    }

    /**
     * @return int
     */
    public function getIdSegment() {
        return $this->id_segment;
    }

    /**
     * @param null $status
     *
     * @return $this
     */
    public function setOldStatus( $status ) {
        $this->old_status = $status;

        return $this;
    }

    /**
     * @return string
     */
    public function getOldStatus() {
        return $this->old_status;
    }

    /**
     * @param null $new_status
     *
     * @return $this
     */
    public function setNewStatus( $new_status ) {
        $this->new_status = $new_status;

        return $this;
    }

    /**
     * @return null
     */
    public function getNewStatus() {
        return $this->new_status;
    }


    /**
     * @param $approved_words
     *
     * @return $this
     */
    public function setApprovedWords( $approved_words ) {
        $this->approved_words = (float)$approved_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getApprovedWords() {
        return $this->approved_words;
    }

    /**
     * @param $draft_words
     *
     * @return $this
     */
    public function setDraftWords( $draft_words ) {
        $this->draft_words = (float)$draft_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getDraftWords() {
        return $this->draft_words;
    }

    /**
     * @param $id_job
     *
     * @return $this
     */
    public function setIdJob( $id_job ) {
        $this->id_job = (int)$id_job;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIdJob() {
        return $this->id_job;
    }

    /**
     * @param $job_password
     *
     * @return $this
     */
    public function setJobPassword( $job_password ) {
        $this->job_password = $job_password;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getJobPassword() {
        return $this->job_password;
    }

    /**
     * @param $new_words
     *
     * @return $this
     */
    public function setNewWords( $new_words ) {
        $this->new_words = (float)$new_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getNewWords() {
        return $this->new_words;
    }

    /**
     * @param $rejected_words
     *
     * @return $this
     */
    public function setRejectedWords( $rejected_words ) {
        $this->rejected_words = (float)$rejected_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getRejectedWords() {
        return $this->rejected_words;
    }

    /**
     * @param $translated_words
     *
     * @return $this
     */
    public function setTranslatedWords( $translated_words ) {
        $this->translated_words = (float)$translated_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getTranslatedWords() {
        return $this->translated_words;
    }

    /**
     * @return int
     */
    public function getApproved2Words() {
        return $this->approved2_words;
    }

    /**
     * @param int $approved2_words
     *
     * @return $this
     */
    public function setApproved2Words( $approved2_words ) {
        $this->approved2_words = (float)$approved2_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getNewRawWords() {
        return $this->new_raw_words;
    }

    /**
     * @param int $new_raw_words
     *
     * @return $this
     */
    public function setNewRawWords( $new_raw_words ) {
        $this->new_raw_words = (float)$new_raw_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getDraftRawWords() {
        return $this->draft_raw_words;
    }

    /**
     * @param int $draft_raw_words
     *
     * @return $this
     */
    public function setDraftRawWords( $draft_raw_words ) {
        $this->draft_raw_words = (float)$draft_raw_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getTranslatedRawWords() {
        return $this->translated_raw_words;
    }

    /**
     * @param int $translated_raw_words
     *
     * @return $this
     */
    public function setTranslatedRawWords( $translated_raw_words ) {
        $this->translated_raw_words = (float)$translated_raw_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getApprovedRawWords() {
        return $this->approved_raw_words;
    }

    /**
     * @param int $approved_raw_words
     *
     * @return $this
     */
    public function setApprovedRawWords( $approved_raw_words ) {
        $this->approved_raw_words = (float)$approved_raw_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getApproved2RawWords() {
        return $this->approved2_raw_words;
    }

    /**
     * @param int $approved2_raw_words
     *
     * @return $this
     */
    public function setApproved2RawWords( $approved2_raw_words ) {
        $this->approved2_raw_words = (float)$approved2_raw_words;

        return $this;
    }

    /**
     * @return int
     */
    public function getRejectedRawWords() {
        return $this->rejected_raw_words;
    }

    /**
     * @param int $rejected_raw_words
     *
     * @return $this
     */
    public function setRejectedRawWords( $rejected_raw_words ) {
        $this->rejected_raw_words = (float)$rejected_raw_words;

        return $this;
    }


    /**
     * @return int
     */
    public function getTotal() {

        $this->total = (
                $this->new_words +
                $this->draft_words +
                $this->translated_words +
                $this->rejected_words +
                $this->approved_words +
                $this->approved2_words
        );

        return $this->total;
    }

    public function getRawTotal() {

        $this->total = (
                $this->new_raw_words +
                $this->draft_raw_words +
                $this->translated_raw_words +
                $this->rejected_raw_words +
                $this->approved_raw_words +
                $this->approved2_raw_words
        );

        return $this->total;
    }

    /**
     * @return array|mixed
     */
    public function jsonSerialize() {

        return [
            Projects_MetadataDao::WORD_COUNT_EQUIVALENT => [
                    'new'        => $this->new_words,
                    'draft'      => $this->draft_words,
                    'translated' => $this->translated_words,
                    'approved'   => $this->approved_words,
                    'approved2'  => $this->approved2_words,
                    'total'      => $this->getTotal()
            ],
            Projects_MetadataDao::WORD_COUNT_RAW        => [
                    'new'        => $this->new_raw_words,
                    'draft'      => $this->draft_raw_words,
                    'translated' => $this->translated_raw_words,
                    'approved'   => $this->approved_raw_words,
                    'approved2'  => $this->approved2_raw_words,
                    'total'      => $this->getRawTotal()
            ]
        ];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            Projects_MetadataDao::WORD_COUNT_EQUIVALENT => [
                'new'        => $this->new_words,
                'draft'      => $this->draft_words,
                'translated' => $this->translated_words,
                'approved'   => $this->approved_words,
                'approved2'  => $this->approved2_words,
                'total'      => $this->getTotal()
            ],
            Projects_MetadataDao::WORD_COUNT_RAW        => [
                'new'        => $this->new_raw_words,
                'draft'      => $this->draft_raw_words,
                'translated' => $this->translated_raw_words,
                'approved'   => $this->approved_raw_words,
                'approved2'  => $this->approved2_raw_words,
                'total'      => $this->getRawTotal()
            ]
        ];
    }

} 