<?php

namespace API\V3;

use Analysis\AnalysisDao;
use API\V2\Exceptions\NotFoundException;
use API\V2\KleinController;
use Constants_ProjectStatus;
use Projects_ProjectDao;
use Url\JobUrlBuilder;

class StatusController extends KleinController {

    /**
     * @var \Projects_ProjectStruct
     */
    private $project;

    /**
     * @var array
     */
    private $projectResultSet;

    /**
     * @var int|null
     */
    private $othersInQueue;

    /**
     * @var
     */
    private $chunksTotalsCache;

    /**
     * @var array
     */
    private $totalsInitStructure = [
            "TOTAL_PAYABLE"       => [ 0, "0" ],
            "REPETITIONS"         => [ 0, "0" ],
            "MT"                  => [ 0, "0" ],
            "NEW"                 => [ 0, "0" ],
            "TM_100"              => [ 0, "0" ],
            "TM_100_PUBLIC"       => [ 0, "0" ],
            "TM_75_99"            => [ 0, "0" ],
            "TM_75_84"            => [ 0, "0" ],
            "TM_85_94"            => [ 0, "0" ],
            "TM_95_99"            => [ 0, "0" ],
            "TM_50_74"            => [ 0, "0" ],
            "INTERNAL_MATCHES"    => [ 0, "0" ],
            "ICE"                 => [ 0, "0" ],
            "NUMBERS_ONLY"        => [ 0, "0" ],
            "eq_word_count"       => [ 0, "0" ],
            "standard_word_count" => [ 0, "0" ],
            "raw_word_count"      => [ 0, "0" ],
    ];

    /**
     * @throws NotFoundException
     * @throws \Exceptions\NotFoundException
     */
    public function index() {

        // params
        $id_project = $this->request->param( 'id_project' );
        $password   = $this->request->param( 'password' );

        // fetch data
        $this->fetchData( $id_project, $password );

        // build project metadata
        try {
            $metadata = $this->renderProjectMetadata();
        } catch ( \Exception $exception ) {
            throw new NotFoundException( 'Error during rendering of project metadata' );
        }

        // build jobs metadata array
        foreach ( $this->project->getJobs() as $job ) {
            try {
                $metadata->jobs[] = $this->renderJobMetadata( $job );
            } catch ( \Exception $exception ) {
                throw new NotFoundException( 'Error during rendering of job with id ' . $job->id );
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

        // get project and resultSet
        if ( null === $this->project = \Projects_ProjectDao::findByIdAndPassword( $id_project, $password ) ) {
            throw new NotFoundException( 'Project not found.' );
        }

        $this->projectResultSet = AnalysisDao::getProjectStatsVolumeAnalysis( $id_project );
        try {
            $amqHandler         = new \AMQHandler();
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
        $projectMetaData          = new \stdClass();
        $projectMetaData->status  = $this->getProjectStatusAnalysis();
        $projectMetaData->summary = $this->getProjectSummary();
        $projectMetaData->analyze = $this->getAnalyzeLink();

        return $projectMetaData;
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
        $_matecat_price_per_word          = 0.03; //(dollari) se indipendente dalla combinazione metterlo nel config
        $_standard_price_per_word         = 0.10; //(dollari) se indipendente dalla combinazione metterlo nel config

        //VERY Expensive cycle ± 0.7 s for 27650 segments ( 150k words )
        foreach ( $this->projectResultSet as $segInfo ) {

            if ( $segInfo[ 'st_status_analysis' ] == 'DONE' ) {
                $_total_segments_analyzed += 1;
            }

            if ( $_total_wc_fast_analysis == 0 and $segInfo[ 'fast_analysis_wc' ] > 0 ) {
                $_total_wc_fast_analysis = $segInfo[ 'fast_analysis_wc' ];
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

            if ( !isset( $totals->chunks[ $segInfo[ 'id_file' ] ] ) ) {
                $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ] = $this->totalsInitStructure;
            }

            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ $keyValue ][ 0 ]             = +$words;
            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ $keyValue ][ 1 ]             = $this->numberToPrint( $this->chunksTotalsCache[ $keyValue ][ 0 ] );
            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'eq_word_count' ][ 0 ]       += $segInfo[ 'eq_word_count' ];
            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'eq_word_count' ][ 1 ]       = $this->numberToPrint( $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'eq_word_count' ][ 0 ] );
            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'standard_word_count' ][ 0 ] += $segInfo[ 'standard_word_count' ];
            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'standard_word_count' ][ 1 ] = $this->numberToPrint( $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'standard_word_count' ][ 0 ] );
            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'raw_word_count' ][ 0 ]      += $segInfo[ 'raw_word_count' ];
            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'raw_word_count' ][ 1 ]      = $this->numberToPrint( $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'raw_word_count' ][ 0 ] );
            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'TOTAL_PAYABLE' ][ 0 ]       += $segInfo[ 'eq_word_count' ];
            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'TOTAL_PAYABLE' ][ 1 ]       = $this->numberToPrint( $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'TOTAL_PAYABLE' ][ 0 ] );
            $this->chunksTotalsCache[ $segInfo[ 'id_file' ] ][ 'FILENAME' ]                 = $segInfo[ 'filename' ];

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

        // Useless???
        if ( $_total_wc_fast_analysis > 0 ) {
            $discount_wc = round( 100 * $_total_wc_tm_analysis / $_total_wc_fast_analysis );
        }

        $discount_wc = 0;

        $standard_wc_time = $_total_wc_standard_analysis / \INIT::$ANALYSIS_WORDS_PER_DAYS;
        $tm_wc_time       = $_total_wc_tm_analysis / \INIT::$ANALYSIS_WORDS_PER_DAYS;
        $fast_wc_time     = $_total_wc_fast_analysis / \INIT::$ANALYSIS_WORDS_PER_DAYS;

        $standard_wc_unit = 'day';
        $tm_wc_unit       = 'day';
        $fast_wc_unit     = 'day';

        if ( $standard_wc_time > 0 and $standard_wc_time < 1 ) {
            $standard_wc_time *= 8; //convert to hours (1 work day = 8 hours)
            $standard_wc_unit = 'hour';
        }
        if ( $standard_wc_time > 0 and $standard_wc_time < 1 ) {
            $standard_wc_time *= 60; //convert to minutes
            $standard_wc_unit = 'minute';
        }

        if ( $tm_wc_time > 0 and $tm_wc_time < 1 ) {
            $tm_wc_time *= 8; //convert to hours (1 work day = 8 hours)
            $tm_wc_unit = 'hour';
        }

        if ( $tm_wc_time > 0 and $tm_wc_time < 1 ) {
            $tm_wc_time *= 60; //convert to minutes
            $tm_wc_unit = 'minute';
        }

        if ( $fast_wc_time > 0 and $fast_wc_time < 1 ) {
            $fast_wc_time *= 8; //convert to hours (1 work day = 8 hours)
            $fast_wc_unit = 'hour';
        }

        if ( $fast_wc_time > 0 and $fast_wc_time < 1 ) {
            $fast_wc_time *= 60; //convert to minutes
            $fast_wc_unit = 'minute';
        }

        if ( $standard_wc_time > 1 ) {
            $standard_wc_unit .= 's';
        }

        if ( $fast_wc_time > 1 ) {
            $fast_wc_unit .= 's';
        }

        if ( $tm_wc_time > 1 ) {
            $tm_wc_unit .= 's';
        }

        $matecat_fee  = ( $_total_wc_fast_analysis - $_total_wc_tm_analysis ) * $_matecat_price_per_word;
        $standard_fee = ( $_total_wc_standard_analysis - $_total_wc_tm_analysis ) * $_standard_price_per_word;
        $discount     = round( $standard_fee - $matecat_fee );

        $summary                        = [];
        $summary[ 'NAME' ]              = $this->project->name;
        $summary[ 'IN_QUEUE_BEFORE' ]   = $this->othersInQueue;
        $summary[ 'STATUS' ]            = $this->project->status_analysis;
        $summary[ 'TOTAL_SEGMENTS' ]    = count( $this->projectResultSet );
        $summary[ 'SEGMENTS_ANALYZED' ] = $_total_segments_analyzed;
        $summary[ 'TOTAL_STANDARD_WC' ] = $_total_wc_standard_analysis;
        $summary[ 'TOTAL_FAST_WC' ]     = $_total_wc_fast_analysis;
        $summary[ 'TOTAL_TM_WC' ]       = $_total_wc_tm_analysis;
        $summary[ 'TOTAL_RAW_WC' ]      = $_total_raw_wc;
        $summary[ 'TOTAL_PAYABLE' ]     = $_total_wc_tm_analysis;

        if ( $this->project->status_analysis == 'FAST_OK' or $this->project->status_analysis == "DONE" ) {
            $summary[ 'PAYABLE_WC_TIME' ] = $this->numberToPrint( $tm_wc_time );
            $summary[ 'PAYABLE_WC_UNIT' ] = $tm_wc_unit;
        } else {
            $summary[ 'PAYABLE_WC_TIME' ] = $this->numberToPrint( $fast_wc_time );
            $summary[ 'PAYABLE_WC_UNIT' ] = $fast_wc_unit;
        }

        $summary[ 'FAST_WC_TIME' ]     = $this->numberToPrint( $fast_wc_time );
        $summary[ 'FAST_WC_UNIT' ]     = $fast_wc_unit;
        $summary[ 'TM_WC_TIME' ]       = $this->numberToPrint( $tm_wc_time );
        $summary[ 'TM_WC_UNIT' ]       = $tm_wc_unit;
        $summary[ 'STANDARD_WC_TIME' ] = $this->numberToPrint( $standard_wc_time );
        $summary[ 'STANDARD_WC_UNIT' ] = $standard_wc_unit;
        $summary[ 'USAGE_FEE' ]        = $this->numberToPrint( $matecat_fee );
        $summary[ 'PRICE_PER_WORD' ]   = $this->numberToPrint( $_matecat_price_per_word );
        $summary[ 'DISCOUNT' ]         = $this->numberToPrint( $discount );
        $summary[ 'DISCOUNT_WC' ]      = $this->numberToPrint( $discount_wc );

        return $summary;
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function getAnalyzeLink() {
        return \Routes::analyze( [
                'project_name' => $this->project->name,
                'id_project'   => $this->project->id,
                'password'     => $this->project->password,
        ] );
    }

    /**
     * @param \Jobs_JobStruct $job
     *
     * @return \stdClass
     * @throws \Exception
     */
    private function renderJobMetadata( \Jobs_JobStruct $job ) {
        $jobMetaData               = new \stdClass();
        $jobMetaData->job_id       = $job->id;
        $jobMetaData->job_password = $job->password;
        $jobMetaData->langpairs    = $job->source . '|' . $job->target;
        $jobMetaData->quality      = $this->getJobQualityData( $job );
        $jobMetaData->totals       = $this->getJobTotals();
        $jobMetaData->urls         = $this->getJobUrls( $job );

        return $jobMetaData;
    }

    /**
     * @param \Jobs_JobStruct $job
     *
     * @return array
     * @throws \ReflectionException
     */
    private function getJobQualityData( \Jobs_JobStruct $job ) {
        $jobQA = new \Revise_JobQA(
                $job->id,
                $job->password,
                \Jobs_JobDao::getEquivalentWordTotal( $job->id, $job->password ),
                new \Constants_Revise
        );

        $jobQA->retrieveJobErrorTotals();
        $jobVote = $jobQA->evalJobVote();

        return [
                'overall' => $jobVote[ 'minText' ],
                'details' => $jobQA->getQaData(),
        ];
    }

    /**
     * @return \stdClass
     */
    private function getJobTotals() {

        $totals            = new \stdClass();
        $totals->chunks    = $this->chunksTotalsCache;
        $totals->aggregate = $this->totalsInitStructure;


        foreach ( $totals->chunks as $id => $chunk ) {
            foreach ( array_keys( $this->totalsInitStructure ) as $key ) {
                $totals->aggregate[ $key ][ 0 ] += $chunk[ $key ][ 0 ];
                $totals->aggregate[ $key ][ 1 ] = $this->numberToPrint( $totals->aggregate[ $key ][ 0 ] );
            }
        }

        return $totals;
    }

    /**
     * @param int $number
     *
     * @return string
     */
    private function numberToPrint( $number ) {
        return number_format( $number, 0, ".", "," );
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
    private function getJobUrls( \Jobs_JobStruct $job ) {
        return [
                'translation' => JobUrlBuilder::create( $job->id, $job->password ),
                'revision1'   => JobUrlBuilder::create( $job->id, $job->password, [ 'revision_number' => 1 ] ),
                'revision2'   => JobUrlBuilder::create( $job->id, $job->password, [ 'revision_number' => 2 ] ),
        ];
    }
}
