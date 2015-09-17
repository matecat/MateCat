<?php

class setTranslationController extends ajaxController {

    protected $__postInput = array();

    protected $propagate = false;
    protected $id_job = false;
    protected $password = false;

    protected $id_translator;
    protected $time_to_edit;
    protected $translation;
    protected $split_chunk_lengths;
    protected $chosen_suggestion_index;
    protected $status;
    protected $split_statuses;

    protected $jobData = array();

    protected $client_target_version;

    /**
     * This constant represent the job progress percentage over
     * which the wordcount could be updated by a massive query.
     */
//    const UPDATE_QUERY_JOB_PROGRESS_THRESHOLD = 90;

    /**
     * The probability over which the wordcount massive query
     * will be triggered
     */
//    const UPDATE_QUERY_PROBABILITY_THRESHOLD = 90;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'id_job'                  => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password'                => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'propagate'               => array(
                        'filter' => FILTER_VALIDATE_BOOLEAN, 'flags' => FILTER_NULL_ON_FAILURE
                ),
                'id_segment'              => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'time_to_edit'            => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'id_translator'           => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'translation'             => array( 'filter' => FILTER_UNSAFE_RAW ),
                'version'                 => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'chosen_suggestion_index' => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'status'                  => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'splitStatuses'           => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job                = $this->__postInput[ 'id_job' ];
        $this->password              = $this->__postInput[ 'password' ];
        $this->propagate             = $this->__postInput[ 'propagate' ]; //not used here but used in child class setAutoPropagationController
        $this->id_segment            = $this->__postInput[ 'id_segment' ];
        $this->time_to_edit          = (int)$this->__postInput[ 'time_to_edit' ]; //cast to int, so the default is 0
        $this->id_translator         = $this->__postInput[ 'id_translator' ];
        $this->client_target_version = ( empty( $this->__postInput[ 'version' ] ) ? '0' : $this->__postInput[ 'version' ] );

        list( $this->translation, $this->split_chunk_lengths ) = CatUtils::parseSegmentSplit( CatUtils::view2rawxliff( $this->__postInput[ 'translation' ] ), ' ' );

        $this->chosen_suggestion_index = $this->__postInput[ 'chosen_suggestion_index' ];
        $this->status                  = strtoupper( $this->__postInput[ 'status' ] );
        $this->split_statuses          = explode( ",", strtoupper( $this->__postInput[ 'splitStatuses' ] ) ); //strtoupper transforms null to ""

        //PATCH TO FIX BOM INSERTIONS
        $this->translation = str_replace( "\xEF\xBB\xBF", '', $this->translation );


        if ( is_null( $this->propagate ) || !isset( $this->propagate ) ) {
            $this->propagate = true;
        }
        Log::doLog( $this->__postInput );

    }

    protected function _checkData( $logName = 'log.txt' ) {

        //change Log file
        Log::$fileName = $logName;

        $this->parseIDSegment();
        if ( empty( $this->id_segment ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "missing id_segment" );
        }

//        $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "prova" );
//        throw new Exception( "prova", -1 );

        //strtoupper transforms null to "" so check for the first element to be an empty string
        if ( !empty( $this->split_statuses[ 0 ] ) && !empty( $this->split_num ) ) {

            if ( count( array_unique( $this->split_statuses ) ) == 1 ) {
                //IF ALL translation chunks are in the same status, we take the status for the entire segment
                $this->status = $this->split_statuses[ 0 ];
            } else {
                $this->status = Constants_TranslationStatus::STATUS_DRAFT;
            }

            foreach ( $this->split_statuses as $pos => $value ) {
                $this->_checkForStatus( $value );
            }

        } else {

            $this->_checkForStatus( $this->status );

        }

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "missing id_job" );
        } else {

//            $this->result[ 'error' ][ ] = array( "code" => -1000, "message" => "test 1" );
//            throw new Exception( 'prova', -1 );

            //get Job Info, we need only a row of jobs ( split )
            $this->jobData = $job_data = getJobData( (int)$this->id_job, $this->password );
            if ( empty( $job_data ) ) {
                $msg = "Error : empty job data \n\n " . var_export( $_POST, true ) . "\n";
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
            }

            //add check for job status archived.
            if ( strtolower( $job_data[ 'status' ] ) == Constants_JobStatus::STATUS_ARCHIVED ) {
                $this->result[ 'errors' ][ ] = array( "code" => -3, "message" => "job archived" );
            }

            //check for Password correctness ( remove segment split )
            $pCheck = new AjaxPasswordCheck();
            if ( empty( $job_data ) || !$pCheck->grantJobAccessByJobData( $job_data, $this->password, $this->id_segment ) ) {
                $this->result[ 'errors' ][ ] = array( "code" => -10, "message" => "wrong password" );
            }

        }

        //ONE OR MORE ERRORS OCCURRED : EXITING
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            throw new Exception( $msg, -1 );
        }

        if ( is_null( $this->translation ) || $this->translation === '' ) {
            Log::doLog( "Empty Translation \n\n" . var_export( $_POST, true ) );

            // won't save empty translation but there is no need to return an errors
            throw new Exception( "Empty Translation \n\n" . var_export( $_POST, true ), 0 );
        }

    }

    protected function _checkForStatus( $status ) {

        switch ( $status ) {
            case Constants_TranslationStatus::STATUS_TRANSLATED:
            case Constants_TranslationStatus::STATUS_APPROVED:
            case Constants_TranslationStatus::STATUS_REJECTED:
            case Constants_TranslationStatus::STATUS_DRAFT:
            case Constants_TranslationStatus::STATUS_NEW:
                break;
            default:
                //NO debug and NO-actions for un-mapped status
                $this->result[ 'code' ] = 1;
                $this->result[ 'data' ] = "OK";

                $msg = "Error Hack Status \n\n " . var_export( $_POST, true );
                throw new Exception( $msg, -1 );
                break;
        }

    }

    public function doAction() {

        try {

            $this->_checkData();

        } catch ( Exception $e ) {

            if ( $e->getCode() == -1 ) {
                Utils::sendErrMailReport( $e->getMessage() );
            }

            Log::doLog( $e->getMessage() );

            return $e->getCode();

        }

        //check tag mismatch
        //get original source segment, first
        $segment = getSegment( $this->id_segment );

        //compare segment-translation and get results
        $check = new QA( $segment[ 'segment' ], $this->translation );
        $check->performConsistencyCheck();

        if ( $check->thereAreWarnings() ) {
            $err_json    = $check->getWarningsJSON();
            $translation = $this->translation;
        } else {
            $err_json    = '';
            $translation = $check->getTrgNormalized();

        }

        /*
         * begin stats counter
         *
         * It works good with default InnoDB Isolation level
         *
         * REPEATABLE-READ offering a row level lock for this id_segment
         *
         */
        $db = Database::obtain();
        $db->begin();

        $old_translation = getCurrentTranslation( $this->id_job, $this->id_segment );

//        $old_version = ( empty( $old_translation['translation_date'] ) ? '0' : date_create( $old_translation['translation_date'] )->getTimestamp() );
//        if( $this->client_target_version != $old_version ){
//            $this->result[ 'errors' ][ ] = array( "code" => -102, "message" => "Translation version mismatch" );
//            $db->rollback();
//            return false;
//        }

        //if volume analysis is not enabled and no translation rows exists
        //create the row
        if ( !INIT::$VOLUME_ANALYSIS_ENABLED && empty( $old_translation[ 'status' ] ) ) {

            $_Translation                             = array();
            $_Translation[ 'id_segment' ]             = (int)$this->id_segment;
            $_Translation[ 'id_job' ]                 = (int)$this->id_job;
            $_Translation[ 'status' ]                 = Constants_TranslationStatus::STATUS_NEW;
            $_Translation[ 'segment_hash' ]           = $segment[ 'segment_hash' ];
            $_Translation[ 'translation' ]            = $segment[ 'segment' ];
            $_Translation[ 'standard_word_count' ]    = $segment[ 'raw_word_count' ];
            $_Translation[ 'serialized_errors_list' ] = '';
            $_Translation[ 'suggestion_position' ]    = 0;
            $_Translation[ 'warning' ]                = false;
            $_Translation[ 'translation_date' ]       = date( "Y-m-d H:i:s" );
            $res                                      = addTranslation( $_Translation );

            if ( $res < 0 ) {
                $this->result[ 'errors' ][ ] = array( "code" => -101, "message" => "database errors" );
                $db->rollback();

                return $res;
            }

            /*
             * begin stats counter
             *
             */
            $old_translation = $_Translation;

        }

        $_Translation                             = array();
        $_Translation[ 'id_segment' ]             = $this->id_segment;
        $_Translation[ 'id_job' ]                 = $this->id_job;
        $_Translation[ 'status' ]                 = $this->status;
        $_Translation[ 'time_to_edit' ]           = $this->time_to_edit;
        $_Translation[ 'translation' ]            = preg_replace( '/[ \t\n\r\0\x0A\xA0]+$/u', '', $translation );
        $_Translation[ 'serialized_errors_list' ] = $err_json;
        $_Translation[ 'suggestion_position' ]    = $this->chosen_suggestion_index;
        $_Translation[ 'warning' ]                = $check->thereAreWarnings();
        $_Translation[ 'translation_date' ]       = date( "Y-m-d H:i:s" );

        /**
         * when the status of the translation changes, the auto propagation flag
         * must be removed
         */
        if ( $_Translation[ 'translation' ] != $old_translation[ 'translation' ] || $this->status == Constants_TranslationStatus::STATUS_TRANSLATED || $this->status == Constants_TranslationStatus::STATUS_APPROVED ) {
            $_Translation[ 'autopropagated_from' ] = 'NULL';
        }

        $res = CatUtils::addSegmentTranslation( $_Translation );

        if ( !empty( $res[ 'errors' ] ) ) {
            $this->result[ 'errors' ] = $res[ 'errors' ];

            $msg = "\n\n Error addSegmentTranslation \n\n Database Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
            $db->rollback();

            return -1;
        }

        if ( INIT::$DQF_ENABLED && !empty( $this->jobData[ 'dqf_key' ] ) &&
                $_Translation[ 'status' ] == Constants_TranslationStatus::STATUS_TRANSLATED
        ) {
            $dqfSegmentStruct = DQF_DqfSegmentStruct::getStruct();

            if ( $old_translation[ 'suggestion' ] == null ) {
                $dqfSegmentStruct->target_segment = "";
                $dqfSegmentStruct->tm_match       = 0;
            } else {
                $dqfSegmentStruct->target_segment = $old_translation[ 'suggestion' ];
                $dqfSegmentStruct->tm_match       = $old_translation[ 'suggestion_match' ];
            }

            $dqfSegmentStruct->task_id            = $this->id_job;
            $dqfSegmentStruct->segment_id         = $this->id_segment;
            $dqfSegmentStruct->source_segment     = $segment[ 'segment' ];
            $dqfSegmentStruct->new_target_segment = $_Translation[ 'translation' ];

            $dqfSegmentStruct->time = $_Translation[ 'time_to_edit' ];
//            $dqfSegmentStruct->mtengine = $this->jobData['id_mt_engine'];
            $dqfSegmentStruct->mt_engine_version = 1;

            try {
                $dqfQueueHandler = new Analysis_DqfQueueHandler();
                $dqfQueueHandler->createSegment( $dqfSegmentStruct );
            } catch ( Exception $exn ) {
                $msg = $exn->getMessage() . "\n\n" . $exn->getTraceAsString();
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
            }
        }

        $propagateToTranslated = true;

        //for the moment, this is set explicitely
        if ( $this->propagate == false ) {
            $propagateToTranslated = false;
        }

        //propagate translations
        $TPropagation = array();

        //Warning: this value will NOT be used to update values,
        //but to exclude current segment from auto-propagation
        $_idSegment = $this->id_segment;

        ( $propagateToTranslated ) ? $TPropagation[ 'status' ] =  $this->status : null /* NO OP */;

        $TPropagation[ 'id_job' ]                 = $this->id_job;
        $TPropagation[ 'translation' ]            = $translation;
        $TPropagation[ 'autopropagated_from' ]    = $this->id_segment;
        $TPropagation[ 'serialized_errors_list' ] = $err_json;
        $TPropagation[ 'warning' ]                = $check->thereAreWarnings();
//        $TPropagation[ 'translation_date' ]       = date( "Y-m-d H:i:s" );
        $TPropagation[ 'segment_hash' ] = $old_translation[ 'segment_hash' ];

        $propagationTotal = array();
        if ( $propagateToTranslated && in_array( $this->status, array(
                Constants_TranslationStatus::STATUS_TRANSLATED, Constants_TranslationStatus::STATUS_APPROVED,
                Constants_TranslationStatus::STATUS_REJECTED
        ) ) ) {

            try {

                $propagationTotal = propagateTranslation( $TPropagation, $this->jobData, $_idSegment, $propagateToTranslated );

            } catch ( Exception $e ) {
                $msg = $e->getMessage() . "\n\n" . $e->getTraceAsString();
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
                $db->rollback();
                return $e->getCode();

            }

        }

        $old_wStruct = $this->recountJobTotals( $old_translation['status'] );

        //redundant because the update is made only where status = old status
        if ( $this->status != $old_translation[ 'status' ] ) {

            //cambiato status, sposta i conteggi
            $old_count = ( !is_null( $old_translation[ 'eq_word_count' ] ) ? $old_translation[ 'eq_word_count' ] : $segment[ 'raw_word_count' ] );

            //if there is not a row in segment_translations because volume analysis is disabled
            //search for a just created row
            $old_status = ( !empty( $old_translation[ 'status' ] ) ? $old_translation[ 'status' ] : Constants_TranslationStatus::STATUS_NEW );

            $counter = new WordCount_Counter( $old_wStruct );
            $counter->setOldStatus( $old_status );
            $counter->setNewStatus( $this->status );

            $newValues   = array();
            $newValues[] = $counter->getUpdatedValues( $old_count );

            foreach ( $propagationTotal as $__pos => $old_value ) {
                $counter->setOldStatus( $old_value[ 'status' ] );
                $counter->setNewStatus( $this->status );
                $newValues[] = $counter->getUpdatedValues( $old_value[ 'total' ] );
            }

            try {
                $newTotals = $counter->updateDB( $newValues );
            } catch ( Exception $e ) {
                $this->result[ 'errors' ][ ] = array( "code" => -101, "message" => "database errors" );
                Log::doLog("Lock: Transaction Aborted. " . $e->getMessage() );
                $db->rollback();

                return $e->getCode();
            }

        } else {
            $newTotals = $old_wStruct;
        }

        $job_stats = CatUtils::getFastStatsForJob( $newTotals );
        $project   = getProject( $this->jobData[ 'id_project' ] );
        $project   = array_pop( $project );

        $job_stats[ 'ANALYSIS_COMPLETE' ] = (
          $project[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE ||
          $project[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE
                ? true : false );

        $file_stats = array();

        $this->result[ 'stats' ]      = $job_stats;
        $this->result[ 'file_stats' ] = $file_stats;
        $this->result[ 'code' ]       = 1;
        $this->result[ 'data' ]       = "OK";
        $this->result[ 'version' ]    = date_create( $_Translation[ 'translation_date' ] )->getTimestamp();

        /* FIXME: added for code compatibility with front-end. Remove. */
        $_warn   = $check->getWarnings();
        $warning = $_warn[ 0 ];
        /* */

        $this->result[ 'warning' ][ 'cod' ] = $warning->outcome;
        if ( $warning->outcome > 0 ) {
            $this->result[ 'warning' ][ 'id' ] = $this->id_segment;
        } else {
            $this->result[ 'warning' ][ 'id' ] = 0;
        }

        //strtoupper transforms null to "" so check for the first element to be an empty string
        if ( !empty( $this->split_statuses[ 0 ] ) && !empty( $this->split_num ) ) {

            /* put the split inside the transaction if they are present */
            $translationStruct             = TranslationsSplit_SplitStruct::getStruct();
            $translationStruct->id_segment = $this->id_segment;
            $translationStruct->id_job     = $this->id_job;

            $translationStruct->target_chunk_lengths = array(
                    'len' => $this->split_chunk_lengths, 'statuses' => $this->split_statuses
            );
            $translationDao                          = new TranslationsSplit_SplitDAO( Database::obtain() );
            $result                                  = $translationDao->update( $translationStruct );

        }

        $db->commit();

        if ( $job_stats[ 'TRANSLATED_PERC' ] == '100' ) {
            $update_completed = setJobCompleteness( $this->id_job, 1 );
        }

        if ( @$update_completed < 0 ) {
            $msg = "\n\n Error setJobCompleteness \n\n " . var_export( $_POST, true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
        }

    }

    private function recountJobTotals( $old_status ) {
        $old_wStruct = new WordCount_Struct();
        $old_wStruct->setIdJob( $this->id_job );
        $old_wStruct->setJobPassword( $this->password );
        $old_wStruct->setNewWords( $this->jobData[ 'new_words' ] );
        $old_wStruct->setDraftWords( $this->jobData[ 'draft_words' ] );
        $old_wStruct->setTranslatedWords( $this->jobData[ 'translated_words' ] );
        $old_wStruct->setApprovedWords( $this->jobData[ 'approved_words' ] );
        $old_wStruct->setRejectedWords( $this->jobData[ 'rejected_words' ] );

        $old_wStruct->setIdSegment( $this->id_segment );

        //redundant, this is made into WordCount_Counter::updateDB
        $old_wStruct->setOldStatus( $old_status );
        $old_wStruct->setNewStatus( $this->status );

        return $old_wStruct ;
    }

}
