<?php
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";

class changePasswordController extends ajaxcontroller {
	private $res_type;
	private $res_id;
	private $password;

	public function __construct() {
		parent::__construct();
		$this->res_type = $this->get_from_get_post('res');
		$this->res_id = $this->get_from_get_post('id');
		if($this->get_from_get_post('password') != '') {
			$this->password = $this->get_from_get_post('password');
		} else {
			$this->password = false;
		}
	}

	public function doAction() {

		$pwd = ($this->password)? $this->password : CatUtils::generate_password();

		$changePass = changePassword($this->res_type, $this->res_id,$pwd);
		$this->result['password'] = $pwd;
		$this->result['undo'] = $this->password;
	}

}

?>
