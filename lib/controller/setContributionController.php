<?php

include INIT::$ROOT . "/lib/utils/mymemory_queries_temp.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class setContributionController extends ajaxcontroller {

	private $id_customer;
	private $id_translator;
	private $key = "";
	private $private_customer;
	private $private_translator;
	private $source;
	private $target;
	private $source_lang;
	private $target_lang;
	private $id_job;

    private $password;

	public function __construct() {
		parent::__construct();
		$this->id_job = $this->get_from_get_post('id_job');

		$this->id_customer = $this->get_from_get_post('id_customer');
		if (empty($this->id_customer)) {
			$this->id_customer = "Anonymous";
		}


		$this->id_translator = $this->get_from_get_post('id_translator');
		if (empty($this->id_translator)) {
			$this->id_translator = "Anonymous";
		}


		$this->private_customer = $this->get_from_get_post('private_customer');
		if (empty($this->private_customer)) {
			$this->private_customer = 0;
		}

		$this->private_translator = $this->get_from_get_post('private_translator');
		if (empty($this->private_translator)) {
			$this->private_translator = 0;
		}

		$this->password = $this->get_from_get_post('password');

		$this->source = $this->get_from_get_post('source');
		$this->target = $this->get_from_get_post('target');
		$this->source_lang = $this->get_from_get_post('source_lang');
		$this->target_lang = $this->get_from_get_post('target_lang');
	}

	public function doAction() {
		if (is_null($this->source) || $this->source === '') {
			$this->result['error'][] = array("code" => -1, "message" => "missing source segment");
		}

		if ( is_null($this->target) || $this->target === '' ) {
			$this->result['error'][] = array("code" => -2, "message" => "missing target segment");
		}


		if (empty($this->source_lang)) {
			$this->result['error'][] = array("code" => -3, "message" => "missing source lang");
		}

		if (empty($this->target_lang)) {
			$this->result['error'][] = array("code" => -2, "message" => "missing target lang");
		}

        //get Job Infos
        $job_data = getJobData( (int) $this->id_job );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if( !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ){
            $this->result['error'][] = array("code" => -10, "message" => "wrong password");
        }

		if (!empty($this->result['error'])) {
			return -1;
		}

		if (!empty($this->id_translator)) {
			$this->key = $this->calculateMyMemoryKey($this->id_translator);
		}

        $id_tms = 1;
        if ( !empty( $this->id_job ) ) { //PER COMPATIBILITa: IL BLOCCO IF PUO ESSERE ELIMINATO DOPO ?? dopo cosa???
            $st     = $job_data; //getJobData($this->id_job);
            $id_tms = $st[ 'id_tms' ];
        }


		if ($id_tms != 0) {
			$set_results = addToMM($this->source, $this->target, $this->source_lang, $this->target_lang, $this->id_translator, $this->key);
			$this->result['code'] = 1;
			$this->result['data'] = "OK";
		} else {
			$this->result['code'] = 1;
			$this->result['data'] = "NOCONTRIB_OK";
		}
	}

	private function calculateMyMemoryKey($id_translator) {
		$key = getTranslatorKey($id_translator);
		return $key;
	}

}
?>

