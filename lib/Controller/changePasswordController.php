<?php

class changePasswordController extends ajaxController {
	protected $res_type;
	protected $res_id;
	protected $password;
	protected $undo;
    protected $old_password;

    public function __construct() {

        parent::readLoginInfo( false ); //need to write to the sessions
		parent::__construct();

        $filterArgs = array(
            'res'          => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'id'           => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'old_password' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'undo'         => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
        );

        $__postInput        = filter_input_array( INPUT_POST, $filterArgs );

        $this->res_type     = $__postInput[ 'res' ];
        $this->res_id       = $__postInput[ 'id' ];
        $this->password     = $__postInput[ 'password' ];
        $this->old_password = $__postInput[ 'old_password' ];
        $this->undo         = $__postInput[ 'undo' ];

    }

	public function doAction() {

        if( !$this->userIsLogged ){
            throw new Exception('User not Logged');
        }

        if ( $this->undo ){
            $new_pwd    = $this->old_password;
            $actual_pwd = $this->password;
        } else {
            $new_pwd    = CatUtils::generate_password();
            $actual_pwd = $this->password;
        }

        $this->changePassword( $actual_pwd, $new_pwd );

        $this->result[ 'password' ] = $new_pwd;
        $this->result[ 'undo' ]     = $this->password;

	}

	protected function changePassword( $actual_pwd, $new_password ){

        if ( $this->res_type == "prj" ) {

            $pStruct = Projects_ProjectDao::findByIdAndPassword( $this->res_id, $actual_pwd );
            $pDao = new Projects_ProjectDao();
            $pDao->changePassword( $pStruct, $new_password );
            $pDao->destroyCacheById( $this->res_id );

            $pStruct->getFeatures()
                    ->run('project_password_changed', $pStruct, $actual_pwd );

        } else {

            $jStruct = Jobs_JobDao::getByIdAndPassword( $this->res_id, $actual_pwd );
            $jDao = new Jobs_JobDao();
            $jDao->changePassword( $jStruct, $new_password );

            $jStruct->getProject()
                    ->getFeatures()
                    ->run('job_password_changed', $jStruct, $actual_pwd );
        }

    }

}