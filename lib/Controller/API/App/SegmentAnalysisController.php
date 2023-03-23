<?php

namespace API\App;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use Features\ReviewExtended\ReviewUtils;
use LQA\EntryDao;
use Url\JobUrlBuilder;
use Exceptions\NotFoundException;
use Url\JobUrlStruct;

class SegmentAnalysisController extends KleinController {

    const MAX_PER_PAGE = 200;

    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk;

    /**
     * @var \Projects_ProjectStruct
     */
    private $project;

    /**
     * @var \Projects_ProjectDao
     */
    private $projectDao;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Segment list
     * from id project/password
     */
    public function project() {

        $page = ($this->request->param('page')) ? (int)$this->request->param('page') : 1;
        $perPage = ($this->request->param('per_page')) ? (int)$this->request->param('per_page') : 50;

        if($perPage > self::MAX_PER_PAGE){
            $perPage = self::MAX_PER_PAGE;
        }

        $idProject = $this->request->param('id_project');
        $password = $this->request->param('password');

        $this->projectDao = new \Projects_ProjectDao();

        try {
            $this->project = $this->projectDao->findByIdAndPassword($idProject, $password);
        } catch (NotFoundException $exception) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => [
                            'message' => $exception->getMessage()
                    ]
            ] );
            exit();
        }

        $segmentsCount = \CatUtils::getSegmentTranslationsCount($this->project);

        try {
            $this->response->json($this->getSegmentsForAProject($idProject, $password, $page, $perPage, $segmentsCount));
            exit();
        } catch (\Exception $exception){
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
     * @throws \Exception
     */
    private function getSegmentsForAProject($idProject, $password, $page, $perPage, $segmentsCount)
    {
        $totalPages = ceil($segmentsCount/$perPage);
        $isLast = ((int)$page === (int)$totalPages);

        if($page > $totalPages or $page <= 0){
            throw new \Exception('Page number '.$page.' is not valid');
        }

        $prev = ($page > 1 ) ? "/api/app/projects/".$idProject."/".$password."/segment-analysis?page=".($page-1)."&per_page=".$perPage : null;
        $next = (!$isLast and $totalPages > 1) ? "/api/app/projects/".$idProject."/".$password."/segment-analysis?page=".($page+1)."&per_page=".$perPage : null;
        $items = $this->getSegmentsFromIdProjectAndPassword($idProject, $password, $page, $perPage);

        return [
                '_links' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total_pages' => $totalPages,
                        'total_items' => $segmentsCount,
                        'next_page' => $next,
                        'prev_page' => $prev,
                ],
                'items' => $items
        ];
    }

    /**
     * @param $idProject
     * @param $password
     * @param $page
     * @param $perPage
     *
     * @return array
     * @throws \Exception
     */
    private function getSegmentsFromIdProjectAndPassword($idProject, $password, $page, $perPage)
    {
        $segments = [];
        $limit = $perPage;
        $offset = ($page-1)*$perPage;

        $segmentsForAnalysis = \Segments_SegmentDao::getSegmentsForAnalysisFromIdProjectAndPassword($idProject, $password, $limit, $offset, 0);
        $projectPasswordsMap  = $this->projectDao->getPasswordsMap($this->project->id);

        foreach ( $segmentsForAnalysis as $segmentForAnalysis ){
            $segments[] = $this->formatSegment($segmentForAnalysis, $projectPasswordsMap);
        }

        return $segments;
    }

    /**
     * @param \DataAccess_IDaoStruct $segmentForAnalysis
     *
     * @return array
     * @throws \Exception
     */
    private function formatSegment(\DataAccess_IDaoStruct $segmentForAnalysis, $projectPasswordsMap)
    {
        // id_request
        $idRequest = @\Segments_SegmentMetadataDao::get($segmentForAnalysis->id, 'id_request')[ 0 ];

        // issues
        $issues_records = EntryDao::findAllBySegmentId( $segmentForAnalysis->id );
        $issues         = [];
        foreach ( $issues_records as $issue_record ) {
            $issues[] = [
                    'source_page'         => $this->humanReadableSourcePage($issue_record->source_page),
                    'id_category'         => (int)$issue_record->id_category,
                    'severity'            => $issue_record->severity,
                    'translation_version' => (int)$issue_record->translation_version,
                    'penalty_points'      => floatval($issue_record->penalty_points),
                    'created_at'          => date( 'c', strtotime( $issue_record->create_date ) ),
            ];
        }

        // original_filename
        $originalFile = ( null !== $segmentForAnalysis->tag_key and $segmentForAnalysis->tag_key === 'original' ) ? $segmentForAnalysis->tag_value : $segmentForAnalysis->filename;

        return [
                'id_segment' => (int)$segmentForAnalysis->id,
                'id_chunk' => (int)$segmentForAnalysis->id_job,
                'chunk_password' => $segmentForAnalysis->job_password,
                'urls' => $this->getJobUrls($segmentForAnalysis, $projectPasswordsMap),
                'id_request' => ($idRequest) ? $idRequest->meta_value : null,
                'filename' => $segmentForAnalysis->filename,
                'original_filename' => $originalFile,
                'source' => $segmentForAnalysis->segment,
                'target' => $segmentForAnalysis->translation,
                'source_lang' => $segmentForAnalysis->source,
                'target_lang' => $segmentForAnalysis->target,
                'source_raw_word_count' => \CatUtils::segment_raw_word_count( $segmentForAnalysis->segment, $segmentForAnalysis->source ),
                'target_raw_word_count' => \CatUtils::segment_raw_word_count( $segmentForAnalysis->translation, $segmentForAnalysis->target ),
                'match_type' => $this->humanReadableMatchType($segmentForAnalysis->match_type),
                'revision_number' => ($segmentForAnalysis->source_page) ? ReviewUtils::sourcePageToRevisionNumber($segmentForAnalysis->source_page) : null,
                'issues' => $issues,
                'status' => $this->getStatusObject($segmentForAnalysis),
        ];
    }

    /**
     * @param $segmentForAnalysis
     * @param array $projectPasswordsMap
     * @return array
     */
    private function getJobUrls($segmentForAnalysis, array $projectPasswordsMap = [])
    {
        $passwords = [];
        foreach ($projectPasswordsMap as $map){
            if($segmentForAnalysis->id_job == $map['id_job'] and $segmentForAnalysis->job_password == $map['t_password']){
                $passwords[JobUrlStruct::LABEL_T] = $map['t_password'];
                $passwords[JobUrlStruct::LABEL_R1] = $map['r_password'];
                $passwords[JobUrlStruct::LABEL_R2] = $map['r2_password'];
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
     * @return array
     */
    private function getStatusObject($segmentForAnalysis)
    {
        $finalVersion = null;

        if($segmentForAnalysis->source_page == 1){
            $finalVersion = 't';
        } elseif($segmentForAnalysis->source_page == 2){
            $finalVersion = 'r1';
        } elseif($segmentForAnalysis->source_page == 3){
            $finalVersion = 'r2';
        }

        $r1 = ($segmentForAnalysis->has_r1 !== null) ? $segmentForAnalysis->raw_word_count : 0;
        $r2 = ($segmentForAnalysis->has_r2 !== null) ? $segmentForAnalysis->raw_word_count : 0;

        if($finalVersion === 't'){
            $r1 = null;
            $r2 = null;
        }

        if($finalVersion === 'r1' and $segmentForAnalysis->has_r1 !== null){
            $r2 = null;
        }

        return [
            'translation_status' => $segmentForAnalysis->status,
            'final_version' => $finalVersion,
            'counts' => [
                'r1' => $r1,
                'r2' => $r2,
            ]
        ];
    }

    /**
     * @param $sourcePage
     * @return string|null
     */
    private function humanReadableSourcePage($sourcePage)
    {
        if($sourcePage == 1){
            return 't';
        }

        if($sourcePage == 2){
            return 'r1';
        }

        if($sourcePage == 3){
            return 'r2';
        }

        return null;
    }

    /**
     * @param string $match_type
     *
     * @return string
     */
    public function humanReadableMatchType( $match_type){
        switch ($match_type) {
            case "INTERNAL":
                return 'INTERNAL_MATCHES';

            case "MT":
                return 'MT';

            case "100%":
                return 'TM_100';

            case "100%_PUBLIC":
                return 'TM_100_PUBLIC';

            case "75%-99%":
                return 'TM_75_99';

            case "75%-84%":
                return 'TM_75_84';

            case "85%-94%":
                return 'TM_85_94';

            case "95%-99%":
                return 'TM_95_99';

            case "50%-74%":
                return 'TM_50_74';

            case "NEW":
            case "NO_MATCH":
                return 'NEW';

            case "ICE":
                return 'ICE';

            case "REPETITIONS":
                return 'REPETITIONS';
        }

        return 'NUMBERS_ONLY';
    }
}