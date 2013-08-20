<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";

class checkFileConversionController extends ajaxcontroller {

      private $file_name;

      public function __construct() {
            parent::__construct();
      }

    /**
        File conversion is not available in open source version.<br />
        But feel free to implement on your own.<br />
        In the meanwhile please, disable this functionality in config.inc.php
        <br />
        <br />
        self::$CONVERSION_ENABLED = false;
     */
      public function doAction() {
            $this->result['converted'] = 1;
      }

}

?>
