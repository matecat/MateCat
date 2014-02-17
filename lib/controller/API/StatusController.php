<?php

class StatusController extends getVolumeAnalysisController {

    protected $api_output = array(
            'status' => 'FAIL'
    );

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

        $this->disableSessions();

        $filterArgs = array(
                'id_project' => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'project_pass'  => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
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

        $this->formatApiData();

        if( !empty( $this->result['errors'] ) ){
            $this->api_output['message'] = $this->result['errors'][1];
        } else{
            $this->api_output = $this->result;
        }
        
    }
} 