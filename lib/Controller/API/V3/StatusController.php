<?php

namespace API\V3;

use AMQHandler;
use Analysis\AnalysisDao;
use API\V2\Exceptions\NotFoundException;
use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\ProjectPasswordValidator;
use Chunks_ChunkStruct;
use Constants_JobStatus;
use Constants_ProjectStatus;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use Routes;
use TmKeyManagement_Filter;
use Url\JobUrlBuilder;

class StatusController extends KleinController {

    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    /**
     * @var Chunks_ChunkStruct[]
     */
    private $chunks;

    /**
     * @var array
     */
    private $projectResultSet;

    /**
     * @var int|null
     */
    private $othersInQueue;

    /**
     * @var array
     */
    private $chunksTotalsCache;

    /**
     * @var array
     */
    private $totalsInitStructure = [
            "TOTAL_PAYABLE"       => 0,
            "REPETITIONS"         => 0,
            "MT"                  => 0,
            "NEW"                 => 0,
            "TM_100"              => 0,
            "TM_100_PUBLIC"       => 0,
            "TM_75_99"            => 0,
            "TM_75_84"            => 0,
            "TM_85_94"            => 0,
            "TM_95_99"            => 0,
            "TM_50_74"            => 0,
            "INTERNAL_MATCHES"    => 0,
            "ICE"                 => 0,
            "NUMBERS_ONLY"        => 0,
            "eq_word_count"       => 0,
            "standard_word_count" => 0,
            "raw_word_count"      => 0,
    ];

    /**
     * Validation callbacks
     */
    public function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new ProjectPasswordValidator( $this ) );
    }

    /**
     * @throws NotFoundException
     * @throws \Exceptions\NotFoundException
     */
    public function index() {

        $noJobsFoundErrorMsg = 'The project doesn\'t have any jobs.';

        // params
        $id_project = $this->request->param( 'id_project' );
        $password   = $this->request->param( 'password' );

        // fetch data
        $this->fetchData( $id_project, $password );

        // return 404 if there are no chunks
        // (or they were deleted)
        $chunksCount = 0;

        if(!empty($this->chunks)){
            foreach ($this->chunks as $chunk){
                if($chunk->status_owner !== Constants_JobStatus::STATUS_DELETED){
                    $chunksCount++;
                }
            }
        }

        if($chunksCount === 0 ){
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                'errors' => [
                    [
                            'code' => 0,
                            'message' => $noJobsFoundErrorMsg
                    ]
                ]
            ] );
            exit();
        }

        // build project metadata
        try {
            $metadata = $this->renderProjectMetadata();
        } catch ( \Exception $exception ) {
            throw new NotFoundException( 'Error during rendering of project metadata' );
        }

        // build jobs metadata array
        foreach ( $this->chunks as $chunk ) {
            try {
                if($chunk->status_owner !== Constants_JobStatus::STATUS_DELETED){
                    $metadata->chunks[] = $this->renderChunkMetadata( $chunk );
                }
            } catch ( \Exception $exception ) {
                throw new NotFoundException( 'Error during rendering of job with id ' . $chunk->id );
            }
        }

        $this->response->json( $metadata );
    }

    /**
     * @param $id_project
     * @param $password
     *
     * @throws NotFoundException
     * @throws \Exceptions\NotFoundException
     */
    private function fetchData( $id_project, $password ) {

        $ttl  = 60 * 5;

        // get project and resultSet
        if ( null === $this->project = Projects_ProjectDao::findByIdAndPassword( $id_project, $password, $ttl ) ) {
            throw new NotFoundException( 'Project not found.' );
        }

        $this->chunks = $this->project->getChunks( $ttl );

        $this->projectResultSet = AnalysisDao::getProjectStatsVolumeAnalysis( $id_project, $ttl );

        try {
            $amqHandler         = new AMQHandler();
            $segmentsBeforeMine = $amqHandler->getActualForQID( $this->project->id );
        } catch ( \Exception $e ) {
            $segmentsBeforeMine = null;
        }

        $this->othersInQueue = ( $segmentsBeforeMine >= 0 ? $segmentsBeforeMine : 0 );
    }

    /**
     * @return \stdClass
     * @throws \Exception
     */
    private function renderProjectMetadata() {
        $projectMetaData              = new \stdClass();
        $projectMetaData->name        = $this->project->name;
        $projectMetaData->subject     = $this->chunks[ 0 ]->subject;
        $projectMetaData->engines     = $this->getProjectEngines();
        $projectMetaData->memory_keys = $this->getProjectMemoryKeys();
        $projectMetaData->status      = $this->getProjectStatusAnalysis();
        $projectMetaData->summary     = $this->getProjectSummary();
        $projectMetaData->analyze     = $this->getAnalyzeLink();

        return $projectMetaData;
    }

    /**
     * @return array
     */
    private function getProjectEngines() {
        return [
            'id_tms_engine' => (int)$this->chunks[ 0 ]->id_tms,
            'id_mt_engine'  => (int)$this->chunks[ 0 ]->id_mt_engine,
        ];
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function getProjectMemoryKeys() {
        $tmKeys  = [];
        $jobKeys = $this->chunks[ 0 ]->getClientKeys( $this->user, TmKeyManagement_Filter::OWNER )[ 'job_keys' ];

        foreach ( $jobKeys as $tmKey ) {
            $tmKeys[][ trim( $tmKey->name ) ] = trim( $tmKey->key );
        }

        return $tmKeys;
    }

    /**
     * @return string
     */
    private function getProjectStatusAnalysis() {
        switch ( $this->project->status_analysis ) {
            case 'NEW':
            case 'FAST_OK':
            case 'NOT_READY_FOR_ANALYSIS':
            case 'BUSY':
                return 'ANALYZING';
            case 'EMPTY':
                return 'NO_SEGMENTS_FOUND';
            case 'NOT_TO_ANALYZE':
                return 'ANALYSIS_NOT_ENABLED';
            case 'DONE':
                return 'DONE';
            default: //this can not be
                return 'FAIL';
        }
    }

    /**
     * @return array
     */
    private function getProjectSummary() {

        //array of totals per job-files
        $_total_segments_analyzed         = 0;
        $_total_wc_fast_analysis          = 0;
        $_total_wc_standard_fast_analysis = 0;
        $_total_raw_wc                    = 0;
        $_total_wc_tm_analysis            = 0;
        $_total_wc_standard_analysis      = 0;

        //
        // *****************************************
        // NOTE 2021-10-07
        // *****************************************
        //
        // IMPROVEMENT: $this->projectResultSet is now cached
        //
        // VERY Expensive cycle ± 0.7 s for 27650 segments ( 150k words )
        //
        foreach ( $this->projectResultSet as $segInfo ) {

            // $this->projectResultSet unique key with jid and password (needed for splitted jobs)
            $key = $segInfo[ 'jid' ] . "-" . $segInfo[ 'jpassword' ];

            if ( $segInfo[ 'st_status_analysis' ] == 'DONE' ) {
                $_total_segments_analyzed += 1;
            }

            if ( $_total_wc_fast_analysis == 0 and $segInfo[ 'fast_analysis_wc' ] > 0 ) {
                $_total_wc_fast_analysis = (float)$segInfo[ 'fast_analysis_wc' ];
            }

            if ( $_total_wc_standard_fast_analysis == 0 and $segInfo[ 'fast_analysis_wc' ] > 0 ) {
                $_total_wc_standard_fast_analysis = $segInfo[ 'fast_analysis_wc' ];
            }

            $words         = $segInfo[ 'raw_word_count' ];
            $eq_words      = $segInfo[ 'eq_word_count' ];
            $st_word_count = $segInfo[ 'standard_word_count' ];

            $_total_raw_wc               += $segInfo[ 'raw_word_count' ];
            $_total_wc_tm_analysis       += $eq_words;
            $_total_wc_standard_analysis += $st_word_count;

            // save chunks totals data in $this->chunksTotalsCache for getJobTotals() function
            $keyValue = $this->getTotalsArrayKeyName( $segInfo[ 'match_type' ] );

            $fileKey = ( null !== $segInfo[ 'id_file_part' ] and '' !== $segInfo[ 'id_file_part' ]  ) ? 'id_file_part' : 'id_file';
            $originalFile = ( null !== $segInfo[ 'tag_key' ] and $segInfo[ 'tag_key' ] === 'original'  ) ? $segInfo[ 'tag_value' ] : $segInfo[ 'filename' ];

            if ( !isset( $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ] ) ) {
                $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ] = $this->totalsInitStructure;
            }

            $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ][ 'id' ]                  = (int)$segInfo[ 'id_file' ];
            $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ][ 'id_file_part' ]        = ( null !== $segInfo[ 'id_file_part' ] and '' !== $segInfo[ 'id_file_part' ] ) ? (int)$segInfo[ 'id_file_part' ]  : null;
            $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ][ $keyValue ]             += $words;
            $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ][ 'eq_word_count' ]       += $segInfo[ 'eq_word_count' ];
            $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ][ 'standard_word_count' ] += $segInfo[ 'standard_word_count' ];
            $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ][ 'raw_word_count' ]      += $segInfo[ 'raw_word_count' ];
            $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ][ 'TOTAL_PAYABLE' ]       += $segInfo[ 'eq_word_count' ];
            $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ][ 'ORIGINAL_FILENAME' ]   = $originalFile;
            $this->chunksTotalsCache[ $key ][ $segInfo[ $fileKey ] ][ 'FILENAME' ]            = $segInfo[ 'filename' ];
        }

        if ( $_total_wc_standard_analysis == 0 and $this->project->status_analysis == Constants_ProjectStatus::STATUS_FAST_OK ) {

            $_total_wc_standard_analysis = $_total_wc_standard_fast_analysis;

        } elseif ( $_total_segments_analyzed == 0 && $this->project->status_analysis == Constants_ProjectStatus::STATUS_NEW ) {

            // Outsource Quote issue
            // fast analysis not done, return the number of raw word count
            // needed because the "getProjectStatsVolumeAnalysis" query based on segment_translations always returns null
            // ( no segment_translations )
            $project_data_fallback = Projects_ProjectDao::getProjectAndJobData( $this->project->id );

            $_total_wc_standard_analysis
                    = $_total_wc_tm_analysis
                    = $_total_raw_wc
                    = $project_data_fallback[ 0 ][ 'standard_analysis_wc' ];

        }

        // if fast quote has been done and tm analysis has not produced any result yet
        if ( $_total_wc_tm_analysis == 0
                and $this->project->status_analysis == Constants_ProjectStatus::STATUS_FAST_OK
                and $_total_wc_fast_analysis > 0
        ) {
            $_total_wc_tm_analysis = $_total_wc_fast_analysis;
        }

        $summary                        = [];
        $summary[ 'IN_QUEUE_BEFORE' ]   = $this->othersInQueue;
        $summary[ 'STATUS' ]            = $this->project->status_analysis;
        $summary[ 'TOTAL_SEGMENTS' ]    = count( $this->projectResultSet );
        $summary[ 'SEGMENTS_ANALYZED' ] = $_total_segments_analyzed;
        $summary[ 'TOTAL_STANDARD_WC' ] = $_total_wc_standard_analysis;
        $summary[ 'TOTAL_FAST_WC' ]     = $_total_wc_fast_analysis;
        $summary[ 'TOTAL_TM_WC' ]       = $_total_wc_tm_analysis;
        $summary[ 'TOTAL_RAW_WC' ]      = $_total_raw_wc;
        $summary[ 'TOTAL_PAYABLE' ]     = $_total_wc_tm_analysis;

        return $summary;
    }

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return \stdClass
     * @throws \Exception
     */
    private function renderChunkMetadata( \Chunks_ChunkStruct $chunk ) {

        $chunkMetadata           = new \stdClass();
        $chunkMetadata->id       = $chunk->id;
        $chunkMetadata->password = $chunk->password;
        $chunkMetadata->source   = $chunk->source;
        $chunkMetadata->source   = $chunk->source;
        $chunkMetadata->target   = $chunk->target;
        $chunkMetadata->details  = $this->getChunkDetails( $chunk->id, $chunk->password );
        $chunkMetadata->urls     = $this->getChunkUrls( $chunk );

        return $chunkMetadata;
    }

    /**
     * @param $chunkId
     * @param $chunkPassword
     *
     * @return \stdClass
     */
    private function getChunkDetails( $chunkId, $chunkPassword ) {
        $details         = new \stdClass();
        $details->files  = $this->getProjectFiles( $chunkId, $chunkPassword );
        $details->totals = $this->getProjectTotals( $chunkId, $chunkPassword );

        return $details;
    }

    /**
     * @param $chunkId
     * @param $chunkPassword
     *
     * @return array
     */
    private function getProjectFiles( $chunkId, $chunkPassword ) {
        return array_values( $this->chunksTotalsCache[ $chunkId . "-" . $chunkPassword ] );
    }

    /**
     * @param $chunkId
     * @param $chunkPassword
     *
     * @return \stdClass
     */
    private function getProjectTotals( $chunkId, $chunkPassword ) {

        $totals = new \stdClass();

        foreach ( $this->chunksTotalsCache[ $chunkId . "-" . $chunkPassword ] as $id => $chunk ) {
            foreach ( array_keys( $this->totalsInitStructure ) as $key ) {
                if ( !isset( $totals->$key ) ) {
                    $totals->$key = 0;
                }
                $totals->$key += $chunk[ $key ];
            }
        }

        return $totals;
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function getAnalyzeLink() {
        return Routes::analyze( [
                'project_name' => $this->project->name,
                'id_project'   => $this->project->id,
                'password'     => $this->project->password,
        ] );
    }

    /**
     * @param string $match_type
     *
     * @return string
     */
    private function getTotalsArrayKeyName( $match_type ) {
        if ( $match_type == "INTERNAL" ) {
            return 'INTERNAL_MATCHES';
        }

        if ( $match_type == "MT" ) {
            return 'MT';
        }

        if ( $match_type == "100%" ) {
            return 'TM_100';
        }

        if ( $match_type == "100%_PUBLIC" ) {
            return 'TM_100_PUBLIC';
        }

        if ( $match_type == "75%-99%" ) {
            return 'TM_75_99';
        }

        if ( $match_type == "75%-84%" ) {
            return 'TM_75_84';
        }

        if ( $match_type == "85%-94%" ) {
            return 'TM_85_94';
        }

        if ( $match_type == "95%-99%" ) {
            return 'TM_95_99';
        }

        if ( $match_type == "50%-74%" ) {
            return 'TM_50_74';
        }

        if ( $match_type == "NO_MATCH" or $match_type == "NEW" ) {
            return 'NEW';
        }

        if ( $match_type == "ICE" ) {
            return "ICE";
        }

        if ( $match_type == "REPETITIONS" ) {
            return 'REPETITIONS';
        }

        return 'NUMBERS_ONLY';
    }

    /**
     * @param \Jobs_JobStruct $job
     *
     * @return array
     * @throws \Exception
     */
    private function getChunkUrls( \Jobs_JobStruct $job ) {

        $jobUrlStruct = JobUrlBuilder::createFromJobStruct( $job, [], $this->project );

        return $jobUrlStruct->getUrls();
    }
}
