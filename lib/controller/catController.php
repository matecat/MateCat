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
class catController extends viewcontroller {
    //put your code here
    private $id_file="";
    public function __construct() {
	echo ".........\n";
        parent::__construct();
        parent::makeTemplate("index.html");
        /*$this->id_file=$_GET['id_file'];
        if (empty($this->id_file)){
            $this->id_file=$_POST['id_file'];
        }*/
        $this->id_file="cc"; // ONLTY FOR TESTING PURPOSE
    }
    
    public function doAction(){
        if (empty($this->id_file)){
            $this->postback("File not specified");
        }

        
    }
    
    public function setTemplateVars() {
        ;
    }
}


?>
