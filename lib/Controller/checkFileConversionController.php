<?php

class checkFileConversionController extends ajaxController {

      private $file_name;

      public function __construct() {

            parent::__construct();
            $this->file_name = $this->get_from_get_post('file_name');
      }

      public function doAction() {
            if (empty($this->file_name)) {
                  $this->result['errors'][] = array("code" => -1, "message" => "Missing file name.");
                  return false;
            }
            $intDir = INIT::$UPLOAD_REPOSITORY.'/'. $_COOKIE['upload_session'] . '_converted';

            $file_path = $intDir . '/' . $this->file_name . '.sdlxliff';
            
            if (file_exists($file_path)) {
                  $this->result['converted'] = 1;
            } else {
                  $this->result['converted'] = 0;
			}
            $this->result['file_name'] = $this->file_name;
			
          
      }

}

?>
