<?php
include INIT::$ROOT."/lib/utils/mymemory_queries_temp.php";
include_once INIT::$UTILS_ROOT . "/engines/mt.class.php";
include_once INIT::$UTILS_ROOT . "/engines/tms.class.php";
include_once INIT::$MODEL_ROOT . "/queries.php";

class deleteContributionController extends ajaxcontroller {

//    private $id_contribution;
    private $seg;
    private $tra;
    private $source_lang;
    private $target_lang;
//    private $source_hash;
    private $id_translator;

    public function __construct() {
        parent::__construct();

        $this->source_lang = $this->get_from_get_post('source_lang');
        $this->target_lang = $this->get_from_get_post('target_lang');
        $this->source = trim($this->get_from_get_post('seg'));
        $this->target = trim($this->get_from_get_post('tra'));
        $this->id_translator=trim($this->get_from_get_post('id_translator'));
        
    }

    public function doAction() {


        if (empty($this->source_lang)) {
            $this->result['error'][] = array("code" => -1, "message" => "missing source_lang");
        }    	

        if (empty($this->target_lang)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing target_lang");
        }    

        if (empty($this->source)) {
            $this->result['error'][] = array("code" => -3, "message" => "missing source");
        }

        if (empty($this->target)) {
            $this->result['error'][] = array("code" => -4, "message" => "missing target");
        }

        //$result = deleteToMM($this->source_lang, $this->target_lang, $this->source, $this->target);
	$tms=new TMS(1);

	$result = $tms->delete($this->source, $this->target,$this->source_lang, $this->target_lang,$this->id_translator);
	
        $this->result['code'] = $result;
        $this->result['data'] = "OK";
    }


}

?>
