<?php
include_once INIT::$MODEL_ROOT."/queries.php";
/**
 * Description of catController
 *
 * @author antonio
 */
class catController extends viewcontroller {
    //put your code here
    private $id_file="";
    private $segments_data=array();
    
    
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
        
        $data=getCurrentFormalOffer($this->id_file);
        foreach ($data as $seg){
            $id_file=$seg['id_file'];
            unset ($seg['id_file']);
            if (!isset ($this->segments_data["$id_file"])){
                $this->segments_data["$id_file"]=array();
            }
            $this->segments_data["$id_file"][]=$seg;                     
        }
//        echo "<pre>";
//        print_r ($this->segments_data);
//        exit;
                      
    }
    
    public function setTemplateVars() {        
        $this->template->segments_data=$this->segments_data;
        
//        echo "<pre>";
//        print_r ($this->template);
//        exit;
//        ;
    }
}


?>
