<?php

include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";

class getVolumeAnalysisController extends ajaxcontroller {
    protected $id_project;
    private $status_project = "";
    private $total_wc_fast_analysis = 0;
    private $total_wc_tm_analysis = 0;
    private $total_wc_standard_analysis = 0;
    private $total_wc_standard_fast_analysis = 0;
    private $total_segments = 0;
    private $segments_analyzed = 0;
    private $matecat_price_per_word = 0.03; //(dollari) se indipendente dalla combinazione metterlo nel config
    private $standard_price_per_word = 0.10; //(dollari) se indipendente dalla combinazione metterlo nel config

    public function __construct() {

        $this->disableSessions();
        parent::__construct();

        $filterArgs = array(
            'pid'                 => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'ppassword'           => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_project = $__postInput[ 'pid' ];
        $this->ppassword  = $__postInput[ 'ppassword' ];

    }

    public function doAction() {

        if ( empty( $this->id_project ) ) {
            $this->result[ 'errors' ] = array( -1, "No id project provided" );
            return -1;
        }

        $project_data = getProjectJobData( $this->id_project );
        $passCheck = new AjaxPasswordCheck();
        $access = $passCheck->grantProjectAccess( $project_data, $this->ppassword );

        if( !$access ){
            $this->result[ 'errors' ] = array( -10, "Wrong Password. Access denied" );
            return -1;
        }


        $res                = getProjectStatsVolumeAnalysis( $this->id_project );
        $numSegmentsInQueue = getNumSegmentsInQueue( $this->id_project );

        $return_data = array( 'jobs' => array(), 'summary' =>
                array( "IN_QUEUE_BEFORE"  => 0, "IN_QUEUE_BEFORE_PRINT" => "0", "STATUS" => "", "TOTAL_SEGMENTS" => 0, "SEGMENTS_ANALYZED" => 0, "TOTAL_SEGMENTS_PRINT" => 0, "SEGMENTS_ANALYZED_PRINT" => 0,
                       "TOTAL_FAST_WC"    => 0, "TOTAL_TM_WC" => 0, "TOTAL_FAST_WC_PRINT" => "0", "TOTAL_STANDARD_WC" => 0, "TOTAL_STANDARD_WC_PRINT" => "0", "TOTAL_TM_WC_PRINT" => "0", "STANDARD_WC_TIME" => 0, "FAST_WC_TIME" => 0, "TM_WC_TIME" => 0,
                       "STANDARD_WC_UNIT" => "", "TM_WC_UNIT" => "", "FAST_WC_UNIT" => "", "USAGE_FEE" => 0.00, "PRICE_PER_WORD" => 0.00, "DISCOUNT" => 0.00 ) );
        $total_init  = array( "TOTAL_PAYABLE" => array( 0, "0" ), "REPETITIONS" => array( 0, "0" ), "MT" => array( 0, "0" ), "NEW" => array( 0, "0" ), "TM_100" => array( 0, "0" ), "TM_75_99" => array( 0, "0" ), "INTERNAL_MATCHES" => array( 0, "0" ), "ICE" => array( 0, "0" ) );

        $this->total_segments = count( $res );

        //array of totals per job-file
        $total_payable = array();

        foreach ( $res as $r ) {
            if ( $r[ 'st_status_analysis' ] == 'DONE' ) {
                $this->segments_analyzed += 1;
            }

            if ( empty( $this->status_project ) ) {
                $this->status_project = $r[ 'status_analysis' ];
            }

            if ( $this->total_wc_fast_analysis == 0 and $r[ 'fast_analysis_wc' ] > 0 ) {
                $this->total_wc_fast_analysis = $r[ 'fast_analysis_wc' ];
            }

            if ( $this->total_wc_standard_fast_analysis == 0 and $r[ 'fast_analysis_wc' ] > 0 ) {
                $this->total_wc_standard_fast_analysis = $r[ 'fast_analysis_wc' ];
            }


            $jid           = $r[ 'jid' ];
            $jpassword     = $r[ 'jpassword' ];
            $words         = $r[ 'raw_word_count' ];
            $eq_words      = $r[ 'eq_word_count' ];
            $st_word_count = $r[ 'standard_word_count' ];


            $this->total_wc_tm_analysis += $eq_words;
            $this->total_wc_standard_analysis += $st_word_count;

            //init indexes to avoid notices
            if ( !array_key_exists( $jid, $return_data[ 'jobs' ] ) ) {
                $return_data[ 'jobs' ][ $jid ]             = array();
                $return_data[ 'jobs' ][ $jid ][ 'chunks' ] = array();
                $return_data[ 'jobs' ][ $jid ][ 'totals' ] = array();
                $total_payable[ $jid ]                     = array();
            }

            if ( !array_key_exists( $jpassword, $return_data[ 'jobs' ][$jid]['chunks'] ) ) {
                $return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ] = array();
                $total_payable[ $jid ][ $jpassword ] = array();
            }

            if( !array_key_exists( $jpassword, $return_data[ 'jobs' ][ $jid ][ 'totals' ] ) ){
                $return_data[ 'jobs' ][ $jid ][ 'totals' ][ $jpassword ] = $total_init;
            }

            if ( !isset( $return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ] ) ) {
                $return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ] = $total_init;
            }
            //END init indexes


            if ( $r[ 'match_type' ] == "INTERNAL" ) {
                $keyValue = 'INTERNAL_MATCHES';
            } elseif ( $r[ 'match_type' ] == "MT" ) {
                $keyValue = 'MT';
            } elseif ( $r[ 'match_type' ] == "100%" ) {
                $keyValue = 'TM_100';
            } elseif ( $r[ 'match_type' ] == "75%-99%" or $r[ 'match_type' ] == "75%-84%" or $r[ 'match_type' ] == "85%-94%" or $r[ 'match_type' ] == "95%-99%" ) {
                $keyValue = 'TM_75_99';
            } elseif ( $r[ 'match_type' ] == "50%-74%" or $r[ 'match_type' ] == "NO_MATCH" or $r[ 'match_type' ] == "NEW" ) {
                $keyValue = 'NEW';
            } elseif ( $r[ 'match_type' ] == "REPETITIONS" ) {
                $keyValue = 'REPETITIONS';
            }

            $w           = $return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ $keyValue ][ 0 ] + $words;
            $words_print = number_format( $w, 0, ".", "," );

            $return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ $keyValue ] = array( $w, $words_print );

            $w           = $return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ $keyValue ][ 0 ] + $words;
            $words_print = number_format( $w, 0, ".", "," );


            $tmp_tot = $return_data[ 'jobs' ][ $jid ][ 'totals' ][ $jpassword ][ $keyValue ][0];
            $tmp_tot += $words;
            $words_print = number_format( $tmp_tot, 0, ".", "," );
            $return_data[ 'jobs' ][ $jid ][ 'totals' ][ $jpassword ][ $keyValue ] = array( $tmp_tot, $words_print );


            //SUM WITH PREVIOUS ( Accumulator )
            $eq_words    = $return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ "TOTAL_PAYABLE" ][ 0 ] + $eq_words;
            $return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ "TOTAL_PAYABLE" ] = array( $eq_words );

            //take note of payable words for job/file combination
            $total_payable[ $jid ][ $jpassword ][ $r[ 'id_file' ] ] = $return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ "TOTAL_PAYABLE" ][ 0 ];

        }

        //sum all totals for each job
        foreach ( $total_payable as $jid => $chunks ) {

            foreach ( $chunks as $_jpassword => $files ) {
                foreach( $files as $fid => $v ){
                    $return_data[ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "TOTAL_PAYABLE" ][ 0 ] += $v;

                    //format numbers after sum
                    $return_data[ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "TOTAL_PAYABLE" ][ 1 ] = number_format( $return_data[ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "TOTAL_PAYABLE" ][ 0 ], 0, ".", "," );
                }
            }
        }

        if ( $this->total_wc_standard_analysis == 0  and $this->status_project == "FAST_OK" ) {
            $this->total_wc_standard_analysis = $this->total_wc_standard_fast_analysis;
        }

        // if fast quote has been done and tm analysis has not produced any result yet
        if ( $this->total_wc_tm_analysis == 0 and $this->status_project == "FAST_OK" and $this->total_wc_fast_analysis > 0 ) {
            $this->total_wc_tm_analysis = $this->total_wc_fast_analysis;
        }


        if ( $this->total_wc_fast_analysis > 0 ) {
            $discount_wc = round( 100 * $this->total_wc_tm_analysis / $this->total_wc_fast_analysis );
        }

        $discount_wc = 0;

        $standard_wc_time = $this->total_wc_standard_analysis / INIT::$ANALYSIS_WORDS_PER_DAYS;
        $tm_wc_time       = $this->total_wc_tm_analysis / INIT::$ANALYSIS_WORDS_PER_DAYS;
        $fast_wc_time     = $this->total_wc_fast_analysis / INIT::$ANALYSIS_WORDS_PER_DAYS;

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


        $matecat_fee  = ( $this->total_wc_fast_analysis - $this->total_wc_tm_analysis ) * $this->matecat_price_per_word;
        $standard_fee = ( $this->total_wc_standard_analysis - $this->total_wc_tm_analysis ) * $this->standard_price_per_word;
        $discount     = round( $standard_fee - $matecat_fee );

        // THIS IS A PATCH (WORKAROUND): not a good pratice. Try solution. the tm analysis fail in set the status to done
        if ( $this->segments_analyzed > 0 and $this->total_segments == $this->segments_analyzed ) {
            $this->status_project = "DONE";
        }
        $return_data[ 'summary' ][ 'IN_QUEUE_BEFORE' ]       = $numSegmentsInQueue;
        $return_data[ 'summary' ][ 'IN_QUEUE_BEFORE_PRINT' ] = number_format( $numSegmentsInQueue, 0, ".", "," );

        $return_data[ 'summary' ][ 'STATUS' ]               = $this->status_project;
        $return_data[ 'summary' ][ 'TOTAL_SEGMENTS' ]       = $this->total_segments;
        $return_data[ 'summary' ][ 'TOTAL_SEGMENTS_PRINT' ] = number_format( $this->total_segments, 0, ".", "," );

        $return_data[ 'summary' ][ 'SEGMENTS_ANALYZED_PRINT' ] = number_format( $this->segments_analyzed, 0, ".", "," );
        $return_data[ 'summary' ][ 'SEGMENTS_ANALYZED' ]       = $this->segments_analyzed;

        $return_data[ 'summary' ][ 'TOTAL_STANDARD_WC' ] = $this->total_wc_standard_analysis;
        $return_data[ 'summary' ][ 'TOTAL_FAST_WC' ]     = $this->total_wc_fast_analysis;
        $return_data[ 'summary' ][ 'TOTAL_TM_WC' ]       = $this->total_wc_tm_analysis;

        $return_data[ 'summary' ][ 'TOTAL_STANDARD_WC_PRINT' ] = number_format( $this->total_wc_standard_analysis, 0, ".", "," );
        $return_data[ 'summary' ][ 'TOTAL_FAST_WC_PRINT' ]     = number_format( $this->total_wc_fast_analysis, 0, ".", "," );
        $return_data[ 'summary' ][ 'TOTAL_TM_WC_PRINT' ]       = number_format( $this->total_wc_tm_analysis, 0, ".", "," );


        $return_data[ 'summary' ][ 'TOTAL_PAYABLE' ]       = $this->total_wc_tm_analysis;
        $return_data[ 'summary' ][ 'TOTAL_PAYABLE_PRINT' ] = number_format( $this->total_wc_tm_analysis, 0, ".", "," );

        if ( $this->status_project == 'FAST_OK' or $this->status_project == "DONE" ) {
            $return_data[ 'summary' ][ 'PAYABLE_WC_TIME' ] = number_format( $tm_wc_time, 0, ".", "," );
            $return_data[ 'summary' ][ 'PAYABLE_WC_UNIT' ] = $tm_wc_unit;
        } else {
            $return_data[ 'summary' ][ 'PAYABLE_WC_TIME' ] = number_format( $fast_wc_time, 0, ".", "," );
            $return_data[ 'summary' ][ 'PAYABLE_WC_UNIT' ] = $fast_wc_unit;
        }

        $return_data[ 'summary' ][ 'FAST_WC_TIME' ] = number_format( $fast_wc_time, 0, ".", "," );
        $return_data[ 'summary' ][ 'FAST_WC_UNIT' ] = $fast_wc_unit;

        $return_data[ 'summary' ][ 'TM_WC_TIME' ] = number_format( $tm_wc_time, 0, ".", "," );
        $return_data[ 'summary' ][ 'TM_WC_UNIT' ] = $tm_wc_unit;

        $return_data[ 'summary' ][ 'STANDARD_WC_TIME' ] = number_format( $standard_wc_time, 0, ".", "," );
        $return_data[ 'summary' ][ 'STANDARD_WC_UNIT' ] = $standard_wc_unit;


        $return_data[ 'summary' ][ 'USAGE_FEE' ]      = number_format( $matecat_fee, 2, ".", "," );
        $return_data[ 'summary' ][ 'PRICE_PER_WORD' ] = number_format( $this->matecat_price_per_word, 3, ".", "," );
        $return_data[ 'summary' ][ 'DISCOUNT' ]       = number_format( $discount, 0, ".", "," );
        $return_data[ 'summary' ][ 'DISCOUNT_WC' ]    = number_format( $discount_wc, 0, ".", "," );

        $this->result[ 'data' ] = $return_data;

    }

}

?>
