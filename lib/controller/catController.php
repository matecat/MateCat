<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$ROOT . "/lib/utils/mymemory_queries_temp.php";
include INIT::$ROOT . "/lib/utils/filetype.class.php";

/**
 * Description of catController
 *
 * @author antonio
 */
class catController extends viewcontroller {

    //put your code here
    private $id_file = "";
    private $segments_data = array();
    private $start_from = 0;
    private $filetype_handler = null;

    public function __construct() {
        //   echo ".........\n";
        parent::__construct();
        parent::makeTemplate("index.html");
        /* $this->id_file=$_GET['id_file'];
          if (empty($this->id_file)){
          $this->id_file=$_POST['id_file'];
          } */
        $this->id_file = "cc"; // ONLTY FOR TESTING PURPOSE
        $this->start_from = $this->get_from_get_post("start");
        if (is_null($this->start_from)) {
            $this->start_from = 0;
        }
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
        return $text;
    }

    public function doAction() {
        if (empty($this->id_file)) {
            $this->postback("File not specified");
        }

        $data = getCurrentFormalOffer($this->id_file, $this->start_from);

        foreach ($data as $i => $seg) {
            $seg['segment'] = $this->stripTagesFromSource($seg['segment']);
            $seg['segment'] = trim($seg['segment']);
            if (empty($seg['segment'])) {
                continue;
            }

            $id_file = $seg['id_file'];

            if (!isset($this->segments_data["$id_file"])) {
                $this->segments_data["$id_file"]['mimetype'] = $seg['mimetype'];
                $this->segments_data["$id_file"]['filename'] = $seg['filename'];

                $this->segments_data["$id_file"]['segments'] = array();
                $this->filetype_handler = new filetype($seg['mimetype']);
            }
            
            //EXAMPLE : IN CASE OF DOC/DOCX FILES REVOME THE SEQUENCE xC2xA0
            $seg['segment'] = $this->filetype_handler->parse($seg['segment']);     
          
            unset($seg['id_file']);
            unset($seg['mimetype']);
            unset($seg['filename']);

            //TODO : IMPROVEMENT: FIND A MECHANISM FOR INCLUDE 
            //THIS CSS MANAGEMENT INSIDE THE TEMPLATE
            $seg["additional_css_class"] = "";
            if ($i % 2 != 0) {
                $seg["additional_css_class"] = "light";
            }



            if ($i == 0) { //get matches only for the first segment                
                $matches = array();
                $matches = getFromMM($seg['segment']);
                
                $matches=array_slice ($matches,0,INIT::$DEFAULT_NUM_RESULTS_FROM_TM);              
              
                $seg['matches'] = $matches;
                
                //$seg['matches_no_mt']=0;
                //foreach ($matches as $m){
                //    if ($m['created-by']!='MT'){
                //        $seg['matches_no_mt']+=1;
                //    }
                //}

                $seg['css_loaded'] = "loaded";
            }

            $this->segments_data["$id_file"]['segments'][] = $seg;
        }
//        echo "<pre>";
//        print_r($this->segments_data);
//        exit;
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
