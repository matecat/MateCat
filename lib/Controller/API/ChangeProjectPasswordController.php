<?php

use Exceptions\NotFoundException;

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 20/02/14
 * Time: 19.34
 * 
 */

class ChangeProjectPasswordController  extends ajaxController {

    protected array $api_output = array(
            'status' => 'FAIL'
    );
    private         $id_project;
    private $new_password;
    private $old_password;

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

        Log::doJsonLog("ChangeProjectPasswordController params: id_project=$this->id_project new_pass=$this->new_password old_pass=$this->old_password");
    }

    public function doAction() {

        $pDao = new Projects_ProjectDao();
        try {
            $pStruct = Projects_ProjectDao::findByIdAndPassword( $this->id_project, $this->old_password );
        } catch ( NotFoundException $e ) {
            $this->api_output[ 'message' ] = 'Wrong id or pass';
            Log::doJsonLog( "ChangeProjectPasswordController error: " . $this->api_output[ 'message' ] );

            return -1; //FAIL
        }

        $pDao->changePassword( $pStruct, $this->new_password );
        $pDao->destroyCacheById( $this->id_project ) ;
        ( new Jobs_JobDao() )->destroyCacheByProjectId( $this->id_project );

        $pStruct->getFeaturesSet()->run('project_password_changed', $pStruct, $this->old_password );

        $this->api_output[ 'status' ]       = 'OK';
        $this->api_output[ 'id_project' ]   = $this->id_project;
        $this->api_output[ 'project_pass' ] = $this->new_password;

        Log::doJsonLog( "ChangeProjectPasswordController result: " . $this->api_output[ 'status' ] );

    }

    public function finalize() {
        $toJson = json_encode( $this->api_output );
        echo $toJson;
    }

} 