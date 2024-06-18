<?php

use Contribution\ContributionSetStruct;
use Contribution\Set;
use EditLog\EditLogSegmentClientStruct;
use Exceptions\ControllerReturnException;
use Exceptions\NotFoundException;
use Features\ReviewExtended\ReviewUtils;
use Features\TranslationVersions;
use Files\FilesPartsDao;
use LQA\QA;
use Matecat\SubFiltering\MateCatFilter;
use WordCount\WordCountStruct;

class setTranslationController extends ajaxController {

    protected $__postInput = [];

    /**
     * @var bool
     *
     * This parameter is not used by the application, but we use it to for information integrity
     *
     * User choice for propagation.
     *
     * Propagate is false IF:
     * - the segment has not repetitions
     * - the segment has some one or more repetitions and the user choose to not propagate it
     * - the segment is already autopropagated ( marked as autopropagated_from ) and it hasn't been changed
     *
     * Propagate is true ( vice versa ) IF:
     * - the segment has one or more repetitions and it's status is NEW/DRAFT
     * - the segment has one or more repetitions and the user choose to propagate it
     * - the segment has one or more repetitions, it is not modified, it doesn't have translation conflicts and a change status is requested
     */
    protected $propagate = true;

    protected $id_job   = false;
    protected $password = false;

    protected $id_translator;
    protected $time_to_edit;

    /**
     * @var Segments_SegmentStruct
     */
    protected $segment;  // this comes from DAO

    protected $split_chunk_lengths;
    protected $chosen_suggestion_index;
    protected $suggestion_array;
    protected $status;
    protected $split_statuses;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;


    protected $client_target_version;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project;
    protected $id_segment;

    protected $id_before;
    protected $id_after;
    protected $context_before;
    protected $context_after;

    /** @var MateCatFilter */
    protected $filter;

    /**
     * @var TranslationVersions\Handlers\TranslationVersionsHandler|TranslationVersions\Handlers\DummyTranslationVersionHandler
     */
    private $VersionsHandler;
    private $revisionNumber;

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'id_job'                  => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'                => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'current_password'        => [
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
                'suggestion_array'        => [ 'filter' => FILTER_UNSAFE_RAW ],
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
                'revision_number'         => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'guess_tag_used'          => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'characters_counter'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ]
        ];

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job            = $this->__postInput[ 'id_job' ];
        $this->password          = $this->__postInput[ 'password' ];
        $this->received_password = $this->__postInput[ 'current_password' ];
        $this->revisionNumber    = $this->__postInput[ 'revision_number' ];

        /*
         * set by the client, mandatory
         * check propagation flag, if it is null the client not sent it, leave default true, otherwise set the value
         */
        !is_null( $this->__postInput[ 'propagate' ] ) ? $this->propagate = $this->__postInput[ 'propagate' ] : null /* do nothing */
        ;

        $this->id_segment = $this->__postInput[ 'id_segment' ];
        $this->id_before  = $this->__postInput[ 'id_before' ];
        $this->id_after   = $this->__postInput[ 'id_after' ];

        $this->time_to_edit          = (int)$this->__postInput[ 'time_to_edit' ]; //cast to int, so the default is 0
        $this->id_translator         = $this->__postInput[ 'id_translator' ];
        $this->client_target_version = ( empty( $this->__postInput[ 'version' ] ) ? '0' : $this->__postInput[ 'version' ] );

        $this->chosen_suggestion_index = $this->__postInput[ 'chosen_suggestion_index' ];
        $this->suggestion_array        = $this->__postInput[ 'suggestion_array' ];

        $this->status         = strtoupper( $this->__postInput[ 'status' ] );
        $this->split_statuses = explode( ",", strtoupper( $this->__postInput[ 'splitStatuses' ] ) ); //strtoupper transforms null to ""

        Log::doJsonLog( $this->__postInput );

    }

    /**
     * @return bool
     */
    private function isSplittedSegment() {
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
            $this->result[ 'errors' ][] = [ "code" => -2, "message" => "missing id_job" ];
        } else {

            //get Job Info, we need only a row of jobs ( split )
            $this->chunk = Chunks_ChunkDao::getByIdAndPassword( (int)$this->id_job, $this->password );

            if ( empty( $this->chunk ) ) {
                $this->result[ 'errors' ][] = [ "code" => -10, "message" => "wrong password" ];
            }

            //add check for job status archived.
            if ( strtolower( $this->chunk[ 'status' ] ) == Constants_JobStatus::STATUS_ARCHIVED ) {
                $this->result[ 'errors' ][] = [ "code" => -3, "message" => "job archived" ];
            }

            $this->project = $this->chunk->getProject();

            $featureSet = $this->getFeatureSet();
            $featureSet->loadForProject( $this->project );

            /** @var MateCatFilter $filter */
            $this->filter = MateCatFilter::getInstance( $featureSet, $this->chunk->source, $this->chunk->target, Segments_SegmentOriginalDataDao::getSegmentDataRefMap( $this->id_segment ) );
        }

        //ONE OR MORE ERRORS OCCURRED : EXITING
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            throw new Exception( $msg, -1 );
        }


        list( $__translation, $this->split_chunk_lengths ) = CatUtils::parseSegmentSplit( $this->__postInput[ 'translation' ], '', $this->filter );

        if ( is_null( $__translation ) || $__translation === '' ) {
            Log::doJsonLog( "Empty Translation \n\n" . var_export( $_POST, true ) );

            // won't save empty translation but there is no need to return an errors
            throw new Exception( "Empty Translation \n\n" . var_export( $_POST, true ), 0 );
        }

        $this->parseIDSegment();

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'errors' ][] = [ "code" => -1, "message" => "missing id_segment" ];
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
     *
     * @throws Exception
     */
    protected function checkStatus( $status ) {

        switch ( $status ) {
            case Constants_TranslationStatus::STATUS_TRANSLATED:
            case Constants_TranslationStatus::STATUS_APPROVED:
            case Constants_TranslationStatus::STATUS_APPROVED2:
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
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     * @throws \Exceptions\NotFoundException
     */
    protected function _getContexts() {

        //Get contexts
        $segmentsList = ( new Segments_SegmentDao )->setCacheTTL( 60 * 60 * 24 )->getContextAndSegmentByIDs(
                [
                        'id_before'  => $this->id_before,
                        'id_segment' => $this->id_segment,
                        'id_after'   => $this->id_after
                ]
        );

        $this->featureSet->filter( 'rewriteContributionContexts', $segmentsList, $this->__postInput );

        if ( isset( $segmentsList->id_before->segment ) ) {
            $this->context_before = $this->filter->fromLayer0ToLayer1( $segmentsList->id_before->segment );
        }

        if ( isset( $segmentsList->id_after->segment ) ) {
            $this->context_after = $this->filter->fromLayer0ToLayer1( $segmentsList->id_after->segment );
        }
    }

    /**
     * @return int|mixed
     * @throws Exception
     */
    public function doAction() {
        $this->checkData();
        $this->readLoginInfo();
        $this->initVersionHandler();
        $this->_getContexts();

        //check tag mismatch
        //get original source segment, first
        $dao           = new \Segments_SegmentDao( \Database::obtain() );
        $this->segment = $dao->getById( $this->id_segment );

        $segment     = $this->filter->fromLayer0ToLayer2( $this->__postInput[ 'segment' ] );
        $translation = $this->filter->fromLayer0ToLayer2( $this->__postInput[ 'translation' ] );

        $check = new QA( $segment, $translation );
        $check->setChunk( $this->chunk );
        $check->setFeatureSet( $this->featureSet );
        $check->setSourceSegLang( $this->chunk->source );
        $check->setTargetSegLang( $this->chunk->target );
        $check->setIdSegment( $this->id_segment );

        if ( isset( $this->__postInput[ 'characters_counter' ] ) ) {
            $check->setCharactersCount( $this->__postInput[ 'characters_counter' ] );
        }

        $check->performConsistencyCheck();

        if ( $check->thereAreWarnings() ) {
            $err_json    = $check->getWarningsJSON();
            $translation = $this->filter->fromLayer2ToLayer0( $this->__postInput[ 'translation' ] );
        } else {
            $err_json         = '';
            $targetNormalized = $check->getTrgNormalized();
            $translation      = $this->filter->fromLayer2ToLayer0( $targetNormalized );
        }

        //PATCH TO FIX BOM INSERTIONS
        $translation = Utils::stripBOM( $translation );

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

        $old_suggestion_array = json_decode( $this->suggestion_array );
        $old_suggestion       = ( $this->chosen_suggestion_index !== null ? @$old_suggestion_array[ $this->chosen_suggestion_index - 1 ] : null );

        $new_translation                         = new Translations_SegmentTranslationStruct();
        $new_translation->id_segment             = $this->id_segment;
        $new_translation->id_job                 = $this->id_job;
        $new_translation->status                 = $this->status;
        $new_translation->segment_hash           = $this->segment->segment_hash;
        $new_translation->translation            = $translation;
        $new_translation->serialized_errors_list = $err_json;
        $new_translation->suggestions_array      = ( $this->chosen_suggestion_index !== null ? $this->suggestion_array : $old_translation->suggestions_array );
        $new_translation->suggestion_position    = ( $this->chosen_suggestion_index !== null ? $this->chosen_suggestion_index : $old_translation->suggestion_position );
        $new_translation->warning                = $check->thereAreWarnings();
        $new_translation->translation_date       = date( "Y-m-d H:i:s" );
        $new_translation->suggestion             = ( ( !empty( $old_suggestion ) ) ? $old_suggestion->translation : $old_translation->suggestion );
        $new_translation->suggestion_source      = $old_translation->suggestion_source;
        $new_translation->suggestion_match       = $old_translation->suggestion_match;

        // update suggestion
        if ( $this->canUpdateSuggestion( $new_translation, $old_translation, $old_suggestion ) ) {
            $new_translation->suggestion = $old_suggestion->translation;

            // update suggestion match
            if ( $old_suggestion->match == "MT" ) {
                // case 1. is MT
                $new_translation->suggestion_match  = 85;
                $new_translation->suggestion_source = 'MT';
            } elseif ( $old_suggestion->match == 'NO_MATCH' ) {
                // case 2. no match
                $new_translation->suggestion_source = 'NO_MATCH';
            } else {
                // case 3. otherwise is TM
                $new_translation->suggestion_match  = $old_suggestion->match;
                $new_translation->suggestion_source = 'TM';
            }
        }

        // time_to_edit should be increased only if the translation was changed
        $new_translation->time_to_edit = 0;
        if ( false === Utils::stringsAreEqual( $new_translation->translation, $old_translation->translation ) ) {
            $new_translation->time_to_edit = $this->time_to_edit;
        }

        $this->_validateSegmentTranslationChange( $new_translation, $old_translation );

        /**
         * Update Time to Edit and
         *
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

        // if saveVersionAndIncrement() return true it means that it was persisted a new version of the parent segment
        if ( $this->VersionsHandler !== null ) {
            $this->VersionsHandler->saveVersionAndIncrement( $new_translation, $old_translation );
        }

        /**
         * when the status of the translation changes, the auto propagation flag
         * must be removed
         */
        if ( $new_translation->translation != $old_translation->translation ||
                $this->status == Constants_TranslationStatus::STATUS_TRANSLATED ||
                $this->status == Constants_TranslationStatus::STATUS_APPROVED ||
                $this->status == Constants_TranslationStatus::STATUS_APPROVED2
        ) {
            $new_translation->autopropagated_from = 'NULL';
        }

        /**
         * Translation is inserted here.
         */
        try {
            CatUtils::addSegmentTranslation( $new_translation, self::isRevision() );
        } catch ( ControllerReturnException $e ) {
            $db->rollback();
            throw  $e;
        }

        /**
         * @see ProjectCompletion
         */
        $this->featureSet->run( 'postAddSegmentTranslation', [
                'chunk'       => $this->chunk,
                'is_review'   => $this->isRevision(),
                'logged_user' => $this->user
        ] );

        $propagationTotal = [
                'totals'                   => [],
                'propagated_ids'           => [],
                'segments_for_propagation' => []
        ];

        if ( $this->propagate && in_array( $this->status, [
                        Constants_TranslationStatus::STATUS_TRANSLATED,
                        Constants_TranslationStatus::STATUS_APPROVED,
                        Constants_TranslationStatus::STATUS_APPROVED2,
                        Constants_TranslationStatus::STATUS_REJECTED
                ] )
        ) {
            //propagate translations
            $TPropagation                             = new Translations_SegmentTranslationStruct();
            $TPropagation[ 'status' ]                 = $this->status;
            $TPropagation[ 'id_job' ]                 = $this->id_job;
            $TPropagation[ 'translation' ]            = $translation;
            $TPropagation[ 'autopropagated_from' ]    = $this->id_segment;
            $TPropagation[ 'serialized_errors_list' ] = $err_json;
            $TPropagation[ 'warning' ]                = $check->thereAreWarnings();
            $TPropagation[ 'segment_hash' ]           = $old_translation[ 'segment_hash' ];
            $TPropagation[ 'translation_date' ]       = Utils::mysqlTimestamp( time() );
            $TPropagation[ 'match_type' ]             = $old_translation[ 'match_type' ];
            $TPropagation[ 'locked' ]                 = $old_translation[ 'locked' ];

            try {
                if ( $this->VersionsHandler !== null ) {
                    $propagationTotal = Translations_SegmentTranslationDao::propagateTranslation(
                            $TPropagation,
                            $this->chunk,
                            $this->id_segment,
                            $this->project,
                            $this->VersionsHandler
                    );
                }

            } catch ( Exception $e ) {
                $msg = $e->getMessage() . "\n\n" . $e->getTraceAsString();
                Log::doJsonLog( $msg );
                Utils::sendErrMailReport( $msg );
                $db->rollback();
                throw new ControllerReturnException( $e->getMessage(), $e->getCode(), $e );
            }
        }

        if ( $this->isSplittedSegment() ) {

            /* put the split inside the transaction if they are present */
            $translationStruct             = TranslationsSplit_SplitStruct::getStruct();
            $translationStruct->id_segment = $this->id_segment;
            $translationStruct->id_job     = $this->id_job;

            $translationStruct->target_chunk_lengths = [
                    'len'      => $this->split_chunk_lengths,
                    'statuses' => $this->split_statuses
            ];
            $translationDao                          = new TranslationsSplit_SplitDAO( Database::obtain() );
            $result                                  = $translationDao->atomicUpdate( $translationStruct );

        }


        /*
         * Hooked by TranslationVersions which manage translation versions
         *
         * This is also the init handler of all R1/R2 handling and Qr score calculation by
         *  *** translationVersionSaved *** hook in TranslationEventsHandler.php hooked by AbstractRevisionFeature
         */
        if ( $this->VersionsHandler !== null ) {
            $this->VersionsHandler->storeTranslationEvent( [
                    'translation'      => $new_translation,
                    'old_translation'  => $old_translation,
                    'propagation'      => $propagationTotal,
                    'chunk'            => $this->chunk,
                    'segment'          => $this->segment,
                    'user'             => $this->user,
                    'source_page_code' => ReviewUtils::revisionNumberToSourcePage( $this->revisionNumber ),
                    'features'         => $this->featureSet,
                    'project'          => $this->project
            ] );
        }

        //COMMIT THE TRANSACTION
        try {
            $db->commit();
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => -101, "message" => $e->getMessage() ];
            Log::doJsonLog( "Lock: Transaction Aborted. " . $e->getMessage() );
            $db->rollback();

            return $e->getCode();
        }

        $newTotals = WordCountStruct::loadFromJob( $this->chunk );

        $job_stats = CatUtils::getFastStatsForJob( $newTotals );
        $job_stats[ 'analysis_complete' ] = (
                $this->project[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE ||
                $this->project[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE
        );

        $file_stats = [];

        $this->result[ 'stats' ]       = $job_stats;
        $this->result[ 'file_stats' ]  = $file_stats;
        $this->result[ 'code' ]        = 1;
        $this->result[ 'data' ]        = "OK";
        $this->result[ 'version' ]     = date_create( $new_translation[ 'translation_date' ] )->getTimestamp();
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

        try {

            $this->featureSet->run( 'setTranslationCommitted', [
                    'translation'      => $new_translation,
                    'old_translation'  => $old_translation,
                    'propagated_ids'   => isset( $propagationTotal[ 'segments_for_propagation' ][ 'propagated_ids' ] ) ? $propagationTotal[ 'segments_for_propagation' ][ 'propagated_ids' ] : null,
                    'chunk'            => $this->chunk,
                    'segment'          => $this->segment,
                    'user'             => $this->user,
                    'source_page_code' => ReviewUtils::revisionNumberToSourcePage( $this->revisionNumber )
            ] );

        } catch ( Exception $e ) {
            Log::doJsonLog( "Exception in setTranslationCommitted callback . " . $e->getMessage() . "\n" . $e->getTraceAsString() );
        }

        try {
            $this->result = $this->featureSet->filter( 'filterSetTranslationResult', $this->result, [
                    'translation'     => $new_translation,
                    'old_translation' => $old_translation,
                    'propagated_ids'  => isset( $propagationTotal[ 'segments_for_propagation' ][ 'propagated_ids' ] ) ? $propagationTotal[ 'segments_for_propagation' ][ 'propagated_ids' ] : null,
                    'chunk'           => $this->chunk,
                    'segment'         => $this->segment
            ] );
        } catch ( Exception $e ) {
            Log::doJsonLog( "Exception in filterSetTranslationResult callback . " . $e->getMessage() . "\n" . $e->getTraceAsString() );
        }

        //EVERY time an user changes a row in his job when the job is completed,
        // a query to do the update is executed...
        // Avoid this by setting a key on redis with a reasonable TTL
        $redisHandler = new RedisHandler();
        $job_status   = $redisHandler->getConnection()->get( 'job_completeness:' . $this->id_job );
        if (
                (
                        (
                                $job_stats[ Projects_MetadataDao::WORD_COUNT_RAW ][ 'draft' ] +
                                $job_stats[ Projects_MetadataDao::WORD_COUNT_RAW ][ 'new' ] == 0
                        )
                        && empty( $job_status )
                )
        ) {
            $redisHandler->getConnection()->setex( 'job_completeness:' . $this->id_job, 60 * 60 * 24 * 15, true ); //15 days

            try {
                $update_completed = Jobs_JobDao::setJobComplete( $this->chunk );
            } catch ( Exception $ignore ) {
            }

            if ( empty( $update_completed ) ) {
                $msg = "\n\n Error setJobCompleteness \n\n " . var_export( $_POST, true );
                $redisHandler->getConnection()->del( 'job_completeness:' . $this->id_job );
                Log::doJsonLog( $msg );
                Utils::sendErrMailReport( $msg );
            }

        }

        $this->result[ 'propagation' ] = $propagationTotal;
        $this->evalSetContribution( $new_translation, $old_translation );
    }

    /**
     * Update suggestion only if:
     *
     * 1) the new state is one of these:
     *      - NEW
     *      - DRAFT
     *      - TRANSLATED
     *
     * 2) the old state is one of these:
     *      - NEW
     *      - DRAFT
     *
     * @param Translations_SegmentTranslationStruct $new_translation
     * @param Translations_SegmentTranslationStruct $old_translation
     * @param null                                  $old_suggestion
     *
     * @return bool
     */
    private function canUpdateSuggestion( Translations_SegmentTranslationStruct $new_translation, Translations_SegmentTranslationStruct $old_translation, $old_suggestion = null ) {
        if ( $old_suggestion === null ) {
            return false;
        }

        $allowedStatuses = [
                Constants_TranslationStatus::STATUS_NEW,
                Constants_TranslationStatus::STATUS_DRAFT,
                Constants_TranslationStatus::STATUS_TRANSLATED,
        ];

        if ( !in_array( $new_translation->status, $allowedStatuses ) ) {
            return false;
        }

        if ( !in_array( $old_translation->status, $allowedStatuses ) ) {
            return false;
        }

        if (
                !empty( $old_suggestion ) and
                isset( $old_suggestion->translation ) and
                isset( $old_suggestion->match ) and
                isset( $old_suggestion->created_by )
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return int|mixed
     */
    protected function checkData() {
        try {
            $this->_checkData();
        } catch ( Exception $e ) {

            if ( $e->getCode() == -1 ) {
                Utils::sendErrMailReport( $e->getMessage() );
            }

            Log::doJsonLog( $e->getMessage() );

            return $e->getCode();

        }
    }

    /**
     * @return Translations_SegmentTranslationStruct
     * @throws ControllerReturnException
     */
    protected function _getOldTranslation() {
        $old_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $this->id_segment, $this->id_job );

        if ( empty( $old_translation ) ) {
            $old_translation = new Translations_SegmentTranslationStruct();
        } // $old_translation if `false` sometimes


        // If volume analysis is not enabled and no translation rows exists, create the row
        if ( !INIT::$VOLUME_ANALYSIS_ENABLED && empty( $old_translation[ 'status' ] ) ) {
            $translation             = new Translations_SegmentTranslationStruct();
            $translation->id_segment = (int)$this->id_segment;
            $translation->id_job     = (int)$this->id_job;
            $translation->status     = Constants_TranslationStatus::STATUS_NEW;

            $translation->segment_hash        = $this->segment[ 'segment_hash' ];
            $translation->translation         = $this->segment[ 'segment' ];
            $translation->standard_word_count = $this->segment[ 'raw_word_count' ];

            $translation->serialized_errors_list = '';
            $translation->suggestion_position    = 0;
            $translation->warning                = false;
            $translation->translation_date       = date( "Y-m-d H:i:s" );

            try {
                CatUtils::addSegmentTranslation( $translation, self::isRevision() );
            } catch ( ControllerReturnException $e ) {
                Database::obtain()->rollback();
                throw $e;
            }

            $old_translation = $translation;
        }

        return $old_translation;
    }

    /**
     * This method returns a representation of the saved translation which
     * should be as much as possible compliant with the future API v2.
     *
     * @param $saved_translation
     *
     * @return array
     * @throws Exception
     */
    private function getTranslationObject( $saved_translation ) {
        return [
                'version_number' => @$saved_translation[ 'version_number' ],
                'sid'            => $saved_translation[ 'id_segment' ],
                'translation'    => $this->filter->fromLayer0ToLayer2( $saved_translation[ 'translation' ] ),
                'status'         => $saved_translation[ 'status' ]

        ];
    }

    private function recountJobTotals( $old_status ) {
        $old_wStruct = new WordCountStruct();
        $old_wStruct->setIdJob( $this->id_job );
        $old_wStruct->setJobPassword( $this->password );
        $old_wStruct->setNewWords( $this->chunk[ 'new_words' ] );
        $old_wStruct->setDraftWords( $this->chunk[ 'draft_words' ] );
        $old_wStruct->setTranslatedWords( $this->chunk[ 'translated_words' ] );
        $old_wStruct->setApprovedWords( $this->chunk[ 'approved_words' ] );
        $old_wStruct->setRejectedWords( $this->chunk[ 'rejected_words' ] );

        $old_wStruct->setIdSegment( $this->id_segment );

        //redundant, this is made into CounterModel::updateDB
        $old_wStruct->setOldStatus( $old_status );
        $old_wStruct->setNewStatus( $this->status );

        return $old_wStruct;
    }

    //TODO: put this method into Job model and use Segment object
    private function updateJobPEE( array $old_translation, array $new_translation ) {

        //update total time to edit
        $tte = $old_translation[ 'time_to_edit' ];
        if ( !self::isRevision() ) {
            if ( false === Utils::stringsAreEqual( $new_translation[ 'translation' ], $old_translation[ 'translation' ] ) ) {
                $tte += $new_translation[ 'time_to_edit' ];
            }
        }

        $segmentRawWordCount  = $this->segment->raw_word_count;
        $editLogSegmentStruct = new EditLogSegmentClientStruct(
                [
                        'suggestion'     => $old_translation[ 'suggestion' ],
                        'translation'    => $old_translation[ 'translation' ],
                        'raw_word_count' => $segmentRawWordCount,
                        'time_to_edit'   => $old_translation[ 'time_to_edit' ] + $new_translation[ 'time_to_edit' ]
                ]
        );

        $oldSegmentStatus               = clone $editLogSegmentStruct;
        $oldSegmentStatus->time_to_edit = $old_translation[ 'time_to_edit' ];

        $oldPEE          = $editLogSegmentStruct->getPEE();
        $oldPee_weighted = $oldPEE * $segmentRawWordCount;

        $editLogSegmentStruct->translation    = $new_translation[ 'translation' ];
        $editLogSegmentStruct->pe_effort_perc = null;

        $newPEE          = $editLogSegmentStruct->getPEE();
        $newPee_weighted = $newPEE * $segmentRawWordCount;

        if ( $editLogSegmentStruct->isValidForEditLog() ) {
            //if the segment was not valid for editlog, and now it is, then just add the weighted pee
            if ( !$oldSegmentStatus->isValidForEditLog() ) {
                $newTotalJobPee = ( $this->chunk[ 'avg_post_editing_effort' ] + $newPee_weighted );
            } //otherwise, evaluate it normally
            else {
                $newTotalJobPee = ( $this->chunk[ 'avg_post_editing_effort' ] - $oldPee_weighted + $newPee_weighted );
            }

            Jobs_JobDao::updateFields(

                    [ 'avg_post_editing_effort' => $newTotalJobPee, 'total_time_to_edit' => $tte ],
                    [
                            'id'       => $this->id_job,
                            'password' => $this->password
                    ] );

        } //segment was valid but now it is no more valid
        elseif ( $oldSegmentStatus->isValidForEditLog() ) {
            $newTotalJobPee = ( $this->chunk[ 'avg_post_editing_effort' ] - $oldPee_weighted );

            Jobs_JobDao::updateFields(
                    [ 'avg_post_editing_effort' => $newTotalJobPee, 'total_time_to_edit' => $tte ],
                    [
                            'id'       => $this->id_job,
                            'password' => $this->password
                    ] );
        } else {
            Jobs_JobDao::updateFields(
                    [ 'total_time_to_edit' => $tte ],
                    [
                            'id'       => $this->id_job,
                            'password' => $this->password
                    ] );
        }

    }

    /**
     * init VersionHandler
     */
    private function initVersionHandler() {

        // fix null pointer error
        if (
                $this->chunk !== null and
                $this->id_segment !== null and
                $this->user !== null and
                $this->project !== null
        ) {
            $this->VersionsHandler = TranslationVersions::getVersionHandlerNewInstance( $this->chunk, $this->id_segment, $this->user, $this->project );
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
                $new_translation->isTranslationStatus() && !$old_translation->isTranslationStatus() &&
                !$old_translation->isRejected() // this handle the case of rejection/rebut behaviour. A status change already happened
        ) {
            Database::obtain()->rollback();
            $msg                        = "Status change not allowed with identical translation on segment {$old_translation->id_segment}.";
            $this->result[ 'errors' ][] = [ "code" => -2000, "message" => $msg ];
            throw new ControllerReturnException( $msg, -1 );
        }
    }

    /**
     * @param $old_translation
     *
     * @return string
     */
    private function statusOrDefault( $old_translation ) {
        if ( empty( $old_translation[ 'status' ] ) ) {
            return Constants_TranslationStatus::STATUS_NEW;
        } else {
            return $old_translation[ 'status' ];
        }
    }

    /**
     * @param $_Translation
     * @param $old_translation
     *
     * @throws Exception
     * @throws NotFoundException
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     */
    private function evalSetContribution( $_Translation, $old_translation ) {
        if ( in_array( $this->status, [
                Constants_TranslationStatus::STATUS_DRAFT,
                Constants_TranslationStatus::STATUS_NEW
        ] ) ) {
            return;
        }

        $skip_set_contribution = false;
        $skip_set_contribution = $this->featureSet->filter( 'filter_skip_set_contribution',
                $skip_set_contribution, $_Translation, $old_translation
        );

        if ( $skip_set_contribution ) {
            return;
        }

        $ownerUid   = Jobs_JobDao::getOwnerUid( $this->id_job, $this->password );
        $filesParts = ( new FilesPartsDao() )->getBySegmentId( $this->id_segment );

        /**
         * Set the new contribution in queue
         */
        $contributionStruct               = new ContributionSetStruct();
        $contributionStruct->fromRevision = self::isRevision();
        $contributionStruct->id_file      = ( $filesParts !== null ) ? $filesParts->id_file : null;
        $contributionStruct->id_job       = $this->id_job;
        $contributionStruct->job_password = $this->password;
        $contributionStruct->id_segment   = $this->id_segment;
        $contributionStruct->segment      = $this->filter->fromLayer0ToLayer1( $this->segment[ 'segment' ] );
        $contributionStruct->translation  = $this->filter->fromLayer0ToLayer1( $_Translation[ 'translation' ] );
        $contributionStruct->api_key              = INIT::$MYMEMORY_API_KEY;
        $contributionStruct->uid          = ( $ownerUid !== null ) ? $ownerUid : 0;;
        $contributionStruct->oldTranslationStatus = $old_translation[ 'status' ];
        $contributionStruct->oldSegment           = $this->filter->fromLayer0ToLayer1( $this->segment[ 'segment' ] ); //
        $contributionStruct->oldTranslation       = $this->filter->fromLayer0ToLayer1( $old_translation[ 'translation' ] );

        /*
         * This parameter is not used by the application, but we use it to for information integrity
         *
         * User choice for propagation.
         *
         * Propagate is false IF:
         * - the segment has not repetitions
         * - the segment has some one or more repetitions and the user choose to not propagate it
         * - the segment is already autopropagated ( marked as autopropagated_from ) and it hasn't been changed
         *
         * Propagate is true ( vice versa ) IF:
         * - the segment has one or more repetitions and it's status is NEW/DRAFT
         * - the segment has one or more repetitions and the user choose to propagate it
         * - the segment has one or more repetitions, it is not modified, it doesn't have translation conflicts and a change status is requested
         */
        $contributionStruct->propagationRequest = $this->propagate;
        $contributionStruct->id_mt              = $this->chunk->id_mt_engine;

        $contributionStruct->context_after  = $this->context_after;
        $contributionStruct->context_before = $this->context_before;

        $this->featureSet->filter(
                'filterContributionStructOnSetTranslation',
                $contributionStruct,
                $this->project,
                $this->segment
        );

        //assert there is not an exception by following the flow
        Set::contribution( $contributionStruct );

        if ( $contributionStruct->id_mt > 1 ) {
            $contributionStruct = $this->featureSet->filter( 'filterSetContributionMT', null, $contributionStruct, $this->project );
            Set::contributionMT( $contributionStruct );
        }

    }
}
