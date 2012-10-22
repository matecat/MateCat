<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of catController
 *
 * @author antonio
 */
class editlogController extends viewcontroller {
    //put your code here
//    private $id_file="";
    private $jid = "";

    public function __construct() {
//		echo ".........\n";
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
    }


}


?>