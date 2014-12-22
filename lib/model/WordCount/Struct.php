<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 24/02/14
 * Time: 18.55
 * 
 */

class WordCount_Struct {

    protected $id_job;
    protected $job_password;
    protected $new_words = 0;
    protected $draft_words = 0;
    protected $translated_words = 0;
    protected $approved_words = 0;
    protected $rejected_words = 0;

    protected $id_segment = null;
    protected $old_status = null;
    protected $new_status = null;

    protected $total;

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
    public function getTotal(){

        $this->total = (
                $this->new_words +
                $this->draft_words +
                $this->translated_words +
                $this->rejected_words +
                $this->approved_words
        );

        return $this->total;
    }

} 