<?php

use ActivityLog\ActivityLogStruct;

class ActivityLogDecorator {

    protected $controller;

    protected $template;

    protected $featureSet;

    public function __construct(activityLogController $controller, PHPTAL $template)
    {

        $this->controller = $controller;
        $this->template = $template;
        $this->featureSet = $controller->getFeatureSet();

    }

    public function decorate()
    {

        if( $this->controller->download ){
            $this->downloadZip( $this->controller->jobLanguageDefinition, $this->controller->rawLogContent );

            // this is only to show the end of process,
            // never reached because process die in $this->controller->finalizeDownload()
            // that override the viewController::finalyze method
            die();
        }

        $this->prepareForView( $this->controller->jobLanguageDefinition, $this->controller->rawLogContent );

    }

    /**
     * Prepare nad fill Zip package and download it
     *
     * @param $jobLanguageDefinition
     * @param $rawLogContent
     *
     */
    protected function downloadZip( $jobLanguageDefinition, $rawLogContent ){

        $outputContent = array();
        foreach( $rawLogContent as $k => $value ){

            if( empty( $value->email ) ) {
                $value->first_name = "Anonymous";
                $value->last_name = "User";
                $value->email = "Unknown";
            }

            $outputContent[ $value->id_job . "-" . $jobLanguageDefinition[ $value->id_job ] ][ ] =
                    $value->ip . " - [" . $value->event_date . "]: " .
                    $value->first_name . " " . $value->last_name .
                    " <" . $value->email . "> - " . ActivityLogStruct::getAction( $value->action );
        }

        $this->controller->content = $this->composeZip( $this->controller->project_data[0][ 'pname' ], $outputContent );
        $this->controller->_filename = $this->controller->project_data[0][ 'pname' ] . "_activity_log.zip";
        $this->controller->finalizeDownload();

    }

    protected static function composeZip( $projectName , $outputContent ) {

        $fileName = tempnam( "/tmp", "zipmat" );
        $zip  = new ZipArchive();
        $zip->open( $fileName, ZipArchive::OVERWRITE );

        // Staff with content
        foreach ( $outputContent as $jobName => $activityLog ) {
            if( $jobName == "-" ){
                $zip->addFromString( "Project-" . $projectName . ".txt", implode( "\r\n", $activityLog ) );
            } else {
                $zip->addFromString( "Job-" . $jobName . ".txt", implode( "\r\n", $activityLog ) );
            }
        }

        // Close and send to users
        $zip->close();

        $fileContent = file_get_contents( $fileName );
        unlink( $fileName );

        return $fileContent;

    }

    public function prepareForView( $jobLanguageDefinition, $rawContent ) {
        // Implement as setTemplateVars() method.

        $outputContent = [];
        foreach( $rawContent as $k => $value ){

            // This filter allows our support team at matecat.com to have their email addresses
            // replaced by a "MateCat Support Team" tag.
            $value = $this->featureSet->filter('filterActivityLogEntry', $value);

            if( empty( $value->email ) ) {
                $value->first_name = "Anonymous";
                $value->last_name = "User";
                $value->email = "Unknown";
            }

            $jobKeyName = '_project_' . $this->controller->project_data[ 0 ][ 'pid' ];

            $outputContent[ $jobKeyName ][ $k ][ 'ip' ]         = $value->ip;
            $outputContent[ $jobKeyName ][ $k ][ 'event_date' ] = $value->event_date;
            $outputContent[ $jobKeyName ][ $k ][ 'id_project' ] = @$this->controller->project_data[ 0 ][ 'pid' ];
            $outputContent[ $jobKeyName ][ $k ][ 'id_job' ]     = trim( $value->id_job );
            $outputContent[ $jobKeyName ][ $k ][ 'lang_pairs' ] = trim( $jobLanguageDefinition[ $value->id_job ] );
            $outputContent[ $jobKeyName ][ $k ][ 'name' ]       = $value->first_name . " " . $value->last_name;
            $outputContent[ $jobKeyName ][ $k ][ 'email' ]      = $value->email;
            $outputContent[ $jobKeyName ][ $k ][ 'action' ]     = ActivityLogStruct::getAction( $value->action );

        }

        $this->template->isLoggedIn    = $this->controller->isLoggedIn();
        $this->template->logged_user   = ( $this->controller->getUser() !== false ) ? $this->controller->getUser()->shortName() : "";
        $this->template->extended_user = ( $this->controller->getUser() !== false ) ? trim( $this->controller->getUser()->fullName() ) : "";
        $this->template->outputContent = $outputContent;
        $this->template->projectID     = $this->controller->project_data[ 0 ][ 'pid' ];
        $this->template->projectName   = $this->controller->project_data[ 0 ][ 'pname' ];

        $this->template->projectUrl = Routes::analyze( array(
            'project_name' => $this->controller->project_data[0][ 'pname' ],
            'id_project' => $this->controller->project_data[ 0 ][ 'pid' ],
            'password' => $this->controller->project_data[ 0 ][ 'ppassword' ]
        ));

    }

}
