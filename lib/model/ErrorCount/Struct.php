<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 28/01/15
 * Time: 14.44
 */
class ErrorCount_Struct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

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
    protected $typing;

    /**
     * @var int
     */
    protected $translation;

    /**
     * @var int
     */
    protected $terminology;

    /**
     * @var int
     */
    protected $quality;

    /**
     * @var int
     */
    protected $style;

    /**
     * @return int
     */
    public function getQuality() {
        return $this->quality;
    }

    /**
     * @return int
     */
    public function getStyle() {
        return $this->style;
    }

    /**
     * @return int
     */
    public function getTerminology() {
        return $this->terminology;
    }

    /**
     * @return int
     */
    public function getTranslation() {
        return $this->translation;
    }

    /**
     * @return int
     */
    public function getTyping() {
        return $this->typing;
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
     * @param int $id_job
     */
    public function setIdJob( $id_job ) {
        $this->id_job = $id_job;
        return $this;
    }

    /**
     * @param string $job_password
     */
    public function setJobPassword( $job_password ) {
        $this->job_password = $job_password;
        return $this;
    }

    /**
     * @param int $quality
     */
    public function setQuality( $quality ) {
        $this->quality = $quality;
        return $this;
    }

    /**
     * @param int $style
     */
    public function setStyle( $style ) {
        $this->style = $style;
        return $this;
    }

    /**
     * @param int $terminology
     */
    public function setTerminology( $terminology ) {
        $this->terminology = $terminology;
        return $this;
    }

    /**
     * @param int $translation
     */
    public function setTranslation( $translation ) {
        $this->translation = $translation;
        return $this;
    }

    /**
     * @param int $typing
     */
    public function setTyping( $typing ) {
        $this->typing = $typing;
        return $this;
    }



//    private function __checkResult( $res ) {
//        if ( $res < 0 ) {
//            Log::doLog( __METHOD__ . "-> Bad call: result is less than zero." );
//            throw new BadMethodCallException( "Bad call: result is less than zero." );
//        }
//    }


}