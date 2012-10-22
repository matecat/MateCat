<?php
include INIT::$ROOT."/lib/utils/mymemory_queries_temp.php";
include_once INIT::$UTILS_ROOT . "/engines/mt.class.php";
include_once INIT::$UTILS_ROOT . "/engines/tms.class.php";
include_once INIT::$MODEL_ROOT . "/queries.php";

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class deleteContributionController extends ajaxcontroller {

//    private $id_contribution;
    private $seg;
    private $tra;
    private $source_lang;
    private $target_lang;
//    private $source_hash;
    private $id_translator;

    public function __construct() {
        parent::__construct();
        // log::doLog('AAA');

//        $this->id_contribution = $this->get_from_get_post('id');
        $this->source_lang = $this->get_from_get_post('source_lang');
        $this->target_lang = $this->get_from_get_post('target_lang');
        $this->source = trim($this->get_from_get_post('seg'));
        $this->target = trim($this->get_from_get_post('tra'));
        $this->id_translator=trim($this->get_from_get_post('id_translator'));
        //        $this->source_hash = $this->get_from_get_post('fp');
        
    }

    public function doAction() {
        // log::doLog('deleteContribution');


        if (empty($this->source_lang)) {
            $this->result['error'][] = array("code" => -1, "message" => "missing source_lang");
        }    	

        if (empty($this->target_lang)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing target_lang");
        }    

        if (empty($this->source)) {
            $this->result['error'][] = array("code" => -3, "message" => "missing source");
        }

        if (empty($this->target)) {
            $this->result['error'][] = array("code" => -4, "message" => "missing target");
        }

        //$result = deleteToMM($this->source_lang, $this->target_lang, $this->source, $this->target);
	$tms=new TMS(1);

	$result = $tms->delete($this->source, $this->target,$this->source_lang, $this->target_lang,$this->id_translator);
	
        $this->result['code'] = $result;
        $this->result['data'] = "OK";
        //        $this->result['data']['matches'] = $matches;

/*
        if (empty($this->id_segment)) {
            $this->result['error'][] = array("code" => -1, "message" => "missing id_segment");
        }
        if (empty($this->text)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing text");
        }

        if (empty($this->num_results)) {
            $this->num_results = INIT::$DEFAULT_NUM_RESULTS_FROM_TM;
        }

        if (!empty($this->result['error'])) {
            return -1;
        }
        
        $st=getSourceTargetFromJob($this->id_job);
        
        $this->source=$st['source'];
        $this->target=$st['target'];


        // UNUSED
        $fake_matches = array();
        $fake_matches[] = array("segment" => $this->text, "translation" => "$this->text fake translation", "quality" => 74, "created_by" => "Vicky", "last_update_date" => "2011-08-21 14:30", "match" => 1);
        $fake_matches[] = array("segment" => $this->text, "translation" => "$this->text fake translation second result", "quality" => 84, "created_by" => "Don", "last_update_date" => "2010-06-21 14:30", "match" => 0.84);
        //$matches = $fake_matches;

        $matches=array();
        $matches = getFromMM($this->text, $this->source,$this->target);
        
        $matches=array_slice ($matches,0,INIT::$DEFAULT_NUM_RESULTS_FROM_TM);

        
        $this->result['data']['matches'] = $matches;
*/
    }


}

?>
