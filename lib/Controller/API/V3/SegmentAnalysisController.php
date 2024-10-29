<?php

namespace API\V3;

use API\App\Json\Analysis\MatchConstants;
use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use CatUtils;
use Chunks_ChunkDao;
use DataAccess_IDaoStruct;
use Database;
use Exception;
use Exceptions\NotFoundException;
use Features\ReviewExtended\ReviewUtils;
use Jobs_JobDao;
use Jobs_JobStruct;
use LQA\EntryDao;
use Pagination\Pager;
use Pagination\PaginationParameters;
use Projects\ProjectTemplateStruct;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use ReflectionException;
use Segments_SegmentDao;
use Segments_SegmentMetadataDao;
use Segments_SegmentNoteDao;
use Url\JobUrlStruct;

class SegmentAnalysisController extends KleinController {

    const MAX_PER_PAGE = 200;

    /**
     * @var Projects_ProjectStruct
     */
    private Projects_ProjectStruct $project;

    /**
     * @var Projects_ProjectDao
     */
    private Projects_ProjectDao $projectDao;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Segment list
     * from id job/password
     * @throws ReflectionException
     */
    public function job() {

        $page    = ( $this->request->param( 'page' ) ) ? (int)$this->request->param( 'page' ) : 1;
        $perPage = ( $this->request->param( 'per_page' ) ) ? (int)$this->request->param( 'per_page' ) : 50;

        if ( $perPage > self::MAX_PER_PAGE ) {
            $perPage = self::MAX_PER_PAGE;
        }

        $idJob         = $this->request->param( 'id_job' );
        $password      = $this->request->param( 'password' );
        $segmentsCount = Jobs_JobDao::getSegmentsCount( $idJob, $password, 0 );

        try {
            $this->response->json( $this->getSegmentsForAJob( $idJob, $password, $page, $perPage, $segmentsCount ) );
            exit();
        } catch ( Exception $exception ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => [
                            'message' => $exception->getMessage()
                    ]
            ] );
        }
    }

    /**
     * @param $idJob
     * @param $password
     * @param $page
     * @param $perPage
     * @param $segmentsCount
     *
     * @return array
     * @throws Exception
     */
    private function getSegmentsForAJob( $idJob, $password, $page, $perPage, $segmentsCount ): array {
        $totalPages = ceil( $segmentsCount / $perPage );
        $isLast     = ( (int)$page === (int)$totalPages );

        if ( ( $page > $totalPages && $totalPages > 0 ) ||  $page <= 0 ) {
            throw new Exception( 'Page number ' . $page . ' is not valid' );
        }

        // raise exception if the job does not exist
        $jobStruct = Chunks_ChunkDao::getByIdAndPassword( $idJob, $password, 60 * 5 );

        $prev  = ( $page > 1 ) ? "/api/app/jobs/" . $idJob . "/" . $password . "/segment-analysis?page=" . ( $page - 1 ) . "&per_page=" . $perPage : null;
        $next  = ( !$isLast and $totalPages > 1 ) ? "/api/app/jobs/" . $idJob . "/" . $password . "/segment-analysis?page=" . ( $page + 1 ) . "&per_page=" . $perPage : null;
        $items = $this->getSegmentsFromIdJobAndPassword( $jobStruct, $page, $perPage );

        return [
                '_links' => [
                        'page'        => $page,
                        'per_page'    => $perPage,
                        'total_pages' => $totalPages,
                        'total_items' => $segmentsCount,
                        'next_page'   => $next,
                        'prev_page'   => $prev,
                ],
                'items'  => $items
        ];
    }

    /**
     * @param Jobs_JobStruct $jobStruct
     * @param int            $page
     * @param int            $perPage
     *
     * @return array
     * @throws Exception
     */
    private function getSegmentsFromIdJobAndPassword( Jobs_JobStruct $jobStruct, int $page, int $perPage ): array {
        $segments         = [];
        $limit            = $perPage;
        $offset           = ( $page - 1 ) * $perPage;
        $this->projectDao = new Projects_ProjectDao();

        $segmentsForAnalysis      = Segments_SegmentDao::getSegmentsForAnalysisFromIdJobAndPassword( $jobStruct->id, $jobStruct->password, $limit, $offset, 0 );
        $projectPasswordsMap      = $this->projectDao->getPasswordsMap( $jobStruct->id_project );
        $issuesNotesAndIdRequests = $this->getIssuesNotesAndIdRequests( $segmentsForAnalysis );

        $notesAggregate      = $issuesNotesAndIdRequests[ 'notesAggregate' ];
        $issuesAggregate     = $issuesNotesAndIdRequests[ 'issuesAggregate' ];
        $idRequestsAggregate = $issuesNotesAndIdRequests[ 'idRequestsAggregate' ];

        foreach ( $segmentsForAnalysis as $segmentForAnalysis ) {
            $segments[] = $this->formatSegment( $segmentForAnalysis, $projectPasswordsMap, $notesAggregate, $issuesAggregate, $idRequestsAggregate );
        }

        return $segments;
    }

    /**
     * Segment list
     * from id project/password
     */
    public function project() {

        $page    = ( $this->request->param( 'page' ) ) ? (int)$this->request->param( 'page' ) : 1;
        $perPage = ( $this->request->param( 'per_page' ) ) ? (int)$this->request->param( 'per_page' ) : 50;

        if ( $perPage > self::MAX_PER_PAGE ) {
            $perPage = self::MAX_PER_PAGE;
        }

        $idProject = $this->request->param( 'id_project' );
        $password  = $this->request->param( 'password' );

        $this->projectDao = new Projects_ProjectDao();

        try {
            $this->project = $this->projectDao->findByIdAndPassword( $idProject, $password );
        } catch ( NotFoundException $exception ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => [
                            'message' => $exception->getMessage()
                    ]
            ] );
            exit();
        }

        $segmentsCount = CatUtils::getSegmentTranslationsCount( $this->project );

        try {
            $this->response->json( $this->getSegmentsForAProject( $idProject, $password, $page, $perPage, $segmentsCount ) );
            exit();
        } catch ( Exception $exception ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => [
                            'message' => $exception->getMessage()
                    ]
            ] );
        }
    }

    /**
     * @param $idProject
     * @param $password
     * @param $page
     * @param $perPage
     * @param $segmentsCount
     *
     * @return array
     * @throws Exception
     */
    private function getSegmentsForAProject( $idProject, $password, $page, $perPage, $segmentsCount ) {
        $totalPages = ceil( $segmentsCount / $perPage );
        $isLast     = ( (int)$page === (int)$totalPages );

        if ( $page > $totalPages or $page <= 0 ) {
            throw new Exception( 'Page number ' . $page . ' is not valid' );
        }

        $prev  = ( $page > 1 ) ? "/api/app/projects/" . $idProject . "/" . $password . "/segment-analysis?page=" . ( $page - 1 ) . "&per_page=" . $perPage : null;
        $next  = ( !$isLast and $totalPages > 1 ) ? "/api/app/projects/" . $idProject . "/" . $password . "/segment-analysis?page=" . ( $page + 1 ) . "&per_page=" . $perPage : null;
        $items = $this->getSegmentsFromIdProjectAndPassword( $idProject, $password, $page, $perPage );

        return [
                '_links' => [
                        'page'        => $page,
                        'per_page'    => $perPage,
                        'total_pages' => $totalPages,
                        'total_items' => $segmentsCount,
                        'next_page'   => $next,
                        'prev_page'   => $prev,
                ],
                'items'  => $items
        ];
    }

    /**
     * @param $idProject
     * @param $password
     * @param $page
     * @param $perPage
     *
     * @return array
     * @throws Exception
     */
    private function getSegmentsFromIdProjectAndPassword( $idProject, $password, $page, $perPage ) {
        $segments = [];
        $limit    = $perPage;
        $offset   = ( $page - 1 ) * $perPage;

        $segmentsForAnalysis      = Segments_SegmentDao::getSegmentsForAnalysisFromIdProjectAndPassword( $idProject, $password, $limit, $offset, 0 );
        $projectPasswordsMap      = $this->projectDao->getPasswordsMap( $this->project->id );
        $issuesNotesAndIdRequests = $this->getIssuesNotesAndIdRequests( $segmentsForAnalysis );

        $notesAggregate      = $issuesNotesAndIdRequests[ 'notesAggregate' ];
        $issuesAggregate     = $issuesNotesAndIdRequests[ 'issuesAggregate' ];
        $idRequestsAggregate = $issuesNotesAndIdRequests[ 'idRequestsAggregate' ];

        foreach ( $segmentsForAnalysis as $segmentForAnalysis ) {
            $segments[] = $this->formatSegment( $segmentForAnalysis, $projectPasswordsMap, $notesAggregate, $issuesAggregate, $idRequestsAggregate );
        }

        return $segments;
    }

    /**
     * @param $segmentsForAnalysis
     *
     * @return array
     */
    private function getIssuesNotesAndIdRequests( $segmentsForAnalysis ) {
        $segmentIds = [];
        foreach ( $segmentsForAnalysis as $segmentForAnalysis ) {
            $segmentIds[] = $segmentForAnalysis->id;
        }

        $notesRecords     = Segments_SegmentNoteDao::getBySegmentIds( $segmentIds );
        $issuesRecords    = EntryDao::getBySegmentIds( $segmentIds );
        $idRequestRecords = Segments_SegmentMetadataDao::getBySegmentIds( $segmentIds, 'id_request' );

        $notesAggregate      = [];
        $issuesAggregate     = [];
        $idRequestsAggregate = [];

        foreach ( $notesRecords as $notesRecord ) {
            $notesAggregate[ $notesRecord->id_segment ][] = $notesRecord->note;
        }

        foreach ( $issuesRecords as $issuesRecord ) {
            $issuesAggregate[ $issuesRecord->id_job ][ $issuesRecord->id_segment ][] = [
                    'source_page'         => $this->humanReadableSourcePage( $issuesRecord->source_page ),
                    'id_category'         => (int)$issuesRecord->id_category,
                    'category'            => $issuesRecord->cat_label,
                    'severity'            => $issuesRecord->severity,
                    'translation_version' => (int)$issuesRecord->translation_version,
                    'penalty_points'      => floatval( $issuesRecord->penalty_points ),
                    'created_at'          => date( DATE_ISO8601, strtotime( $issuesRecord->create_date ) ),
            ];
        }

        foreach ( $idRequestRecords as $idRequestRecord ) {
            $idRequestsAggregate[ $idRequestRecord->id_segment ] = $idRequestRecord;
        }

        return [
                'notesAggregate'      => $notesAggregate,
                'issuesAggregate'     => $issuesAggregate,
                'idRequestsAggregate' => $idRequestsAggregate,
        ];
    }

    /**
     * @param DataAccess_IDaoStruct $segmentForAnalysis
     * @param                       $projectPasswordsMap
     * @param                       $notesAggregate
     * @param                       $issuesAggregate
     *
     * @return array
     * @throws Exception
     */
    private function formatSegment( DataAccess_IDaoStruct $segmentForAnalysis, $projectPasswordsMap, $notesAggregate, $issuesAggregate, $idRequestsAggregate ) {
        // id_request
        $idRequest = $idRequestsAggregate[ $segmentForAnalysis->id ] ?? null;

        // original_filename
        $originalFile = ( null !== $segmentForAnalysis->tag_key and $segmentForAnalysis->tag_key === 'original' ) ? $segmentForAnalysis->tag_value : $segmentForAnalysis->filename;

        // issues
        $issues = [];
        if (
                isset( $issuesAggregate[ $segmentForAnalysis->id_job ] ) and
                isset( $issuesAggregate[ $segmentForAnalysis->id_job ][ $segmentForAnalysis->id ] ) and
                !empty( $issuesAggregate[ $segmentForAnalysis->id_job ][ $segmentForAnalysis->id ] )
        ) {
            $issues = $issuesAggregate[ $segmentForAnalysis->id_job ][ $segmentForAnalysis->id ];
        }

        return [
                'id_segment'            => (int)$segmentForAnalysis->id,
                'id_chunk'              => (int)$segmentForAnalysis->id_job,
                'chunk_password'        => $segmentForAnalysis->job_password,
                'urls'                  => $this->getJobUrls( $segmentForAnalysis, $projectPasswordsMap ),
                'id_request'            => ( $idRequest ) ? $idRequest->meta_value : null,
                'filename'              => $segmentForAnalysis->filename,
                'original_filename'     => $originalFile,
                'source'                => $segmentForAnalysis->segment,
                'target'                => $segmentForAnalysis->translation,
                'source_lang'           => $segmentForAnalysis->source,
                'target_lang'           => $segmentForAnalysis->target,
                'source_raw_word_count' => CatUtils::segment_raw_word_count( $segmentForAnalysis->segment, $segmentForAnalysis->source ),
                'target_raw_word_count' => CatUtils::segment_raw_word_count( $segmentForAnalysis->translation, $segmentForAnalysis->target ),
                'match_type'            => MatchConstants::toExternalMatchTypeValue( $segmentForAnalysis->match_type ?? 'NEW' ),
                'revision_number'       => ( $segmentForAnalysis->source_page ) ? ReviewUtils::sourcePageToRevisionNumber( $segmentForAnalysis->source_page ) : null,
                'issues'                => $issues,
                'notes'                 => ( !empty( $notesAggregate[ $segmentForAnalysis->id ] ) ? $notesAggregate[ $segmentForAnalysis->id ] : [] ),
                'status'                => $this->getStatusObject( $segmentForAnalysis ),
                'last_edit'             => ( $segmentForAnalysis->last_edit !== null ) ? date( DATE_ISO8601, strtotime( $segmentForAnalysis->last_edit ) ) : null,
        ];
    }

    /**
     * @param       $segmentForAnalysis
     * @param array $projectPasswordsMap
     *
     * @return array
     */
    private function getJobUrls( $segmentForAnalysis, array $projectPasswordsMap = [] ) {
        $passwords = [];
        foreach ( $projectPasswordsMap as $map ) {
            if (
                    ( $segmentForAnalysis->id >= $map[ 'job_first_segment' ] and $segmentForAnalysis->id <= $map[ 'job_last_segment' ] ) and
                    $segmentForAnalysis->id_job == $map[ 'id_job' ] and
                    $segmentForAnalysis->job_password == $map[ 't_password' ]
            ) {
                $passwords[ JobUrlStruct::LABEL_T ]  = $map[ 't_password' ];
                $passwords[ JobUrlStruct::LABEL_R1 ] = $map[ 'r_password' ];
                $passwords[ JobUrlStruct::LABEL_R2 ] = $map[ 'r2_password' ];
            }
        }

        $jobUrlStruct = new JobUrlStruct(
                $segmentForAnalysis->id_job,
                $segmentForAnalysis->project_name,
                $segmentForAnalysis->source,
                $segmentForAnalysis->target,
                $passwords,
                null,
                $segmentForAnalysis->id
        );

        return $jobUrlStruct->getUrls();
    }

    /**
     * @param $segmentForAnalysis
     *
     * @return array
     */
    private function getStatusObject( $segmentForAnalysis ) {
        $finalVersion = null;

        if ( $segmentForAnalysis->source_page == 1 ) {
            $finalVersion = 't';
        } elseif ( $segmentForAnalysis->source_page == 2 ) {
            $finalVersion = 'r1';
        } elseif ( $segmentForAnalysis->source_page == 3 ) {
            $finalVersion = 'r2';
        }

        $r1 = ( $segmentForAnalysis->has_r1 !== null ) ? $segmentForAnalysis->raw_word_count : 0;
        $r2 = ( $segmentForAnalysis->has_r2 !== null ) ? $segmentForAnalysis->raw_word_count : 0;

        if ( $finalVersion === 't' ) {
            $r1 = null;
            $r2 = null;
        }

        if ( $finalVersion === 'r1' and $segmentForAnalysis->has_r1 !== null ) {
            $r2 = null;
        }

        return [
                'translation_status' => $segmentForAnalysis->status,
                'final_version'      => $finalVersion,
                'counts'             => [
                        'r1' => $r1,
                        'r2' => $r2,
                ]
        ];
    }

    /**
     * @param $sourcePage
     *
     * @return string|null
     */
    private function humanReadableSourcePage( $sourcePage ) {
        if ( $sourcePage == 1 ) {
            return 't';
        }

        if ( $sourcePage == 2 ) {
            return 'r1';
        }

        if ( $sourcePage == 3 ) {
            return 'r2';
        }

        return null;
    }

}