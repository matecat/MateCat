<?php
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

/**
 * Description of catController
 *
 * @author antonio
 */
class editlogDownloadController extends downloadController {

    public function __construct() {

        $filterArgs = array(
                'jid'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job    = $__postInput[ "jid" ];
        $this->password  = $__postInput[ "password" ];
        $this->setFilename( "Edit-log-export-" . $this->id_job . ".csv" );

    }

    public function doAction() {

        $job = Jobs_JobDao::getById( $this->id_job, 60 * 60 )[ 0 ];

        $this->model = new EditLog_EditLogModel( $this->id_job, $this->password );
        $this->model->setStartId( $job->job_first_segment );
        $this->model->setSegmentsPerPage( PHP_INT_MAX );
        list( $data, , ) = $this->model->getEditLogData();

        $csvHandler = new SplTempFileObject( -1 );
        $csvHandler->setCsvControl(';');

        $csv_fields = [
                "Project Name", 
                "Source", 
                "Target", 
                "Job ID", 
                "Segment ID", 
                "Suggestion Source", 
                "Words", 
                "Match percentage", 
                "Time-to-edit",
                "Post-editing effort", 
                "Segment", 
                "Suggestion", 
                "Translation", 
                "ID translator",
                "Suggestion1-source", 
                "Suggestion1-translation", 
                "Suggestion1-match", 
                "Suggestion1-origin",
                "Suggestion2-source", 
                "Suggestion2-translation", 
                "Suggestion2-match", 
                "Suggestion2-origin",
                "Suggestion3-source", 
                "Suggestion3-translation", 
                "Suggestion3-match", 
                "Suggestion3-origin",
                "Chosen-Suggestion", 
                "Statistically relevant"
        ];

        $csvHandler->fputcsv( $csv_fields );

        foreach ( $data as $d ) {

            $combined = array_combine( $csv_fields, array_fill( 0, count( $csv_fields ), '' ) );

            $combined[ "Project Name" ]           = $d->proj_name;
            $combined[ "Source" ]                 = $d->job_source;
            $combined[ "Target" ]                 = $d->job_target;
            $combined[ "Job ID" ]                 = $this->id_job;
            $combined[ "Segment ID" ]             = $d->id;
            $combined[ "Suggestion Source" ]      = $d->suggestion_source;
            $combined[ "Words" ]                  = $d->raw_word_count;
            $combined[ "Match percentage" ]       = $d->suggestion_match;
            $combined[ "Time-to-edit" ]           = $d->time_to_edit;
            $combined[ "Post-editing effort" ]    = $d->pe_effort_perc;
            $combined[ "Segment" ]                = $d->source_csv;
            $combined[ "Suggestion" ]             = $d->sug_csv;
            $combined[ "Translation" ]            = $d->translation_csv;
            $combined[ "ID translator" ]          = $d->id_translator;
            $combined[ "Chosen-Suggestion" ]      = $d->suggestion_position;
            $combined[ "Statistically relevant" ] = ( $d->stats_valid ? 1 : 0 );

            if ( !empty( $d->suggestions_array ) ) {

                $sar = json_decode( $d->suggestions_array );

                $combined[ "Suggestion1-source" ]      = $sar[ 0 ]->segment;
                $combined[ "Suggestion1-translation" ] = $sar[ 0 ]->translation;
                $combined[ "Suggestion1-match" ]       = $sar[ 0 ]->match;
                $combined[ "Suggestion1-origin" ]      = ( substr( $sar[ 0 ]->created_by, 0, 2 ) == 'MT' ) ? 'MT' : 'TM';

                if ( isset ( $sar[ 1 ] ) ) {
                    $combined[ "Suggestion2-source" ]      = $sar[ 1 ]->segment;
                    $combined[ "Suggestion2-translation" ] = $sar[ 1 ]->translation;
                    $combined[ "Suggestion2-match" ]       = $sar[ 1 ]->match;
                    $combined[ "Suggestion2-origin" ]      = ( substr( $sar[ 1 ]->created_by, 0, 2 ) == 'MT' ) ? 'MT' : 'TM';
                }

                if ( isset ( $sar[ 2 ] ) ) {
                    $combined[ "Suggestion3-source" ]      = $sar[ 2 ]->segment;
                    $combined[ "Suggestion3-translation" ] = $sar[ 2 ]->translation;
                    $combined[ "Suggestion3-match" ]       = $sar[ 2 ]->match;
                    $combined[ "Suggestion3-origin" ]      = ( substr( $sar[ 2 ]->created_by, 0, 2 ) == 'MT' ) ? 'MT' : 'TM';
                }

            }

            $csvHandler->fputcsv( $combined );

        }

        $csvHandler->rewind();

        foreach ( $csvHandler as $row ) {
            $this->outputContent .= $row;
        }

        /**
         * Retrieve user information
         */
        $this->checkLogin();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->id_job;
        $activity->id_project = Projects_ProjectDao::findByJobId( $data[ 0 ]->job_id, 60 * 60 )->id; //assume that all rows have the same project id
        $activity->action     = ActivityLogStruct::DOWNLOAD_EDIT_LOG;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
        
    }

}

