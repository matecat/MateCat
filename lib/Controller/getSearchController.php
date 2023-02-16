<?php

use Features\ReviewExtended\ReviewUtils;
use Features\TranslationVersions;
use Matecat\Finder\WholeTextFinder;
use Matecat\SubFiltering\MateCatFilter;
use Search\ReplaceEventStruct;
use Search\SearchModel;
use Search\SearchQueryParamsStruct;

class getSearchController extends ajaxController {

    private $job;
    private $token;
    private $password;
    private $source;
    private $target;
    private $status;
    private $replace;
    private $function; //can be search, replace
    private $isMatchCaseRequested;
    private $isExactMatchRequested;
    private $matchCase;
    private $exactMatch;
    private $strictMode;
    private $revisionNumber;

    private $queryParams = [];

    /**
     * @var Chunks_ChunkStruct
     */
    protected $job_data;

    /**
     * @var Database|IDatabase
     */
    private $db;

    /**
     * @var SearchModel
     */
    private $searchModel;

    /**
     * @var Search_ReplaceHistory
     */
    private $srh;

    /**
     * getSearchController constructor.
     * @throws ReflectionException
     * @throws \Predis\Connection\ConnectionException
     */
    public function __construct() {

        parent::__construct();
        $this->readLoginInfo();

        $filterArgs = [
                'function'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'job'             => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'token'           => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'source'          => [ 'filter' => FILTER_UNSAFE_RAW ],
                'target'          => [ 'filter' => FILTER_UNSAFE_RAW ],
                'status'          => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                'replace'         => [ 'filter' => FILTER_UNSAFE_RAW ],
                'password'        => [ 'filter' => FILTER_UNSAFE_RAW ],
                'matchcase'       => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'exactmatch'      => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'strict_mode'     => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'revision_number' => [ 'filter' => FILTER_VALIDATE_INT ]
        ];

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->function              = $__postInput[ 'function' ]; //can be: search / replace
        $this->job                   = $__postInput[ 'job' ];
        $this->token                 = $__postInput[ 'token' ];
        $this->source                = $__postInput[ 'source' ];
        $this->target                = $__postInput[ 'target' ];
        $this->status                = strtolower( $__postInput[ 'status' ] );
        $this->replace               = $__postInput[ 'replace' ];
        $this->password              = $__postInput[ 'password' ];
        $this->isMatchCaseRequested  = $__postInput[ 'matchcase' ];
        $this->isExactMatchRequested = $__postInput[ 'exactmatch' ];
        $this->strictMode            = $__postInput[ 'strict_mode' ];
        $this->revisionNumber        = $__postInput[ 'revision_number' ];

        if ( empty( $this->status ) ) {
            $this->status = "all";
        }

        switch ( $this->status ) {
            case 'translated':
            case 'approved':
            case 'rejected':
            case 'draft':
            case 'new':
                break;
            default:
                $this->status = "all";
                break;
        }

        $this->queryParams = new SearchQueryParamsStruct( [
                'job'                   => $this->job,
                'password'              => $this->password,
                'key'                   => null,
                'src'                   => null,
                'trg'                   => null,
                'status'                => $this->status,
                'replacement'           => $this->replace,
                'isMatchCaseRequested'  => $this->isMatchCaseRequested,
                'isExactMatchRequested' => $this->isExactMatchRequested,
                'strictMode'            => $this->strictMode,
        ] );

        if ( in_array( strtoupper( $this->queryParams->status ), Constants_TranslationStatus::$REVISION_STATUSES ) ) {
            if ( !empty( $this->revisionNumber ) ) {
                $this->queryParams->sourcePage = ReviewUtils::revisionNumberToSourcePage( $this->revisionNumber );
            } else {
                $this->queryParams->sourcePage = Constants::SOURCE_PAGE_REVISION;
            }
        }

        $this->db = Database::obtain();

        // Search_ReplaceHistory init
        $srh_driver = ( isset( \INIT::$REPLACE_HISTORY_DRIVER ) and '' !== \INIT::$REPLACE_HISTORY_DRIVER ) ? \INIT::$REPLACE_HISTORY_DRIVER : 'redis';
        $srh_ttl    = ( isset( \INIT::$REPLACE_HISTORY_TTL ) and '' !== \INIT::$REPLACE_HISTORY_TTL ) ? \INIT::$REPLACE_HISTORY_TTL : 300;
        $this->srh  = Search_ReplaceHistoryFactory::create( $this->queryParams[ 'job' ], $srh_driver, $srh_ttl );

        //get Job Info
        $this->job_data = Chunks_ChunkDao::getByIdAndPassword( (int)$this->job, $this->password );

        /** @var MateCatFilter $filter */
        $filter            = MateCatFilter::getInstance( $this->getFeatureSet(), $this->job_data->source, $this->job_data->target, [] );
        $this->searchModel = new SearchModel( $this->queryParams, $filter );
    }

    /**
     * @return mixed|void
     * @throws Exception
     */
    public function doAction() {

        $this->result[ 'token' ] = $this->token;

        if ( empty( $this->job ) ) {
            $this->result[ 'errors' ][] = [ "code" => -2, "message" => "missing id job" ];

            return;
        }

        $this->featureSet->loadForProject( $this->job_data->getProject() );

        switch ( $this->function ) {
            case 'find':
                $this->doSearch();
                break;
            case 'replaceAll':
                $this->doReplaceAll();
                break;
            case 'redoReplaceAll':
                $this->redoReplaceAll();
                break;
            case 'undoReplaceAll':
                $this->undoReplaceAll();
                break;
            default :
                $this->result[ 'errors' ][] = [ "code" => -11, "message" => "unknown  function. Use find or replace" ];

                return;
        }
    }

    /**
     * Perform a regular search
     */
    private function doSearch() {

        if ( !empty( $this->source ) and !empty( $this->target ) ) {
            $this->queryParams[ 'key' ] = 'coupled';
            $this->queryParams[ 'src' ] = html_entity_decode( $this->source ); // source strings are not escaped as html entites in DB. Example: &lt; must be decoded to <
            $this->queryParams[ 'trg' ] = $this->target;
        } elseif ( !empty( $this->source ) ) {
            $this->queryParams[ 'key' ] = 'source';
            $this->queryParams[ 'src' ] = html_entity_decode( $this->source ); // source strings are not escaped as html entites in DB. Example: &lt; must be decoded to <
        } elseif ( !empty( $this->target ) ) {
            $this->queryParams[ 'key' ] = 'target';
            $this->queryParams[ 'trg' ] = $this->target;
        } elseif ( empty( $this->source ) and empty( $this->target ) ) {
            $this->queryParams[ 'key' ] = 'status_only';
        }

        try {
            $strictMode = ( null !== $this->queryParams[ 'strictMode' ] ) ? $this->queryParams[ 'strictMode' ] : true;
            $res        = $this->searchModel->search( $strictMode );
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => -1000, "message" => "internal error: see the log" ];

            return;
        }

        $this->result[ 'total' ]    = $res[ 'count' ];
        $this->result[ 'segments' ] = $res[ 'sid_list' ];
    }

    /**
     * Perform a search and replace αφετέρου
     *
     * @throws Exception
     */
    private function doReplaceAll() {

        $search_results = [];

        // perform a regular search
        $this->doSearch( true );

        // and then hydrate the $search_results array
        foreach ( $this->result[ 'segments' ] as $segmentId ) {
            $search_results[] = Translations_SegmentTranslationDao::findBySegmentAndJob( $segmentId, $this->queryParams[ 'job' ] )->toArray();
        }

        // set the replacement in queryParams
        $this->queryParams[ 'replacement' ] = $this->replace;

        // update segment translations
        $this->_updateSegments( $search_results );

        // and save replace events
        $replace_version = ( $this->srh->getCursor() + 1 );
        foreach ( $search_results as $key => $tRow ) {
            $this->_saveReplacementEvent( $replace_version, $tRow );
        }
    }

    /**
     * @param $replace_version
     * @param $tRow
     */
    private function _saveReplacementEvent( $replace_version, $tRow ) {
        $event                                 = new ReplaceEventStruct();
        $event->replace_version                = $replace_version;
        $event->id_segment                     = $tRow[ 'id_segment' ];
        $event->id_job                         = $this->queryParams[ 'job' ];
        $event->job_password                   = $this->queryParams[ 'password' ];
        $event->source                         = $this->queryParams[ 'source' ];
        $event->target                         = $this->queryParams[ 'target' ];
        $event->replacement                    = $this->queryParams[ 'replacement' ];
        $event->translation_before_replacement = $tRow[ 'translation' ];
        $event->translation_after_replacement  = $this->_getReplacedSegmentTranslation( $tRow[ 'translation' ] );
        $event->status                         = $tRow[ 'status' ];

        $this->srh->save( $event );
        $this->srh->updateIndex( $replace_version );

        Log::doJsonLog( 'Replacement event for segment #' . $tRow[ 'id_segment' ] . ' correctly saved.' );
    }

    /**
     * @param $translation
     *
     * @return string|string[]|null
     */
    private function _getReplacedSegmentTranslation( $translation ) {
        $replacedSegmentTranslation = WholeTextFinder::findAndReplace(
                $translation,
                $this->queryParams->target,
                $this->queryParams->replacement,
                true,
                $this->queryParams->isExactMatchRequested,
                $this->queryParams->isMatchCaseRequested,
                true
        );

        return ( !empty( $replacedSegmentTranslation ) ) ? $replacedSegmentTranslation[ 'replacement' ] : $translation;
    }

    /**
     * @throws Exception
     */
    private function undoReplaceAll() {
        $search_results = $this->_getSegmentForUndoReplaceAll();
        $this->_updateSegments( $search_results );

        $this->srh->undo();
    }

    /**
     * @return array
     */
    private function _getSegmentForUndoReplaceAll() {
        $results = [];
        $cursor  = $this->srh->getCursor();

        if ( $cursor === 0 ) {
            $versionToMove = 0;
        } elseif ( $cursor === 1 ) {
            $versionToMove = 1;
        } else {
            $versionToMove = $cursor - 1;
        }

        $events = $this->srh->get( $versionToMove );

        foreach ( $events as $event ) {
            $results[] = [
                    'id_segment'  => $event->id_segment,
                    'id_job'      => $event->id_job,
                    'translation' => $event->translation_after_replacement,
                    'status'      => $event->status,
            ];
        }

        return $results;
    }

    /**
     * @throws Exception
     */
    private function redoReplaceAll() {
        $search_results = $this->_getSegmentForRedoReplaceAll();
        $this->_updateSegments( $search_results );

        $this->srh->redo();
    }

    /**
     * @return array
     */
    private function _getSegmentForRedoReplaceAll() {
        $results = [];

        $versionToMove = $this->srh->getCursor() + 1;
        $events        = $this->srh->get( $versionToMove );

        foreach ( $events as $event ) {
            $results[] = [
                    'id_segment'  => $event->id_segment,
                    'id_job'      => $event->id_job,
                    'translation' => $event->translation_before_replacement,
                    'status'      => $event->status,
            ];
        }

        return $results;
    }

    /**
     * @param $search_results
     *
     * @return int|mixed
     * @throws Exception
     */
    private function _updateSegments( $search_results ) {
        $chunk           = Chunks_ChunkDao::getByIdAndPassword( (int)$this->job, $this->password );
        $project         = Projects_ProjectDao::findByJobId( (int)$this->job );
        $versionsHandler = TranslationVersions::getVersionHandlerNewInstance( $chunk, $this->id_segment, $this->user, $project );

        // loop all segments to replace
        foreach ( $search_results as $key => $tRow ) {

            // start the transaction
            $this->db->begin();

            $old_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( (int)$tRow[ 'id_segment' ], (int)$tRow[ 'id_job' ] );
            $segment         = ( new Segments_SegmentDao() )->getById( $tRow[ 'id_segment' ] );

            // Propagation
            $propagationTotal = [
                    'propagated_ids' => []
            ];

            if ( $old_translation->translation !== $tRow[ 'translation' ] && in_array( $old_translation->status, [
                            Constants_TranslationStatus::STATUS_TRANSLATED,
                            Constants_TranslationStatus::STATUS_APPROVED,
                            Constants_TranslationStatus::STATUS_REJECTED
                    ] )
            ) {
                $TPropagation                             = new Translations_SegmentTranslationStruct();
                $TPropagation[ 'status' ]                 = $tRow[ 'status' ];
                $TPropagation[ 'id_job' ]                 = $this->job;
                $TPropagation[ 'translation' ]            = $tRow[ 'translation' ];
                $TPropagation[ 'autopropagated_from' ]    = $this->id_segment;
                $TPropagation[ 'serialized_errors_list' ] = $old_translation->serialized_errors_list;
                $TPropagation[ 'warning' ]                = $old_translation->warning;
                $TPropagation[ 'segment_hash' ]           = $old_translation[ 'segment_hash' ];

                try {
                    $propagationTotal = Translations_SegmentTranslationDao::propagateTranslation(
                            $TPropagation,
                            $this->job_data,
                            $this->id_segment,
                            $project,
                            $versionsHandler,
                            true
                    );

                } catch ( Exception $e ) {
                    $msg = $e->getMessage() . "\n\n" . $e->getTraceAsString();
                    Log::doJsonLog( $msg );
                    Utils::sendErrMailReport( $msg );
                    $this->db->rollback();

                    return $e->getCode();
                }
            }

            $filter              = MateCatFilter::getInstance( $this->getFeatureSet(), $this->job_data->source, $this->job_data->target, [] );
            $replacedTranslation = $filter->fromLayer1ToLayer0( $this->_getReplacedSegmentTranslation( $tRow[ 'translation' ] ) );
            $replacedTranslation = Utils::stripBOM( $replacedTranslation );

            // Setup $new_translation
            $new_translation                         = new Translations_SegmentTranslationStruct();
            $new_translation->id_segment             = $tRow[ 'id_segment' ];
            $new_translation->id_job                 = $this->job;
            $new_translation->status                 = $this->_getNewStatus( $old_translation );
            $new_translation->time_to_edit           = $old_translation->time_to_edit;
            $new_translation->segment_hash           = $segment->segment_hash;
            $new_translation->translation            = $replacedTranslation;
            $new_translation->serialized_errors_list = $old_translation->serialized_errors_list;
            $new_translation->suggestion_position    = $old_translation->suggestion_position;
            $new_translation->warning                = $old_translation->warning;
            $new_translation->translation_date       = date( "Y-m-d H:i:s" );

            $version_number = $old_translation->version_number;
            if ( false === \Utils::stringsAreEqual( $new_translation->translation, $old_translation->translation ) ) {
                $version_number++;
            }

            $new_translation->version_number = $version_number;

            // Save version
            $versionsHandler->saveVersionAndIncrement( $new_translation, $old_translation );

            // preSetTranslationCommitted
            $versionsHandler->storeTranslationEvent( [
                    'translation'       => $new_translation,
                    'old_translation'   => $old_translation,
                    'propagation'       => $propagationTotal,
                    'chunk'             => $chunk,
                    'segment'           => $segment,
                    'user'              => $this->user,
                    'source_page_code'  => ReviewUtils::revisionNumberToSourcePage( $this->revisionNumber ),
                    'controller_result' => & $this->result,
                    'features'          => $this->featureSet,
                    'project'           => $project
            ] );

            // commit the transaction
            try {
                Translations_SegmentTranslationDao::updateTranslationAndStatusAndDate( $new_translation );
                $this->db->commit();
            } catch ( Exception $e ) {
                $this->result[ 'errors' ][] = [ "code" => -101, "message" => $e->getMessage() ];
                Log::doJsonLog( "Lock: Transaction Aborted. " . $e->getMessage() );
                $this->db->rollback();

                return $e->getCode();
            }

            // setTranslationCommitted
            try {
                $this->featureSet->run( 'setTranslationCommitted', [
                        'translation'      => $new_translation,
                        'old_translation'  => $old_translation,
                        'propagated_ids'   => $propagationTotal[ 'propagated_ids' ],
                        'chunk'            => $chunk,
                        'segment'          => $segment,
                        'user'             => $this->user,
                        'source_page_code' => ReviewUtils::revisionNumberToSourcePage( $this->revisionNumber )
                ] );
            } catch ( Exception $e ) {
                Log::doJsonLog( "Exception in setTranslationCommitted callback . " . $e->getMessage() . "\n" . $e->getTraceAsString() );
            }
        }
    }

    /**
     * @param Translations_SegmentTranslationStruct $translationStruct
     *
     * @return string
     */
    private function _getNewStatus( Translations_SegmentTranslationStruct $translationStruct ) {

        if ( false === $this->revisionNumber ) {
            return Constants_TranslationStatus::STATUS_TRANSLATED;
        }

        if ( $translationStruct->status === Constants_TranslationStatus::STATUS_TRANSLATED ) {
            return Constants_TranslationStatus::STATUS_TRANSLATED;
        }

        return Constants_TranslationStatus::STATUS_APPROVED;
    }

    /**
     * @return array
     * @throws Exception
     */
    private function _getSearchResults() {
        $query = $this->searchModel->_loadReplaceAllQuery();

        return $this->executeQuery( $query );
    }

    /**
     * @param $query
     *
     * @return array
     * @throws Exception
     */
    private function executeQuery( $query ) {
        Log::doJsonLog( $query );

        try {
            $stmt = $this->db->getConnection()->prepare( $query );
            $stmt->execute();

            return $stmt->fetchAll( \PDO::FETCH_ASSOC );
        } catch ( \PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );
            throw new \Exception( $e->getMessage(), $e->getCode() * -1, $e );
        }
    }
}
