<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class getSearch extends ajaxcontroller {

    private $job;
    private $token;
    private $password;
    private $source;
    private $target;
    private $status;
    private $replace;
    private $function; //can be search, replace
    private $matchCase;

    public function __construct() {
        parent::__construct();


        $this->function = $this->get_from_get_post('function');

        $this->job = $this->get_from_get_post('job');
        

        $this->token = $this->get_from_get_post('token');

        $this->source = $this->get_from_get_post('source');

        $this->target = $this->get_from_get_post('target');

        $this->status = $this->get_from_get_post('status');
        if (empty($this->status)) {
            $this->status = "all";
        }

        $this->replace = $this->get_from_get_post('replace');

        $this->password = $this->get_from_get_post("password");

        $this->matchCase = $this->get_from_get_post('matchCase');
        if (empty($this->matchCase)) {
            $this->matchCase = false;
        }
    }

    public function doAction() {
        $this->result['token'] = $this->token;
        if (empty($this->job)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing id job");
            return;
        }
        //get Job Infos
        $job_data = getJobData((int) $this->job);

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if (!$pCheck->grantJobAccessByJobData($job_data, $this->password)) {
            $this->result['error'][] = array("code" => -10, "message" => "wrong password");
            return;
        }

        switch ($this->function) {
            case 'find':
                $this->doSearch();
                break;
            case 'replace':
                break;
            default :
                $this->result['error'][] = array("code" => -11, "message" => "unknown  function. Use find or replace");
                return;
        }
    }

    private function doSearch() {
        if (empty($this->source) and empty($this->target)) {
            $this->result['error'][] = array("code" => -11, "message" => "missing search string");
            return;
        }

        if (!empty($this->source) and !empty($this->target)) {
            $this->result['error'][] = array("code" => -3, "message" => "specify only one between source and target");
            return;
        }

        $key = "";
        if (!empty($this->source)) {
            $key = "source";
            $val = $this->source;
        }

        if (!empty($this->target)) {
            $key = "target";
            $val = $this->target;
        }


        $res = doSearchQuery($this->job, $key, $val, $this->status);
        if (is_numeric($res) and $res < 0) {
            $this->result['error'][] = array("code" => -1000, "message" => "internal error: see the log");
            return;
        }
        $this->result['total'] = $res['num_res'];
        if ($res['num_res'] == 0) {
            $res['sidlist'] = '';
        }
        $this->result['segments'] = $res['sidlist'];
    }

}