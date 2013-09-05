<?php
include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$UTILS_ROOT . "/filetype.class.php";
include INIT::$UTILS_ROOT . "/cat.class.php";
include INIT::$UTILS_ROOT . "/langs/languages.class.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class getSpellcheck_fakeController extends ajaxcontroller {

	private $id_segment;
	private $id_job;
	private $num_results;
	private $text;
	private $source;
	private $target;
	private $id_mt_engine;
	private $id_tms;
	private $id_translator;

    private $__postInput = array();

	public function __construct() {
		parent::__construct();

	}

	public function doAction() {

//        $this->result['data'] = '[{"chiave1":["primo","secondo","terzo"]},{"chiave2":"valore2"},{"chiave3":"valore3"}]';
        
$a = array('<foo>',"'bar'",'"baz"','&blong&', "\xc3\xa9");
$ar = '{coso:22}';
        $this->result['data'] = $ar;


    }

}


?>
