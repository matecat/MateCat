<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";

class cancelJobsController extends ajaxcontroller {

      private $res_type;
      private $res_id;

      public function __construct() {
            parent::__construct();
            $this->res_type = $this->get_from_get_post('res');
            $this->res_id = $this->get_from_get_post('id');
      }

      public function doAction() {
            if ($this->res_type == "prj") {
                  $query = "update jobs set disabled=1 where id_project=$this->res_id";
            } else {
                  $query = "update jobs set disabled=1 where id=$this->res_id";
            }



/*
            if (empty($this->file_name)) {
                  $this->result['errors'][] = array("code" => -1, "message" => "Missing file name.");
                  return false;
            }
            $intDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/upload/' . $_COOKIE['upload_session'] . '_converted';

            $file_path = $intDir . '/' . $this->file_name . '.sdlxliff';
            
//    	log::doLog('FILEPATH: ' .$file_path);

            if (file_exists($file_path)) {
                  $this->result['converted'] = 1;
            } else {
                  $this->result['converted'] = 0;
			}
            $this->result['file_name'] = $this->file_name;
*/			
          
      }

}

?>