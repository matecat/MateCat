<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";

/**
 * Description of catController
 *
 * @author antonio
 */
class editlogController extends viewcontroller {
    private $jid = "";
    private $pid="";

    public function __construct() {
        parent::__construct();
        parent::makeTemplate("editlog.html");
        $this->jid = $this->get_from_get_post("jid");
        $this->password = $this->get_from_get_post("password");
        
    }

    public function doAction() {
    	
    	 $tmp = CatUtils::getEditingLogData($this->jid,$this->password);
         $this->data = $tmp[0];
         $this->stats = $tmp[1];

		$this->job_stats = CatUtils::getStatsForJob($this->jid);
		if (count($files_found)==1){
			$this->downloadFileName=$files_found[0];
		}

         
//    	log::doLog('SOURCE_CODE:');
//		$coso = $this->data;
//    	log::doLog($coso[0]['pname']);
//    	log::doLog($this->data[0]['source_code']);
//        log::doLog($this->data);
    }
    
    public function setTemplateVars() {
    	$this->template->jid = $this->jid;
    	$this->template->password = $this->password;
        $this->template->data = $this->data;
        $this->template->stats = $this->stats; 
        $this->template->pname=$this->data[0]['pname'];
        $this->template->source_code=$this->data[0]['source_lang'];
        $this->template->target_code=$this->data[0]['target_lang'];
		$this->template->job_stats=$this->job_stats;

	//echo "<pre>";
	//print_r ($this->data); exit;
    }


}


?>
