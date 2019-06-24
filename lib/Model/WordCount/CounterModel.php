<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 26/02/14
 * Time: 10.45
 *
 */

use WordCount\WordCounterDao;

class WordCount_CounterModel {

    /**
     * @var WordCount_Struct
     */
    protected $oldWCount;

    /**
     * @var WordCount_Struct
     */
    protected $newWCount;

    protected $newStatusCall;
    protected $oldStatusCall;
    protected $newStatus;
    protected $oldStatus;

    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    protected static $constCache = array();

    /**
     * @param WordCount_Struct $oldWCount
     *
     * @throws ReflectionException
     */
    public function __construct( WordCount_Struct $oldWCount = null ) {

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

    public function setOldWordCount( WordCount_Struct $oldWCount ) {
        $this->oldWCount = $oldWCount;
    }

    /**
     * @param Projects_ProjectStruct $project
     */
    public function setProject(Projects_ProjectStruct $project ) {
        $this->project = $project;
    }

    public function setNewStatus( $new_status ) {
        $this->_verifyStatus( $new_status );
        $this->newStatusCall = $this->methodNameForStatusCall($new_status) ;
        $this->newStatus     = $new_status;
    }

    public function setOldStatus( $old_status ) {
        $this->_verifyStatus( $old_status );
        $this->oldStatusCall = $this->methodNameForStatusCall( $old_status );
        $this->oldStatus     = $old_status;
    }

    /**
     * @param $words_amount int the new status
     *
     * @return WordCount_Struct
     * @throws LogicException
     */
    public function getUpdatedValues( $words_amount ) {

        if ( $this->oldWCount === null ) {
            throw new LogicException( __METHOD__ . " Error: old word count is not defined." );
        }

        $newWCount = new WordCount_Struct();
        $newWCount->setIdJob( $this->oldWCount->getIdJob() );
        $newWCount->setJobPassword( $this->oldWCount->getJobPassword() );

        $newWCount->setIdSegment( $this->oldWCount->getIdSegment() );
        $newWCount->setOldStatus( $this->oldStatus );
        $newWCount->setNewStatus( $this->newStatus );

        if (!$this->equivalentStatuses() ) {
            $callSetNew = 'set' . $this->methodNameForStatusCall( $this->newStatus );
            $callSetOld = 'set' . $this->methodNameForStatusCall( $this->oldStatus );

            $newWCount->$callSetOld( -$words_amount );
            $newWCount->$callSetNew( +$words_amount );
        }

        return $newWCount;

    }

    /**
     * @param WordCount_Struct[] $_wordCount_Struct_Array
     *
     * @return WordCount_Struct
     * @throws Exception
     */
    public function updateDB( array $_wordCount_Struct_Array ) {

        $differentialCountStruct = $this->sumDifferentials( $_wordCount_Struct_Array );

        $res = WordCounterDao::updateWordCount( $differentialCountStruct );

        if ( $res < 0 ) {
            throw new Exception( "Failed to update counter", $res );
        }

        $newTotalWCount = new WordCount_Struct();
        $newTotalWCount->setNewWords( $this->oldWCount->getNewWords() + $differentialCountStruct->getNewWords() );
        $newTotalWCount->setTranslatedWords( $this->oldWCount->getTranslatedWords() + $differentialCountStruct->getTranslatedWords() );
        $newTotalWCount->setApprovedWords( $this->oldWCount->getApprovedWords() + $differentialCountStruct->getApprovedWords() );
        $newTotalWCount->setRejectedWords( $this->oldWCount->getRejectedWords() + $differentialCountStruct->getRejectedWords() );
        $newTotalWCount->setDraftWords( $this->oldWCount->getDraftWords() + $differentialCountStruct->getDraftWords() );
        $newTotalWCount->setIdSegment( $this->oldWCount->getIdSegment() );
        $newTotalWCount->setOldStatus( $this->oldStatus );
        $newTotalWCount->setNewStatus( $this->newStatus );
        $newTotalWCount->setIdJob( $this->oldWCount->getIdJob() );
        $newTotalWCount->setJobPassword( $this->oldWCount->getJobPassword() );

        return $newTotalWCount;

    }

    public function updateCounts( WordCount_Struct $wordCount_Struct ) {

        $id_job = $wordCount_Struct->getIdJob();;
        $password = $wordCount_Struct->getJobPassword();

        Log::doJsonLog( sprintf(
                        "Requested updateCounts for job %s-%s",
                        $id_job,
                        $password
                )
        );

        $sum_sql = $this->getSumSqlBasedOnProjectWordCountType();

        $queryStats = "select
                        st.status,
                        $sum_sql as wc_sum
                        from
                            jobs j join segment_translations st  on j.id = st.id_job
                          join segments s on s.id = st.id_segment
                        where j.id = :id_job
                        and j.password = :password
                        and s.show_in_cattool = 1
                        group by st.status";

        /*
         * generate job counters
         */
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $queryStats );

        $stmt->execute( [
                'id_job'   => $id_job,
                'password' => $password
        ] );

        $jobStats = $stmt->fetchAll();

        $new_words        = 0.0;
        $draft_words      = 0.0;
        $translated_words = 0.0;
        $approved_words   = 0.0;
        $rejected_words   = 0.0;


        // find the appropriate column based on
        // project config.

        foreach ( $jobStats as $row_stat ) {

            $counter_name = strtolower( $row_stat[ 'status' ] ) . "_words";
            if ( isset( $$counter_name ) ) {
                $$counter_name += $row_stat[ 'wc_sum' ];
            }

        }

        /**
         * update job counters
         */

        $queryUpdate = "UPDATE jobs AS j SET
           new_words = :new_words,
           draft_words = :draft_words,
           translated_words = :translated_words,
           approved_words = :approved_words,
           rejected_words = :rejected_words
           WHERE j.id = :id_job
           AND j.password = :password";

        $stmt = $conn->prepare( $queryUpdate );

        $stmt->execute( [
                'new_words'        => $new_words,
                'draft_words'      => $draft_words,
                'translated_words' => $translated_words,
                'approved_words'   => $approved_words,
                'rejected_words'   => $rejected_words,
                'id_job'           => $id_job,
                'password'         => $password,
        ] );

        return $stmt->rowCount();
    }

    /**
     * @param WordCount_Struct[] $wordCount_Struct
     *
     * @return WordCount_Struct
     */
    public function sumDifferentials( $wordCount_Struct ) {

        $newWCount = new WordCount_Struct();
        $newWCount->setIdSegment( $this->oldWCount->getIdSegment() );
        $newWCount->setOldStatus( $this->oldStatus );
        $newWCount->setNewStatus( $this->newStatus );
        $newWCount->setIdJob( $this->oldWCount->getIdJob() );
        $newWCount->setJobPassword( $this->oldWCount->getJobPassword() );
        /**
         * @var WordCount_Struct $count
         */
        foreach ( $wordCount_Struct as $count ) {
            $newWCount->setNewWords( $newWCount->getNewWords() + $count->getNewWords() );
            $newWCount->setTranslatedWords( $newWCount->getTranslatedWords() + $count->getTranslatedWords() );
            $newWCount->setApprovedWords( $newWCount->getApprovedWords() + $count->getApprovedWords() );
            $newWCount->setRejectedWords( $newWCount->getRejectedWords() + $count->getRejectedWords() );
            $newWCount->setDraftWords( $newWCount->getDraftWords() + $count->getDraftWords() );
        }

        return $newWCount;

    }

    public function initializeJobWordCount( $id_job, $jPassword ) {

        $_details = WordCounterDao::getStatsForJob( $id_job, null, $jPassword );
        //Log::doJsonLog( "--- trying to Iitialize/reset job total word count." );

        $job_details = array_pop( $_details ); //get the row

        $wStruct = new WordCount_Struct();
        $wStruct->setIdJob( $job_details[ 'id' ] );
        $wStruct->setJobPassword( $jPassword );
        $wStruct->setNewWords( $job_details[ Constants_TranslationStatus::STATUS_NEW ] );
        $wStruct->setDraftWords( $job_details[ Constants_TranslationStatus::STATUS_DRAFT ] - $job_details[ Constants_TranslationStatus::STATUS_NEW ] );
        $wStruct->setTranslatedWords( $job_details[ Constants_TranslationStatus::STATUS_TRANSLATED ] );
        $wStruct->setApprovedWords( $job_details[ Constants_TranslationStatus::STATUS_APPROVED ] );
        $wStruct->setRejectedWords( $job_details[ Constants_TranslationStatus::STATUS_REJECTED ] );
        WordCounterDao::initializeWordCount( $wStruct );

        //Log::doJsonLog( $wStruct );

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
    private function methodNameForStatusCall( $name ) {
        if ( in_array( strtoupper($name), Constants_TranslationStatus::$POST_REVISION_STATUSES  ) ) {
            return 'TranslatedWords' ;
        }
        return ucfirst( strtolower( $name ) ) . 'Words';

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

    private function getSumSqlBasedOnProjectWordCountType() {
        $sum_eq_word_count = 'sum(st.eq_word_count)';
        $sum_raw_word_count = 'sum(s.raw_word_count)';

        if ( !$this->project ) {
            return $sum_eq_word_count ;
        }

        $type = $this->project->getWordCountType();
        if ( $type == Projects_MetadataDao::WORD_COUNT_RAW ) {
            return $sum_raw_word_count ;
        }
        else {
            return $sum_eq_word_count ;
        }
    }

} 
