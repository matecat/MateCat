<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use CatUtils;
use Chunks_ChunkDao;
use Constants_Engines;
use Constants_JobStatus;
use Constants_ProjectStatus;
use Constants_TranslationStatus;
use Contribution\ContributionSetStruct;
use Contribution\Set;
use Database;
use EditLog\EditLogSegmentStruct;
use Exception;
use Exceptions\ControllerReturnException;
use Exceptions\NotFoundException;
use Features\ReviewExtended\ReviewUtils;
use Features\TranslationVersions;
use Features\TranslationVersions\Handlers\TranslationVersionsHandler;
use Files\FilesPartsDao;
use INIT;
use InvalidArgumentException;
use Jobs_JobDao;
use Jobs_JobStruct;
use Klein\Response;
use LQA\QA;
use Matecat\SubFiltering\MateCatFilter;
use Projects_MetadataDao;
use RedisHandler;
use RuntimeException;
use Segments_SegmentDao;
use Segments_SegmentOriginalDataDao;
use Segments_SegmentStruct;
use Translations_SegmentTranslationDao;
use Translations_SegmentTranslationStruct;
use TranslationsSplit_SplitDAO;
use TranslationsSplit_SplitStruct;
use Utils;
use WordCount\WordCountStruct;

class SetTranslationController extends KleinController {

    /**
     * @var array
     */
    protected array $data;

    protected $id_job;

    protected $password;

    protected $received_password;

    /**
     * @var Jobs_JobStruct
     */
    protected $chunk;

    /**
     * @var Segments_SegmentStruct
     */
    protected $segment;  // this comes from DAO

    /**
     * @var MateCatFilter
     */
    protected $filter;

    /**
     * @var TranslationVersionsHandler
     */
    protected $VersionsHandler;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function translate(): Response
    {
        try {
            $this->data = $this->validateTheRequest();
            $this->checkData();
            $this->initVersionHandler();
            $this->getContexts();

            //check tag mismatch
            //get original source segment, first
            $dao                   = new Segments_SegmentDao( \Database::obtain() );
            $this->data['segment'] = $dao->getById( $this->data['id_segment'] );

            $segment     = $this->filter->fromLayer0ToLayer2( $this->data[ 'segment' ][ 'segment' ] );
            $translation = $this->filter->fromLayer0ToLayer2( $this->data[ 'translation' ] );

            $check = new QA( $segment, $translation );
            $check->setChunk( $this->data['chunk'] );
            $check->setFeatureSet( $this->featureSet );
            $check->setSourceSegLang( $this->data['chunk']->source );
            $check->setTargetSegLang( $this->data['chunk']->target );
            $check->setIdSegment( $this->data['id_segment'] );

            if ( isset( $this->data[ 'characters_counter' ] ) ) {
                $check->setCharactersCount( $this->data[ 'characters_counter' ] );
            }

            $check->performConsistencyCheck();

            if ( $check->thereAreWarnings() ) {
                $err_json    = $check->getWarningsJSON();
                $translation = $this->filter->fromLayer2ToLayer0( $this->data[ 'translation' ] );
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

            $old_translation = $this->getOldTranslation();

            $old_suggestion_array = json_decode( $this->data['suggestion_array'] );
            $old_suggestion       = ( $this->data['chosen_suggestion_index'] !== null ? @$old_suggestion_array[ $this->data['chosen_suggestion_index'] - 1 ] : null );

            $new_translation                         = new Translations_SegmentTranslationStruct();
            $new_translation->id_segment             = $this->data['id_segment'];
            $new_translation->id_job                 = $this->data['id_job'];
            $new_translation->status                 = $this->data['status'];
            $new_translation->segment_hash           = $this->data['segment']->segment_hash;
            $new_translation->translation            = $translation;
            $new_translation->serialized_errors_list = $err_json;
            $new_translation->suggestions_array      = ( $this->data['chosen_suggestion_index'] !== null ? $this->data['suggestion_array'] : $old_translation->suggestions_array );
            $new_translation->suggestion_position    = ( $this->data['chosen_suggestion_index'] !== null ? $this->data['chosen_suggestion_index'] : $old_translation->suggestion_position );
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
                    $new_translation->suggestion_source = Constants_Engines::MT;
                } elseif ( $old_suggestion->match == 'NO_MATCH' ) {
                    // case 2. no match
                    $new_translation->suggestion_source = 'NO_MATCH';
                } else {
                    // case 3. otherwise is TM
                    $new_translation->suggestion_match  = $old_suggestion->match;
                    $new_translation->suggestion_source = Constants_Engines::TM;
                }
            }

            $new_translation->time_to_edit = $this->data['time_to_edit'];

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
            if ( $new_translation->translation != $old_translation->translation or
                $this->data['status'] == Constants_TranslationStatus::STATUS_TRANSLATED or
                $this->data['status'] == Constants_TranslationStatus::STATUS_APPROVED or
                $this->data['status'] == Constants_TranslationStatus::STATUS_APPROVED2
            ) {
                $new_translation->autopropagated_from = 'NULL';
            }

            /**
             * Translation is inserted here.
             */
            try {
                CatUtils::addSegmentTranslation( $new_translation, $this->isRevision() );
            } catch ( ControllerReturnException $e ) {
                $db->rollback();
                throw new RuntimeException($e->getMessage());
            }

            /**
             * @see ProjectCompletion
             */
            $this->featureSet->run( 'postAddSegmentTranslation', [
                'chunk'       => $this->data['chunk'],
                'is_review'   => $this->isRevision(),
                'logged_user' => $this->user
            ] );

            $propagationTotal = [
                'totals'                   => [],
                'propagated_ids'           => [],
                'segments_for_propagation' => []
            ];

            if ( $this->data['propagate'] && in_array( $this->data['status'], [
                    Constants_TranslationStatus::STATUS_TRANSLATED,
                    Constants_TranslationStatus::STATUS_APPROVED,
                    Constants_TranslationStatus::STATUS_APPROVED2,
                    Constants_TranslationStatus::STATUS_REJECTED
                ] )
            ) {
                //propagate translations
                $TPropagation                             = new Translations_SegmentTranslationStruct();
                $TPropagation[ 'status' ]                 = $this->data['status'];
                $TPropagation[ 'id_job' ]                 = $this->data['id_job'];
                $TPropagation[ 'translation' ]            = $translation;
                $TPropagation[ 'autopropagated_from' ]    = $this->data['id_segment'];
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
                            $this->data['chunk'],
                            $this->data['id_segment'],
                            $this->data['project'],
                        );
                    }

                } catch ( Exception $e ) {
                    $msg = $e->getMessage() . "\n\n" . $e->getTraceAsString();
                    $this->log( $msg );
                    Utils::sendErrMailReport( $msg );
                    $db->rollback();
                    throw new RuntimeException( $e->getMessage(), $e->getCode() );
                }
            }

            if ( $this->isSplittedSegment() ) {
                /* put the split inside the transaction if they are present */
                $translationStruct             = TranslationsSplit_SplitStruct::getStruct();
                $translationStruct->id_segment = $this->data['id_segment'];
                $translationStruct->id_job     = $this->data['id_job'];

                $translationStruct->target_chunk_lengths = [
                    'len'      => $this->data['split_chunk_lengths'],
                    'statuses' => $this->data['split_statuses']
                ];

                $translationDao = new TranslationsSplit_SplitDAO( Database::obtain() );
                $translationDao->atomicUpdate( $translationStruct );
            }

            //COMMIT THE TRANSACTION
            try {

                /*
                 * Hooked by TranslationVersions which manage translation versions
                 *
                 * This is also the init handler of all R1/R2 handling and Qr score calculation by
                 * by TranslationEventsHandler and BatchReviewProcessor
                 */
                if ( $this->VersionsHandler !== null ) {
                    $this->VersionsHandler->storeTranslationEvent( [
                        'translation'      => $new_translation,
                        'old_translation'  => $old_translation,
                        'propagation'      => $propagationTotal,
                        'chunk'            => $this->data['chunk'],
                        'segment'          => $this->data['segment'],
                        'user'             => $this->user,
                        'source_page_code' => ReviewUtils::revisionNumberToSourcePage( $this->data['revisionNumber'] ),
                        'features'         => $this->featureSet,
                        'project'          => $this->data['project']
                    ] );
                }

                $db->commit();

            } catch ( Exception $e ) {
                $this->log( "Lock: Transaction Aborted. " . $e->getMessage() );
                $db->rollback();

                throw new RuntimeException($e->getMessage());
            }

            $newTotals = WordCountStruct::loadFromJob( $this->data['chunk'] );

            $job_stats                        = CatUtils::getFastStatsForJob( $newTotals );
            $job_stats[ 'analysis_complete' ] = (
                $this->data['project'][ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE or
                $this->data['project'][ 'status_analysis' ] == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE
            );

            $file_stats = [];
            $result = [];

            $result[ 'stats' ]       = $job_stats;
            $result[ 'file_stats' ]  = $file_stats;
            $result[ 'code' ]        = 1;
            $result[ 'data' ]        = "OK";
            $result[ 'version' ]     = date_create( $new_translation[ 'translation_date' ] )->getTimestamp();
            $result[ 'translation' ] = $this->getTranslationObject( $new_translation );

            /* FIXME: added for code compatibility with front-end. Remove. */
            $_warn   = $check->getWarnings();
            $warning = $_warn[ 0 ];
            /* */

            $result[ 'warning' ][ 'cod' ] = $warning->outcome;
            if ( $warning->outcome > 0 ) {
                $result[ 'warning' ][ 'id' ] = $this->data['id_segment'];
            } else {
                $result[ 'warning' ][ 'id' ] = 0;
            }

            try {
                $this->featureSet->run( 'setTranslationCommitted', [
                    'translation'      => $new_translation,
                    'old_translation'  => $old_translation,
                    'propagated_ids'   => isset( $propagationTotal[ 'segments_for_propagation' ][ 'propagated_ids' ] ) ? $propagationTotal[ 'segments_for_propagation' ][ 'propagated_ids' ] : null,
                    'chunk'            => $this->data['chunk'],
                    'segment'          => $this->data['segment'],
                    'user'             => $this->user,
                    'source_page_code' => ReviewUtils::revisionNumberToSourcePage( $this->data['revisionNumber'] )
                ] );

            } catch ( Exception $e ) {
                $this->log( "Exception in setTranslationCommitted callback . " . $e->getMessage() . "\n" . $e->getTraceAsString() );
                throw new RuntimeException($e->getMessage());
            }

            try {
                $result = $this->featureSet->filter( 'filterSetTranslationResult', $result, [
                    'translation'     => $new_translation,
                    'old_translation' => $old_translation,
                    'propagated_ids'  => isset( $propagationTotal[ 'segments_for_propagation' ][ 'propagated_ids' ] ) ? $propagationTotal[ 'segments_for_propagation' ][ 'propagated_ids' ] : null,
                    'chunk'           => $this->data['chunk'],
                    'segment'         => $this->data['segment']
                ] );
            } catch ( Exception $e ) {
                $this->log( "Exception in filterSetTranslationResult callback . " . $e->getMessage() . "\n" . $e->getTraceAsString() );
                throw new RuntimeException($e->getMessage());
            }

            //EVERY time an user changes a row in his job when the job is completed,
            // a query to do the update is executed...
            // Avoid this by setting a key on redis with a reasonable TTL
            $redisHandler = new RedisHandler();
            $job_status   = $redisHandler->getConnection()->get( 'job_completeness:' . $this->data['id_job'] );
            if (
            (
                (
                    $job_stats[ Projects_MetadataDao::WORD_COUNT_RAW ][ 'draft' ] +
                    $job_stats[ Projects_MetadataDao::WORD_COUNT_RAW ][ 'new' ] == 0
                )
                and empty( $job_status )
            )
            ) {
                $redisHandler->getConnection()->setex( 'job_completeness:' . $this->data['id_job'], 60 * 60 * 24 * 15, true ); //15 days

                try {
                    $update_completed = Jobs_JobDao::setJobComplete( $this->data['chunk'] );
                } catch ( Exception $ignore ) {
                }

                if ( empty( $update_completed ) ) {
                    $msg = "\n\n Error setJobCompleteness \n\n " . var_export( $_POST, true );
                    $redisHandler->getConnection()->del( 'job_completeness:' . $this->data['id_job'] );
                    $this->log( $msg );
                    Utils::sendErrMailReport( $msg );
                }
            }

            $result[ 'propagation' ] = $propagationTotal;
            $this->evalSetContribution( $new_translation, $old_translation );

            return $this->response->json($result);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest()
    {
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH  ] );
        $received_password = filter_var( $this->request->param( 'current_password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH  ] );
        $propagate = filter_var( $this->request->param( 'propagate' ), FILTER_VALIDATE_BOOLEAN, [ 'flags' => FILTER_NULL_ON_FAILURE ] );
        $id_segment = filter_var( $this->request->param( 'id_segment' ), FILTER_SANITIZE_NUMBER_INT );
        $time_to_edit = filter_var( $this->request->param( 'time_to_edit' ), FILTER_SANITIZE_NUMBER_INT );
        $id_translator = filter_var( $this->request->param( 'id_translator' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH  ] );
        $translation = filter_var( $this->request->param( 'translation' ), FILTER_UNSAFE_RAW );
        $segment = filter_var( $this->request->param( 'segment' ), FILTER_UNSAFE_RAW );
        $version = filter_var( $this->request->param( 'version' ), FILTER_SANITIZE_NUMBER_INT );
        $chosen_suggestion_index = filter_var( $this->request->param( 'chosen_suggestion_index' ), FILTER_SANITIZE_NUMBER_INT );
        $suggestion_array = filter_var( $this->request->param( 'suggestion_array' ), FILTER_UNSAFE_RAW );
        $status = filter_var( $this->request->param( 'status' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH  ] );
        $splitStatuses = filter_var( $this->request->param( 'splitStatuses' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH  ] );
        $context_before = filter_var( $this->request->param( 'context_before' ), FILTER_UNSAFE_RAW );
        $context_after = filter_var( $this->request->param( 'context_after' ), FILTER_UNSAFE_RAW );
        $id_before = filter_var( $this->request->param( 'id_before' ), FILTER_SANITIZE_NUMBER_INT );
        $id_after = filter_var( $this->request->param( 'id_after' ), FILTER_SANITIZE_NUMBER_INT );
        $revisionNumber = filter_var( $this->request->param( 'revision_number' ), FILTER_SANITIZE_NUMBER_INT );
        $guess_tag_used = filter_var( $this->request->param( 'guess_tag_used' ), FILTER_VALIDATE_BOOLEAN );
        $characters_counter = filter_var( $this->request->param( 'characters_counter' ), FILTER_SANITIZE_NUMBER_INT );

        /*
         * set by the client, mandatory
         * check propagation flag, if it is null the client not sent it, leave default true, otherwise set the value
         */
        $propagate             = (!is_null($propagate)) ? $propagate : null; /* do nothing */
        $client_target_version = ( empty( $version ) ? '0' : $version );
        $status                = strtoupper( $status );
        $split_statuses        = explode( ",", strtoupper( $splitStatuses ) ); //strtoupper transforms null to ""

        if ( empty( $id_job ) ) {
            throw new InvalidArgumentException("Missing id job", -2);
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException("Missing password", -3);
        }

        //get Job Info, we need only a row of jobs ( split )
        $chunk = Chunks_ChunkDao::getByIdAndPassword( (int)$id_job, $password );
        $this->chunk = $chunk;

        if ( empty( $chunk ) ) {
            throw new NotFoundException("Wrong password", -3);
        }

        //add check for job status archived.
        if ( strtolower( $chunk[ 'status' ] ) == Constants_JobStatus::STATUS_ARCHIVED ) {
            throw new NotFoundException("Job archived", -3);
        }

        //check tag mismatch
        //get original source segment, first
        $dao           = new Segments_SegmentDao( Database::obtain() );
        $this->segment = $dao->getById( $id_segment );

        $this->id_job = $id_job;
        $this->password = $password;
        $this->received_password = $received_password;

        $data = [
            'id_job' => $id_job ,
            'password' => $password ,
            'received_password' => $received_password ,
            'id_segment' => $id_segment ,
            'time_to_edit' => $time_to_edit ,
            'id_translator' => $id_translator ,
            'translation' => $translation ,
            'segment' => $segment ,
            'version' => $version ,
            'chosen_suggestion_index' => $chosen_suggestion_index ,
            'suggestion_array' => $suggestion_array ,
            'splitStatuses' => $splitStatuses ,
            'context_before' => $context_before ,
            'context_after' => $context_after ,
            'id_before' => $id_before ,
            'id_after' => $id_after ,
            'revisionNumber' => (int)$revisionNumber ,
            'guess_tag_used' => $guess_tag_used ,
            'characters_counter' => $characters_counter ,
            'propagate' => $propagate ,
            'client_target_version' => $client_target_version ,
            'status' => $status ,
            'split_statuses' => $split_statuses ,
            'chunk' => $chunk ,
        ];

        $this->log( $data );

        return $data;
    }

    /**
     * @return bool
     */
    private function isSplittedSegment(): bool
    {
        return !empty( $this->data['split_statuses'][ 0 ] ) && !empty( $this->data['split_num'] );
    }

    /**
     * setStatusForSplittedSegment
     *
     * If splitted segments have different statuses, we reset status
     * to draft.
     */
    private function setStatusForSplittedSegment(): void
    {
        if ( count( array_unique( $this->data['split_statuses'] ) ) == 1 ) {
            // IF ALL translation chunks are in the same status,
            // we take the status for the entire segment
            $this->data['status'] = $this->data['split_statuses'][ 0 ];
        } else {
            $this->data['status'] = Constants_TranslationStatus::STATUS_DRAFT;
        }
    }

    /**
     * @throws Exception
     */
    protected function checkData(): void
    {
        $this->data['project'] = $this->data['chunk']->getProject();

        $featureSet = $this->getFeatureSet();
        $featureSet->loadForProject( $this->data['project'] );

        /** @var MateCatFilter $filter */
        $this->filter = MateCatFilter::getInstance( $featureSet, $this->data['chunk']->source, $this->data['chunk']->target, Segments_SegmentOriginalDataDao::getSegmentDataRefMap( $this->data['id_segment'] ) );

        [ $__translation, $this->data['split_chunk_lengths'] ] = CatUtils::parseSegmentSplit( $this->data[ 'translation' ], '', $this->filter );

        if ( is_null( $__translation ) || $__translation === '' ) {
            $this->log( "Empty Translation \n\n" . var_export( $_POST, true ) );
            throw new RuntimeException( "Empty Translation \n\n" . var_export( $_POST, true ), 0 );
        }

        $explodeIdSegment = explode( "-", $this->data['id_segment']);
        $this->data['id_segment'] = $explodeIdSegment[0];
        $this->data['split_num'] = $explodeIdSegment[1];

        if ( empty( $this->data['id_segment'] ) ) {
            throw new Exception("missing id_segment", -1);
        }

        if ( $this->isSplittedSegment() ) {
            $this->setStatusForSplittedSegment();
        }

        $this->checkStatus( $this->data['status'] );
    }

    /**
     * Throws exception if status is not valid.
     *
     * @param $status
     *
     * @throws Exception
     */
    private function checkStatus( $status ): void
    {
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
                $msg = "Error Hack Status \n\n " . var_export( $_POST, true );
                throw new Exception( $msg, -1 );
                break;
        }
    }

    /**
     * @throws Exception
     */
    private function getContexts(): void
    {
        //Get contexts
        $segmentsList = ( new Segments_SegmentDao )->setCacheTTL( 60 * 60 * 24 )->getContextAndSegmentByIDs(
            [
                'id_before'  => $this->data['id_before'],
                'id_segment' => $this->data['id_segment'],
                'id_after'   => $this->data['id_after']
            ]
        );

        $this->featureSet->filter( 'rewriteContributionContexts', $segmentsList, $this->data );

        if ( isset( $segmentsList->id_before->segment ) ) {
            $this->data['context_before'] = $this->filter->fromLayer0ToLayer1( $segmentsList->id_before->segment );
        }

        if ( isset( $segmentsList->id_after->segment ) ) {
            $this->data['context_after'] = $this->filter->fromLayer0ToLayer1( $segmentsList->id_after->segment );
        }
    }

    /**
     * init VersionHandler
     */
    private function initVersionHandler(): void
    {
        // fix null pointer error
        if (
            $this->data['chunk'] !== null and
            $this->data['id_segment'] !== null and
            $this->user !== null and
            $this->data['project'] !== null
        ) {
            $this->VersionsHandler = TranslationVersions::getVersionHandlerNewInstance( $this->data['chunk'], $this->data['id_segment'], $this->user, $this->data['project'] );
        }
    }

    /**
     * @return Translations_SegmentTranslationStruct
     * @throws Exception
     */
    private function getOldTranslation(): ?Translations_SegmentTranslationStruct
    {
        $old_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $this->data['id_segment'], $this->data['id_job'] );

        if ( empty( $old_translation ) ) {
            $old_translation = new Translations_SegmentTranslationStruct();
        } // $old_translation if `false` sometimes


        // If volume analysis is not enabled and no translation rows exists, create the row
        if ( !INIT::$VOLUME_ANALYSIS_ENABLED && empty( $old_translation[ 'status' ] ) ) {
            $translation             = new Translations_SegmentTranslationStruct();
            $translation->id_segment = (int)$this->data['id_segment'];
            $translation->id_job     = (int)$this->data['id_job'];
            $translation->status     = Constants_TranslationStatus::STATUS_NEW;

            $translation->segment_hash        = $this->data['segment'][ 'segment_hash' ];
            $translation->translation         = $this->data['segment'][ 'segment' ];
            $translation->standard_word_count = $this->data['segment'][ 'raw_word_count' ];

            $translation->serialized_errors_list = '';
            $translation->suggestion_position    = 0;
            $translation->warning                = false;
            $translation->translation_date       = date( "Y-m-d H:i:s" );

            try {
                CatUtils::addSegmentTranslation( $translation, $this->isRevision() );
            } catch ( ControllerReturnException $e ) {
                Database::obtain()->rollback();
                throw new RuntimeException($e->getMessage());
            }

            $old_translation = $translation;
        }

        return $old_translation;
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
    private function canUpdateSuggestion(
        Translations_SegmentTranslationStruct $new_translation,
        Translations_SegmentTranslationStruct $old_translation,
        $old_suggestion = null ): bool
    {
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
     * @param array $old_translation
     * @param array $new_translation
     */
    private function updateJobPEE( array $old_translation, array $new_translation ): void
    {
        //update total time to edit
        $jobTotalTTEForTranslation = $this->chunk[ 'total_time_to_edit' ];
        if ( !self::isRevision() ) {
            $jobTotalTTEForTranslation += $new_translation[ 'time_to_edit' ];
        }

        $segmentRawWordCount  = $this->segment->raw_word_count;
        $editLogSegmentStruct = new EditLogSegmentStruct(
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

        $editLogSegmentStruct->translation = $new_translation[ 'translation' ];

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

                [ 'avg_post_editing_effort' => $newTotalJobPee, 'total_time_to_edit' => $jobTotalTTEForTranslation ],
                [
                    'id'       => $this->id_job,
                    'password' => $this->password
                ] );

        } //segment was valid but now it is no more valid
        elseif ( $oldSegmentStatus->isValidForEditLog() ) {
            $newTotalJobPee = ( $this->chunk[ 'avg_post_editing_effort' ] - $oldPee_weighted );

            Jobs_JobDao::updateFields(
                [ 'avg_post_editing_effort' => $newTotalJobPee, 'total_time_to_edit' => $jobTotalTTEForTranslation ],
                [
                    'id'       => $this->id_job,
                    'password' => $this->password
                ] );
        } elseif ( $jobTotalTTEForTranslation != 0 ) {
            Jobs_JobDao::updateFields(
                [ 'total_time_to_edit' => $jobTotalTTEForTranslation ],
                [
                    'id'       => $this->id_job,
                    'password' => $this->password
                ] );
        }
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
    private function getTranslationObject( $saved_translation ): array
    {
        return [
            'version_number' => @$saved_translation[ 'version_number' ],
            'sid'            => $saved_translation[ 'id_segment' ],
            'translation'    => $this->filter->fromLayer0ToLayer2( $saved_translation[ 'translation' ] ),
            'status'         => $saved_translation[ 'status' ]

        ];
    }

    /**
     * @param $_Translation
     * @param $old_translation
     *
     * @throws Exception
     */
    private function evalSetContribution( $_Translation, $old_translation ): void
    {
        if ( in_array( $this->data['status'], [
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

        $ownerUid   = Jobs_JobDao::getOwnerUid( (int)$this->data['id_job'], $this->data['password']);
        $filesParts = ( new FilesPartsDao() )->getBySegmentId( $this->data['id_segment'] );

        /**
         * Set the new contribution in queue
         */
        $contributionStruct               = new ContributionSetStruct();
        $contributionStruct->fromRevision = $this->isRevision();
        $contributionStruct->id_file      = ( $filesParts !== null ) ? $filesParts->id_file : null;
        $contributionStruct->id_job       = $this->data['id_job'];
        $contributionStruct->job_password = $this->data['password'];
        $contributionStruct->id_segment   = $this->data['id_segment'];
        $contributionStruct->segment      = $this->filter->fromLayer0ToLayer1( $this->data['segment'][ 'segment' ] );
        $contributionStruct->translation  = $this->filter->fromLayer0ToLayer1( $_Translation[ 'translation' ] );
        $contributionStruct->api_key      = INIT::$MYMEMORY_API_KEY;
        $contributionStruct->uid          = ( $ownerUid !== null ) ? $ownerUid : 0;;
        $contributionStruct->oldTranslationStatus = $old_translation[ 'status' ];
        $contributionStruct->oldSegment           = $this->filter->fromLayer0ToLayer1( $this->data['segment'][ 'segment' ] ); //
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
        $contributionStruct->propagationRequest = $this->data['propagate'];
        $contributionStruct->id_mt              = $this->data['chunk']->id_mt_engine;

        $contributionStruct->context_after  = $this->data['context_after'];
        $contributionStruct->context_before = $this->data['context_before'];

        $this->featureSet->filter(
            'filterContributionStructOnSetTranslation',
            $contributionStruct,
            $this->data['project'],
            $this->data['segment']
        );

        //assert there is not an exception by following the flow
        Set::contribution( $contributionStruct );

        if ( $contributionStruct->id_mt > 1 ) {
            Set::contributionMT( $contributionStruct );
        }
    }
}
