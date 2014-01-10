<?php
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . "/manage.class.php";
include_once INIT::$UTILS_ROOT . "/langs/languages.class.php";

/**
 * Description of manageController
 *
 * @author andrea
 */

class getProjectsController extends ajaxcontroller {

	private $jid = "";
	private $pid = "";
	public $notAllCancelled = 0;

	public function __construct() {

        $this->disableSessions();
		parent::__construct();

		$this->lang_handler=Languages::getInstance();
		if (isset($_POST['page'])) {
			$this->page = ($_POST['page'] == '')? 1 : $_POST['page'];
		} else {
			$this->page = 1;
		};

		if (isset($_POST['step'])) {
			$this->step = $_POST['step'];
		} else {
			$this->step = 100;
		};

		if (isset($_POST['project'])) {
			$this->project_id = $_POST['project'];
		} else {
			$this->project_id = false;
		};

		if (isset($_POST['filter'])) {
			$this->filter_enabled = true;
		} else {
			$this->filter_enabled = false;
		};

		if (isset($_POST['pn'])) {
			$this->search_in_pname = $_POST['pn'];
		} else {
			$this->search_in_pname = false;
		};

		if (isset($_POST['source'])) {
			$this->search_source = $_POST['source'];
		} else {
			$this->search_source = false;
		};

		if (isset($_POST['target'])) {
			$this->search_target = $_POST['target'];
		} else {
			$this->search_target = false;
		};

		if (isset($_POST['status'])) {
			$this->search_status = $_POST['status'];
		} else {
			$this->search_status = 'active';
		};

		if (isset($_POST['onlycompleted'])) {
			$this->search_onlycompleted = $_POST['onlycompleted'];
		} else {
			$this->search_onlycompleted = false;
		};	
	}

	public function doAction() {

		$time_loop_tot=0;
		$time_loop=0;
		$start = (($this->page - 1) * $this->step);

		$projects = ManageUtils::queryProjects($start,$this->step,$this->search_in_pname,$this->search_source,$this->search_target,$this->search_status,$this->search_onlycompleted,$this->filter_enabled,$this->project_id);

		$projnum = getProjectsNumber($start,$this->step,$this->search_in_pname,$this->search_source,$this->search_target,$this->search_status,$this->search_onlycompleted,$this->filter_enabled);

        	//log::doLog('PNUMBER:',$projnum);

		$this->result['data'] = json_encode($projects);
		$this->result['page'] = $this->page;
		$this->result['pnumber'] = $projnum[0]['c'];

	}


	public function cmp($a, $b) {
		return strcmp($a["id"], $b["id"]);
	}



}

?>
