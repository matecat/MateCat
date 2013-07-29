<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . '/QA.php';

define('DEFAULT_NUM_RESULTS', 2);

class setTranslationController extends ajaxcontroller {

	private $id_job;
	private $id_segment;
	private $id_translator;
	private $status;
	private $time_to_edit;
	private $translation;
	private $id_first_file;
	private $err;
	private $chosen_suggestion_index;

	public function __construct() {
		parent::__construct();
		$this->id_job = $this->get_from_get_post('id_job');
		$this->id_segment = $this->get_from_get_post('id_segment');
		$this->id_translator = $this->get_from_get_post('id_translator');
		$this->status = strtoupper($this->get_from_get_post('status'));
		$this->time_to_edit = $this->get_from_get_post('time_to_edit');
		$this->translation = $this->get_from_get_post('translation');
		$this->id_first_file = $this->get_from_get_post('id_first_file');
		$this->err = $this->get_from_get_post('errors');
		//index of suggestions from which the translator drafted the contribution
		$this->chosen_suggestion_index=$this->get_from_get_post('chosen_suggestion_index');

	}

	public function doAction() {

		if (empty($this->id_segment)) {
			$this->result['error'][] = array("code" => -1, "message" => "missing id_segment");
		}

		if (empty($this->id_job)) {
			$this->result['error'][] = array("code" => -2, "message" => "missing id_job");
		}

		if (empty($this->id_first_file)) {
			$this->result['error'][] = array("code" => -2, "message" => "missing id_job");
		}

		if (empty($this->time_to_edit)) {
			$this->time_to_edit = 0;
		}

		if (empty($this->status)) {
			$this->status = 'DRAFT';
		}

		if (is_null($this->translation) || $this->translation === '') {
			//log::doLog("empty translation");
			return 0; // won's save empty translation but there is no need to return an error 
		}

		//ONE OR MORE ERRORS OCCURRED : EXITING
		if (!empty($this->result['error'])) {
			log::doLog(__CLASS__ .":".__FUNCTION__." error - ".$this->result['error'] );
			return -1;
		}
		$this->translation = CatUtils::view2rawxliff($this->translation);

		//check tag mismatch
		//get original source segment, first
		$segment=getSegment($this->id_segment);            
                
		//compare segment-translation and get results
        $check = new QA($segment['segment'], $this->translation);
        $check->performConsistencyCheck();
        
        if( $check->thereAreErrors() ){
              $err_json = $check->getErrorsJSON();
              $translation = $this->translation;
        } else {
              $err_json = '';
              $translation = $check->getTrgNormalized();
        }
        
		$res = CatUtils::addSegmentTranslation($this->id_segment, $this->id_job, $this->status, $this->time_to_edit, $translation, $err_json,$this->chosen_suggestion_index, $check->thereAreErrors() );

		if (!empty($res['error'])) {
			$this->result['error'] = $res['error'];
			return -1;
		}

		$job_stats = CatUtils::getStatsForJob($this->id_job);
		$file_stats = CatUtils::getStatsForFile($this->id_first_file);

		$is_completed = ($job_stats['TRANSLATED_PERC'] == '100')? 1 : 0;

		$update_completed = setJobCompleteness($this->id_job, $is_completed);

		$this->result['stats'] = $job_stats;
		$this->result['file_stats'] = $file_stats;
		$this->result['code'] = 1;
		$this->result['data'] = "OK";
                
                /* FIXME: added for code compatibility with front-end. Remove. */
                $_warn = $check->getErrors();
                $warning = $_warn[0];
                /* */
                
		$this->result['warning']['cod']=$warning->outcome;
		if($warning->outcome>0){
			$this->result['warning']['id']=$this->id_segment;
		} else {
			$this->result['warning']['id']=0;
		}
	}

}

?>
