<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 28/01/15
 * Time: 14.44
 */
class ErrorCount_Struct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    /**
     * @var int
     */
    protected $id_job;

    /**
     * @var string
     */
    protected $job_password;

    /**
     * @var int
     */
    public $typing_min;

    /**
     * @var int
     */
    public $translation_min;

    /**
     * @var int
     */
    public $terminology_min;

    /**
     * @var int
     */
    public $language_min;

    /**
     * @var int
     */
    public $style_min;

    /**
     * @var int
     */
    public $typing_maj;

    /**
     * @var int
     */
    public $translation_maj;

    /**
     * @var int
     */
    public $terminology_maj;

    /**
     * @var int
     */
    public $language_maj;

    /**
     * @var int
     */
    public $style_maj;
    
    
    /**
     * @return int
     */
    public function getLanguageMin() {
        return $this->language_min;
    }

    /**
     * @return int
     */
    public function getStyleMin() {
        return $this->style_min;
    }

    /**
     * @return int
     */
    public function getTerminologyMin() {
        return $this->terminology_min;
    }

    /**
     * @return int
     */
    public function getTranslationMin() {
        return $this->translation_min;
    }

    /**
     * @return int
     */
    public function getTypingMin() {
        return $this->typing_min;
    }

    /**
     * @return int
     */
    public function getIdJob() {
        return $this->id_job;
    }

    /**
     * @return string
     */
    public function getJobPassword() {
        return $this->job_password;
    }

    /**
     * @param $id_job int
     *
     * @return $this
     */
    public function setIdJob( $id_job ) {
        $this->id_job = $id_job;
        return $this;
    }

    /**
     * @param string $job_password
     * @return $this
     */
    public function setJobPassword( $job_password ) {
        $this->job_password = $job_password;
        return $this;
    }

    /**
     * @param int $language_min
     * @return $this
     */
    public function setLanguageMin( $language_min ) {
        $this->language_min = $language_min;
        return $this;
    }

    /**
     * @param int $style_min
     * @return $this
     */
    public function setStyleMin( $style_min ) {
        $this->style_min = $style_min;
        return $this;
    }

    /**
     * @param int $terminology_min
     * @return $this
     */
    public function setTerminologyMin( $terminology_min ) {
        $this->terminology_min = $terminology_min;
        return $this;
    }

    /**
     * @param int $translation_min
     * @return $this
     */
    public function setTranslationMin( $translation_min ) {
        $this->translation_min = $translation_min;
        return $this;
    }

    /**
     * @param int $typing_min
     * @return $this
     */
    public function setTypingMin( $typing_min ) {
        $this->typing_min = $typing_min;
        return $this;
    }

    /**
     * @return int
     */
    public function getTypingMaj() {
        return $this->typing_maj;
    }

    /**
     * @param int $typing_maj
     *
     * @return $this
     */
    public function setTypingMaj( $typing_maj ) {
        $this->typing_maj = $typing_maj;

        return $this;
    }

    /**
     * @return int
     */
    public function getTranslationMaj() {
        return $this->translation_maj;
    }

    /**
     * @param int $translation_maj
     *
     * @return $this
     */
    public function setTranslationMaj( $translation_maj ) {
        $this->translation_maj = $translation_maj;

        return $this;
    }

    /**
     * @return int
     */
    public function getTerminologyMaj() {
        return $this->terminology_maj;
    }

    /**
     * @param int $terminology_maj
     *
     * @return $this
     */
    public function setTerminologyMaj( $terminology_maj ) {
        $this->terminology_maj = $terminology_maj;

        return $this;
    }

    /**
     * @return int
     */
    public function getLanguageMaj() {
        return $this->language_maj;
    }

    /**
     * @param int $language_maj
     *
     * @return $this
     */
    public function setLanguageMaj( $language_maj ) {
        $this->language_maj = $language_maj;

        return $this;
    }

    /**
     * @return int
     */
    public function getStyleMaj() {
        return $this->style_maj;
    }

    /**
     * @param int $style_maj
     *
     * @return $this
     */
    public function setStyleMaj( $style_maj ) {
        $this->style_maj = $style_maj;

        return $this;
    }

    public function thereAreDifferences(){
        $err = false;
        foreach ( $this->toArray() as $errName => $errValue ){
            $err |= (bool)$errValue;
        }
        return $err;
    }

}