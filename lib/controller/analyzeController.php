<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/langs/languages.class.php";

class analyzeController extends viewController {

    private $pid;
    private $ppassword;
    private $jpassword;
    private $pname = "";
    private $total_raw_word_count = 0;
    private $total_raw_word_count_print = "";
    private $fast_analysis_wc = 0;
    private $tm_analysis_wc = 0;
    private $standard_analysis_wc = 0;
    private $fast_analysis_wc_print = "";
    private $standard_analysis_wc_print = "";
    private $tm_analysis_wc_print = "";
    private $raw_wc_time = 0;
    private $fast_wc_time = 0;
    private $tm_wc_time = 0;
    private $standard_wc_time = 0;
    private $fast_wc_unit = "";
    private $tm_wc_unit = "";
    private $raw_wc_unit = "";
    private $standard_wc_unit = "";
    private $jobs = array();
    private $project_not_found = false;
    private $project_status = "";
    private $num_segments = 0;
    private $num_segments_analyzed = 0;

    public function __construct() {
        parent::__construct( false );

        $this->pid      = $this->get_from_get_post( "pid" );
        $this->jid      = $this->get_from_get_post( "jid" );
        $pass = $this->get_from_get_post( "password" );

        if( !empty( $this->jid ) ){
            parent::makeTemplate( "jobAnalysis.html" );
            $this->jpassword = $pass;
            $this->ppassword = null;
        } else {
            parent::makeTemplate( "analyze.html" );
            $this->jid       = null;
            $this->jpassword = null;
            $this->ppassword = $pass;
        }

    }

    public function doAction() {

        $project_by_jobs_data = getProjectData( $this->pid, $this->ppassword, $this->jid, $this->jpassword );

        $lang_handler = Languages::getInstance();

        if ( empty( $project_by_jobs_data ) ) {
            $this->project_not_found = true;
        }

        foreach ( $project_by_jobs_data as &$p_jdata ) {

            $this->num_segments += $p_jdata[ 'total_segments' ];
            if ( empty( $this->pname ) ) {
                $this->pname = $p_jdata[ 'name' ];
            }

            if ( empty( $this->project_status ) ) {
                $this->project_status = $p_jdata[ 'status_analysis' ];
                if ( $this->standard_analysis_wc == 0 ) {
                    $this->standard_analysis_wc = $p_jdata[ 'standard_analysis_wc' ];
                }
            }

            //equivalent word count global
            if ( $this->tm_analysis_wc == 0 ) {
                $this->tm_analysis_wc = $p_jdata[ 'tm_analysis_wc' ];
            }
            if ( $this->tm_analysis_wc == 0 ) {
                $this->tm_analysis_wc = $p_jdata[ 'fast_analysis_wc' ];
            }
            $this->tm_analysis_wc_print = number_format( $this->tm_analysis_wc, 0, ".", "," );

            if ( $this->fast_analysis_wc == 0 ) {
                $this->fast_analysis_wc       = $p_jdata[ 'fast_analysis_wc' ];
                $this->fast_analysis_wc_print = number_format( $this->fast_analysis_wc, 0, ".", "," );
            }

            // if zero then print empty instead of 0
            if ( $this->standard_analysis_wc == 0 ) {
                $this->standard_analysis_wc_print = "";
            }

            if ( $this->fast_analysis_wc == 0 ) {
                $this->fast_analysis_wc_print = "";
            }

            if ( $this->tm_analysis_wc == 0 ) {
                $this->tm_analysis_wc_print = "";
            }

            $this->total_raw_word_count += $p_jdata[ 'file_raw_word_count' ];

            $source = $lang_handler->getLocalizedName( $p_jdata[ 'source' ] );
            $target = $lang_handler->getLocalizedName( $p_jdata[ 'target' ] );

            if ( !isset( $this->jobs[ $p_jdata[ 'jid' ] ] ) ) {

                if ( !isset( $this->jobs[ $p_jdata[ 'jid' ] ][ 'splitted' ] ) ) {
                    $this->jobs[ $p_jdata[ 'jid' ] ][ 'splitted' ] = '';
                }

                $this->jobs[ $p_jdata[ 'jid' ] ][ 'jid' ]    = $p_jdata[ 'jid' ];
                $this->jobs[ $p_jdata[ 'jid' ] ][ 'source' ] = $source;
                $this->jobs[ $p_jdata[ 'jid' ] ][ 'target' ] = $target;

            }

            $source_short = $p_jdata[ 'source' ];
            $target_short = $p_jdata[ 'target' ];
            $password     = $p_jdata[ 'jpassword' ];

            unset( $p_jdata[ 'name' ] );
            unset( $p_jdata[ 'source' ] );
            unset( $p_jdata[ 'target' ] );
            unset( $p_jdata[ 'jpassword' ] );


            unset( $p_jdata[ 'fast_analysis_wc' ] );
            unset( $p_jdata[ 'tm_analysis_wc' ] );
            unset( $p_jdata[ 'standard_analysis_wc' ] );


            if ( !isset( $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ] ) ) {
                $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ]                   = array();
                $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'jid' ]          = $p_jdata[ 'jid' ];
                $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'source' ]       = $source;
                $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'target' ]       = $target;
                $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'jpassword' ]    = $password;
                $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'source_short' ] = $source_short;
                $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'target_short' ] = $target_short;

                if ( !array_key_exists( "total_raw_word_count", $this->jobs[ $p_jdata[ 'jid' ] ] ) ) {
                    $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'total_raw_word_count' ] = 0;
                }

                if ( !array_key_exists( "total_eq_word_count", $this->jobs[ $p_jdata[ 'jid' ] ] ) ) {
                    $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'total_eq_word_count' ] = 0;
                }

            }

            //calculate total word counts per job (summing different files)
            $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'total_raw_word_count' ] += $p_jdata[ 'file_raw_word_count' ];
            //format the total (yeah, it's ugly doing it every cycle)
            $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'total_raw_word_count_print' ] = number_format( $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'total_raw_word_count' ], 0, ".", "," );

            $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'total_eq_word_count' ] += $p_jdata[ 'file_eq_word_count' ];
            $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'total_eq_word_count_print' ] = number_format( $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'total_eq_word_count' ], 0, ".", "," );

            $p_jdata[ 'file_eq_word_count' ] = number_format( $p_jdata[ 'file_eq_word_count' ], 0, ".", "," );
            $p_jdata[ 'file_raw_word_count' ] = number_format( $p_jdata[ 'file_raw_word_count' ], 0, ".", "," );

            $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ][ $password ][ 'files' ][ $p_jdata[ 'id_file' ] ] = $p_jdata;

            $this->jobs[ $p_jdata[ 'jid' ] ][ 'splitted' ] = ( count( $this->jobs[ $p_jdata[ 'jid' ] ][ 'chunks' ] ) > 1 ? 'splitted' : '' );

        }


        $raw_wc_time  = $this->total_raw_word_count / INIT::$ANALYSIS_WORDS_PER_DAYS;
        $tm_wc_time   = $this->tm_analysis_wc / INIT::$ANALYSIS_WORDS_PER_DAYS;
        $fast_wc_time = $this->fast_analysis_wc / INIT::$ANALYSIS_WORDS_PER_DAYS;

        $raw_wc_unit  = 'day';
        $tm_wc_unit   = 'day';
        $fast_wc_unit = 'day';

        if ( $raw_wc_time > 0 and $raw_wc_time < 1 ) {
            $raw_wc_time *= 8; //convert to hours (1 work day = 8 hours)
            $raw_wc_unit = 'hour';
        }

        if ( $raw_wc_time > 0 and $raw_wc_time < 1 ) {
            $raw_wc_time *= 60; //convert to minutes
            $raw_wc_unit = 'minute';
        }

        if ( $raw_wc_time > 1 ) {
            $raw_wc_unit .= 's';
        }


        if ( $tm_wc_time > 0 and $tm_wc_time < 1 ) {
            $tm_wc_time *= 8; //convert to hours (1 work day = 8 hours)
            $tm_wc_unit = 'hour';
        }

        if ( $tm_wc_time > 0 and $tm_wc_time < 1 ) {
            $tm_wc_time *= 60; //convert to minutes
            $tm_wc_unit = 'minute';
        }

        if ( $tm_wc_time > 1 ) {
            $tm_wc_unit .= 's';
        }

        if ( $fast_wc_time > 0 and $fast_wc_time < 1 ) {
            $fast_wc_time *= 8; //convert to hours (1 work day = 8 hours)
            $fast_wc_unit = 'hour';
        }

        if ( $fast_wc_time > 0 and $fast_wc_time < 1 ) {
            $fast_wc_time *= 60; //convert to minutes
            $fast_wc_unit = 'minute';
        }

        if ( $fast_wc_time > 1 ) {
            $fast_wc_unit .= 's';
        }

        $this->raw_wc_time  = ceil( $raw_wc_time );
        $this->fast_wc_time = ceil( $fast_wc_time );
        $this->tm_wc_time   = ceil( $tm_wc_time );
        $this->raw_wc_unit  = $raw_wc_unit;
        $this->tm_wc_unit   = $tm_wc_unit;
        $this->fast_wc_unit = $fast_wc_unit;


        if ( $this->raw_wc_time == 8 and $this->raw_wc_unit == "hours" ) {
            $this->raw_wc_time = 1;
            $this->raw_wc_unit = "day";
        }
        if ( $this->raw_wc_time == 60 and $this->raw_wc_unit == "minutes" ) {
            $this->raw_wc_time = 1;
            $this->raw_wc_unit = "hour";
        }

        if ( $this->fast_wc_time == 8 and $this->fast_wc_time == "hours" ) {
            $this->fast_wc_time = 1;
            $this->fast_wc_time = "day";
        }
        if ( $this->tm_wc_time == 60 and $this->tm_wc_time == "minutes" ) {
            $this->tm_wc_time = 1;
            $this->tm_wc_time = "hour";
        }

        if ( $this->total_raw_word_count == 0 ) {
            $this->total_raw_word_count_print = "";
        } else {
            $this->total_raw_word_count_print = number_format( $this->total_raw_word_count, 0, ".", "," );
        }

//        echo "<pre>" . print_r ( $this->jobs, true ) . "</pre>"; exit;

    }

    public function setTemplateVars() {

        $this->template->jobs                       = $this->jobs;
        $this->template->fast_analysis_wc           = $this->fast_analysis_wc;
        $this->template->fast_analysis_wc_print     = $this->fast_analysis_wc_print;
        $this->template->tm_analysis_wc             = $this->tm_analysis_wc;
        $this->template->tm_analysis_wc_print       = $this->tm_analysis_wc_print;
        $this->template->standard_analysis_wc       = $this->standard_analysis_wc;
        $this->template->standard_analysis_wc_print = $this->standard_analysis_wc_print;
        $this->template->total_raw_word_count       = $this->total_raw_word_count;
        $this->template->total_raw_word_count_print = $this->total_raw_word_count_print;
        $this->template->pname                      = $this->pname;
        $this->template->pid                        = $this->pid;
        $this->template->project_password           = $this->ppassword;
        $this->template->project_not_found          = $this->project_not_found;
        $this->template->fast_wc_time               = $this->fast_wc_time;
        $this->template->tm_wc_time                 = $this->tm_wc_time;
        $this->template->tm_wc_unit                 = $this->tm_wc_unit;
        $this->template->fast_wc_unit               = $this->fast_wc_unit;
        $this->template->standard_wc_unit           = $this->standard_wc_unit;
        $this->template->raw_wc_time                = $this->raw_wc_time;
        $this->template->standard_wc_time           = $this->standard_wc_time;
        $this->template->raw_wc_unit                = $this->raw_wc_unit;
        $this->template->project_status             = $this->project_status;
        $this->template->num_segments               = $this->num_segments;
        $this->template->num_segments_analyzed      = $this->num_segments_analyzed;
        $this->template->logged_user                = trim( $this->logged_user[ 'first_name' ] . " " . $this->logged_user[ 'last_name' ] );
        $this->template->build_number               = INIT::$BUILD_NUMBER;
	    $this->template->enable_outsource           = INIT::$ENABLE_OUTSOURCE;
//	    $this->template->translated_openid          = 'http://openid.translated.home/';
	    $this->template->translated_openid          = 'http://openid.loc/';

        $this->template->isLoggedIn = $this->isLoggedIn();

        if ( isset( $_SESSION[ '_anonym_pid' ] ) && !empty( $_SESSION[ '_anonym_pid' ] ) ) {
            $_SESSION[ 'incomingUrl' ]         = INIT::$HTTPHOST . $_SERVER[ 'REQUEST_URI' ];
            $_SESSION[ '_newProject' ]         = 1;
            $this->template->showModalBoxLogin = true;
        } else {
            $this->template->showModalBoxLogin = false;
        }

        $this->template->incomingUrl = '/login?incomingUrl=' . $_SERVER[ 'REQUEST_URI' ];

    }

}
