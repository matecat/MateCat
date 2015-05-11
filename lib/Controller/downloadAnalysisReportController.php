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

        //get job language and data
        //Fixed Bug: need a specific job, because we need The target Language
        //Removed from within the foreach cycle, the job is always the same....
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

        $analysisStatus = new Analysis_APIStatus( $_project_data );
        $result = $analysisStatus->fetchData()->getResult();

    }


}