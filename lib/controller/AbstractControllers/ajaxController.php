<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/01/14
 * Time: 18.57
 * 
 */

abstract class ajaxController extends controller {

    protected $result;

    public function disableSessions(){
        INIT::sessionClose();
    }

    protected function __construct() {
        parent::__construct();
        $buffer = ob_get_contents();
        ob_get_clean();
        // ob_start("ob_gzhandler");        // compress page before sending
        header('Content-Type: application/json; charset=utf-8');
        $this->result = array("error" => array(), "data" => array());
    }

    public function finalize() {
        $toJson = json_encode($this->result);
        echo $toJson;
    }

}