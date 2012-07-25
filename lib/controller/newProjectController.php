<?php
class newProjectController extends viewcontroller {
    //put your code here
    public function __construct() {
	echo ".........\n";
        parent::__construct();
        parent::makeTemplate("upload.html");
    }
    
    public function doAction(){
        
    }
    
    public function setTemplateVars() {
        ;
    }
}


?>
