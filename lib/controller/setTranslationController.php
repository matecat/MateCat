<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
define('DEFAULT_NUM_RESULTS', 2);

class setTranslationController extends ajaxcontroller {

    private $id_job;
    private $id_segment;
    private $id_translator;
    private $status;
    private $time_to_edit;
    private $translation;

    public function __construct() {
        parent::__construct();

	//print_r ($_REQUEST);exit;

        $this->id_job = $this->get_from_get_post('id_job');
        $this->id_segment = $this->get_from_get_post('id_segment');
        $this->id_translator = $this->get_from_get_post('id_translator');
        $this->status = strtoupper($this->get_from_get_post('status'));
        $this->time_to_edit = $this->get_from_get_post('time_to_edit');
        $this->translation = $this->get_from_get_post('translation');
    }

    public function doAction() {
        if (empty($this->id_segment)) {
            $this->result['error'][] = array("code" => -1, "message" => "missing id_segment");
        }

        if (empty($this->id_job)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing id_job");
        }

        if (empty($this->id_translator)) {
            $this->result['error'][] = array("code" => -3, "message" => "missing id_translator");
        }

        if (empty($this->time_to_edit)) {
            $this->time_to_edit = 0;
        }

        if (empty($this->status)) {
            $this->status = 'DRAFT';
        }

	if (empty ($this->transation)){
		return 0 ; // won's save empty translation but there is no need to return an error 
	}

        //ONE OR MORE ERRORS OCCURRED : EXITING
        if (!empty($this->result['error'])) {
            return -1;
        }


        $insertRes = setTranslationInsert($this->id_segment, $this->id_job, $this->status, $this->time_to_edit, $this->translation);
        log::doLog($insertRes);
        if ($insertRes < 0 and $insertRes != -1062) {
            $this->result['error'][] = array("code" => -4, "message" => "error occurred during the storing (INSERT) of the translation for the segment $this->id_segment - $insertRes");
            return -1;
        }
        if ($insertRes == -1062) {
            // the translaion for this segment still exists : update it
            $updateRes = setTranslationUpdate($this->id_segment, $this->id_job, $this->status, $this->time_to_edit, $this->translation);            
            if ($updateRes < 0) {
                $this->result['error'][] = array("code" => -5, "message" => "error occurred during the storing (UPDATE) of the translation for the segment $this->id_segment");
                return -1;
            }
        }  
        $this->result['code'] = 1;
        $this->result['data'] = "OK";
    }

}

?>
