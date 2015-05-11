<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 20/02/14
 * Time: 19.34
 * 
 */

class ChangeProjectPasswordController  extends ajaxController {

    protected $api_output = array(
            'status' => 'FAIL'
    );

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'id_project'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'old_pass'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
                'new_pass'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput        = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_project   = $__postInput[ 'id_project' ];
        $this->new_password = $__postInput[ 'new_pass' ];
        $this->old_password = $__postInput[ 'old_pass' ];

    }

    public function doAction() {

        $changePass = changePassword( 'prj', $this->id_project, $this->old_password, $this->new_password );

        if( $changePass <= 0 ){
            $this->api_output[ 'message' ]       = 'Wrong id or pass';
            return -1; //FAIL
        }

        $this->api_output[ 'status' ]       = 'OK';
        $this->api_output[ 'id_project' ]   = $this->id_project;
        $this->api_output[ 'project_pass' ] = $this->new_password;

    }

    public function finalize() {
        $toJson = json_encode( $this->api_output );
        echo $toJson;
    }

} 