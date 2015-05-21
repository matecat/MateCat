<?php
/**
 * User: gremorian
 * Date: 11/05/15
 * Time: 20.37
 * 
 */

class downloadAnalysisReportController extends downloadController {

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

    }


    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {

        $_project_data = getProjectJobData( $this->id_project );

        $pCheck = new AjaxPasswordCheck();
        $access = $pCheck->grantProjectAccess( $_project_data, $this->password );

        //check for Password correctness
        if ( !$access ) {
            $msg = "Error : wrong password provided for download \n\n " . var_export( $_POST, true ) . "\n";
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
            return null;
        }

        $analysisStatus = new Analysis_XTRFStatus( $_project_data );
        $outputContent = $analysisStatus->fetchData()->getResult();

        $this->content = $this->composeZip( $_project_data[0][ 'pname' ], $outputContent );
        $this->filename = $_project_data[0][ 'pname' ] . ".zip";

    }

    protected function composeZip( $projectName , $outputContent ) {

        $fileName = tempnam( "/tmp", "zipmat" );
        $zip  = new ZipArchive();
        $zip->open( $fileName, ZipArchive::OVERWRITE );

        // Staff with content
        foreach ( $outputContent as $jobName => $jobAnalysisValues ) {
            $zip->addFromString( "Job-" . $jobName, $jobAnalysisValues );
        }

        // Close and send to users
        $zip->close();

        $fileContent = file_get_contents( $fileName );
        unlink( $fileName );

        return $fileContent;

    }

}