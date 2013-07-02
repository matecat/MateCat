<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include INIT::$UTILS_ROOT . "/langs/languages.class.php";

/**
 * Description of manageController
 *
 * @author andrea
 */
class manageController extends viewcontroller {

	private $jid = "";
	private $pid = "";
	public $notAllCancelled = 0;

	public function __construct() {
		parent::__construct(true);
		parent::makeTemplate("manage.html");
		$this->jid = $this->get_from_get_post("jid");
		$this->password = $this->get_from_get_post("password");
		$this->lang_handler = Languages::getInstance();
		if (isset($_GET['page'])) {
			$this->page = ($_GET['page'] == '') ? 1 : $_GET['page'];
		} else {
			$this->page = 1;
		};

		if (isset($_GET['filter'])) {
			$this->filter_enabled = true;
		} else {
			$this->filter_enabled = false;
		};
	}

	public function doAction() {

	}

	public function setTemplateVars() {

		$this->template->prev_page = ($this->page - 1);
		$this->template->next_page = ($this->page + 1);
		$this->template->languages = $this->lang_handler->getEnabledLanguages('en');
		$this->template->filtered = $this->filter_enabled;
		$this->template->filtered_class = ($this->filter_enabled) ? ' open' : '';
		$this->template->logged_user = trim($this->logged_user['first_name'] . " " . $this->logged_user['last_name']);
		$this->template->build_number = INIT::$BUILD_NUMBER;
	}

}

?>
