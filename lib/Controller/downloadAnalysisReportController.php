<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

/**
 * User: gremorian
 * Date: 11/05/15
 * Time: 20.37
 * 
 */

class downloadAnalysisReportController extends downloadController {

    /**
     * @var int
     */
    protected $id_project;

    /**
     * @var string
     */
    protected $download_type;  // switch flag, for now not important

    public function __construct() {

        $filterArgs = array(
            'id_project'    => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'      => array(
                'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
            'download_type' => array(
                'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            )
        );

        $__postInput = filter_var_array( $_REQUEST, $filterArgs );

        $this->id_project    = $__postInput[ 'id_project' ];
        $this->password      = $__postInput[ 'password' ];
        $this->download_type = $__postInput[ 'download_type' ]; // switch flag, for now not important

        $this->featureSet = new FeatureSet();

    }


    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     * @throws Exception
     */
    function doAction() {

        $_project_data = Projects_ProjectDao::getProjectAndJobData( $this->id_project );

        $pCheck = new AjaxPasswordCheck();
        $access = $pCheck->grantProjectAccess( $_project_data, $this->password );

        //check for Password correctness
        if ( !$access ) {
            $msg = "Error : wrong password provided for download \n\n " . var_export( $_POST, true ) . "\n";
            Log::doJsonLog( $msg );
            Utils::sendErrMailReport( $msg );
            return null;
        }

        $this->featureSet->loadForProject( Projects_ProjectDao::findById( $this->id_project, 60 * 60 * 24 ) );

        $analysisStatus = new Analysis_XTRFStatus( $_project_data, $this->featureSet );
        $outputContent = $analysisStatus->fetchData()->getResult();

        $this->outputContent = $this->composeZip( $_project_data[0][ 'pname' ], $outputContent );
        $this->_filename     = $_project_data[0][ 'pname' ] . ".zip";

        /**
         * Retrieve user information
         */
        $this->readLoginInfo();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $_project_data[ 0 ][ 'jid' ];
        $activity->id_project = $this->id_project; //assume that all rows have the same project id
        $activity->action     = ActivityLogStruct::DOWNLOAD_ANALYSIS_REPORT;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

    protected static function composeZip( $projectName , $outputContent ) {

        $fileName = tempnam( "/tmp", "zipmat" );
        $zip  = new ZipArchive();
        $zip->open( $fileName, ZipArchive::OVERWRITE );

        // Staff with content
        foreach ( $outputContent as $jobName => $jobAnalysisValues ) {
            $zip->addFromString( "Job-" . $jobName . ".txt", $jobAnalysisValues );
        }

        // Close and send to users
        $zip->close();

        $fileContent = file_get_contents( $fileName );
        unlink( $fileName );

        return $fileContent;

    }

}