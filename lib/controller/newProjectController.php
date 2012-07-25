<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of catController
 *
 * @author antonio
 */
class newProjectController extends viewcontroller {
    //put your code here    
    public function __construct() {
	
        parent::__construct();
        parent::makeTemplate("upload.html");
        /*$this->id_file=$_GET['id_file'];
        if (empty($this->id_file)){
            $this->id_file=$_POST['id_file'];
        }*/
        $this->id_file="cc"; // ONLTY FOR TESTING PURPOSE
    }
    
    public function doAction(){       
    }
    
    public function setTemplateVars() {
        ;
    }
}


?>
