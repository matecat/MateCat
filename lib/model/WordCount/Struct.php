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

    protected $total = 0;

    /**
     * @param int $approved_words
     */
    public function setApprovedWords( $approved_words ) {
        $this->approved_words = (float)$approved_words;
    }

    /**
     * @return int
     */
    public function getApprovedWords() {
        return $this->approved_words;
    }

    /**
     * @param int $draft_words
     */
    public function setDraftWords( $draft_words ) {
        $this->draft_words = (float)$draft_words;
    }

    /**
     * @return int
     */
    public function getDraftWords() {
        return $this->draft_words;
    }

    /**
     * @param mixed $id_job
     */
    public function setIdJob( $id_job ) {
        $this->id_job = (int)$id_job;
    }

    /**
     * @return mixed
     */
    public function getIdJob() {
        return $this->id_job;
    }

    /**
     * @param mixed $job_password
     */
    public function setJobPassword( $job_password ) {
        $this->job_password = $job_password;
    }

    /**
     * @return mixed
     */
    public function getJobPassword() {
        return $this->job_password;
    }

    /**
     * @param int $new_words
     */
    public function setNewWords( $new_words ) {
        $this->new_words = (float)$new_words;
    }

    /**
     * @return int
     */
    public function getNewWords() {
        return $this->new_words;
    }

    /**
     * @param int $rejected_words
     */
    public function setRejectedWords( $rejected_words ) {
        $this->rejected_words = (float)$rejected_words;
    }

    /**
     * @return int
     */
    public function getRejectedWords() {
        return $this->rejected_words;
    }

    /**
     * @param int $translated_words
     */
    public function setTranslatedWords( $translated_words ) {
        $this->translated_words = (float)$translated_words;
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
        return $this->total = (
                $this->new_words +
                $this->draft_words +
                $this->translated_words +
                $this->rejected_words +
                $this->approved_words
        );
    }

} 