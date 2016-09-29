<?php

use \Contribution\ContributionStruct, \Contribution\Set;
use Analysis\DqfQueueHandler;

class setTranslationController extends ajaxController {

    protected $__postInput = array();

    /**
     * Set as true the propagation default
     * @var bool
     */
    protected $propagate = true;

    protected $id_job = false;
    protected $password = false;

    protected $id_translator;
    protected $time_to_edit;
    protected $translation;

    /**
     * @var string
     */
    protected $_segment; // this comes from UI but is not used at moment

    /**
     * @var Segments_SegmentStruct
     */
    protected $segment;  // this comes from DAO

    protected $split_chunk_lengths;
    protected $chosen_suggestion_index;
    protected $status;
    protected $split_statuses;

    protected $jobData = array();



    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    protected $client_target_version;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    /**
     * @var \Features\TranslationVersions\SegmentTranslationVersionHandler
     */
    private $VersionsHandler ;

    /**
     * @var FeatureSet
     */
    protected $feature_set;

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
                'segment'                 => array( 'filter' => FILTER_UNSAFE_RAW ),
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

        /*
         * set by the client, mandatory
         * check propagation flag, if it is null the client not sent it, leave default true, otherwise set the value
         */
        !is_null( $this->__postInput[ 'propagate' ] ) ? $this->propagate = $this->__postInput[ 'propagate' ] : null /* do nothing */ ;

        $this->propagate             = $this->__postInput[ 'propagate' ]; //set by the client, mandatory
        $this->id_segment            = $this->__postInput[ 'id_segment' ];
        $this->time_to_edit          = (int)$this->__postInput[ 'time_to_edit' ]; //cast to int, so the default is 0
        $this->id_translator         = $this->__postInput[ 'id_translator' ];
        $this->client_target_version = ( empty( $this->__postInput[ 'version' ] ) ? '0' : $this->__postInput[ 'version' ] );

        list( $this->translation, $this->split_chunk_lengths ) = CatUtils::parseSegmentSplit( CatUtils::view2rawxliff( $this->__postInput[ 'translation' ] ), ' ' );
        list( $this->_segment, /** not useful assignment */ ) = CatUtils::parseSegmentSplit( CatUtils::view2rawxliff( $this->__postInput[ 'segment' ] ), ' ' );

        $this->chosen_suggestion_index = $this->__postInput[ 'chosen_suggestion_index' ];

        $this->status                  = strtoupper( $this->__postInput[ 'status' ] );
        $this->split_statuses          = explode( ",", strtoupper( $this->__postInput[ 'splitStatuses' ] ) ); //strtoupper transforms null to ""

        //PATCH TO FIX BOM INSERTIONS
        $this->translation = str_replace( "\xEF\xBB\xBF", '', $this->translation );

        Log::doLog( $this->__postInput );

    }

    /**
     * @return bool
     */
    private function isSplittedSegment() {
        //strtoupper transforms null to "" so check for the first element to be an empty string
        return !empty( $this->split_statuses[ 0 ] ) && !empty( $this->split_num );
    }

    /**
     * setStatusForSplittedSegment
     *
     * If splitted segments have different statuses, we reset status
     * to draft.
     */
    private function setStatusForSplittedSegment() {
        if ( count( array_unique( $this->split_statuses ) ) == 1 ) {
            // IF ALL translation chunks are in the same status,
            // we take the status for the entire segment
            $this->status = $this->split_statuses[ 0 ];
        } else {
            $this->status = Constants_TranslationStatus::STATUS_DRAFT;
        }
    }

    protected function _checkData() {
        $this->parseIDSegment();

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'errors' ][] = array( "code" => -1, "message" => "missing id_segment" );
        }

        if ( $this->isSplittedSegment() ) {
            $this->setStatusForSplittedSegment();
        }

        $this->checkStatus( $this->status );

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][] = array( "code" => -2, "message" => "missing id_job" );
        } else {

            //get Job Info, we need only a row of jobs ( split )
            $this->jobData = getJobData( (int)$this->id_job, $this->password );

            if ( empty( $this->jobData ) ) {
                $msg = "Error : empty job data \n\n " . var_export( $_POST, true ) . "\n";
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
            }

            //add check for job status archived.
            if ( strtolower( $this->jobData[ 'status' ] ) == Constants_JobStatus::STATUS_ARCHIVED ) {
                $this->result[ 'errors' ][] = array( "code" => -3, "message" => "job archived" );
            }

            //check for Password correctness ( remove segment split )
            $pCheck = new AjaxPasswordCheck();
            if ( empty( $this->jobData ) || !$pCheck->grantJobAccessByJobData( $this->jobData, $this->password, $this->id_segment ) ) {
                $this->result[ 'errors' ][] = array( "code" => -10, "message" => "wrong password" );
            }

            /**
             * Here we instantiate new objects in order to migrate towards
             * a more object oriented approach.
             */
            $this->project = Projects_ProjectDao::findByJobId( $this->id_job );
            $this->chunk = Chunks_ChunkDao::getByIdAndPassword($this->id_job, $this->password );
            $this->feature_set = FeatureSet::fromIdCustomer($this->project->id_customer);
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

    /**
     * Throws exception if status is not valid.
     *
     * @param $status
     * @throws Exception
     */
    protected function checkStatus( $status ) {

        switch ( $status ) {
            case Constants_TranslationStatus::STATUS_TRANSLATED:
            case Constants_TranslationStatus::STATUS_APPROVED:
            case Constants_TranslationStatus::STATUS_REJECTED:
            case Constants_TranslationStatus::STATUS_DRAFT:
            case Constants_TranslationStatus::STATUS_NEW:
            case Constants_TranslationStatus::STATUS_FIXED:
            case Constants_TranslationStatus::STATUS_REBUTTED:
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

        $this->checkLogin();
        $this->initVersionHandler();

        //check tag mismatch
        //get original source segment, first
        $dao = new \Segments_SegmentDao( \Database::obtain() );
        $this->segment = $dao->getById( $this->id_segment );

        //compare segment-translation and get results
        // QA here stands for Quality Assurance
        $check = new QA( $this->segment[ 'segment' ], $this->translation );
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
        if ( false === $old_translation ) {
            $old_translation = array();
        } // $old_translation if `false` sometimes

        //if volume analysis is not enabled and no translation rows exists
        //create the row
        if ( !INIT::$VOLUME_ANALYSIS_ENABLED && empty( $old_translation[ 'status' ] ) ) {

            $_Translation                             = array();
            $_Translation[ 'id_segment' ]             = (int)$this->id_segment;
            $_Translation[ 'id_job' ]                 = (int)$this->id_job;
            $_Translation[ 'status' ]                 = Constants_TranslationStatus::STATUS_NEW;
            $_Translation[ 'segment_hash' ]           = $this->segment[ 'segment_hash' ];
            $_Translation[ 'translation' ]            = $this->segment[ 'segment' ];
            $_Translation[ 'standard_word_count' ]    = $this->segment[ 'raw_word_count' ];
            $_Translation[ 'serialized_errors_list' ] = '';
            $_Translation[ 'suggestion_position' ]    = 0;
            $_Translation[ 'warning' ]                = false;
            $_Translation[ 'translation_date' ]       = date( "Y-m-d H:i:s" );
            $res                                      = addTranslation( $_Translation );

            if ( $res < 0 ) {
                $this->result[ 'errors' ][] = array( "code" => -101, "message" => "database errors" );
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
         * Evaluate new Avg post-editing effort for the job:
         * - get old translation
         * - get suggestion
         * - evaluate $_seg_oldPEE and normalize it on the number of words for this segment
         *
         * - get new translation
         * - evaluate $_seg_newPEE and normalize it on the number of words for this segment
         *
         * - get $_jobTotalPEE
         * - evaluate $_jobTotalPEE - $_seg_oldPEE + $_seg_newPEE and save it into the job's row
         */

        $this->updateJobPEE( $old_translation, $_Translation );
        $editLogModel                      = new EditLog_EditLogModel( $this->id_job, $this->password );
        $this->result[ 'pee_error_level' ] = $editLogModel->getMaxIssueLevel();


        // TODO: move this into a feature callback
        $this->evaluateVersionSave( $_Translation, $old_translation );

        /**
         * when the status of the translation changes, the auto propagation flag
         * must be removed
         */
        if ( $_Translation[ 'translation' ] != $old_translation[ 'translation' ] ||
            $this->status == Constants_TranslationStatus::STATUS_TRANSLATED ||
            $this->status == Constants_TranslationStatus::STATUS_APPROVED ) {
            $_Translation[ 'autopropagated_from' ] = 'NULL';
        }

        $this->feature_set->run('preAddSegmentTranslation', array(
            'new_translation' => $_Translation,
            'old_translation' => $old_translation
        ));

        /**
         * Translation is inserted here.
         *
         */
        $res = CatUtils::addSegmentTranslation( $_Translation );

        if ( !empty( $res[ 'errors' ] ) ) {
            $this->result[ 'errors' ] = $res[ 'errors' ];

            $msg = "\n\n Error addSegmentTranslation \n\n Database Error \n\n " .
                var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
            $db->rollback();

            return -1;
        }

        $this->feature_set->run('postAddSegmentTranslation', array(
            'chunk' => $this->chunk,
            'is_review' => $this->isRevision(),
            'logged_user' => $this->logged_user
        ));

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
            $dqfSegmentStruct->source_segment     = $this->segment[ 'segment' ];
            $dqfSegmentStruct->new_target_segment = $_Translation[ 'translation' ];

            $dqfSegmentStruct->time = $_Translation[ 'time_to_edit' ];
            $dqfSegmentStruct->mt_engine_version = 1;

            try {
                $dqfQueueHandler = new DqfQueueHandler();
                $dqfQueueHandler->createSegment( $dqfSegmentStruct );
            } catch ( Exception $exn ) {
                $msg = $exn->getMessage() . "\n\n" . $exn->getTraceAsString();
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
            }
        }

        //propagate translations
        $TPropagation = array();
        $propagationTotal = array();

        if ( $this->propagate && in_array( $this->status, array(
            Constants_TranslationStatus::STATUS_TRANSLATED,
            Constants_TranslationStatus::STATUS_APPROVED,
            Constants_TranslationStatus::STATUS_REJECTED
            ) )
        ) {

            $TPropagation[ 'status' ]                 = $this->status;
            $TPropagation[ 'id_job' ]                 = $this->id_job;
            $TPropagation[ 'translation' ]            = $translation;
            $TPropagation[ 'autopropagated_from' ]    = $this->id_segment;
            $TPropagation[ 'serialized_errors_list' ] = $err_json;
            $TPropagation[ 'warning' ]                = $check->thereAreWarnings();
            $TPropagation[ 'segment_hash' ]           = $old_translation[ 'segment_hash' ];

            try {
                if ( $this->VersionsHandler != null ) {
                    $this->VersionsHandler->savePropagation( array(
                            'propagation'             => $TPropagation,
                            'old_translation'         => $old_translation,
                            'job_data'                => $this->jobData
                    ));
                }

                $propagationTotal = propagateTranslation(
                    $TPropagation,
                        $this->jobData,
                        $this->id_segment,
                        $this->project
                );

            } catch ( Exception $e ) {
                $msg = $e->getMessage() . "\n\n" . $e->getTraceAsString();
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
                $db->rollback();

                return $e->getCode();

            }

        }

        $old_wStruct = $this->recountJobTotals( $old_translation[ 'status' ] );

        /*
         * Seems redundant because the update inside the Word_Count object is made only where received_status != old_status
         *
         * But for ICE matches, we need perform the counter update even if TRANSLATED == TRANSLATED
         * because the ICE matches are already set translated by default
         */
        if ( $this->status != $old_translation[ 'status' ] || $old_translation[ 'match_type' ] == 'ICE' ) {

            $old_status = $this->statusOrDefault( $old_translation );
            $old_count = $this->getOldCount( $this->segment, $old_translation );

            // if there is not a row in segment_translations because volume analysis is disabled
            // search for a just created row

            $counter = new WordCount_Counter( $old_wStruct );
            $counter->setOldStatus( $old_status );
            $counter->setNewStatus( $this->status );

            $newValues   = array();
            $newValues[] = $counter->getUpdatedValues( $old_count );

            foreach ( $propagationTotal['totals'] as $__pos => $old_value ) {
                $counter->setOldStatus( $old_value[ 'status' ] );
                $counter->setNewStatus( $this->status );
                $newValues[] = $counter->getUpdatedValues( $old_value[ 'total' ] );
            }

            try {
                $newTotals = $counter->updateDB( $newValues );

            } catch ( Exception $e ) {
                $this->result[ 'errors' ][] = array( "code" => -101, "message" => "database errors" );
                Log::doLog( "Lock: Transaction Aborted. " . $e->getMessage() );
                $db->rollback();

                return $e->getCode();
            }

        } else {
            $newTotals = $old_wStruct;
        }

        //update total time to edit
        try {
            updateTotalTimeToEdit( $this->id_job, $this->password, $this->time_to_edit );
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = array( "code" => -101, "message" => "database errors" );
            Log::doLog( "Lock: Transaction Aborted. " . $e->getMessage() );
            $db->rollback();

            return $e->getCode();
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

        $this->result[ 'translation' ] = $this->getTranslationObject( $_Translation );

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

        $this->feature_set->run('setTranslationCommitted', array(
                'translation'     => $_Translation,
                'old_translation' => $old_translation,
                'propagated_ids'  => $propagationTotal['propagated_ids'],
                'chunk'           => $this->chunk,
                'segment'         => $this->segment
                ));

        //EVERY time an user changes a row in his job when the job is completed,
        // a query to do the update is executed...
        // Avoid this by setting a key on redis with an reasonable TTL
        $redisHandler = new RedisHandler();
        $job_status   = $redisHandler->getConnection()->get( 'job_completeness:' . $this->id_job );
        if ( $job_stats[ 'TRANSLATED_PERC' ] == '100' && empty( $job_status ) ) {
            $redisHandler->getConnection()->setex( 'job_completeness:' . $this->id_job, 60 * 60 * 24 * 15, true ); //15 days
            $update_completed = setJobCompleteness( $this->id_job, 1 );
            if ( $update_completed < 0 ) {
                $msg = "\n\n Error setJobCompleteness \n\n " . var_export( $_POST, true );
                $redisHandler->getConnection()->del( 'job_completeness:' . $this->id_job );
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
            }
        }

        $this->evalSetContribution( $_Translation, $old_translation );

        $this->logForTagProjection(CatUtils::rawxliff2view($this->translation));

    }

    /**
     * This method returns a representation of the saved translation which
     * should be as much as possible compliant with the future API v2.
     *
     */
    private function getTranslationObject( $saved_translation ) {
        $translation = array(
                'version_number' => $saved_translation['version_number'],
                'sid'            => $saved_translation['id_segment'],
                'translation'    => \CatUtils::rawxliff2view( $saved_translation['translation'] ),
                'status'         => $saved_translation['status']
        );
        return $translation ;
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

        return $old_wStruct;
    }

    //TODO: put this method into Job model and use Segnent object
    private function updateJobPEE( Array $old_translation, Array $new_translation ) {

        $segmentRawWordCount = $this->segment->raw_word_count;
        $segment             = new EditLog_EditLogSegmentClientStruct(
                array(
                        'suggestion'     => $old_translation[ 'suggestion' ],
                        'translation'    => $old_translation[ 'translation' ],
                        'raw_word_count' => $segmentRawWordCount,
                        'time_to_edit'   => $old_translation[ 'time_to_edit' ] + $new_translation[ 'time_to_edit' ]
                )
        );

        $oldSegment               = clone $segment;
        $oldSegment->time_to_edit = $old_translation[ 'time_to_edit' ];

        $oldPEE          = $segment->getPeePerc();
        $oldPee_weighted = $oldPEE * $segmentRawWordCount;

        $segment->translation    = $new_translation[ 'translation' ];
        $segment->pe_effort_perc = null;

        $newPEE          = $segment->getPeePerc();
        $newPee_weighted = $newPEE * $segmentRawWordCount;

        if ( $segment->isValidForEditLog() ) {
            //if the segment was not valid for editlog and now it is, then just add the weighted pee
            if ( !$oldSegment->isValidForEditLog() ) {
                $newTotalJobPee = ( $this->jobData[ 'avg_post_editing_effort' ] + $newPee_weighted );
            } //otherwise, evaluate it normally
            else {
                $newTotalJobPee = ( $this->jobData[ 'avg_post_editing_effort' ] - $oldPee_weighted + $newPee_weighted );

            }
            $queryUpdateJob = "update jobs
                                set avg_post_editing_effort = %f
                                where id = %d and password = '%s'";

            $db = Database::obtain();
            $db->query(
                    sprintf(
                            $queryUpdateJob,
                            $newTotalJobPee,
                            $this->id_job,
                            $this->password
                    )
            );
        } //segment was valid but now it is no more
        else if ( $oldSegment->isValidForEditLog() ) {
            $newTotalJobPee = ( $this->jobData[ 'avg_post_editing_effort' ] - $oldPee_weighted );

            $queryUpdateJob = "update jobs
                                set avg_post_editing_effort = %f
                                where id = %d and password = '%s'";

            $db = Database::obtain();
            $db->query(
                    sprintf(
                            $queryUpdateJob,
                            $newTotalJobPee,
                            $this->id_job,
                            $this->password
                    )
            );
        }
    }

    private function initVersionHandler() {
        if ($this->project->isFeatureEnabled('translation_versions')) {
            $this->VersionsHandler = new \Features\TranslationVersions\SegmentTranslationVersionHandler(
                    $this->id_job,
                    $this->id_segment,
                    $this->uid,
                    $this->jobData['id_project'],
                    $this->isRevision()
            );
        }
    }

    private function evaluateVersionSave( &$_Translation, &$old_translation ) {
        if ( $this->VersionsHandler == null ) {
            return;
        }

        /**
         * Translation version handler: save old translation.
         * TODO: move this in an model observer for segment translation.
         * TODO: really, this is not good.
         */

        $version_saved = $this->VersionsHandler->saveVersion( $_Translation, $old_translation );

        if ( $version_saved ) {
            $_Translation['version_number'] = $old_translation['version_number'] + 1;
        } else {
            $_Translation['version_number'] = $old_translation['version_number'];
        }

        if ( $_Translation['version_number'] == null ) {
            $_Translation['version_number'] = 0 ;
        }
    }

    /**
     * @param $old_translation
     *
     * @return string
     */
    private function statusOrDefault( $old_translation ) {
        if ( empty( $old_translation['status'] ) ) {
            return Constants_TranslationStatus::STATUS_NEW ;
        } else {
            return $old_translation[ 'status' ] ;
        }
    }


    /**
     * Returns the old_count to pass to WordCounter, based on project
     * configuration, picking from either eq_word_count or raw_word_count
     *
     * @param $segment
     * @param $old_translation
     *
     * @return mixed
     */
    private function getOldCount($segment, $old_translation ) {
        $word_count_type = $this->project->getWordCountType();

        if ( $word_count_type == Projects_MetadataDao::WORD_COUNT_RAW ) {
            $old_count = $segment['raw_word_count'];
        } else {
            if ( is_null( $old_translation[ 'eq_word_count' ] ) || $old_translation['status'] == 'ICE' ) {
                $old_count = $segment[ 'raw_word_count' ] ;
            } else {
                $old_count = $old_translation[ 'eq_word_count' ] ;
            }
        }
        return $old_count ;
    }

    private function logForTagProjection($msg) {
        $logfile = \Log::$fileName;  //Todo: check why is null

        \Log::$fileName = 'tagProjection.log';
        \Log::doLog( $msg );
        \Log::$fileName = $logfile;
    }

    /**
     * @param $_Translation
     * @param $old_translation
     */
    private function evalSetContribution( $_Translation, $old_translation ) {
        if ( in_array( $this->status, array(
                Constants_TranslationStatus::STATUS_DRAFT,
                Constants_TranslationStatus::STATUS_NEW
        ) ) ) {
            return;
        }
        
        /**
         * Set the new contribution in queue
         */
        $contributionStruct                       = new ContributionStruct();
        $contributionStruct->fromRevision         = self::isRevision();
        $contributionStruct->id_job               = $this->id_job;
        $contributionStruct->job_password         = $this->password;
        $contributionStruct->segment              = $this->segment[ 'segment' ];
        $contributionStruct->translation          = $_Translation[ 'translation' ];
        $contributionStruct->api_key              = \INIT::$MYMEMORY_API_KEY;
        $contributionStruct->uid                  = $this->uid;
        $contributionStruct->oldTranslationStatus = $old_translation[ 'status' ];
        $contributionStruct->oldSegment           = $this->segment[ 'segment' ]; //we do not change the segment source
        $contributionStruct->oldTranslation       = $old_translation[ 'translation' ];
        $contributionStruct->propagationRequest   = $this->propagate;

        //assert there is not an exception by following the flow
        WorkerClient::init( new AMQHandler() );
        Set::contribution( $contributionStruct );
    }
}
