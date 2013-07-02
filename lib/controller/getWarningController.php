<?
include_once INIT::$MODEL_ROOT . "/queries.php";

class getWarningController extends ajaxcontroller{	

	private $id_job;

	public function __destruct(){
	}

	public function  __construct() {
		parent::__construct();	
		$this->id_job = $this->get_from_get_post('id_job');
	}

	function doAction (){
		$this->result=getWarning($this->id_job);
	}
}
?>