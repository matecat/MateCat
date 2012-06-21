<?php

include_once INIT::$MODEL_ROOT . "/queries.php";

/**
 * Description of catController
 *
 * @author antonio
 */
class catController extends viewcontroller {

    //put your code here
    private $id_file = "";
    private $segments_data = array();

    public function __construct() {
        echo ".........\n";
        parent::__construct();
        parent::makeTemplate("index.html");
        /* $this->id_file=$_GET['id_file'];
          if (empty($this->id_file)){
          $this->id_file=$_POST['id_file'];
          } */
        $this->id_file = "cc"; // ONLTY FOR TESTING PURPOSE
    }

    private function stripTagesFromSource($text) {
        //       echo "<pre>";


        $pattern_g_o = '|(<.*?>)|';
        $pattern_g_c = '|(</.*?>)|';
        $pattern_x = '|(<.*?/>)|';

        // echo "first  -->  $text \n";
        $text = preg_replace($pattern_x, "", $text);
        // echo "after1  --> $text\n";

        $text = preg_replace($pattern_g_o, "", $text);
        //  echo "after2  -->  $text \n";
//
        $text = preg_replace($pattern_g_c, "", $text);
        // echo "after3  -->  $text \n\n";
        $text=  html_entity_decode($text);
        
        return $text;
    }

    public function doAction() {
        if (empty($this->id_file)) {
            $this->postback("File not specified");
        }

        $data = getCurrentFormalOffer($this->id_file);

        foreach ($data as $i => $seg) {
            $id_file = $seg['id_file'];
            $seg['segment'] = $this->stripTagesFromSource($seg['segment']);
            unset($seg['id_file']);
            if (!isset($this->segments_data["$id_file"])) {
                $this->segments_data["$id_file"] = array();
            }

            //TODO : IMPROVEMENT: FIND A MECHANISM FOR INCLUDE 
            //THIS CSS MANAGEMENT INSIDE THE TEMPLATE
            $seg["additional_css_class"] = "";
            if ($i % 2 != 0) {
                $seg["additional_css_class"] = "light";
            }
            
            
            
            if ($i==0) { //get matches only for the first segment
                $fake_matches = array();
                $fake_matches[] = array("segment" => $seg['segment'], "translation" => "LISTEN > LEARN > LEAD", "quelity" => 74, "created_by" => "Vicky", "last_update_date" => "2011-08-21 14:30", "match" => 1);
                
                $matches = $fake_matches;
                $seg['matches']=$matches;
                $seg['css_loaded']="loaded";
            }
            
            

            $this->segments_data["$id_file"][] = $seg;
        }
//        echo "<pre>";
//        print_r ($this->segments_data);
//		exit;
    }

    public function setTemplateVars() {
        $this->template->segments_data = $this->segments_data;

//        echo "<pre>";
//        print_r ($this->template);
//        exit;
        ;
    }

}

?>
