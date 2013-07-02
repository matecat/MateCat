<?php

include INIT::$ROOT . "/lib/utils/mymemory_queries_temp.php";
include INIT::$MODEL_ROOT . "/queries.php";

class setContributionController extends ajaxcontroller {

    private $id_customer;
    private $id_translator;
    private $key = "";
    private $private_customer;
    private $private_translator;
    private $source;
    private $target;
    private $source_lang;
    private $target_lang;
    private $id_job;

    public function __construct() {
        parent::__construct();
        $this->id_job = $this->get_from_get_post('id_job');

        $this->id_customer = $this->get_from_get_post('id_customer');
        if (empty($this->id_customer)) {
            $this->id_customer = "Anonymous";
        }


        $this->id_translator = $this->get_from_get_post('id_translator');
        if (empty($this->id_translator)) {
            $this->id_translator = "Anonymous";
        }

        log::doLog(__CLASS__ . " - $this->id_translator");

        $this->private_customer = $this->get_from_get_post('private_customer');
        if (empty($this->private_customer)) {
            $this->private_customer = 0;
        }

        $this->private_translator = $this->get_from_get_post('private_translator');
        if (empty($this->private_translator)) {
            $this->private_translator = 0;
        }

        $this->source = $this->get_from_get_post('source');
        $this->target = $this->get_from_get_post('target');
        $this->source_lang = $this->get_from_get_post('source_lang');
        $this->target_lang = $this->get_from_get_post('target_lang');
    }

    public function doAction() {
        if (empty($this->source)) {
            $this->result['error'][] = array("code" => -1, "message" => "missing source segment");
        }

        if (empty($this->target)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing target segment");
        }


        if (empty($this->source_lang)) {
            $this->result['error'][] = array("code" => -3, "message" => "missing source lang");
        }

        if (empty($this->target_lang)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing target lang");
        }

        if (!empty($this->result['error'])) {
            return -1;
        }

        if (!empty($this->id_translator)) {
            $this->key = $this->calculateMyMemoryKey($this->id_translator);
            log::doLog("key is $this->key");
        }

        $id_tms = 1;
        if (!empty($this->id_job)) { //PER COMPATIBILITa: IL BLOCCO IF PUO ESSERE ELIMINATO DOPO
            $st = getJobData($this->id_job);
            $id_tms = $st['id_tms'];
            log::doLog("id tms is $id_tms");
        }


        if ($id_tms != 0) {
            log::doLog(__CLASS__ . ":" . __FUNCTION__ . " -  contribution done");
            $set_results = addToMM($this->source, $this->target, $this->source_lang, $this->target_lang, $this->id_translator, $this->key);
            $this->result['code'] = 1;
            $this->result['data'] = "OK";
        } else {
            log::doLog(__CLASS__ . ":" . __FUNCTION__ . " - no contribution done");
            $this->result['code'] = 1;
            $this->result['data'] = "NOCONTRIB_OK";
        }
    }

    private function calculateMyMemoryKey($id_translator) {
        $key = getTranslatorKey($id_translator);
        return $key;
    }

}
?>

