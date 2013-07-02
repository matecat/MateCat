<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$UTILS_ROOT . "/mymemory_queries_temp.php";
include INIT::$UTILS_ROOT . "/filetype.class.php";
include INIT::$UTILS_ROOT . "/cat.class.php";
include INIT::$UTILS_ROOT . "/langs/languages.class.php";

/**
 * Description of catController
 *
 * @author antonio
 */
class catController extends viewcontroller {

	private $data = array();
	private $cid = "";
	private $jid = "";
	private $tid = "";
	private $password = "";
	private $source = "";
	private $pname = "";
	private $create_date = "";
	private $filetype_handler = null;
	private $start_from = 0;
	private $page = 0;
	private $start_time = 0.00;
	private $downloadFileName;
	private $job_stats = array();
	private $source_rtl = false;
	private $target_rtl = false;

	private $job_not_found = false;
	private $job_archived = false;
	private $job_cancelled = false;

	private $thisUrl;

	public function __construct() {
		$this->start_time = microtime(1) * 1000;

		parent::__construct(false);
		parent::makeTemplate("index.html");
		$this->jid = $this->get_from_get_post("jid");
		$this->password = $this->get_from_get_post("password");
		$this->start_from = $this->get_from_get_post("start");
		$this->page = $this->get_from_get_post("page");

		if (isset($_GET['step'])) {
			$this->step = $_GET['step'];
		} else {
			$this->step = 1000;
		};

		if (is_null($this->page)) {
			$this->page = 1;
		}
		if (is_null($this->start_from)) {
			$this->start_from = ($this->page - 1) * $this->step;
		}

		$this->downloadFileName = "";

		$this->thisUrl=$_SERVER['REQUEST_URI'];

	}

	public function __destruct(){
	}

	private function parse_time_to_edit($ms) {
		if ($ms <= 0) {
			return array("00", "00", "00", "00");
		}

		$usec = $ms % 1000;
		$ms = floor($ms / 1000);

		$seconds = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
		$ms = floor($ms / 60);

		$minutes = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
		$ms = floor($ms / 60);

		$hours = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
		$ms = floor($ms / 60);

		return array($hours, $minutes, $seconds, $usec);
	}

	public function doAction() {
		$files_found = array();
		$lang_handler = Languages::getInstance();

		$data = getSegmentsInfo($this->jid, $this->password);
		if (empty($data) or $data < 0) {
			$this->job_not_found = true;
		}


		$first_not_translated_found = false;

		foreach ($data as $i => $seg) {

			if (empty($this->pname)) {
				$this->pname = $seg['pname'];
				$this->downloadFileName = $seg['pname'] . ".zip"; // will be overwritten below in case of one file job
			}

			if (empty($this->last_opened_segment)) {
				$this->last_opened_segment = $seg['last_opened_segment'];
			}

			if (empty($this->cid)) {
				$this->cid = $seg['cid'];
			}

			if (empty($this->pid)) {
				$this->pid = $seg['pid'];
			}

			if (empty($this->tid)) {
				$this->tid = $seg['tid'];
			}

			if (empty($this->create_date)) {
				$this->create_date = $seg['create_date'];
			}

			if (empty($this->source_code)) {
				$this->source_code = $seg['source'];
			}

			if (empty($this->target_code)) {
				$this->target_code = $seg['target'];
			}

			if (empty($this->source)) {
				$s = explode("-", $seg['source']);
				$source = strtoupper($s[0]);
				$this->source = $source;
				$this->source_rtl= ($lang_handler->isRTL(strtolower($this->source)))? ' rtl-source' : '';
			}

			if (empty($this->target)) {
				$t = explode("-", $seg['target']);
				$target = strtoupper($t[0]);
				$this->target = $target;
				$this->target_rtl= ($lang_handler->isRTL(strtolower($this->target)))? ' rtl-target' : '';
			}
			//check if language belongs to supported right-to-left languages


			if ($seg['status'] == 'archived') {
				$this->job_archived = true;
			}
			if ($seg['status'] == 'cancelled') {
				$this->job_cancelled = true;
			}
			$id_file = $seg['id_file'];


			if (!isset($this->data["$id_file"])) {
				$files_found[] = $seg['filename'];
				$file_stats = CatUtils::getStatsForFile($id_file);

				$this->data["$id_file"]['jid'] = $seg['jid'];
				$this->data["$id_file"]["filename"] = $seg['filename'];
				$this->data["$id_file"]["mime_type"] = $seg['mime_type'];
				$this->data["$id_file"]['id_segment_start'] = $seg['id_segment_start'];
				$this->data["$id_file"]['id_segment_end'] = $seg['id_segment_end'];
				$this->data["$id_file"]['source'] = $lang_handler->getLocalizedName($seg['source'],'en');
				$this->data["$id_file"]['target'] = $lang_handler->getLocalizedName($seg['target'],'en');
				$this->data["$id_file"]['source_code'] = $seg['source'];
				$this->data["$id_file"]['target_code'] = $seg['target'];
				$this->data["$id_file"]['last_opened_segment'] = $seg['last_opened_segment'];
				$this->data["$id_file"]['file_stats'] = $file_stats;
			}
			$this->filetype_handler = new filetype($seg['mime_type']);



			unset($seg['id_file']);
			unset($seg['source']);
			unset($seg['target']);
			unset($seg['source_code']);
			unset($seg['target_code']);
			unset($seg['mime_type']);
			unset($seg['filename']);
			unset($seg['jid']);
			unset($seg['pid']);
			unset($seg['cid']);
			unset($seg['tid']);
			unset($seg['pname']);
			unset($seg['create_date']);
			unset($seg['id_segment_end']);
			unset($seg['id_segment_start']);
			unset($seg['last_opened_segment']);
		}

		if (empty($this->last_opened_segment)) {
			$this->last_opened_segment = getFirstSegmentId($this->jid, $this->password);
		}

		$this->job_stats = CatUtils::getStatsForJob($this->jid);
		if (count($files_found) == 1) {
			$this->downloadFileName = $files_found[0];
		}

	}

	public function setTemplateVars() {
		$this->template->jid = $this->jid;
		$this->template->password = $this->password;
		$this->template->cid = $this->cid;
		$this->template->create_date = $this->create_date;
		$this->template->pname = $this->pname;
		$this->template->pid = $this->pid;
		$this->template->tid = $this->tid;
		$this->template->source = $this->source;
		$this->template->target = $this->target;
		$this->template->source_rtl = $this->source_rtl;
		$this->template->target_rtl = $this->target_rtl;

		$this->template->source_code = $this->source_code;
		$this->template->target_code = $this->target_code;

		$this->template->last_opened_segment = $this->last_opened_segment;
		$this->template->data = $this->data;

		$this->template->job_stats = $this->job_stats;

		$end_time = microtime(true) * 1000;
		$load_time = $end_time - $this->start_time;
		$this->template->load_time = $load_time;
		$this->template->time_to_edit_enabled = INIT::$TIME_TO_EDIT_ENABLED;
		$this->template->build_number = INIT::$BUILD_NUMBER;
		$this->template->downloadFileName = $this->downloadFileName;
		$this->template->job_not_found = $this->job_not_found;
		$this->template->job_archived = ($this->job_archived)? ' archived' : '';
		$this->template->job_cancelled = $this->job_cancelled;
		$this->template->logged_user=trim($this->logged_user['first_name']." ".$this->logged_user['last_name']);
		$this->template->incomingUrl = '/login?incomingUrl='.$this->thisUrl;
		$this->template->warningPollingInterval=1000*(INIT::$WARNING_POLLING_INTERVAL);
	}

}
?>
