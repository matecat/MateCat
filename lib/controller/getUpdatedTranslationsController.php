<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
//include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class getUpdatedTranslationsController extends ajaxcontroller {

    private $last_timestamp = "";
    private $first_segment = "";
    private $last_segment = "";

    public function __construct() {

        $this->disableSessions();
        parent::__construct();

        $filterArgs = array(
            'first_segment' => array('filter' => FILTER_SANITIZE_NUMBER_INT),
            'last_segment' => array('filter' => FILTER_SANITIZE_NUMBER_INT),
            'last_timestamp' => array('filter' => FILTER_SANITIZE_NUMBER_INT)
        );

        $__postInput = filter_input_array(INPUT_POST, $filterArgs);

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->last_timestamp = $__postInput['last_timestamp']/1000;
        $this->first_segment = $__postInput['first_segment'];
        $this->last_segment = $__postInput['last_segment'];
    }

    public function doAction() {
//        $pCheck = new AjaxPasswordCheck();
//        //check for Password correctness
//        if (!$pCheck->grantJobAccessByJobData($job_data, $this->password)) {
//            $this->result['error'][] = array("code" => -10, "message" => "wrong password");
//            return;
//        }

        $data = getUpdatedTranslations($this->last_timestamp, $this->first_segment, $this->last_segment);
        $this->result['data'] = $data;
    }

}