<?php

include_once INIT::$UTILS_ROOT . "/cat.class.php";

class passwordGeneratorController extends ajaxcontroller {

      public function __construct() {
            parent::__construct();
      }

      public function doAction() {
            $this->result['password'] = 'changedpassword';
      }

}

?>