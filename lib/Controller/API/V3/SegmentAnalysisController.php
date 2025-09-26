<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\Analysis\Constants\ConstantsInterface;
use Model\Analysis\Constants\MatchConstantsFactory;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\EntryDao;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentNoteDao;
use Plugins\Features\ReviewExtended\ReviewUtils;
use ReflectionException;
use Utils\Tools\CatUtils;
use Utils\Url\JobUrls;

class SegmentAnalysisController extends KleinController {

    const MAX_PER_PAGE = 200;

    /**
     * @var ProjectStruct
     */
    private ProjectStruct $project;

    /**
     * @var \Model\Projects\ProjectDao
     */
    private ProjectDao $projectDao;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Segment list
     * from id job/password
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function job() {

        $page    = ( $this->request->param( 'page' ) ) ? (int)$this->request->param( 'page' ) : 1;
        $perPage = ( $this->request->param( 'per_page' ) ) ? (int)$this->request->param( 'per_page' ) : 50;

        if ( $perPage > self::MAX_PER_PAGE ) {
            $perPage = self::MAX_PER_PAGE;
        }

        $idJob         = $this->request->param( 'id_job' );
        $password      = $this->request->param( 'password' );
        $segmentsCount = JobDao::getSegmentsCount( $idJob, $password );

        // raise exception if the job does not exist
        $jobStruct     = ChunkDao::getByIdAndPassword( $idJob, $password );
        $this->project = $jobStruct->getProject();

        $mt_qe_workflow_enabled = $this->project->getMetadataValue( MetadataDao::MT_QE_WORKFLOW_ENABLED ) ?? false;
        $matchClass             = MatchConstantsFactory::getInstance( $mt_qe_workflow_enabled );
        $this->response->json( $this->getSegmentsForAJob( $jobStruct, $page, $perPage, $segmentsCount, $matchClass ) );
        exit();

    }

    /**
     * @param JobStruct          $jobStruct
     * @param int                $page
     * @param int                $perPage
     * @param int                $segmentsCount
     * @param ConstantsInterface $matchClass
     *
     * @return array
     * @throws ReflectionException
     * @throws Exception
     */
    private function getSegmentsForAJob( JobStruct $jobStruct, int $page, int $perPage, int $segmentsCount, ConstantsInterface $matchClass ): array {
        $totalPages = ceil( $segmentsCount / $perPage );
        $isLast     = ( $page === (int)$totalPages );

        if ( ( $page > $totalPages && $totalPages > 0 ) || $page <= 0 ) {
            throw new Exception( 'Page number ' . $page . ' is not valid' );
        }

        $prev  = ( $page > 1 ) ? "/api/app/jobs/" . $jobStruct->id . "/" . $jobStruct->password . "/segment-analysis?page=" . ( $page - 1 ) . "&per_page=" . $perPage : null;
        $next  = ( !$isLast and $totalPages > 1 ) ? "/api/app/jobs/" . $jobStruct->id . "/" . $jobStruct->password . "/segment-analysis?page=" . ( $page + 1 ) . "&per_page=" . $perPage : null;
        $items = $this->getSegmentsFromIdJobAndPassword( $jobStruct, $page, $perPage, $matchClass );

        return [
                'workflow_type' => $matchClass->getWorkflowType(),
                '_links'        => [
                        'page'        => $page,
                        'per_page'    => $perPage,
                        'total_pages' => $totalPages,
                        'total_items' => $segmentsCount,
                        'next_page'   => $next,
                        'prev_page'   => $prev,
                ],
                'items'         => $items,
        ];
    }

    /**
     * @param JobStruct          $jobStruct
     * @param int                $page
     * @param int                $perPage
     * @param ConstantsInterface $matchConstants
     *
     * @return array
     * @throws ReflectionException
     * @throws Exception
     */
    private function getSegmentsFromIdJobAndPassword( JobStruct $jobStruct, int $page, int $perPage, ConstantsInterface $matchConstants ): array {
        $segments         = [];
        $limit            = $perPage;
        $offset           = ( $page - 1 ) * $perPage;
        $this->projectDao = new ProjectDao();

        $segmentsForAnalysis      = SegmentDao::getSegmentsForAnalysisFromIdJobAndPassword( $jobStruct->id, $jobStruct->password, $limit, $offset );
        $projectPasswordsMap      = $this->projectDao->getPasswordsMap( $jobStruct->id_project );
        $issuesNotesAndIdRequests = $this->getIssuesNotesAndIdRequests( $segmentsForAnalysis );

        $notesAggregate      = $issuesNotesAndIdRequests[ 'notesAggregate' ];
        $issuesAggregate     = $issuesNotesAndIdRequests[ 'issuesAggregate' ];
        $idRequestsAggregate = $issuesNotesAndIdRequests[ 'idRequestsAggregate' ];

        foreach ( $segmentsForAnalysis as $segmentForAnalysis ) {
            $segments[] = $this->formatSegment( $segmentForAnalysis, $projectPasswordsMap, $notesAggregate, $issuesAggregate, $idRequestsAggregate, $matchConstants );
        }

        return $segments;
    }

    /**
     * Segment list
     * from id project/password
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    public function project() {

        $page    = ( $this->request->param( 'page' ) ) ? (int)$this->request->param( 'page' ) : 1;
        $perPage = ( $this->request->param( 'per_page' ) ) ? (int)$this->request->param( 'per_page' ) : 50;

        if ( $perPage > self::MAX_PER_PAGE ) {
            $perPage = self::MAX_PER_PAGE;
        }

        $idProject = $this->request->param( 'id_project' );
        $password  = $this->request->param( 'password' );

        $this->projectDao       = new ProjectDao();
        $this->project          = $this->projectDao->findByIdAndPassword( $idProject, $password );
        $mt_qe_workflow_enabled = $this->project->getMetadataValue( MetadataDao::MT_QE_WORKFLOW_ENABLED ) ?? false;
        $matchClass             = MatchConstantsFactory::getInstance( $mt_qe_workflow_enabled );
        $segmentsCount          = CatUtils::getSegmentTranslationsCount( $this->project );
        $this->response->json( $this->getSegmentsForAProject( $idProject, $password, $page, $perPage, $segmentsCount, $matchClass ) );
        exit();

    }

    /**
     * @param int                $idProject
     * @param string             $password
     * @param int                $page
     * @param int                $perPage
     * @param int                $segmentsCount
     * @param ConstantsInterface $matchClass
     *
     * @return array
     * @throws Exception
     */
    private function getSegmentsForAProject( int $idProject, string $password, int $page, int $perPage, int $segmentsCount, ConstantsInterface $matchClass ): array {
        $totalPages = ceil( $segmentsCount / $perPage );
        $isLast     = ( $page === (int)$totalPages );

        if ( $page > $totalPages or $page <= 0 ) {
            throw new Exception( 'Page number ' . $page . ' is not valid' );
        }

        $prev  = ( $page > 1 ) ? "/api/app/projects/" . $idProject . "/" . $password . "/segment-analysis?page=" . ( $page - 1 ) . "&per_page=" . $perPage : null;
        $next  = ( !$isLast and $totalPages > 1 ) ? "/api/app/projects/" . $idProject . "/" . $password . "/segment-analysis?page=" . ( $page + 1 ) . "&per_page=" . $perPage : null;
        $items = $this->getSegmentsFromIdProjectAndPassword( $idProject, $password, $page, $perPage, $matchClass );

        return [
                'workflow_type' => $matchClass->getWorkflowType(),
                '_links'        => [
                        'page'        => $page,
                        'per_page'    => $perPage,
                        'total_pages' => $totalPages,
                        'total_items' => $segmentsCount,
                        'next_page'   => $next,
                        'prev_page'   => $prev,
                ],
                'items'         => $items,
        ];
    }

    /**
     * @param int                $idProject
     * @param string             $password
     * @param int                $page
     * @param int                $perPage
     * @param ConstantsInterface $matchConstants
     *
     * @return array
     * @throws ReflectionException
     * @throws Exception
     */
    private function getSegmentsFromIdProjectAndPassword( int $idProject, string $password, int $page, int $perPage, ConstantsInterface $matchConstants ): array {
        $segments = [];
        $limit    = $perPage;
        $offset   = ( $page - 1 ) * $perPage;

        $segmentsForAnalysis      = SegmentDao::getSegmentsForAnalysisFromIdProjectAndPassword( $idProject, $password, $limit, $offset );
        $projectPasswordsMap      = $this->projectDao->getPasswordsMap( $this->project->id );
        $issuesNotesAndIdRequests = $this->getIssuesNotesAndIdRequests( $segmentsForAnalysis );

        $notesAggregate      = $issuesNotesAndIdRequests[ 'notesAggregate' ];
        $issuesAggregate     = $issuesNotesAndIdRequests[ 'issuesAggregate' ];
        $idRequestsAggregate = $issuesNotesAndIdRequests[ 'idRequestsAggregate' ];

        foreach ( $segmentsForAnalysis as $segmentForAnalysis ) {
            $segments[] = $this->formatSegment( $segmentForAnalysis, $projectPasswordsMap, $notesAggregate, $issuesAggregate, $idRequestsAggregate, $matchConstants );
        }

        return $segments;
    }

    /**
     * @param $segmentsForAnalysis
     *
     * @return array
     * @throws ReflectionException
     */
    private function getIssuesNotesAndIdRequests( $segmentsForAnalysis ): array {

        $segmentIds = [];

        foreach ( $segmentsForAnalysis as $segmentForAnalysis ) {
            $segmentIds[] = $segmentForAnalysis->id;
        }

        $notesRecords     = SegmentNoteDao::getBySegmentIds( $segmentIds );
        $issuesRecords    = EntryDao::getBySegmentIds( $segmentIds );
        $idRequestRecords = SegmentMetadataDao::getBySegmentIds( $segmentIds, 'id_request' );

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
                    'created_at'          => date( DATE_ATOM, strtotime( $issuesRecord->create_date ) ),
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
     * @param IDaoStruct         $segmentForAnalysis
     * @param array              $projectPasswordsMap
     * @param array              $notesAggregate
     * @param array              $issuesAggregate
     * @param array              $idRequestsAggregate
     * @param ConstantsInterface $matchConstants
     *
     * @return array
     * @throws Exception
     */
    private function formatSegment( IDaoStruct $segmentForAnalysis, array $projectPasswordsMap, array $notesAggregate, array $issuesAggregate, array $idRequestsAggregate, ConstantsInterface $matchConstants ): array {
        // id_request
        $idRequest = $idRequestsAggregate[ $segmentForAnalysis->id ] ?? null;

        /**
         * @var $segmentForAnalysis ShapelessConcreteStruct
         */
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

        /** @var MateCatFilter $filter */
        $jobStruct   = JobDao::getByIdAndPassword( (int)$segmentForAnalysis->id_job, $segmentForAnalysis->job_password );
        $metadataDao = new MetadataDao();
        $filter      = MateCatFilter::getInstance( $this->featureSet, $segmentForAnalysis->source, $segmentForAnalysis->target, [], $metadataDao->getSubfilteringCustomHandlers( (int)$jobStruct->id_project ) );

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
                'source_raw_word_count' => CatUtils::segment_raw_word_count( $segmentForAnalysis->segment, $segmentForAnalysis->source, $filter ),
                'target_raw_word_count' => CatUtils::segment_raw_word_count( $segmentForAnalysis->translation, $segmentForAnalysis->target, $filter ),
                'match_type'            => $matchConstants::toExternalMatchTypeName( $segmentForAnalysis->match_type ?? 'default' ),
                'revision_number'       => ( $segmentForAnalysis->source_page ) ? ReviewUtils::sourcePageToRevisionNumber( $segmentForAnalysis->source_page ) : null,
                'issues'                => $issues,
                'notes'                 => ( !empty( $notesAggregate[ $segmentForAnalysis->id ] ) ? $notesAggregate[ $segmentForAnalysis->id ] : [] ),
                'status'                => $this->getStatusObject( $segmentForAnalysis ),
                'last_edit'             => ( $segmentForAnalysis->last_edit !== null ) ? date( DATE_ATOM, strtotime( $segmentForAnalysis->last_edit ) ) : null,
        ];
    }

    /**
     * @param       $segmentForAnalysis
     * @param array $projectPasswordsMap
     *
     * @return array
     */
    private function getJobUrls( $segmentForAnalysis, array $projectPasswordsMap = [] ): array {
        $passwords = [];
        foreach ( $projectPasswordsMap as $map ) {
            if (
                    ( $segmentForAnalysis->id >= $map[ 'job_first_segment' ] and $segmentForAnalysis->id <= $map[ 'job_last_segment' ] ) and
                    $segmentForAnalysis->id_job == $map[ 'id_job' ] and
                    $segmentForAnalysis->job_password == $map[ 't_password' ]
            ) {
                $passwords[ JobUrls::LABEL_T ]  = $map[ 't_password' ];
                $passwords[ JobUrls::LABEL_R1 ] = $map[ 'r_password' ];
                $passwords[ JobUrls::LABEL_R2 ] = $map[ 'r2_password' ];
            }
        }

        $jobUrlStruct = new JobUrls(
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
    private function getStatusObject( $segmentForAnalysis ): array {
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
    private function humanReadableSourcePage( $sourcePage ): ?string {
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