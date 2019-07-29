<?php


use Features\TranslationVersions\SegmentTranslationVersionHandler;
use Search\SearchModel;
use Search\SearchQueryParamsStruct;
use SubFiltering\Filter;

class getSearchController extends ajaxController {

    private $job;
    private $token;
    private $password;
    private $source;
    private $target;
    private $status;
    private $replace;
    private $function; //can be search, replace
    private $matchCase;
    private $exactMatch;
    private $revisionNumber;

    private $queryParams = [];

    protected $job_data = [];

    /**
     * @var Database|IDatabase
     */
    private $db;

    /**
     * @var SearchModel
     */
    private $searchModel;

    /**
     * getSearchController constructor.
     * @throws ReflectionException
     * @throws \Predis\Connection\ConnectionException
     */
    public function __construct() {

        parent::__construct();

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
                'revision_number' => [ 'filter' => FILTER_VALIDATE_INT ]
        ];

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->function       = $__postInput[ 'function' ]; //can be: search / replace
        $this->job            = $__postInput[ 'job' ];
        $this->token          = $__postInput[ 'token' ];
        $this->source         = $__postInput[ 'source' ];
        $this->target         = $__postInput[ 'target' ];
        $this->status         = strtolower( $__postInput[ 'status' ] );
        $this->replace        = $__postInput[ 'replace' ];
        $this->password       = $__postInput[ 'password' ];
        $this->matchCase      = $__postInput[ 'matchcase' ];
        $this->exactMatch     = $__postInput[ 'exactmatch' ];
        $this->revisionNumber = $__postInput[ 'revision_number' ];

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
                'job'         => $this->job,
                'password'    => $this->password,
                'key'         => null,
                'src'         => null,
                'trg'         => null,
                'status'      => $this->status,
                'replacement' => $this->replace,
                'matchCase'   => $this->matchCase,
                'exactMatch'  => $this->exactMatch,
        ] );

        if ( in_array( strtoupper( $this->queryParams->status ), Constants_TranslationStatus::$REVISION_STATUSES ) ) {
            if ( !empty( $this->revisionNumber ) ) {
                $this->queryParams->sourcePage = \Features\SecondPassReview\Utils::revisionNumberToSourcePage( $this->revisionNumber );
            } else {
                $this->queryParams->sourcePage = Constants::SOURCE_PAGE_REVISION;
            }
        }

        $this->db          = Database::obtain();
        $this->searchModel = new SearchModel( $this->queryParams );
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

        //get Job Info
        $this->job_data = Jobs_JobDao::getByIdAndPassword( (int)$this->job, $this->password );
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

    private function doSearch() {

        if ( !empty( $this->source ) and !empty( $this->target ) ) {
            $this->queryParams[ 'key' ] = 'coupled';
            $this->queryParams[ 'src' ] = $this->source;
            $this->queryParams[ 'trg' ] = $this->target;
        } elseif ( !empty( $this->source ) ) {
            $this->queryParams[ 'key' ] = 'source';
            $this->queryParams[ 'src' ] = $this->source;
        } elseif ( !empty( $this->target ) ) {
            $this->queryParams[ 'key' ] = 'target';
            $this->queryParams[ 'trg' ] = $this->target;
        } elseif ( empty( $this->source ) and empty( $this->target ) ) {
            $this->queryParams[ 'key' ] = 'status_only';
        }

        try {
            $res = $this->searchModel->search();
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => -1000, "message" => "internal error: see the log" ];

            return;
        }

        $this->result[ 'total' ]    = $res[ 'count' ];
        $this->result[ 'segments' ] = $res[ 'sid_list' ];

    }

    /**
     * @throws Exception
     */
    private function doReplaceAll() {

        $Filter = Filter::getInstance( $this->featureSet );

        $this->queryParams[ 'trg' ]         = $Filter->fromLayer2ToLayer0( $this->target );
        $this->queryParams[ 'src' ]         = $Filter->fromLayer2ToLayer0( $this->source );
        $this->queryParams[ 'replacement' ] = $Filter->fromLayer2ToLayer0( $this->replace );

        $chunk   = Chunks_ChunkDao::getByIdAndPassword( (int)$this->job, $this->password );
        $project = Projects_ProjectDao::findByJobId( (int)$this->job );

        // loop all segments to replace
        foreach ( $this->_getSearchResults() as $key => $tRow ) {

            // start the transaction
            $this->db->begin();

            $old_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $tRow[ 'id_segment' ], (int)$this->job );
            $segment         = ( new Segments_SegmentDao() )->getById( $tRow[ 'id_segment' ] );

            if ( $project->isFeatureEnabled( 'translation_versions' ) ) {
                $versionsHandler = new SegmentTranslationVersionHandler(
                        (int)$this->job,
                        $tRow[ 'id_segment' ],
                        $this->user->uid,
                        $project->id
                );
            }

            // Propagation
            $propagationTotal = [
                    'propagated_ids' => []
            ];

            if ( in_array( $old_translation->status, [
                    Constants_TranslationStatus::STATUS_TRANSLATED,
                    Constants_TranslationStatus::STATUS_APPROVED,
                    Constants_TranslationStatus::STATUS_REJECTED
            ] )
            ) {
                $TPropagation[ 'status' ]                 = $this->status;
                $TPropagation[ 'id_job' ]                 = $this->job;
                $TPropagation[ 'translation' ]            = $tRow[ 'translation' ];
                $TPropagation[ 'autopropagated_from' ]    = $this->id_segment;
                $TPropagation[ 'serialized_errors_list' ] = $old_translation->serialized_errors_list;
                $TPropagation[ 'warning' ]                = $old_translation->warning;
                $TPropagation[ 'segment_hash' ]           = $old_translation[ 'segment_hash' ];

                try {
                    if ( $versionsHandler != null ) {
                        $versionsHandler->savePropagation( [
                                'propagation'     => $TPropagation,
                                'old_translation' => $old_translation,
                                'job_data'        => $this->job_data
                        ] );
                    }

                    $propagationTotal = Translations_SegmentTranslationDao::propagateTranslation(
                            $TPropagation,
                            $this->job_data,
                            $this->id_segment,
                            $project,
                            false
                    );

                } catch ( Exception $e ) {
                    $msg = $e->getMessage() . "\n\n" . $e->getTraceAsString();
                    Log::doJsonLog( $msg );
                    Utils::sendErrMailReport( $msg );
                    $this->db->rollback();

                    return $e->getCode();

                }
            }

            // Setup $new_translation
            $new_translation                         = new Translations_SegmentTranslationStruct();
            $new_translation->id_segment             = $tRow[ 'id_segment' ];
            $new_translation->id_job                 = $this->job;
            $new_translation->status                 = $this->_getNewStatus($old_translation);
            $new_translation->time_to_edit           = $old_translation->time_to_edit;
            $new_translation->segment_hash           = $segment->segment_hash;
            $new_translation->translation            = $tRow[ 'translation' ];
            $new_translation->serialized_errors_list = $old_translation->serialized_errors_list;
            $new_translation->suggestion_position    = $old_translation->suggestion_position;
            $new_translation->warning                = $old_translation->warning;
            $new_translation->version_number         = $old_translation->version_number;
            $new_translation->translation_date       = date( "Y-m-d H:i:s" );

            // preSetTranslationCommitted
            $this->featureSet->run( 'preSetTranslationCommitted', [
                    'translation'       => $new_translation,
                    'old_translation'   => $old_translation,
                    'propagation'       => $propagationTotal,
                    'chunk'             => $chunk,
                    'segment'           => $segment,
                    'user'              => $this->user,
                    'source_page_code'  => self::getRefererSourcePageCode( $this->featureSet ),
                    'controller_result' => & $this->result,
                    'features'          => $this->featureSet
            ] );

            //COMMIT THE TRANSACTION
            try {
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
                        'source_page_code' => self::getRefererSourcePageCode( $this->featureSet )
                ] );
            } catch ( Exception $e ) {
                Log::doJsonLog( "Exception in setTranslationCommitted callback . " . $e->getMessage() . "\n" . $e->getTraceAsString() );
            }
        }

        // replaceAll
        $this->searchModel->replaceAll( $this->_getSearchResults() );

    }

    /**
     * @throws Exception
     */
    private function undoReplaceAll() {

        /**
         * Leave the FatalErrorHandler catch the Exception, so the message with Contact Support will be sent
         * @throws Exception
         */
        $this->searchModel->undoReplaceAll();
    }

    /**
     * @throws Exception
     */
    private function redoReplaceAll() {

        /**
         * Leave the FatalErrorHandler catch the Exception, so the message with Contact Support will be sent
         * @throws Exception
         */
        $this->searchModel->redoReplaceAll();
    }

    /**
     * @param Translations_SegmentTranslationStruct $translationStruct
     *
     * @return string
     */
    private function _getNewStatus(Translations_SegmentTranslationStruct $translationStruct) {

        switch ($this->revisionNumber){

            // false = TRANSLATED
            case false:
                return Constants_TranslationStatus::STATUS_TRANSLATED;

            // 1 = REVISION
            // 2 = 2ND REVISION
            case 1:
            case 2:
                if($translationStruct->status === Constants_TranslationStatus::STATUS_TRANSLATED){
                    return Constants_TranslationStatus::STATUS_TRANSLATED;
                }

                return Constants_TranslationStatus::STATUS_APPROVED;
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function _getSearchResults() {
        $query = $this->searchModel->loadReplaceAllQuery();

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
