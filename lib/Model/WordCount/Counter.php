<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 26/02/14
 * Time: 10.45
 *
 */
class WordCount_Counter {

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

    protected static $constCache = array();

    /**
     * @param WordCount_Struct $oldWCount
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

    public function setNewStatus( $new_status ) {
        $this->_verifyStatus( $new_status );
        $this->newStatusCall = ucfirst( strtolower( $new_status ) ) . 'Words';
        $this->newStatus     = $new_status;
    }

    public function setOldStatus( $old_status ) {
        $this->_verifyStatus( $old_status );
        $this->oldStatusCall = ucfirst( strtolower( $old_status ) ) . 'Words';
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

        //Log::doLog( $newWCount );

        $callSetNew = 'set' . $this->newStatusCall;
        $callSetOld = 'set' . $this->oldStatusCall;

        $newWCount->$callSetOld( -$words_amount );
        $newWCount->$callSetNew( +$words_amount );

        //Log::doLog( $newWCount );

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

        $res = updateWordCount( $differentialCountStruct );

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

    public function updateDB_countAll( array $_wordCount_Struct_Array ) {
        /**
         * @var $_wordCount_Struct_Array WordCount_Struct
         */
        $_wordCount_Struct_Array = $_wordCount_Struct_Array[0];
        $id_job   = $_wordCount_Struct_Array->getIdJob();;
        $password = $_wordCount_Struct_Array->getJobPassword();

        Log::doLog( sprintf(
                        "Requested updateDB_countAll for job %s-%s",
                        $id_job,
                        $password
                )
        );

        $queryStats = "select
                        st.status, sum(st.eq_word_count) as eq_wc
                        from
                            jobs j join segment_translations st  on j.id = st.id_job
                          join segments s on s.id = st.id_segment
                        where j.id = %d
                        and j.password = '%s'
                        and s.show_in_cattool = 1
                        group by st.status";

        $queryUpdate = "UPDATE jobs AS j SET
           new_words = %d,
           draft_words = %d,
           translated_words = %d,
           approved_words = %d,
           rejected_words = %d
           WHERE j.id = %d
           AND j.password = '%s'";

        /*
         * generate job counters
         */
        $db       = Database::obtain();
        $jobStats = $db->fetch_array(
                sprintf(
                        $queryStats,
                        $id_job,
                        $password
                )
        );

        $new_words        = 0.0;
        $draft_words      = 0.0;
        $translated_words = 0.0;
        $approved_words   = 0.0;
        $rejected_words   = 0.0;

        foreach ( $jobStats as $row_stat ) {

            $counter_name = strtolower( $row_stat[ 'status' ] ) . "_words";
            if ( isset( $$counter_name ) ) {
                $$counter_name += $row_stat[ 'eq_wc' ];
            }

        }

        /**
         * update job counters
         */
        $db->query(
                sprintf(
                        $queryUpdate,
                        $new_words,
                        $draft_words,
                        $translated_words,
                        $approved_words,
                        $rejected_words,
                        $id_job,
                        $password
                )
        );

        if ( $db->affected_rows > 0 ) {
            $newTotalWCount = new WordCount_Struct();
            $newTotalWCount->setNewWords( $new_words );
            $newTotalWCount->setTranslatedWords( $translated_words );
            $newTotalWCount->setApprovedWords( $approved_words );
            $newTotalWCount->setRejectedWords( $rejected_words );
            $newTotalWCount->setDraftWords( $draft_words );
            $newTotalWCount->setIdSegment( $_wordCount_Struct_Array->getIdSegment() );
            $newTotalWCount->setOldStatus( $this->oldStatus );
            $newTotalWCount->setNewStatus( $this->newStatus );
            $newTotalWCount->setIdJob( $id_job );
            $newTotalWCount->setJobPassword( $password );

            return $newTotalWCount;
        } else {
            throw new Exception( 'Failed to upload counters' );
        }

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

        $_details = getStatsForJob( $id_job, null, $jPassword );
        //Log::doLog( "--- trying to Iitialize/reset job total word count." );

        $job_details = array_pop( $_details ); //get the row

        $wStruct = new WordCount_Struct();
        $wStruct->setIdJob( $job_details[ 'id' ] );
        $wStruct->setJobPassword( $jPassword );
        $wStruct->setNewWords( $job_details[ Constants_TranslationStatus::STATUS_NEW ] );
        $wStruct->setDraftWords( $job_details[ Constants_TranslationStatus::STATUS_DRAFT ] - $job_details[ Constants_TranslationStatus::STATUS_NEW ] );
        $wStruct->setTranslatedWords( $job_details[ Constants_TranslationStatus::STATUS_TRANSLATED ] );
        $wStruct->setApprovedWords( $job_details[ Constants_TranslationStatus::STATUS_APPROVED ] );
        $wStruct->setRejectedWords( $job_details[ Constants_TranslationStatus::STATUS_REJECTED ] );
        initializeWordCount( $wStruct );

        //Log::doLog( $wStruct );

        return $wStruct;

    }

} 
