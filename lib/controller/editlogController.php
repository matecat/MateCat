<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/CatUtils.php";

/**
 * Description of catController
 *
 * @author antonio
 */
class editlogController extends viewController {

	private $jid = "";
	private $pid = "";
	private $thisUrl;

	public function __construct() {
		parent::__construct();
		parent::makeTemplate("editlog.html");
		$this->jid = $this->get_from_get_post("jid");
		$this->password = $this->get_from_get_post("password");
		$this->thisUrl=$_SERVER['REQUEST_URI'];

        /**
         * Temporary hack to Test TER Versions
         */
        if( isset($_GET['v']) ){
            $this->ter_test = (bool)$_GET['v'];
        }

    }

	public function doAction() {

		$tmp = CatUtils::getEditingLogData($this->jid, $this->password, $this->ter_test);
		$this->data = $tmp[0];
		$this->stats = $tmp[1];

		$this->job_stats = CatUtils::getStatsForJob($this->jid);

	}

	public function setTemplateVars() {
		$this->template->jid = $this->jid;
		$this->template->password = $this->password;
		$this->template->data = $this->data;
		$this->template->stats = $this->stats;
		$this->template->pname = $this->data[0]['pname'];
		$this->template->source_code = $this->data[0]['source_lang'];
		$this->template->target_code = $this->data[0]['target_lang'];
		$this->template->job_stats = $this->job_stats;
		$this->template->build_number = INIT::$BUILD_NUMBER;
		$this->template->logged_user = trim($this->logged_user['first_name'] . " " . $this->logged_user['last_name']);
		$this->template->incomingUrl = '/login?incomingUrl='.$this->thisUrl;

	}

}

?>
