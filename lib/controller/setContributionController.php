<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
define('DEFAULT_NUM_RESULTS', 2);

class setContributionController extends ajaxcontroller {

    private $id_customer;
    private $id_translator;
    private $private_customer;
    private $private_translator;
    private $source;
    private $source_lang;
    private $target;
    private $target_lang;

    public function __construct() {
        parent::__construct();

        $this->id_customer = $this->get_from_get_post('id_customer');
        $this->id_translator = $this->get_from_get_post('id_translator');
        $this->private_customer = $this->get_from_get_post('private_customer');
        $this->private_translator = $this->get_from_get_post('private_translator');
        $this->source = $this->get_from_get_post('source');
        $this->source_lang = $this->get_from_get_post('source_lang');
        $this->target = $this->get_from_get_post('target');
        $this->target_lang = $this->get_from_get_post('target_lang');
    }

    public function doAction() {
//        if (empty($this->id_segment)) {
//            $this->result['error'][] = array("code" => -1, "message" => "missing id_segment");
//        }
//        if (empty($this->id_segment)) {
//            $this->result['error'][] = array("code" => -2, "message" => "missing text");
//        }
//
//        if (empty($this->num_results)) {
//            $this->num_results = DEFAULT_NUM_RESULTS;
//        }
//
//        if (!empty($this->result['error'])) {
//            return -1;
//        }

        $this->result['code'] = 1;
        $this->result['data'] = "OK";
    }

}

?>
