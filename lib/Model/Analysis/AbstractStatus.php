<?php

use Analysis\AnalysisDao;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */
abstract class Analysis_AbstractStatus {

    protected $_data_struct = array();

    /**
     * Carry the result from Executed Controller Action and returned in json format to the Client
     *
     * @var array
     */
    protected $result = array( "errors" => array(), "data" => array() );

    protected $_globals = array();

    protected $total_segments = 0;
    protected $_resultSet = array();
    protected $_others_in_queue = 0;
    protected $_project_data = array();
    protected $status_project = "";

    protected $_total_init_struct = array(
            "TOTAL_PAYABLE" => array( 0, "0" ), "REPETITIONS" => array( 0, "0" ), "MT" => array( 0, "0" ),
            "NEW"           => array( 0, "0" ), "TM_100" => array( 0, "0" ), "TM_100_PUBLIC" => array( 0, "0" ),
            "TM_75_99"      => array( 0, "0" ),
            "TM_75_84"      => array( 0, "0" ), "TM_85_94" => array( 0, "0" ), "TM_95_99" => array( 0, "0" ),
            "TM_50_74"      => array( 0, "0" ), "INTERNAL_MATCHES" => array( 0, "0" ), "ICE" => array( 0, "0" ),
            "NUMBERS_ONLY"  => array( 0, "0" )
    );

    protected   $featureSet;

    public function __construct( $_project_data , FeatureSet $features) {
        $this->id_project = $_project_data[0]['pid'];
        $this->_project_data = $_project_data;
        $this->featureSet = $features;
    }

    /**
     * @return array
     */
    public function getResult() {
        return $this->result;
    }

    /**
     * @return array
     */
    public function getGlobals() {
        return $this->_globals;
    }

    /**
     * Fetch data for the project
     *
     */
    protected function _fetchProjectData() {

        $this->_resultSet = AnalysisDao::getProjectStatsVolumeAnalysis( $this->id_project );

        try {
            $amqHandler         = new \AMQHandler();
            $segmentsBeforeMine = $amqHandler->getActualForQID( $this->id_project );
        } catch ( Exception $e ) {
            $segmentsBeforeMine = null;
        }

        $this->_others_in_queue = ( $segmentsBeforeMine >= 0 ? $segmentsBeforeMine : 0 );

        $this->total_segments = count( $this->_resultSet );

        //get status of project
        $this->status_project = $this->_project_data[ 0 ][ 'status_analysis' ];

    }

    /**
     * Perform the computation
     *
     * @return $this
     */
    public function fetchData() {

        $this->_fetchProjectData();

        $this->result[ 'data' ] = $this->_data_struct;

        //array of totals per job-files
        $total_word_counters                    = array();
        $_total_segments_analyzed         = 0;
        $_total_wc_fast_analysis          = 0;
        $_total_wc_standard_fast_analysis = 0;
        $_total_raw_wc                    = 0;
        $_total_wc_tm_analysis            = 0;
        $_total_wc_standard_analysis      = 0;
        $_matecat_price_per_word          = 0.03; //(dollari) se indipendente dalla combinazione metterlo nel config
        $_standard_price_per_word         = 0.10; //(dollari) se indipendente dalla combinazione metterlo nel config

        $target = null;
        $outsourceAvailable = false;

        //VERY Expensive cycle ± 0.7 s for 27650 segments ( 150k words )
        foreach ( $this->_resultSet as $segInfo ) {

            if ( $segInfo[ 'st_status_analysis' ] == 'DONE' ) {
                $_total_segments_analyzed += 1;
            }

            if ( $_total_wc_fast_analysis == 0 and $segInfo[ 'fast_analysis_wc' ] > 0 ) {
                $_total_wc_fast_analysis = $segInfo[ 'fast_analysis_wc' ];
            }

            if ( $_total_wc_standard_fast_analysis == 0 and $segInfo[ 'fast_analysis_wc' ] > 0 ) {
                $_total_wc_standard_fast_analysis = $segInfo[ 'fast_analysis_wc' ];
            }

            $jid           = $segInfo[ 'jid' ];
            $jpassword     = $segInfo[ 'jpassword' ];
            $words         = $segInfo[ 'raw_word_count' ];
            $eq_words      = $segInfo[ 'eq_word_count' ];
            $st_word_count = $segInfo[ 'standard_word_count' ];

            $_total_raw_wc += $segInfo[ 'raw_word_count' ];
            $_total_wc_tm_analysis += $eq_words;
            $_total_wc_standard_analysis += $st_word_count;

            // is outsource available?
            if($target === null or $segInfo['target'] !== $target){
                $outsourceAvailable = $this->featureSet->filter( 'outsourceAvailable', $segInfo['target'] );

                // if the hook is not triggered by any plugin
                if(is_string($outsourceAvailable)){
                    $outsourceAvailable = true;
                }

                $target = $segInfo['target'];
            }

            //init indexes to avoid notices
            if ( !array_key_exists( $jid, $this->result[ 'data' ][ 'jobs' ] ) ) {
                $this->result[ 'data' ][ 'jobs' ][ $jid ]             = array();
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'chunks' ] = array();
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'outsource_available' ] = $outsourceAvailable;
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ] = array();
                $total_word_counters[ $jid ]                                = array();
            }

            if ( !array_key_exists( $jpassword, $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'chunks' ] ) ) {
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ] = array();
                $total_word_counters[ $jid ][ $jpassword ]                                = array();
            }

            if ( !array_key_exists( $jpassword, $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ] ) ) {
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $jpassword ] = $this->_total_init_struct;
            }

            if ( !isset( $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $segInfo[ 'id_file' ] ] ) ) {
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $segInfo[ 'id_file' ] ] = $this->_total_init_struct;
            }
            //END init indexes


            if ( $segInfo[ 'match_type' ] == "INTERNAL" ) {
                $keyValue = 'INTERNAL_MATCHES';
            } elseif ( $segInfo[ 'match_type' ] == "MT" ) {
                $keyValue = 'MT';
            } elseif ( $segInfo[ 'match_type' ] == "100%" ) {
                $keyValue = 'TM_100';
            } elseif ( $segInfo[ 'match_type' ] == "100%_PUBLIC" ) {
                $keyValue = 'TM_100_PUBLIC';
            } elseif ( $segInfo[ 'match_type' ] == "75%-99%" ) {
                $keyValue = 'TM_75_99';
            } elseif ( $segInfo[ 'match_type' ] == "75%-84%" ) {
                $keyValue = 'TM_75_84';
            } elseif ( $segInfo[ 'match_type' ] == "85%-94%" ) {
                $keyValue = 'TM_85_94';
            } elseif ( $segInfo[ 'match_type' ] == "95%-99%" ) {
                $keyValue = 'TM_95_99';
            } elseif ( $segInfo[ 'match_type' ] == "50%-74%" ) {
                $keyValue = 'TM_50_74';
            } elseif ( $segInfo[ 'match_type' ] == "NO_MATCH" or $segInfo[ 'match_type' ] == "NEW" ) {
                $keyValue = 'NEW';
            } elseif ( $segInfo[ 'match_type' ] == "ICE" ) {
                $keyValue = "ICE";
            } elseif ( $segInfo[ 'match_type' ] == "REPETITIONS" ) {
                $keyValue = 'REPETITIONS';
            } else {
                $keyValue = 'NUMBERS_ONLY';
            }

            $w           = $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $segInfo[ 'id_file' ] ][ $keyValue ][ 0 ] + $words;
            $words_print = number_format( $w, 0, ".", "," );

            $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $segInfo[ 'id_file' ] ][ $keyValue ] = array(
                    $w, $words_print
            );

            $tmp_tot = $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $jpassword ][ $keyValue ][ 0 ];
            $tmp_tot += $words;
            $words_print                                                                     = number_format( $tmp_tot, 0, ".", "," );
            $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $jpassword ][ $keyValue ] = array(
                    $tmp_tot, $words_print
            );

            //SUM WITH PREVIOUS ( Accumulator )
            //take note of payable words for job/file combination
            $total_word_counters[ $jid ][ $jpassword ][ 'eq_word_count' ] = isset( $total_word_counters[ $jid ][ $jpassword ][ 'eq_word_count' ] ) ? ($total_word_counters[ $jid ][ $jpassword ][ 'eq_word_count' ] + $segInfo[ 'eq_word_count' ]) : $segInfo[ 'eq_word_count' ];
            $total_word_counters[ $jid ][ $jpassword ][ 'standard_word_count' ] = isset( $total_word_counters[ $jid ][ $jpassword ][ 'standard_word_count' ] ) ? ($total_word_counters[ $jid ][ $jpassword ][ 'standard_word_count' ] + $segInfo[ 'standard_word_count' ]) : $segInfo[ 'standard_word_count' ];
            $total_word_counters[ $jid ][ $jpassword ][ 'raw_word_count' ] = isset( $total_word_counters[ $jid ][ $jpassword ][ 'raw_word_count' ] ) ? ($total_word_counters[ $jid ][ $jpassword ][ 'raw_word_count' ] + $segInfo[ 'raw_word_count' ]) : $segInfo[ 'raw_word_count' ];

            $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $segInfo[ 'id_file' ] ][ 'FILENAME' ] = $segInfo[ 'filename' ];

            $total_file_payable = $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $segInfo[ 'id_file' ] ][ 'TOTAL_PAYABLE' ][ 0 ] += $segInfo[ 'eq_word_count' ];
            $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $segInfo[ 'id_file' ] ][ 'TOTAL_PAYABLE' ][ 1 ]                       = number_format( $total_file_payable, 0, ".", "," );

        }

        $this->_resultSet = array(); //free memory

        //sum all totals for each job
        //N^2 but there are a little number of rows max 30
        foreach ( $total_word_counters as $jid => $chunks ) {
            foreach ( $chunks as $_jpassword => $v ) {
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "TOTAL_PAYABLE" ][ 0 ]       = $v[ 'eq_word_count' ]; //compatibility
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "eq_word_count" ][ 0 ]       = $v[ 'eq_word_count' ];
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "standard_word_count" ][ 0 ] = $v[ 'standard_word_count' ];
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "raw_word_count" ][ 0 ]      = $v[ 'raw_word_count' ];
                //format numbers after sum
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "TOTAL_PAYABLE" ][ 1 ]       = number_format( $v[ 'eq_word_count' ] + 0.00000001, 0, ".", "," );
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "eq_word_count" ][ 1 ]       = number_format( $v[ 'eq_word_count' ] + 0.00000001, 0, ".", "," );
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "standard_word_count" ][ 1 ] = number_format( $v[ 'standard_word_count' ] + 0.00000001, 0, ".", "," );
                $this->result[ 'data' ][ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "raw_word_count" ][ 1 ]      = number_format( $v[ 'raw_word_count' ] + 0.00000001, 0, ".", "," );
            }
        }

        if ( $_total_wc_standard_analysis == 0 AND $this->status_project == Constants_ProjectStatus::STATUS_FAST_OK ) {

            $_total_wc_standard_analysis = $_total_wc_standard_fast_analysis;

        } elseif ( $_total_segments_analyzed == 0 && $this->status_project == Constants_ProjectStatus::STATUS_NEW ) {

            //Outsource Quote issue
            //fast analysis not done, return the number of raw word count
            //needed because the "getProjectStatsVolumeAnalysis" query based on segment_translations always returns null
            //( no segment_translations )
            $project_data_fallback = Projects_ProjectDao::getProjectAndJobData( $this->id_project );

            foreach ( $project_data_fallback as $i => $_job_fallback ) {
                $this->result[ 'data' ][ 'jobs' ][ $_job_fallback[ 'jid' ] ][ 'totals' ][ $_job_fallback[ 'jpassword' ] ][ "TOTAL_PAYABLE" ][ 0 ] = $_job_fallback[ 'standard_analysis_wc' ];

                //format numbers after sum
                $this->result[ 'data' ][ 'jobs' ][ $_job_fallback[ 'jid' ] ][ 'totals' ][ $_job_fallback[ 'jpassword' ] ][ "TOTAL_PAYABLE" ][ 1 ] = number_format( $_job_fallback[ 'standard_analysis_wc' ], 0, ".", "," );
            }

            $_total_wc_standard_analysis
                    = $_total_wc_tm_analysis
                    = $_total_raw_wc
                    = $project_data_fallback[ 0 ][ 'standard_analysis_wc' ];

        }

        // if fast quote has been done and tm analysis has not produced any result yet
        if ( $_total_wc_tm_analysis == 0
                AND $this->status_project == Constants_ProjectStatus::STATUS_FAST_OK
                AND $_total_wc_fast_analysis > 0
        ) {
            $_total_wc_tm_analysis = $_total_wc_fast_analysis;
        }

        if ( $_total_wc_fast_analysis > 0 ) {
            $discount_wc = round( 100 * $_total_wc_tm_analysis / $_total_wc_fast_analysis );
        }

        $discount_wc = 0;

        $standard_wc_time = $_total_wc_standard_analysis / INIT::$ANALYSIS_WORDS_PER_DAYS;
        $tm_wc_time       = $_total_wc_tm_analysis / INIT::$ANALYSIS_WORDS_PER_DAYS;
        $fast_wc_time     = $_total_wc_fast_analysis / INIT::$ANALYSIS_WORDS_PER_DAYS;

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

        $this->result[ 'data' ][ 'summary' ][ 'NAME' ]              = $this->_project_data[ 0 ][ 'pname' ];
        $this->result[ 'data' ][ 'summary' ][ 'IN_QUEUE_BEFORE' ]   = $this->_others_in_queue;
        $this->result[ 'data' ][ 'summary' ][ 'STATUS' ]            = $this->status_project;
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_SEGMENTS' ]    = $this->total_segments;
        $this->result[ 'data' ][ 'summary' ][ 'SEGMENTS_ANALYZED' ] = $_total_segments_analyzed;
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_STANDARD_WC' ] = $_total_wc_standard_analysis;
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_FAST_WC' ]     = $_total_wc_fast_analysis;
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_TM_WC' ]       = $_total_wc_tm_analysis;
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_RAW_WC' ]      = $_total_raw_wc;
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_PAYABLE' ]     = $_total_wc_tm_analysis;

        if ( $this->status_project == 'FAST_OK' or $this->status_project == "DONE" ) {
            $this->result[ 'data' ][ 'summary' ][ 'PAYABLE_WC_TIME' ] = number_format( $tm_wc_time, 0, ".", "," );
            $this->result[ 'data' ][ 'summary' ][ 'PAYABLE_WC_UNIT' ] = $tm_wc_unit;
        } else {
            $this->result[ 'data' ][ 'summary' ][ 'PAYABLE_WC_TIME' ] = number_format( $fast_wc_time, 0, ".", "," );
            $this->result[ 'data' ][ 'summary' ][ 'PAYABLE_WC_UNIT' ] = $fast_wc_unit;
        }

        $this->result[ 'data' ][ 'summary' ][ 'FAST_WC_TIME' ]     = number_format( $fast_wc_time, 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'FAST_WC_UNIT' ]     = $fast_wc_unit;
        $this->result[ 'data' ][ 'summary' ][ 'TM_WC_TIME' ]       = number_format( $tm_wc_time, 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'TM_WC_UNIT' ]       = $tm_wc_unit;
        $this->result[ 'data' ][ 'summary' ][ 'STANDARD_WC_TIME' ] = number_format( $standard_wc_time, 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'STANDARD_WC_UNIT' ] = $standard_wc_unit;
        $this->result[ 'data' ][ 'summary' ][ 'USAGE_FEE' ]        = number_format( $matecat_fee, 2, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'PRICE_PER_WORD' ]   = number_format( $_matecat_price_per_word, 3, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'DISCOUNT' ]         = number_format( $discount, 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'DISCOUNT_WC' ]      = number_format( $discount_wc, 0, ".", "," );


        $this->_globals = array(
                'STATUS_PROJECT'    => $this->status_project,
                'IN_QUEUE_BEFORE'   => $this->_others_in_queue,
                'TOTAL_SEGMENTS'    => $this->total_segments,
                'SEGMENTS_ANALYZED' => $_total_segments_analyzed,
                'TOTAL_STANDARD_WC' => $_total_wc_standard_analysis,
                'TOTAL_FAST_WC'     => $_total_wc_fast_analysis,
                'TOTAL_TM_WC'       => $_total_wc_tm_analysis + 0.00000001,
                'TOTAL_RAW_WC'      => $_total_raw_wc,
                'TOTAL_PAYABLE'     => $_total_wc_tm_analysis + 0.00000001,
        );

        return $this;

    }

}