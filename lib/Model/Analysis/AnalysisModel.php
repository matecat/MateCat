<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/18/16
 * Time: 3:24 PM
 */

class Analysis_AnalysisModel {

    public $pname = "";
    public $total_raw_word_count = 0;
    public $total_raw_word_count_print = "";
    public $fast_analysis_wc = 0;
    public $tm_analysis_wc = 0;
    public $standard_analysis_wc = 0;
    public $fast_analysis_wc_print = "";
    public $standard_analysis_wc_print = "";
    public $tm_analysis_wc_print = "";
    public $raw_wc_time = 0;
    public $fast_wc_time = 0;
    public $tm_wc_time = 0;
    public $standard_wc_time = 0;
    public $fast_wc_unit = "";
    public $tm_wc_unit = "";
    public $raw_wc_unit = "";
    public $standard_wc_unit = "";
    public $jobs = array();
    public $project_not_found = false;
    public $project_status = "";
    public $num_segments = 0;
    public $num_segments_analyzed = 0;
    public $proj_payable_rates;
    public $subject;

    /**
     * @var Projects_ProjectStruct
     */
    private $project;
    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk;

    public function __construct( Projects_ProjectStruct $project, Chunks_ChunkStruct $chunk=null) {
        $this->chunk = $chunk ;
        $this->project = $project ;
    }

    public function loadData() {
        $project_data = $this->getProjectData();
        $lang_handler = Langs_Languages::getInstance();

        $this->subject = $project_data[ 0 ][ 'subject' ];

        foreach ( $project_data as &$p_jdata ) {

            $p_jdata[ 'filename' ] = ZipArchiveExtended::getFileName( $p_jdata[ 'filename' ] );

            //json_decode payable rates
            $p_jdata[ 'payable_rates' ] = json_decode( $p_jdata[ 'payable_rates' ], true );

            $this->num_segments += $p_jdata[ 'total_segments' ];
            if ( empty( $this->pname ) ) {
                $this->pname = $p_jdata[ 'name' ];
            }

            $this->project_status = $p_jdata[ 'status_analysis' ];

            if ( $this->standard_analysis_wc == 0 ) {
                $this->standard_analysis_wc = $p_jdata[ 'standard_analysis_wc' ];
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
                $this->jobs[ $p_jdata[ 'jid' ] ][ 'rates' ]                                 = $p_jdata[ 'payable_rates' ];

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

            $p_jdata[ 'file_eq_word_count' ]  = number_format( $p_jdata[ 'file_eq_word_count' ], 0, ".", "," );
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

    }

    private function getProjectData() {
        if ( $this->chunk == null ) {
            $project_by_jobs_data = getProjectData( $this->project->id, $this->project->password );
        }
        else {
            $project_by_jobs_data = getProjectData( $this->project->id, $this->project->password,
                    $this->chunk->id, $this->chunk->password );
        }
        return $project_by_jobs_data ;
    }





}