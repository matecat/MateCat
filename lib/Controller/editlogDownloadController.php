<?php
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

/**
 * Description of catController
 *
 * @author antonio
 */
class editlogDownloadController extends downloadController {

    private $jid = "";
    private $password;

    public function __construct() {

        $filterArgs = array(
                'jid'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->jid      = $__postInput[ "jid" ];
        $this->password = $__postInput[ "password" ];
        $this->_filename = "Edit-log-export-" . $this->jid . ".csv";

    }

    public function doAction() {

        $data = CatUtils::getEditingLogData($this->jid, $this->password, true);
        $data = $data[0];

        $csvHandler = new SplTempFileObject( -1 );
        $csvHandler->setCsvControl(';');

        $csv_fields = array(
                "Job ID", "Segment ID", "Suggestion Source", "Words", "Match percentage", "Time-to-edit",
                "Post-editing effort", "HTER", "Segment", "Suggestion", "Translation", "MT QE", "ID translator",
                "Suggestion1-source", "Suggestion1-translation", "Suggestion1-match", "Suggestion1-origin",
                "Suggestion2-source", "Suggestion2-translation", "Suggestion2-match", "Suggestion2-origin",
                "Suggestion3-source", "Suggestion3-translation", "Suggestion3-match", "Suggestion3-origin",
                "Choosen-Suggestion","Statistically relevant"
        );

        $csvHandler->fputcsv( $csv_fields );

        foreach ( $data as $d ){
            $statistical_relevant=($d['stats-valid']=='No'?0:1);
            $sid            = $d[ 'sid' ];
            $sugg_source    = $d[ 'ss' ];
            $rwc            = $d[ 'rwc' ];
            $sugg_match     = $d[ 'sm' ];
            $sugg_tte       = $d[ 'tte' ];
            $pe_effort_perc = $d[ 'pe_effort_perc' ];
            $hter           = $d[ 'ter' ];
            $segment        = $d[ 'source_csv' ];
            $suggestion     = $d[ 'sug_csv' ];
            $translation    = $d[ 'translation_csv' ];
            $id_translator  = $d[ 'tid' ];

            $s1_source      = "";
            $s2_source      = "";
            $s3_source      = "";
            $s1_translation = "";
            $s2_translation = "";
            $s3_translation = "";
            $s1_match       = "";
            $s2_match       = "";
            $s3_match       = "";
            $s1_origin      = "";
            $s2_origin      = "";
            $s3_origin      = "";

            $mt_qe = $d[ 'mt_qe' ];

            if ( !empty( $d[ 'sar' ] ) ) {

                $sar            = json_decode( $d[ 'sar' ] );

                $s1_source      = $sar[ 0 ]->segment;
                $s1_translation = $sar[ 0 ]->translation;
                $s1_match       = $sar[ 0 ]->match;

                if ( isset ( $sar[ 1 ] ) ) {
                    $s2_source      = $sar[ 1 ]->segment;
                    $s2_translation = $sar[ 1 ]->translation;
                    $s2_match       = $sar[ 1 ]->match;
                }

                if ( isset ( $sar[ 2 ] ) ) {
                    $s3_source      = $sar[ 2 ]->segment ;
                    $s3_translation = $sar[ 2 ]->translation;
                    $s3_match       = $sar[ 2 ]->match;
                }

                $s1_origin = ( substr( $sar[ 0 ]->created_by, 0, 2 ) == 'MT' ) ? 'MT' : 'TM';
                $s2_origin = ( substr( $sar[ 1 ]->created_by, 0, 2 ) == 'MT' ) ? 'MT' : 'TM';
                $s3_origin = ( substr( $sar[ 2 ]->created_by, 0, 2 ) == 'MT' ) ? 'MT' : 'TM';
            }

            $row_array = array(
                    $this->jid, $sid, $sugg_source, $rwc, $sugg_match, $sugg_tte, $pe_effort_perc, $hter, $segment,
                    $suggestion, $translation, $mt_qe, $id_translator, $s1_source, $s1_translation, $s1_match,
                    $s1_origin, $s2_source, $s2_translation, $s2_match, $s2_origin, $s3_source, $s3_translation,
                    $s3_match, $s3_origin, $d[ 'sp' ],$statistical_relevant
            );

            $csvHandler->fputcsv( $row_array );

        }

        $csvHandler->rewind();

        foreach ( $csvHandler as $row ) {
            $this->content .= $row;
        }

        /**
         * Retrieve user information
         */
        $this->checkLogin();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->jid;
        $activity->id_project = $data[ 0 ][ 'id_project' ]; //assume that all rows have the same project id
        $activity->action     = ActivityLogStruct::DOWNLOAD_EDIT_LOG;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
        
    }

}

