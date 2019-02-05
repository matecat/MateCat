<?php

use \Contribution\ContributionSetStruct, \Contribution\Set;
use Analysis\DqfQueueHandler;
use Exceptions\ControllerReturnException;
use SubFiltering\Filters\FromViewNBSPToSpaces;

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

    /**
     * @var Jobs_JobStruct
     */
    protected $jobData;



    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    protected $client_target_version;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;
    protected $id_segment;

    protected $id_before;
    protected $id_after;
    protected $context_before;
    protected $context_after;

    /**
     * @var \Features\TranslationVersions\SegmentTranslationVersionHandler
     */
    private $VersionsHandler ;

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'id_job'                  => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'                => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'propagate'               => [
                        'filter' => FILTER_VALIDATE_BOOLEAN, 'flags' => FILTER_NULL_ON_FAILURE
                ],
                'id_segment'              => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'time_to_edit'            => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_translator'           => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'translation'             => [ 'filter' => FILTER_UNSAFE_RAW ],
                'segment'                 => [ 'filter' => FILTER_UNSAFE_RAW ],
                'version'                 => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'chosen_suggestion_index' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'status'                  => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'splitStatuses'           => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'context_before'          => [ 'filter' => FILTER_UNSAFE_RAW ],
                'context_after'           => [ 'filter' => FILTER_UNSAFE_RAW ],
                'id_before'               => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_after'                => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
        ];

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
        $this->id_before             = $this->__postInput[ 'id_before' ];
        $this->id_after              = $this->__postInput[ 'id_after' ];

        $this->time_to_edit          = (int)$this->__postInput[ 'time_to_edit' ]; //cast to int, so the default is 0
        $this->id_translator         = $this->__postInput[ 'id_translator' ];
        $this->client_target_version = ( empty( $this->__postInput[ 'version' ] ) ? '0' : $this->__postInput[ 'version' ] );

        $this->chosen_suggestion_index = $this->__postInput[ 'chosen_suggestion_index' ];

        $this->status                  = strtoupper( $this->__postInput[ 'status' ] );
        $this->split_statuses          = explode( ",", strtoupper( $this->__postInput[ 'splitStatuses' ] ) ); //strtoupper transforms null to ""

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

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][] = array( "code" => -2, "message" => "missing id_job" );
        } else {

            //get Job Info, we need only a row of jobs ( split )
            $this->jobData = Jobs_JobDao::getByIdAndPassword( (int)$this->id_job, $this->password );

            if ( empty( $this->jobData ) ) {
                $this->result[ 'errors' ][] = array( "code" => -10, "message" => "wrong password" );
            }

            //add check for job status archived.
            if ( strtolower( $this->jobData[ 'status' ] ) == Constants_JobStatus::STATUS_ARCHIVED ) {
                $this->result[ 'errors' ][] = array( "code" => -3, "message" => "job archived" );
            }

            /**
             * Here we instantiate new objects in order to migrate towards
             * a more object oriented approach.
             */
            $this->chunk      = Chunks_ChunkDao::getByIdAndPassword($this->id_job, $this->password );
            $this->project    = $this->chunk->getProject();

            $this->featureSet->loadForProject( $this->project ) ;

        }

        //ONE OR MORE ERRORS OCCURRED : EXITING
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            throw new Exception( $msg, -1 );
        }


        $Filter = \SubFiltering\Filter::getInstance( $this->featureSet );
        list( $this->translation, $this->split_chunk_lengths ) = CatUtils::parseSegmentSplit( $Filter->fromLayer2ToLayer0( $this->__postInput[ 'translation' ] ), ' ' );
        list( $this->_segment, /** not useful assignment */ ) = CatUtils::parseSegmentSplit( $Filter->fromLayer2ToLayer0( $this->__postInput[ 'segment' ] ), ' ' );

        //PATCH TO FIX BOM INSERTIONS
        $this->translation = str_replace( "\xEF\xBB\xBF", '', $this->translation );

        if ( is_null( $this->translation ) || $this->translation === '' ) {
            Log::doLog( "Empty Translation \n\n" . var_export( $_POST, true ) );

            // won't save empty translation but there is no need to return an errors
            throw new Exception( "Empty Translation \n\n" . var_export( $_POST, true ), 0 );
        }

        $this->parseIDSegment();

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'errors' ][] = array( "code" => -1, "message" => "missing id_segment" );
        }

        if ( $this->isSplittedSegment() ) {
            $this->setStatusForSplittedSegment();
        }

        $this->checkStatus( $this->status );

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

    /**
     * @throws Exceptions_RecordNotFound
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    protected function _getContexts(){

        //Get contexts
        $segmentsList = ( new Segments_SegmentDao )->setCacheTTL( 60 * 60 * 24 )->getContextAndSegmentByIDs(
                [
                        'id_before'  => $this->id_before,
                        'id_segment' => $this->id_segment,
                        'id_after'   => $this->id_after
                ]
        );

        $this->featureSet->filter( 'rewriteContributionContexts', $segmentsList, $this->__postInput );

        $Filter = \SubFiltering\Filter::getInstance( $this->featureSet );
        $this->context_before = $Filter->fromLayer0ToLayer1( $segmentsList->id_before->segment );
        $this->context_after  = $Filter->fromLayer0ToLayer1( $segmentsList->id_after->segment );

    }

    /**
     * @return int|mixed
     * @throws Exceptions_RecordNotFound
     * @throws ReflectionException
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     * @throws \Predis\Connection\ConnectionException
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
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

        $this->readLoginInfo();
        $this->initVersionHandler();
        $this->_getContexts();

        //check tag mismatch
        //get original source segment, first
        $dao = new \Segments_SegmentDao( \Database::obtain() );
        $this->segment = $dao->getById( $this->id_segment );

        //compare segment-translation and get results
        // QA here stands for Quality Assurance
        $spaceHandler = new FromViewNBSPToSpaces();
        $check = new QA( $spaceHandler->transform( $this->__postInput[ 'segment' ] ), $spaceHandler->transform( $this->__postInput[ 'translation' ] ) );
        $check->setFeatureSet( $this->featureSet );
        $check->performConsistencyCheck();

        if ( $check->thereAreWarnings() ) {
            $err_json    = $check->getWarningsJSON();
            $translation = $this->translation;
        } else {
            $err_json    = '';
            $Filter = \SubFiltering\Filter::getInstance( $this->featureSet );
            $translation = $Filter->fromLayer1ToLayer0( $check->getTrgNormalized() );
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

        $old_translation = $this->_getOldTranslation();

        $new_translation                         = new Translations_SegmentTranslationStruct() ;
        $new_translation->id_segment             = $this->id_segment;
        $new_translation->id_job                 = $this->id_job;
        $new_translation->status                 = $this->status;
        $new_translation->time_to_edit           = $this->time_to_edit;
        $new_translation->segment_hash           = $this->segment->segment_hash ;

        $new_translation->translation            = $translation;
        $new_translation->serialized_errors_list = $err_json;

        $new_translation->suggestion_position    = $this->chosen_suggestion_index;
        $new_translation->warning                = $check->thereAreWarnings();
        $new_translation->translation_date       = date( "Y-m-d H:i:s" );


        $this->_validateSegmentTranslationChange($new_translation, $old_translation) ;

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

        $this->updateJobPEE( $old_translation->toArray(), $new_translation->toArray() );

        $editLogModel                      = new EditLog_EditLogModel( $this->id_job, $this->password, $this->featureSet );
        $this->result[ 'pee_error_level' ] = $editLogModel->getMaxIssueLevel();


        // TODO: move this into a feature callback
        $this->__evaluateVersionSave( $new_translation, $old_translation );

        /**
         * when the status of the translation changes, the auto propagation flag
         * must be removed
         */
        if ( $new_translation->translation != $old_translation->translation ||
            $this->status == Constants_TranslationStatus::STATUS_TRANSLATED ||
            $this->status == Constants_TranslationStatus::STATUS_APPROVED ) {
            $new_translation->autopropagated_from = 'NULL';
        }

        $this->featureSet->run('preAddSegmentTranslation', array(
            'new_translation' => $new_translation,
            'old_translation' => $old_translation
        ));

        /**
         * Translation is inserted here.
         */
        CatUtils::addSegmentTranslation( $new_translation, $this->result[ 'errors' ] );

        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "\n\n Error addSegmentTranslation \n\n Database Error \n\n " .
                var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
            $db->rollback();

            return -1;
        }

        $this->featureSet->run('postAddSegmentTranslation', array(
            'chunk'       =>  $this->chunk,
            'is_review'   =>  $this->isRevision(),
            'logged_user' =>  $this->user
        ));

        //propagate translations
        $TPropagation     = [];
        $propagationTotal = [
                'propagated_ids' => []
        ];

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
        $this->result[ 'version' ]    = date_create( $new_translation[ 'translation_date' ] )->getTimestamp();

        $this->result[ 'translation' ] = $this->getTranslationObject( $new_translation );

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

        //COMMIT THE TRANSACTION
        try {
            $db->commit();
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = array( "code" => -101, "message" => $e->getMessage() );
            Log::doLog( "Lock: Transaction Aborted. " . $e->getMessage() );
            $db->rollback();

            return $e->getCode();
        }

        try {

            $this->featureSet->run('setTranslationCommitted', [
                    'translation'      => $new_translation,
                    'old_translation'  => $old_translation,
                    'propagated_ids'   => $propagationTotal['propagated_ids'],
                    'chunk'            => $this->chunk,
                    'segment'          => $this->segment,
                    'user'             => $this->user,
                    'source_page_code' => $this->_getSourcePageCode()
            ] );

        } catch ( Exception $e ){
            Log::doLog( "Exception in setTranslationCommitted callback . " . $e->getMessage() . "\n" . $e->getTraceAsString() );
        }

        try {
            $this->result = $this->featureSet->filter('filterSetTranslationResult', $this->result, array(
                    'translation'     => $new_translation,
                    'old_translation' => $old_translation,
                    'propagated_ids'  => $propagationTotal['propagated_ids'],
                    'chunk'           => $this->chunk,
                    'segment'         => $this->segment
            ));
        } catch ( Exception $e ){
            Log::doLog( "Exception in filterSetTranslationResult callback . " . $e->getMessage() . "\n" . $e->getTraceAsString() );
        }

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

        $this->evalSetContribution( $new_translation, $old_translation );

    }

    /**
     * @return Translations_SegmentTranslationStruct
     * @throws ControllerReturnException
     */
    protected function _getOldTranslation() {
        $old_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $this->id_segment, $this->id_job );

        if ( false === $old_translation ) {
            $old_translation = new Translations_SegmentTranslationStruct() ;
        } // $old_translation if `false` sometimes


        // If volume analysis is not enabled and no translation rows exists, create the row
        if ( !INIT::$VOLUME_ANALYSIS_ENABLED && empty( $old_translation[ 'status' ] ) ) {
            $translation                         = new Translations_SegmentTranslationStruct();
            $translation->id_segment             = (int)$this->id_segment;
            $translation->id_job                 = (int)$this->id_job;
            $translation->status                 = Constants_TranslationStatus::STATUS_NEW;

            $translation->segment_hash           = $this->segment[ 'segment_hash' ];
            $translation->translation            = $this->segment[ 'segment' ];
            $translation->standard_word_count    = $this->segment[ 'raw_word_count' ];

            $translation->serialized_errors_list = '';
            $translation->suggestion_position    = 0;
            $translation->warning                = false;
            $translation->translation_date       = date( "Y-m-d H:i:s" );

            CatUtils::addSegmentTranslation( $translation, $this->result[ 'errors' ] );

            if ( !empty( $this->result[ 'errors' ] ) ) {
                $db->rollback();
                throw new ControllerReturnException('addSegmentTranslation failed', -1 );
            }

            $old_translation = $translation;
        }

        return $old_translation ;
    }

    /**
     * This method returns a representation of the saved translation which
     * should be as much as possible compliant with the future API v2.
     *
     * @param $saved_translation
     *
     * @return array
     * @throws Exceptions_RecordNotFound
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    private function getTranslationObject( $saved_translation ) {
        $filter = \SubFiltering\Filter::getInstance( $this->featureSet );
        $translation = array(
                'version_number' => @$saved_translation['version_number'],
                'sid'            => $saved_translation['id_segment'],
                'translation'    =>$filter->fromLayer0ToLayer2( $saved_translation['translation'] ),
                'status'         => $saved_translation['status']
        );
        return $translation ;
    }

    private function _getSourcePageCode() {
        $code = $this->isRevision() ? Constants::SOURCE_PAGE_REVISION :
                Constants::SOURCE_PAGE_TRANSLATE ;

        return $this->featureSet->filter('filterSourcePageCode', $code ) ;
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

        $oldPEE          = $segment->getPEE();
        $oldPee_weighted = $oldPEE * $segmentRawWordCount;

        $segment->translation    = $new_translation[ 'translation' ];
        $segment->pe_effort_perc = null;

        $newPEE          = $segment->getPEE();
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
                    $this->user->uid,
                    $this->jobData['id_project'],
                    $this->isRevision()
            );
        }
    }

    /**
     * This method does consistency check on the input data comparing pervious version and current version.
     * This method was introduced to prevent inconsistent reviewed_words_count.
     *
     * @param Translations_SegmentTranslationStruct $new_translation
     * @param Translations_SegmentTranslationStruct $old_translation
     *
     * @throws ControllerReturnException
     */
    protected function _validateSegmentTranslationChange(
            Translations_SegmentTranslationStruct $new_translation,
            Translations_SegmentTranslationStruct $old_translation
    ) {
        /*
         * Next condition checks for ICE being set to TRANSLATED status when no change to the ICE is made.
         */
        if (
                $old_translation->isICE() &&
                $new_translation->translation == $old_translation->translation &&
                $new_translation->isTranslationStatus() && !$old_translation->isTranslationStatus()
        )  {
            Database::obtain()->rollback() ;
            $msg = "Status change not allowed with identical translation on segment {$old_translation->id_segment}." ;
            $this->result[ 'errors' ][] = [ "code" => -2000, "message" => $msg ];
            throw new ControllerReturnException( $msg , -1 ) ;
        }
    }

    private function __evaluateVersionSave( Translations_SegmentTranslationStruct $new_translation,
                                            Translations_SegmentTranslationStruct $old_translation
    ) {
        if ( $this->VersionsHandler == null ) {
            return;
        }

        $version_saved = $this->VersionsHandler->saveVersion( $new_translation, $old_translation );

        if ( $version_saved ) {
            $new_translation->version_number = $old_translation->version_number + 1;
        } else {
            $new_translation->version_number = $old_translation->version_number ;
        }

        if ( $new_translation->version_number == null ) {
            $new_translation->version_number = 0 ;
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
            if ( is_null( $old_translation[ 'eq_word_count' ] ) || $old_translation[ 'match_type' ] == 'ICE' ) {
                $old_count = $segment[ 'raw_word_count' ] ;
            } else {
                $old_count = $old_translation[ 'eq_word_count' ] ;
            }
        }
        return $old_count ;
    }

    /**
     * @param $_Translation
     * @param $old_translation
     *
     * @throws Exception
     * @throws Exceptions_RecordNotFound
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     */
    private function evalSetContribution( $_Translation, $old_translation ) {
        if ( in_array( $this->status, array(
                Constants_TranslationStatus::STATUS_DRAFT,
                Constants_TranslationStatus::STATUS_NEW
        ) ) ) {
            return;
        }

        $skip_set_contribution = false ;
        $skip_set_contribution = $this->featureSet->filter('filter_skip_set_contribution',
                $skip_set_contribution, $_Translation, $old_translation
        );

        if ( $skip_set_contribution ) {
            return ;
        }
        
        /**
         * Set the new contribution in queue
         */
        $Filter = \SubFiltering\Filter::getInstance( $this->featureSet );

        $contributionStruct                       = new ContributionSetStruct();
        $contributionStruct->fromRevision         = self::isRevision();
        $contributionStruct->id_job               = $this->id_job;
        $contributionStruct->job_password         = $this->password;
        $contributionStruct->id_segment           = $this->id_segment;
        $contributionStruct->segment              = $Filter->fromLayer0ToLayer1( $this->segment[ 'segment' ] );
        $contributionStruct->translation          = $Filter->fromLayer0ToLayer1( $_Translation[ 'translation' ] );
        $contributionStruct->api_key              = \INIT::$MYMEMORY_API_KEY;
        $contributionStruct->uid                  = $this->user->uid;
        $contributionStruct->oldTranslationStatus = $old_translation[ 'status' ];
        $contributionStruct->oldSegment           = $Filter->fromLayer0ToLayer1( $this->segment[ 'segment' ] ); //
        $contributionStruct->oldTranslation       = $Filter->fromLayer0ToLayer1( $old_translation[ 'translation' ] );
        $contributionStruct->propagationRequest   = $this->propagate;
        $contributionStruct->id_mt                = $this->jobData->id_mt_engine;

        $contributionStruct->context_after        = $this->context_after;
        $contributionStruct->context_before       = $this->context_before;

        $this->featureSet->filter(
                'filterContributionStructOnSetTranslation',
                $contributionStruct,
                $this->project
        );

        /** TODO Remove , is only for debug purposes */
        try {
            $element            = new \TaskRunner\Commons\QueueElement();
            $element->params    = $contributionStruct;
            $element->__toString();
            \Utils::raiseJsonExceptionError( true );
        } catch ( Exception $e ){
            Log::doLog( $contributionStruct );
        }
        /** TODO Remove */

        //assert there is not an exception by following the flow
        WorkerClient::init( new AMQHandler() );
        Set::contribution( $contributionStruct );

        $contributionStruct = $this->featureSet->filter( 'filterSetContributionMT', null, $contributionStruct, $this->project ) ;
        Set::contributionMT( $contributionStruct );

    }
}
