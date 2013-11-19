<?
include_once INIT::$MODEL_ROOT . "/queries.php";

class formLoginController extends ajaxcontroller{	
	private $login='';
	private $pass='';	

	public function  __construct() {
		parent::__construct();

		//set parameters
		if(isset($_POST['login']) and !empty($_POST['login'])){
			$this->login=$_POST['login'];
		}

		if(isset($_POST['pass']) and !empty($_POST['pass'])){
			$this->pass=$_POST['pass'];
		}
	}

	function doAction (){	
		//if parameters are set
		if(!empty($this->login) or !empty($this->pass) and !isset($_POST['logout']) and !isset($_POST['reset'])){
			//check login
			$this->doLogin();
		}
		if(isset($_POST['logout'])){
			unset($_SESSION['cid']);
			AuthCookie::destroyAuthentication();
			$this->result='unlogged';
		}
		if(isset($_POST['reset'])){
			$outcome=sendResetLink($this->login);
			if($outcome) $this->result='sent';
		}
	}


	private function doLogin(){
		$result=checkLogin($this->login,$this->pass);
		if($result){
			//if ok, write in session
			$_SESSION['cid']=$_POST['login'];
			$this->result='logged';
		}

	}
}
?>
