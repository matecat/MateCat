<?php

use Model\Analysis\Status;

class getVolumeAnalysisController extends ajaxController {
    protected $id_project;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'pid'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'ppassword' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'jpassword' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_project = $__postInput[ 'pid' ];
        $this->ppassword  = $__postInput[ 'ppassword' ];
        $this->jpassword  = $__postInput[ 'jpassword' ];

        /**
         * Retrieve user information
         */
        $this->readLoginInfo();

    }

    public function doAction() {

        if ( empty( $this->id_project ) ) {
            $this->result[ 'errors' ] = array( -1, "No id project provided" );
            return -1;
        }

        $_project_data = Projects_ProjectDao::getProjectAndJobData( $this->id_project );

        $passCheck = new AjaxPasswordCheck();
        $access    = $passCheck->grantProjectAccess( $_project_data, $this->ppassword ) || $passCheck->grantProjectJobAccessOnJobPass( $_project_data, null, $this->jpassword );

        if ( !$access ) {
            $this->result[ 'errors' ] = array( -10, "Wrong Password. Access denied" );
            return -1;
        }

        $analysisStatus = new Status( $_project_data, $this->featureSet, $this->user );
        $this->result = $analysisStatus->fetchData()->getResult();

    }

}

