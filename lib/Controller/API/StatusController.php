<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */
class StatusController extends ajaxController {

    protected $api_output = array(
        'status' => 'FAIL'
    );

    protected $id_project;
    protected $ppassword;

    /**
     * @param mixed $id_project
     *
     * @return $this
     */
    public function setIdProject( $id_project ) {
        $this->id_project = $id_project;

        return $this;
    }

    /**
     * @param mixed $ppassword
     *
     * @return $this
     */
    public function setPpassword( $ppassword ) {
        $this->ppassword = $ppassword;

        return $this;
    }

    public function getApiOutput(){
        return json_encode( $this->api_output );
    }

    /**
     * Check Status of a created Project With HTTP POST ( application/x-www-form-urlencoded ) protocol
     *
     * POST Params:
     *
     * 'id_project'         => (int)    ID of Project to check
     * 'ppassword'          => (string) Project Password
     *
     */
    public function __construct() {

        parent::__construct();

        $filterArgs = array(
            'id_project'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'project_pass' => array(
                'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
        );

        $__postInput = filter_input_array( INPUT_GET, $filterArgs );

        $this->id_project = $__postInput[ 'id_project' ];
        $this->ppassword  = $__postInput[ 'project_pass' ];

    }

    public function finalize() {
        $toJson = json_encode( $this->api_output );
        echo $toJson;
    }

    public function doAction() {

        if ( empty( $this->id_project ) ) {
            $this->api_output[ 'message' ] = array( -1, "No id project provided" );

            return -1;
        }

        $_project_data = Projects_ProjectDao::getProjectAndJobData( $this->id_project );

        $passCheck = new AjaxPasswordCheck();
        $access    = $passCheck->grantProjectAccess( $_project_data, $this->ppassword ) || $passCheck->grantProjectJobAccessOnJobPass( $_project_data, null, $this->jpassword );

        if ( !$access ) {
            $this->api_output[ 'message' ] = array( -10, "Wrong Password. Access denied" );

            return -1;
        }

        $analysisStatus   = new Analysis_APIStatus( $_project_data, $this->featureSet );
        $this->api_output = $analysisStatus->fetchData()->getResult();

    }

} 