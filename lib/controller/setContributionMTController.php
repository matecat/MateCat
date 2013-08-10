<?php

include INIT::$ROOT . "/lib/utils/mymemory_queries_temp.php";
include_once INIT::$UTILS_ROOT . "/engines/mt.class.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class setContributionMTController extends ajaxcontroller {

	private $segment;
    PRIVATE $password;
	private $translation;
	private $source_lang;
	private $target_lang;
	private $id_segment;
	private $id_job;
	private $suggestion_json_array;
	private $chosen_suggestion_index;
	private $time_to_edit;
	private $mt;

	public function __construct() {
		parent::__construct();
        //segment
        $this->segment = $this->get_from_get_post( 'source' );
        //translation
        $this->translation = $this->get_from_get_post( 'target' );
        //source
        $this->source_lang = $this->get_from_get_post( 'source_lang' );
        //target
        $this->target_lang = $this->get_from_get_post( 'target_lang' );
        //id of translation unit in workbench
        $this->id_segment = $this->get_from_get_post( 'id_segment' );
        //id job
        $this->id_job = $this->get_from_get_post( 'id_job' );
        //index of suggestions from which the translator drafted the contribution
        $this->chosen_suggestion_index = $this->get_from_get_post( 'chosen_suggestion_index' );
        //how much time it needed to translate this segment
        $this->time_to_edit = $this->get_from_get_post( 'time_to_edit' );

        $this->password = $this->get_from_get_post( 'password' );

    }

	public function doAction() {
		if (empty($this->segment)) {
			$this->result['error'][] = array("code" => -1, "message" => "missing source segment");
		}

		if (empty($this->translation)) {
			$this->result['error'][] = array("code" => -2, "message" => "missing target translation");
		}


		if (empty($this->source_lang)) {
			$this->result['error'][] = array("code" => -3, "message" => "missing source lang");
		}

		if (empty($this->target_lang)) {
			$this->result['error'][] = array("code" => -4, "message" => "missing target lang");
		}

		if (empty($this->time_to_edit)) {
			$this->result['error'][] = array("code" => -5, "message" => "missing time to edit");
		}

		if (empty($this->id_segment)) {
			$this->result['error'][] = array("code" => -6, "message" => "missing segment id");
		}

        //get Job Infos
        $job_data = getJobData( (int) $this->id_job );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if( !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ){
            $this->result['error'][] = array( "code" => -10, "message" => "wrong password" );
            return;
        }

		//mt engine to contribute to
		if (empty($job_data['id_mt_engine'])){
			return false;
		}

		$this->mt = new MT($job_data['id_mt_engine']);

		//array of storicised suggestions for current segment
		$this->suggestion_json_array=json_decode(getArrayOfSuggestionsJSON($this->id_segment),true);

		//extra parameters
		$extra=json_encode(
				array(
					'id_segment'=>$this->id_segment,
					'suggestion_json_array'=>$this->suggestion_json_array,
					'chosen_suggestion_index'=>$this->chosen_suggestion_index,
					'time_to_edit'=>$this->time_to_edit
				     )
				);
		//send stuff
		$outcome=$this->mt->set($this->segment, $this->translation, $this->source_lang, $this->target_lang, 'demo@matecat.com', $extra);
		if (is_array($outcome)){
			$this->result['errors']=$outcome;
		}

	}

}
?>
