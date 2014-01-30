<?php

include_once INIT::$MODEL_ROOT . "/queries.php";

class pauseResumeController extends ajaxController {

	private $id_project;
	private $act;

	public function __construct() {
		parent::__construct();
		$this->id_project = $this->get_from_get_post('pid');
		$this->act = $this->get_from_get_post('act');
	}

	public function doAction() {
		if (empty($this->id_project)) {
			$this->result['errors'] = array(-1, "No id project provided");
			return -1;
		}
		$status = ($this->act == 'cancel')? 'CANCEL' : 'NEW';

		$res = changeProjectStatus($this->id_project,$status);

	}

}

?>
