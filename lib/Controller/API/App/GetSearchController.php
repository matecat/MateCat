<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Matecat\Finder\WholeTextFinder;
use Matecat\SubFiltering\MateCatFilter;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Projects\ProjectDao;
use Model\Search\ReplaceEventStruct;
use Model\Search\SearchModel;
use Model\Search\SearchQueryParamsStruct;
use Model\Segments\SegmentDao;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\TranslationVersions;
use ReflectionException;
use RuntimeException;
use Utils\Constants\TranslationStatus;
use Utils\Registry\AppConfig;
use Utils\Search\ReplaceHistory;
use Utils\Search\ReplaceHistoryFactory;
use Utils\Tools\Utils;

class GetSearchController extends AbstractStatefulKleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function search(): void {

        $request = $this->validateTheRequest();
        $res     = $this->doSearch( $request );

        $this->response->json( [
                'data'     => [],
                'errors'   => [],
                'token'    => $request[ 'token' ],
                'total'    => $res[ 'count' ],
                'segments' => $res[ 'sid_list' ],
        ] );

    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function replaceAll(): void {

        $request        = $this->validateTheRequest();
        $res            = $this->doSearch( $request );
        $search_results = [];

        // and then hydrate the $search_results array
        foreach ( $res[ 'sid_list' ] as $segmentId ) {
            $search_results[] = SegmentTranslationDao::findBySegmentAndJob( $segmentId, $request[ 'queryParams' ][ 'job' ] )->toArray();
        }

        // set the replacement in queryParams
        $request[ 'queryParams' ][ 'replacement' ] = $request[ 'replace' ];

        // update segment translations
        $this->updateSegments( $search_results, $request[ 'job' ], $request[ 'password' ], $request[ 'id_segment' ] ?? null, $request[ 'queryParams' ], $request[ 'revisionNumber' ] );

        // and save replace events
        $srh             = $this->getReplaceHistory( $request[ 'job' ] );
        $replace_version = ( $srh->getCursor() + 1 );

        foreach ( $search_results as $tRow ) {
            $this->saveReplacementEvent( $replace_version, $tRow, $srh, $request[ 'queryParams' ] );
        }

        $this->response->json( [
                "errors"   => [],
                "data"     => [],
                "token"    => $request[ 'token' ] ?? null,
                "total"    => $res[ 'count' ] ?? 0,
                "segments" => $res[ 'sid_list' ]
        ] );

    }

    // not is use

    /**
     * @throws Exception
     */
    public function redoReplaceAll(): void {

        $request        = $this->validateTheRequest();
        $shr            = $this->getReplaceHistory( $request[ 'job' ] );
        $search_results = $this->getSegmentForRedoReplaceAll( $shr );
        $this->updateSegments( $search_results, $request[ 'job' ], $request[ 'password' ], $request[ 'id_segment' ], $request[ 'queryParams' ], $request[ 'revisionNumber' ] );
        $shr->redo();

        $this->response->json( [
                'success' => true
        ] );

    }

    // not is use

    /**
     * @throws Exception
     */
    public function undoReplaceAll(): void {

        $request        = $this->validateTheRequest();
        $shr            = $this->getReplaceHistory( $request[ 'job' ] );
        $search_results = $this->getSegmentForUndoReplaceAll( $shr );
        $this->updateSegments( $search_results, $request[ 'job' ], $request[ 'password' ], $request[ 'id_segment' ], $request[ 'queryParams' ], $request[ 'revisionNumber' ] );
        $shr->undo();

        $this->response->json( [
                'success' => true
        ] );

    }

    /**
     * @return array
     */
    private function validateTheRequest(): array {
        $job                   = filter_var( $this->request->param( 'job' ), FILTER_SANITIZE_NUMBER_INT );
        $token                 = filter_var( $this->request->param( 'token' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $source                = filter_var( $this->request->param( 'source' ), FILTER_UNSAFE_RAW );
        $target                = filter_var( $this->request->param( 'target' ), FILTER_UNSAFE_RAW );
        $status                = filter_var( $this->request->param( 'status' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $replace               = filter_var( $this->request->param( 'replace' ), FILTER_UNSAFE_RAW );
        $password              = filter_var( $this->request->param( 'password' ), FILTER_UNSAFE_RAW );
        $isMatchCaseRequested  = filter_var( $this->request->param( 'matchcase' ), FILTER_VALIDATE_BOOLEAN );
        $isExactMatchRequested = filter_var( $this->request->param( 'exactmatch' ), FILTER_VALIDATE_BOOLEAN );
        $inCurrentChunkOnly    = filter_var( $this->request->param( 'inCurrentChunkOnly' ), FILTER_VALIDATE_BOOLEAN );
        $revision_number       = filter_var( $this->request->param( 'revision_number' ), FILTER_SANITIZE_NUMBER_INT, [ 'filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => [ 'default' => null ] ] );

        if ( empty( $job ) ) {
            throw new InvalidArgumentException( "missing id job", -2 );
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException( "missing job password", -3 );
        }

        $job = (int)$job;

        switch ( $status ) {
            case 'translated':
            case 'approved':
            case 'approved2':
            case 'rejected':
            case 'draft':
            case 'new':
                break;
            default:
                $status = "all";
                break;
        }

        $queryParams = new SearchQueryParamsStruct( [
                'job'                   => $job,
                'password'              => $password,
                'key'                   => null,
                'src'                   => null,
                'trg'                   => null,
                'status'                => $status,
                'replacement'           => $replace,
                'isMatchCaseRequested'  => $isMatchCaseRequested,
                'isExactMatchRequested' => $isExactMatchRequested,
                'inCurrentChunkOnly'    => $inCurrentChunkOnly,
        ] );

        return [
                'job'                   => $job,
                'token'                 => $token,
                'source'                => $source,
                'target'                => $target,
                'status'                => $status,
                'replace'               => $replace,
                'password'              => $password,
                'isMatchCaseRequested'  => $isMatchCaseRequested,
                'isExactMatchRequested' => $isExactMatchRequested,
                'inCurrentChunkOnly'    => $inCurrentChunkOnly,
                'revisionNumber'        => $revision_number,
                'queryParams'           => $queryParams,
        ];
    }

    /**
     * @param $job_id
     * @param $password
     *
     * @return JobStruct|null
     * @throws Exception
     */
    private function getJobData( $job_id, $password ): ?JobStruct {
        return ChunkDao::getByIdAndPassword( (int)$job_id, $password );
    }

    /**
     * @param $job_id
     *
     * @return ReplaceHistory
     */
    private function getReplaceHistory( $job_id ): ReplaceHistory {
        // ReplaceHistory init
        $srh_driver = ( isset( AppConfig::$REPLACE_HISTORY_DRIVER ) and '' !== AppConfig::$REPLACE_HISTORY_DRIVER ) ? AppConfig::$REPLACE_HISTORY_DRIVER : 'redis';
        $srh_ttl    = ( isset( AppConfig::$REPLACE_HISTORY_TTL ) and 0 !== AppConfig::$REPLACE_HISTORY_TTL ) ? AppConfig::$REPLACE_HISTORY_TTL : 300;

        return ReplaceHistoryFactory::create( $job_id, $srh_driver, $srh_ttl );
    }

    /**
     * @param SearchQueryParamsStruct $queryParams
     * @param JobStruct               $jobStruct
     *
     * @return SearchModel
     * @throws Exception
     */
    private function getSearchModel( SearchQueryParamsStruct $queryParams, JobStruct $jobStruct ): SearchModel {
        /** @var MateCatFilter $filter */
        $metadata = new MetadataDao();
        $filter   = MateCatFilter::getInstance( $this->getFeatureSet(), $jobStruct->source, $jobStruct->target, [], $metadata->getSubfilteringCustomHandlers( $jobStruct->id, $jobStruct->password ) );

        return new SearchModel( $queryParams, $filter );
    }

    /**
     * @param ReplaceHistory $srh
     *
     * @return array
     */
    private function getSegmentForRedoReplaceAll( ReplaceHistory $srh ): array {
        $results = [];

        $versionToMove = $srh->getCursor() + 1;
        $events        = $srh->get( $versionToMove );

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
     * @param ReplaceHistory $srh
     *
     * @return array
     */
    private function getSegmentForUndoReplaceAll( ReplaceHistory $srh ): array {
        $results = [];
        $cursor  = $srh->getCursor();

        if ( $cursor === 0 ) {
            $versionToMove = 0;
        } elseif ( $cursor === 1 ) {
            $versionToMove = 1;
        } else {
            $versionToMove = $cursor - 1;
        }

        $events = $srh->get( $versionToMove );

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
     * @param $request
     *
     * @return array
     */
    private function doSearch( $request ): array {
        $queryParams = $request[ 'queryParams' ];

        if ( !empty( $request[ 'source' ] ) and !empty( $request[ 'target' ] ) ) {
            $queryParams[ 'key' ] = 'coupled';
            $queryParams[ 'src' ] = html_entity_decode( $request[ 'source' ] ); // source strings are not escaped as html entites in DB. Example: &lt; must be decoded to <
            $queryParams[ 'trg' ] = $request[ 'target' ];
        } elseif ( !empty( $request[ 'source' ] ) ) {
            $queryParams[ 'key' ] = 'source';
            $queryParams[ 'src' ] = html_entity_decode( $request[ 'source' ] ); // source strings are not escaped as html entites in DB. Example: &lt; must be decoded to <
        } elseif ( !empty( $request[ 'target' ] ) ) {
            $queryParams[ 'key' ] = 'target';
            $queryParams[ 'trg' ] = $request[ 'target' ];
        } else {
            $queryParams[ 'key' ] = 'status_only';
        }

        try {
            $inCurrentChunkOnly = $queryParams[ 'inCurrentChunkOnly' ];
            $jodData            = $this->getJobData( $request[ 'job' ], $request[ 'password' ] );
            $searchModel        = $this->getSearchModel( $queryParams, $jodData );

            return $searchModel->search( $inCurrentChunkOnly );
        } catch ( Exception $e ) {
            throw new RuntimeException( "internal error: see the log", -1000 );
        }
    }

    /**
     * @param                         $search_results
     * @param int                     $id_job
     * @param string                  $password
     * @param string                  $id_segment
     * @param SearchQueryParamsStruct $queryParams
     * @param int|null                $revisionNumber
     *
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     */
    private function updateSegments( $search_results, int $id_job, string $password, string $id_segment, SearchQueryParamsStruct $queryParams, ?int $revisionNumber = null ): void {
        $db = Database::obtain();

        $chunk           = ChunkDao::getByIdAndPassword( $id_job, $password );
        $project         = ProjectDao::findByJobId( $id_job );
        $versionsHandler = TranslationVersions::getVersionHandlerNewInstance( $chunk, $this->user, $project, $id_segment );

        // loop all segments to replace
        foreach ( $search_results as $tRow ) {

            // start the transaction
            $db->begin();

            $old_translation = SegmentTranslationDao::findBySegmentAndJob( (int)$tRow[ 'id_segment' ], (int)$tRow[ 'id_job' ] );
            $segment         = ( new SegmentDao() )->getById( $tRow[ 'id_segment' ] );

            // Propagation
            $propagationTotal = [
                    'propagated_ids' => []
            ];

            if ( $old_translation->translation !== $tRow[ 'translation' ] && in_array( $old_translation->status, [
                            TranslationStatus::STATUS_TRANSLATED,
                            TranslationStatus::STATUS_APPROVED,
                            TranslationStatus::STATUS_APPROVED2,
                            TranslationStatus::STATUS_REJECTED
                    ] )
            ) {
                $TPropagation                             = new SegmentTranslationStruct();
                $TPropagation[ 'status' ]                 = $tRow[ 'status' ];
                $TPropagation[ 'id_job' ]                 = $id_job;
                $TPropagation[ 'translation' ]            = $tRow[ 'translation' ];
                $TPropagation[ 'autopropagated_from' ]    = $id_segment;
                $TPropagation[ 'serialized_errors_list' ] = $old_translation->serialized_errors_list;
                $TPropagation[ 'warning' ]                = $old_translation->warning;
                $TPropagation[ 'segment_hash' ]           = $old_translation[ 'segment_hash' ];

                try {
                    $propagationTotal = SegmentTranslationDao::propagateTranslation(
                            $TPropagation,
                            $chunk,
                            $id_segment,
                            $project
                    );

                } catch ( Exception $e ) {
                    $msg = $e->getMessage() . "\n\n" . $e->getTraceAsString();
                    $this->logger->debug( $msg );
                    Utils::sendErrMailReport( $msg );
                    $db->rollback();

                    throw new RuntimeException( "A fatal error occurred during saving of segments" );
                }
            }

            $filter              = MateCatFilter::getInstance( $this->getFeatureSet(), $chunk->source, $chunk->target );
            $replacedTranslation = $filter->fromLayer1ToLayer0( $this->getReplacedSegmentTranslation( $tRow[ 'translation' ], $queryParams ) );
            $replacedTranslation = Utils::stripBOM( $replacedTranslation );

            // Setup $new_translation
            $new_translation                         = new SegmentTranslationStruct();
            $new_translation->id_segment             = $tRow[ 'id_segment' ];
            $new_translation->id_job                 = $chunk->id;
            $new_translation->status                 = $this->getNewStatus( $old_translation, $revisionNumber );
            $new_translation->time_to_edit           = $old_translation->time_to_edit;
            $new_translation->segment_hash           = $segment->segment_hash;
            $new_translation->translation            = $replacedTranslation;
            $new_translation->serialized_errors_list = $old_translation->serialized_errors_list;
            $new_translation->suggestion_position    = $old_translation->suggestion_position;
            $new_translation->warning                = $old_translation->warning;
            $new_translation->translation_date       = date( "Y-m-d H:i:s" );

            $version_number = $old_translation->version_number;
            if ( false === Utils::stringsAreEqual( $new_translation->translation, $old_translation->translation ) ) {
                $version_number++;
            }

            $new_translation->version_number = $version_number;

            // Save version
            $versionsHandler->saveVersionAndIncrement( $new_translation, $old_translation );

            // preSetTranslationCommitted
            $versionsHandler->storeTranslationEvent( [
                    'translation'      => $new_translation,
                    'old_translation'  => $old_translation,
                    'propagation'      => $propagationTotal,
                    'chunk'            => $chunk,
                    'segment'          => $segment,
                    'user'             => $this->user,
                    'source_page_code' => ReviewUtils::revisionNumberToSourcePage( $revisionNumber ),
                    'features'         => $this->featureSet,
                    'project'          => $project
            ] );

            // commit the transaction
            try {
                SegmentTranslationDao::updateTranslationAndStatusAndDate( $new_translation );
                $db->commit();
            } catch ( Exception $e ) {
                $this->logger->debug( "Lock: Transaction Aborted. " . $e->getMessage() );
                $db->rollback();

                throw new RuntimeException( "A fatal error occurred during saving of segments" );
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
                        'source_page_code' => ReviewUtils::revisionNumberToSourcePage( $revisionNumber )
                ] );
            } catch ( Exception $e ) {
                $this->logger->debug( "Exception in setTranslationCommitted callback . " . $e->getMessage() . "\n" . $e->getTraceAsString() );

                throw new RuntimeException( "Exception in setTranslationCommitted callback" );
            }
        }
    }

    /**
     * @param SegmentTranslationStruct $translationStruct
     * @param int|null                 $revisionNumber
     *
     * @return string
     */
    private function getNewStatus( SegmentTranslationStruct $translationStruct, ?int $revisionNumber = null ): string {
        if ( !isset( $revisionNumber ) ) {
            return TranslationStatus::STATUS_TRANSLATED;
        }

        if ( $translationStruct->status === TranslationStatus::STATUS_TRANSLATED ) {
            return TranslationStatus::STATUS_TRANSLATED;
        }

        return TranslationStatus::STATUS_APPROVED;
    }

    /**
     * @param                         $translation
     * @param SearchQueryParamsStruct $queryParams
     *
     * @return string|string[]|null
     */
    private function getReplacedSegmentTranslation( $translation, SearchQueryParamsStruct $queryParams ) {
        $replacedSegmentTranslation = WholeTextFinder::findAndReplace(
                $translation,
                $queryParams->target,
                $queryParams->replacement,
                true,
                $queryParams->isExactMatchRequested,
                $queryParams->isMatchCaseRequested,
                true
        );

        return ( !empty( $replacedSegmentTranslation ) ) ? $replacedSegmentTranslation[ 'replacement' ] : $translation;
    }

    /**
     * @param                         $replace_version
     * @param                         $tRow
     * @param ReplaceHistory          $srh
     * @param SearchQueryParamsStruct $queryParams
     */
    private function saveReplacementEvent( $replace_version, $tRow, ReplaceHistory $srh, SearchQueryParamsStruct $queryParams ): void {
        $event                                 = new ReplaceEventStruct();
        $event->replace_version                = $replace_version;
        $event->id_segment                     = $tRow[ 'id_segment' ];
        $event->id_job                         = $queryParams[ 'job' ];
        $event->job_password                   = $queryParams[ 'password' ];
        $event->source                         = $queryParams[ 'source' ];
        $event->target                         = $queryParams[ 'target' ];
        $event->replacement                    = $queryParams[ 'replacement' ];
        $event->translation_before_replacement = $tRow[ 'translation' ];
        $event->translation_after_replacement  = $this->getReplacedSegmentTranslation( $tRow[ 'translation' ], $queryParams );
        $event->status                         = $tRow[ 'status' ];

        $srh->save( $event );
        $srh->updateIndex( $replace_version );

        $this->logger->debug( 'Replacement event for segment #' . $tRow[ 'id_segment' ] . ' correctly saved.' );
    }
}
