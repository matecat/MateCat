<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 26/02/14
 * Time: 10.45
 *
 */

namespace WordCount;

use BadMethodCallException;
use Constants_TranslationStatus;
use Exception;
use LogicException;
use Projects_ProjectStruct;
use ReflectionClass;

class CounterModel {

    /**
     * @var WordCountStruct
     */
    protected $oldWCount;
    protected $newStatus;
    protected $oldStatus;

    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    protected static $constCache = [];
    /**
     * @var WordCountStruct[]
     */
    private $values;

    /**
     * @return WordCountStruct[]
     */
    public function getValues() {
        return $this->values;
    }

    /**
     * @param WordCountStruct $oldWCount
     *
     */
    public function __construct( WordCountStruct $oldWCount = null ) {

        $reflect          = new ReflectionClass( 'Constants_TranslationStatus' );
        self::$constCache = array_flip( $reflect->getConstants() );

        if ( $oldWCount !== null ) {
            $this->setOldWordCount( $oldWCount );
        }

    }

    /**
     * @param $status
     *
     * @throws BadMethodCallException
     */
    protected function _verifyStatus( $status ) {
        if ( !array_key_exists( $status, self::$constCache ) ) {
            throw new BadMethodCallException( __METHOD__ . " Error: " . $status . " status is not defined." );
        }
    }

    public function setOldWordCount( WordCountStruct $oldWCount ) {
        $this->oldWCount = $oldWCount;
    }

    /**
     * @param Projects_ProjectStruct $project
     */
    public function setProject( Projects_ProjectStruct $project ) {
        $this->project = $project;
    }

    public function setNewStatus( $new_status ) {
        $this->_verifyStatus( $new_status );
        $this->newStatus = $new_status;
    }

    public function setOldStatus( $old_status ) {
        $this->_verifyStatus( $old_status );
        $this->oldStatus = $old_status;
    }

    public function setUpdatedValues( $weighted_words_amount, $raw_words_amount ) {
        $this->values[] = $this->getUpdatedValues( $weighted_words_amount, $raw_words_amount );
    }

    /**
     * @param $weighted_words_amount int the new status
     *
     * @return WordCountStruct
     * @throws LogicException
     */
    public function getUpdatedValues( $weighted_words_amount, $raw_words_amount ) {

        if ( $this->oldWCount === null ) {
            throw new LogicException( __METHOD__ . " Error: old word count is not defined." );
        }

        $newWCount = new WordCountStruct();
        $newWCount->setIdJob( $this->oldWCount->getIdJob() );
        $newWCount->setJobPassword( $this->oldWCount->getJobPassword() );

        $newWCount->setIdSegment( $this->oldWCount->getIdSegment() );
        $newWCount->setOldStatus( $this->oldStatus );
        $newWCount->setNewStatus( $this->newStatus );

        if ( !$this->equivalentStatuses() ) {

            $callSetNew = 'set' . $this->methodNameForStatusCall( $this->newStatus );
            $callSetOld = 'set' . $this->methodNameForStatusCall( $this->oldStatus );

            $newWCount->$callSetOld( -$weighted_words_amount );
            $newWCount->$callSetNew( +$weighted_words_amount );

            if ( !empty( $raw_words_amount ) ) {
                $callSetNew = 'set' . $this->methodNameForStatusCall( $this->newStatus, true );
                $callSetOld = 'set' . $this->methodNameForStatusCall( $this->oldStatus, true );
                $newWCount->$callSetOld( -$raw_words_amount );
                $newWCount->$callSetNew( +$raw_words_amount );
            }

        }


        return $newWCount;

    }

    /**
     * @param WordCountStruct[] $_wordCount_Struct_Array
     *
     * @return WordCountStruct
     * @throws Exception
     */
    public function updateDB( array $_wordCount_Struct_Array ) {

        $differentialCountStruct = $this->sumDifferentials( $_wordCount_Struct_Array );

        $res = WordCounterDao::updateWordCount( $differentialCountStruct );

        if ( $res <= 0 ) {
            throw new Exception( "Failed to update counter", $res );
        }

        $newTotalWCount = new WordCountStruct();
        $newTotalWCount->setNewWords( $this->oldWCount->getNewWords() + $differentialCountStruct->getNewWords() );
        $newTotalWCount->setDraftWords( $this->oldWCount->getDraftWords() + $differentialCountStruct->getDraftWords() );
        $newTotalWCount->setTranslatedWords( $this->oldWCount->getTranslatedWords() + $differentialCountStruct->getTranslatedWords() );
        $newTotalWCount->setApprovedWords( $this->oldWCount->getApprovedWords() + $differentialCountStruct->getApprovedWords() );
        $newTotalWCount->setApproved2Words( $this->oldWCount->getApproved2Words() + $differentialCountStruct->getApproved2Words() );
        $newTotalWCount->setRejectedWords( $this->oldWCount->getRejectedWords() + $differentialCountStruct->getRejectedWords() );

        $newTotalWCount->setNewRawWords( $this->oldWCount->getNewRawWords() + $differentialCountStruct->getNewRawWords() );
        $newTotalWCount->setDraftRawWords( $this->oldWCount->getDraftRawWords() + $differentialCountStruct->getDraftRawWords() );
        $newTotalWCount->setTranslatedRawWords( $this->oldWCount->getTranslatedRawWords() + $differentialCountStruct->getTranslatedRawWords() );
        $newTotalWCount->setApprovedRawWords( $this->oldWCount->getApprovedRawWords() + $differentialCountStruct->getApprovedRawWords() );
        $newTotalWCount->setApproved2RawWords( $this->oldWCount->getApproved2RawWords() + $differentialCountStruct->getApproved2RawWords() );
        $newTotalWCount->setRejectedRawWords( $this->oldWCount->getRejectedRawWords() + $differentialCountStruct->getRejectedRawWords() );

        $newTotalWCount->setIdSegment( $this->oldWCount->getIdSegment() );
        $newTotalWCount->setOldStatus( $this->oldStatus );
        $newTotalWCount->setNewStatus( $this->newStatus );
        $newTotalWCount->setIdJob( $this->oldWCount->getIdJob() );
        $newTotalWCount->setJobPassword( $this->oldWCount->getJobPassword() );

        return $newTotalWCount;

    }

    /**
     * @param WordCountStruct[] $wordCount_Struct
     *
     * @return WordCountStruct
     */
    public function sumDifferentials( $wordCount_Struct ) {

        $newWCount = new WordCountStruct();
        $newWCount->setIdSegment( $this->oldWCount->getIdSegment() );
        $newWCount->setOldStatus( $this->oldStatus );
        $newWCount->setNewStatus( $this->newStatus );
        $newWCount->setIdJob( $this->oldWCount->getIdJob() );
        $newWCount->setJobPassword( $this->oldWCount->getJobPassword() );

        foreach ( $wordCount_Struct as $count ) {
            $newWCount->setNewWords( $newWCount->getNewWords() + $count->getNewWords() );
            $newWCount->setTranslatedWords( $newWCount->getTranslatedWords() + $count->getTranslatedWords() );
            $newWCount->setDraftWords( $newWCount->getDraftWords() + $count->getDraftWords() );
            $newWCount->setApprovedWords( $newWCount->getApprovedWords() + $count->getApprovedWords() );
            $newWCount->setApproved2Words( $newWCount->getApproved2Words() + $count->getApproved2Words() );
            $newWCount->setRejectedWords( $newWCount->getRejectedWords() + $count->getRejectedWords() );

            $newWCount->setNewRawWords( $newWCount->getNewRawWords() + $count->getNewRawWords() );
            $newWCount->setDraftRawWords( $newWCount->getDraftRawWords() + $count->getDraftRawWords() );
            $newWCount->setTranslatedRawWords( $newWCount->getTranslatedRawWords() + $count->getTranslatedRawWords() );
            $newWCount->setApprovedRawWords( $newWCount->getApprovedRawWords() + $count->getApprovedRawWords() );
            $newWCount->setApproved2RawWords( $newWCount->getApproved2RawWords() + $count->getApproved2RawWords() );
            $newWCount->setRejectedRawWords( $newWCount->getRejectedRawWords() + $count->getRejectedRawWords() );

        }

        return $newWCount;

    }

    public function initializeJobWordCount( $id_job, $jPassword, WordCounterDao $wordCounterDao = null ) {

        if ( !$wordCounterDao ) {
            $wordCounterDao = new WordCounterDao();
        }

        $_details = $wordCounterDao->getStatsForJob( $id_job, null, $jPassword );

        $_job_details = array_pop( $_details ); //get the row
        $job_details  = [];

        foreach ( $_job_details as $key => $value ) {
            $k = explode( "_", $key ); // EX: split TOTAL_RAW
            if ( !empty( $k[ 1 ] ) ) {
                $job_details[ $k[ 0 ] ][ 'raw' ] = $value;
            } elseif ( $k[ 0 ] != 'id' ) {
                $job_details[ $k[ 0 ] ][ 'weighted' ] = $value;
            } else {
                $job_details[ $key ] = $value;
            }
        }

        $wStruct = new WordCountStruct();
        $wStruct->setIdJob( $job_details[ 'id' ] );
        $wStruct->setJobPassword( $jPassword );
        $wStruct->setNewWords( $job_details[ Constants_TranslationStatus::STATUS_NEW ][ 'weighted' ] );
        $wStruct->setDraftWords( $job_details[ Constants_TranslationStatus::STATUS_DRAFT ][ 'weighted' ] );
        $wStruct->setTranslatedWords( $job_details[ Constants_TranslationStatus::STATUS_TRANSLATED ][ 'weighted' ] );
        $wStruct->setApprovedWords( $job_details[ Constants_TranslationStatus::STATUS_APPROVED ][ 'weighted' ] );
        $wStruct->setApproved2Words( $job_details[ Constants_TranslationStatus::STATUS_APPROVED2 ][ 'weighted' ] );
        $wStruct->setRejectedWords( $job_details[ Constants_TranslationStatus::STATUS_REJECTED ][ 'weighted' ] );

        $wStruct->setNewRawWords( $job_details[ Constants_TranslationStatus::STATUS_NEW ][ 'raw' ] );
        $wStruct->setDraftRawWords( $job_details[ Constants_TranslationStatus::STATUS_DRAFT ][ 'raw' ] );
        $wStruct->setTranslatedRawWords( $job_details[ Constants_TranslationStatus::STATUS_TRANSLATED ][ 'raw' ] );
        $wStruct->setApprovedRawWords( $job_details[ Constants_TranslationStatus::STATUS_APPROVED ][ 'raw' ] );
        $wStruct->setApproved2RawWords( $job_details[ Constants_TranslationStatus::STATUS_APPROVED2 ][ 'raw' ] );
        $wStruct->setRejectedRawWords( $job_details[ Constants_TranslationStatus::STATUS_REJECTED ][ 'raw' ] );

        $wordCounterDao->initializeWordCount( $wStruct );

        return $wStruct;

    }

    /**
     * Returns the name of the method to call. In case of fixed and rebutted,
     * translated and rejected are returned respectively.
     *
     * @param $name
     *
     * @return string
     */
    private function methodNameForStatusCall( $name, $raw_count = false ) {
        if ( in_array( strtoupper( $name ), Constants_TranslationStatus::$POST_REVISION_STATUSES ) ) {
            return 'TranslatedWords';
        }

        return ucfirst( strtolower( $name ) ) . ( $raw_count ? "Raw" : "" ) . 'Words';

    }

    /**
     * Checks whether the old and new statuses are equal in regard
     * of the database column to update.
     */
    private function equivalentStatuses() {
        return (
                $this->methodNameForStatusCall( $this->newStatus ) ==
                $this->methodNameForStatusCall( $this->oldStatus )
        );
    }

}
