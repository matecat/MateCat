<?php

class changePasswordController extends ajaxController {
	protected $res_type;
	protected $res_id;
	protected $password;
	protected $undo;

	public function __construct() {

        parent::checkLogin();
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

        changePassword( $this->res_type, $this->res_id, $actual_pwd, $new_pwd );
        $this->result[ 'password' ] = $new_pwd;
        $this->result[ 'undo' ]     = $this->password;

	}

}

?>
