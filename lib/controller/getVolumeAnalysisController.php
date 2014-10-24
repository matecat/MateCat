<?php

include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/CatUtils.php";

class getVolumeAnalysisController extends ajaxController {
    protected $id_project;
    protected $status_project = "";
    protected $total_raw_wc = 0;
    protected $total_wc_fast_analysis = 0;
    protected $total_wc_tm_analysis = 0;
    protected $total_wc_standard_analysis = 0;
    protected $total_wc_standard_fast_analysis = 0;
    protected $total_segments = 0;
    protected $segments_analyzed = 0;
    protected $matecat_price_per_word = 0.03; //(dollari) se indipendente dalla combinazione metterlo nel config
    protected $standard_price_per_word = 0.10; //(dollari) se indipendente dalla combinazione metterlo nel config

    protected $return_data = array();

    protected $_data_struct = array(
            'jobs'    => array(),
            'summary' =>
                    array(
                            "IN_QUEUE_BEFORE"         => 0, "IN_QUEUE_BEFORE_PRINT" => "0", "STATUS" => "",
                            "TOTAL_SEGMENTS"          => 0, "SEGMENTS_ANALYZED" => 0, "TOTAL_SEGMENTS_PRINT" => 0,
                            "SEGMENTS_ANALYZED_PRINT" => 0,
                            "TOTAL_FAST_WC"           => 0, "TOTAL_TM_WC" => 0, "TOTAL_FAST_WC_PRINT" => "0",
                            "TOTAL_STANDARD_WC"       => 0, "TOTAL_STANDARD_WC_PRINT" => "0",
                            "TOTAL_TM_WC_PRINT"       => "0",
                            "STANDARD_WC_TIME"        => 0, "FAST_WC_TIME" => 0, "TM_WC_TIME" => 0,
                            "STANDARD_WC_UNIT"        => "", "TM_WC_UNIT" => "", "FAST_WC_UNIT" => "",
                            "USAGE_FEE"               => 0.00,
                            "PRICE_PER_WORD"          => 0.00, "DISCOUNT" => 0.00
                    )
    );

    protected $_api_data_struct = array(
            'jobs'    => array(),
            'summary' =>
                    array(
                            "IN_QUEUE_BEFORE"         => 0, "STATUS" => "",
                            "TOTAL_SEGMENTS"          => 0, "SEGMENTS_ANALYZED" => 0,
                            "TOTAL_FAST_WC"           => 0, "TOTAL_TM_WC" => 0,
                            "TOTAL_STANDARD_WC"       => 0,
                            "STANDARD_WC_TIME"        => 0, "FAST_WC_TIME" => 0, "TM_WC_TIME" => 0,
                            "STANDARD_WC_UNIT"        => "", "TM_WC_UNIT" => "", "FAST_WC_UNIT" => "",
                            "USAGE_FEE"               => 0.00,
                            "PRICE_PER_WORD"          => 0.00, "DISCOUNT" => 0.00
                    )
    );

    protected $total_init = array(
            "TOTAL_PAYABLE"    => array( 0, "0" ), "REPETITIONS" => array( 0, "0" ), "MT" => array( 0, "0" ),
            "NEW"              => array( 0, "0" ), "TM_100" => array( 0, "0" ), "TM_75_99" => array( 0, "0" ),
            "INTERNAL_MATCHES" => array( 0, "0" ), "ICE" => array( 0, "0" ), "NUMBERS_ONLY" => array( 0, "0" )
    );

    protected $_resultSet = array();
    protected $_others_in_queue = 0;
    protected $_project_data = array();

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
            'pid'                 => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'ppassword'           => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'jpassword'           => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_project = $__postInput[ 'pid' ];
        $this->ppassword  = $__postInput[ 'ppassword' ];
        $this->jpassword  = $__postInput[ 'jpassword' ];

    }

    public function doAction() {
        $this->formatWebData();
    }

    public function formatApiData(){
        $this->_fetchProjectData( $this->_api_data_struct );
        $this->_formatData( false );
    }

    public function formatWebData(){
        $this->_fetchProjectData( $this->_data_struct );
        $this->_formatData( true );
    }

    protected function _fetchProjectData( array $return_data ) {

        if ( empty( $this->id_project ) ) {
            $this->result[ 'errors' ] = array( -1, "No id project provided" );
            return -1;
        }

        $this->_project_data = getProjectJobData( $this->id_project );

        $passCheck = new AjaxPasswordCheck();
        $access = $passCheck->grantProjectAccess( $this->_project_data, $this->ppassword ) || $passCheck->grantProjectJobAccessOnJobPass( $this->_project_data, null, $this->jpassword );

        if( !$access ){
            $this->result[ 'errors' ] = array( -10, "Wrong Password. Access denied" );
            return -1;
        }

        $this->_resultSet       = getProjectStatsVolumeAnalysis( $this->id_project );
        $this->_others_in_queue = getNumSegmentsInQueue( $this->id_project );

        $this->total_segments = count( $this->_resultSet );

        //get status of project
        $this->status_project = $this->_project_data[ 0 ][ 'status_analysis' ];

        $this->return_data    = $return_data;

    }

    protected function _formatData( $_forWEB = true ){

        //array of totals per job-file
        $total_payable = array();

        //VERY Expensive cycle ± 0.7 s for 27650 segments ( 150k words )
        foreach ( $this->_resultSet as $r ) {

            if ( $r[ 'st_status_analysis' ] == 'DONE' ) {
                $this->segments_analyzed += 1;
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

            $this->total_raw_wc += $r['raw_word_count'];
            $this->total_wc_tm_analysis += $eq_words;
            $this->total_wc_standard_analysis += $st_word_count;

            //init indexes to avoid notices
            if ( !array_key_exists( $jid, $this->return_data[ 'jobs' ] ) ) {
                $this->return_data[ 'jobs' ][ $jid ]             = array();
                $this->return_data[ 'jobs' ][ $jid ][ 'chunks' ] = array();
                $this->return_data[ 'jobs' ][ $jid ][ 'totals' ] = array();
                $total_payable[ $jid ]                     = array();
            }

            if ( !array_key_exists( $jpassword, $this->return_data[ 'jobs' ][$jid]['chunks'] ) ) {
                $this->return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ] = array();
                $total_payable[ $jid ][ $jpassword ] = array();
            }

            if( !array_key_exists( $jpassword, $this->return_data[ 'jobs' ][ $jid ][ 'totals' ] ) ){
                $this->return_data[ 'jobs' ][ $jid ][ 'totals' ][ $jpassword ] = $this->total_init;
            }

            if ( !isset( $this->return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ] ) ) {
                $this->return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ] = $this->total_init;
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
            } elseif( $r[ 'match_type' ] == "ICE" ){
                $keyValue = "ICE";
            } elseif ( $r[ 'match_type' ] == "REPETITIONS" ) {
                $keyValue = 'REPETITIONS';
            } else {
                $keyValue = 'NUMBERS_ONLY';
            }

            $w           = $this->return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ $keyValue ][ 0 ] + $words;
            $words_print = number_format( $w, 0, ".", "," );

            $this->return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ $keyValue ] = array( $w, $words_print );

            $tmp_tot = $this->return_data[ 'jobs' ][ $jid ][ 'totals' ][ $jpassword ][ $keyValue ][0];
            $tmp_tot += $words;
            $words_print = number_format( $tmp_tot, 0, ".", "," );
            $this->return_data[ 'jobs' ][ $jid ][ 'totals' ][ $jpassword ][ $keyValue ] = array( $tmp_tot, $words_print );


            //SUM WITH PREVIOUS ( Accumulator )
            $eq_words       = $this->return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ "TOTAL_PAYABLE" ][ 0 ] + $eq_words;
            $eq_words_print = number_format( $eq_words, 0, ".", "," );
            $this->return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ "TOTAL_PAYABLE" ] = array( $eq_words, $eq_words_print );

            //take note of payable words for job/file combination
            $total_payable[ $jid ][ $jpassword ][ $r[ 'id_file' ] ] = $this->return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ "TOTAL_PAYABLE" ][ 0 ];

            $this->return_data[ 'jobs' ][ $jid ][ 'chunks' ][ $jpassword ][ $r[ 'id_file' ] ][ 'FILENAME' ] = $r[ 'filename' ];

        }

        //sum all totals for each job
        //N^3 but there are a little number of rows max 30
        foreach ( $total_payable as $jid => $chunks ) {

            foreach ( $chunks as $_jpassword => $files ) {
                foreach( $files as $fid => $v ){
                    $this->return_data[ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "TOTAL_PAYABLE" ][ 0 ] += $v;

                    //format numbers after sum
                    $this->return_data[ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "TOTAL_PAYABLE" ][ 1 ] = number_format( $this->return_data[ 'jobs' ][ $jid ][ 'totals' ][ $_jpassword ][ "TOTAL_PAYABLE" ][ 0 ], 0, ".", "," );
                }
            }
        }

        if ( $this->total_wc_standard_analysis == 0 AND $this->status_project == Constants_ProjectStatus::STATUS_FAST_OK ) {

            $this->total_wc_standard_analysis = $this->total_wc_standard_fast_analysis;

        } elseif( $this->segments_analyzed == 0 && $this->status_project == Constants_ProjectStatus::STATUS_NEW ){

            //Outsource Quote issue
            //fast analysis not done, return the number of raw word count
            //needed because the "getProjectStatsVolumeAnalysis" query based on segment_translations always returns null
            //( no segment_translations )
            $project_data_fallback = getProjectJobData( $this->id_project );

            foreach( $project_data_fallback as $i => $_job_fallback ){
                $this->return_data[ 'jobs' ][ $_job_fallback['jid'] ][ 'totals' ][ $_job_fallback['jpassword'] ][ "TOTAL_PAYABLE" ][ 0 ] = $_job_fallback['standard_analysis_wc'];

                //format numbers after sum
                $this->return_data[ 'jobs' ][ $_job_fallback['jid'] ][ 'totals' ][ $_job_fallback['jpassword'] ][ "TOTAL_PAYABLE" ][ 1 ] = number_format( $_job_fallback['standard_analysis_wc'], 0, ".", "," );
            }

            $this->total_wc_standard_analysis
                    = $this->total_wc_tm_analysis
                    = $this->total_raw_wc
                    = $project_data_fallback[0]['standard_analysis_wc'];

        }

        // if fast quote has been done and tm analysis has not produced any result yet
        if ( $this->total_wc_tm_analysis == 0
                AND $this->status_project == Constants_ProjectStatus::STATUS_FAST_OK
                AND $this->total_wc_fast_analysis > 0
        ) {
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
        //TODO: ADDENDUM, the previous described bug should already be solved, try to remove workaround
        if ( $this->segments_analyzed > 0 AND $this->total_segments == $this->segments_analyzed ) {
            $this->status_project = "DONE";
        }

        $this->return_data[ 'summary' ][ 'NAME' ]              = $this->_project_data[ 0 ][ 'pname' ];
        $this->return_data[ 'summary' ][ 'IN_QUEUE_BEFORE' ]   = $this->_others_in_queue;
        $this->return_data[ 'summary' ][ 'STATUS' ]            = $this->status_project;
        $this->return_data[ 'summary' ][ 'TOTAL_SEGMENTS' ]    = $this->total_segments;
        $this->return_data[ 'summary' ][ 'SEGMENTS_ANALYZED' ] = $this->segments_analyzed;
        $this->return_data[ 'summary' ][ 'TOTAL_STANDARD_WC' ] = $this->total_wc_standard_analysis;
        $this->return_data[ 'summary' ][ 'TOTAL_FAST_WC' ]     = $this->total_wc_fast_analysis;
        $this->return_data[ 'summary' ][ 'TOTAL_TM_WC' ]       = $this->total_wc_tm_analysis;
        $this->return_data[ 'summary' ][ 'TOTAL_RAW_WC' ]      = $this->total_raw_wc;
        $this->return_data[ 'summary' ][ 'TOTAL_PAYABLE' ]     = $this->total_wc_tm_analysis;

        if ( $this->status_project == 'FAST_OK' or $this->status_project == "DONE" ) {
            $this->return_data[ 'summary' ][ 'PAYABLE_WC_TIME' ] = number_format( $tm_wc_time, 0, ".", "," );
            $this->return_data[ 'summary' ][ 'PAYABLE_WC_UNIT' ] = $tm_wc_unit;
        } else {
            $this->return_data[ 'summary' ][ 'PAYABLE_WC_TIME' ] = number_format( $fast_wc_time, 0, ".", "," );
            $this->return_data[ 'summary' ][ 'PAYABLE_WC_UNIT' ] = $fast_wc_unit;
        }

        $this->return_data[ 'summary' ][ 'FAST_WC_TIME' ]     = number_format( $fast_wc_time, 0, ".", "," );
        $this->return_data[ 'summary' ][ 'FAST_WC_UNIT' ]     = $fast_wc_unit;
        $this->return_data[ 'summary' ][ 'TM_WC_TIME' ]       = number_format( $tm_wc_time, 0, ".", "," );
        $this->return_data[ 'summary' ][ 'TM_WC_UNIT' ]       = $tm_wc_unit;
        $this->return_data[ 'summary' ][ 'STANDARD_WC_TIME' ] = number_format( $standard_wc_time, 0, ".", "," );
        $this->return_data[ 'summary' ][ 'STANDARD_WC_UNIT' ] = $standard_wc_unit;
        $this->return_data[ 'summary' ][ 'USAGE_FEE' ]        = number_format( $matecat_fee, 2, ".", "," );
        $this->return_data[ 'summary' ][ 'PRICE_PER_WORD' ]   = number_format( $this->matecat_price_per_word, 3, ".", "," );
        $this->return_data[ 'summary' ][ 'DISCOUNT' ]         = number_format( $discount, 0, ".", "," );
        $this->return_data[ 'summary' ][ 'DISCOUNT_WC' ]      = number_format( $discount_wc, 0, ".", "," );

        //aggregate Extra Infos
        if ( $_forWEB ){

            $this->return_data[ 'summary' ][ 'IN_QUEUE_BEFORE_PRINT' ]   = number_format( $this->_others_in_queue, 0, ".", "," );
            $this->return_data[ 'summary' ][ 'TOTAL_SEGMENTS_PRINT' ]    = number_format( $this->total_segments, 0, ".", "," );
            $this->return_data[ 'summary' ][ 'SEGMENTS_ANALYZED_PRINT' ] = number_format( $this->segments_analyzed, 0, ".", "," );
            $this->return_data[ 'summary' ][ 'TOTAL_STANDARD_WC_PRINT' ] = number_format( $this->total_wc_standard_analysis, 0, ".", "," );
            $this->return_data[ 'summary' ][ 'TOTAL_FAST_WC_PRINT' ]     = number_format( $this->total_wc_fast_analysis, 0, ".", "," );
            $this->return_data[ 'summary' ][ 'TOTAL_TM_WC_PRINT' ]       = number_format( $this->total_wc_tm_analysis, 0, ".", "," );
            $this->return_data[ 'summary' ][ 'TOTAL_RAW_WC_PRINT' ]      = number_format( $this->total_raw_wc, 0, ".", "," );
            $this->return_data[ 'summary' ][ 'TOTAL_PAYABLE_PRINT' ]     = number_format( $this->total_wc_tm_analysis, 0, ".", "," );

        } else {

//            Log::doLog( $this->_project_data );

            switch ( $this->status_project ) {
                case 'NEW':
                case 'FAST_OK':
                case 'NOT_READY_FOR_ANALYSIS':
                    $this->result['status']  = 'ANALYZING';
                    break;
                case 'EMPTY':
                    $this->result['status']  = 'NO_SEGMENTS_FOUND';
                    break;
                case 'NOT_TO_ANALYZE':
                    $this->result['status']  = 'ANALYSIS_NOT_ENABLED';
                    break;
                case 'DONE':
                    $this->result['status']  = 'DONE';
                    break;
                default: //this can not be
                    $this->result['status']  = 'FAIL';
                    break;
            }

            $this->result['analyze'] = "/analyze/" . $this->_project_data[0]['pname'] . "/" . $this->_project_data[0]['pid'] . "-" . $this->_project_data[0]['ppassword'];
            $this->result['jobs']   = array();

            foreach( $this->_project_data as $job ){
                $this->result[ 'jobs' ][ 'langpairs' ][ $job[ 'jid_jpassword' ] ] = $job[ 'lang_pair' ];
                $this->result[ 'jobs' ][ 'job-url' ][ $job[ 'jid_jpassword' ] ]   = "/translate/" . $job[ 'job_url' ];
            }

        }

        $this->result[ 'data' ] = $this->return_data;

    }

}

