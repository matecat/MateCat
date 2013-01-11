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
    }
    
    public function setTemplateVars() {
    	$this->template->jid = $this->jid;
    	$this->template->password = $this->password;
        $this->template->data = $this->data;
        $this->template->stats = $this->stats; 

	//echo "<pre>";
	//print_r ($this->data); exit;
    }


}


?>
