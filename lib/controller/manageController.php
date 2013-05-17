<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include INIT::$UTILS_ROOT . "/langs/languages.class.php";

/**
 * Description of catController
 *
 * @author andrea
 */
class manageController extends viewcontroller {

    private $jid = "";
    private $pid = "";

    public function __construct() {
        parent::__construct();
        parent::makeTemplate("manage.html");
        $this->jid = $this->get_from_get_post("jid");
        $this->password = $this->get_from_get_post("password");
		$this->lang_handler=Languages::getInstance();
    }

    public function doAction() {
    	
        $data = getProjects();
/*
		$stringa = array();
    	foreach ($data as $item) {
    		array_push($stringa, $item['id']);
		}
		$prova = implode(",", $stringa);
    	
    	log::doLog('GET PROJECTS:' , $prova);
*/

//    	log::doLog('GET PROJECTS:' , $data[0]['job']);


		$projects = array();
    	foreach ($data as $item) {
    		$project = array();
			$project['id'] = $item['pid'];
			$project['name'] = $item['name'];
			$mt = array(
				"1" => "MyMemory (All Pairs)",
				"2" => "FBK-IT (EN->IT)",
				"3" => "LIUM-IT (EN->DE)",
				"4" => "FBK-LEGAL (EN>IT)",
				"5" => "LIUM-LEGAL (EN->DE)",
				"6" => "TEST PURPOSE FBK (EN->IT)"
			);
			
//			$project['id_engine_mt'] = (is_null($project['id_engine_mt']))? 'null' : $mt[$item['id_engine_mt']];
	
			$jobs = array();
			$jobs_strings = explode(',',$item['job']);
    		foreach ($jobs_strings as $job_string) {
				$job = array();
    			$job_array = explode('##',$job_string);
				$job['id']= $job_array[0];
				$job['source']= $job_array[1];
				$job['target']= $job_array[2];
				$job['sourceTxt'] = $this->lang_handler->getLocalizedName($job['source'],'en');
				$job['targetTxt'] = $this->lang_handler->getLocalizedName($job['target'],'en');

				$job['create_date']= $job_array[3];
				$job['password']= $job_array[4];
				$job['stats'] = CatUtils::getStatsForJob($job['id']);
				$project['id_engine_mt']= $job_array[5];
				$project['private_tm_key']= $job_array[6];
				

				
				array_push($jobs,$job);
			}
			$project['jobs'] = $jobs;
			
			array_push($projects,$project);
			
/*
    		log::doLog('JOB:' , $item['job']);
    		$job = $item['job'];
			$j = explode('##',$job);
    		log::doLog('CREATE DATE:' , $j[3]);
*/
		}
//    	log::doLog('PROJECTS:' , $projects);
		$this->projects = $projects;

/*
    	foreach ($data as $item) {
	    	foreach ($item as $k) {
	    		log::doLog('JOBS:' , $k);
			}
		}
*/		
		
/*
        $tmp = CatUtils::getEditingLogData($this->jid, $this->password);
        $this->data = $tmp[0];
        $this->stats = $tmp[1];

        $this->job_stats = CatUtils::getStatsForJob($this->jid);
*/        


//    	log::doLog('SOURCE_CODE:');
//		$coso = $this->data;
//    	log::doLog($coso[0]['pname']);
//    	log::doLog($this->data[0]['source_code']);
//        log::doLog($this->data);
    }

    public function setTemplateVars() {

//    	log::doLog('PROJECTS:' , $this->projects);

        $this->template->projects = $this->projects;


/*
        $this->template->jid = $this->jid;
        $this->template->password = $this->password;
        $this->template->data = $this->data;
        $this->template->stats = $this->stats;
        $this->template->pname = $this->data[0]['pname'];
        $this->template->source_code = $this->data[0]['source_lang'];
        $this->template->target_code = $this->data[0]['target_lang'];
        $this->template->job_stats = $this->job_stats;
*/
        //echo "<pre>";
        //print_r ($this->data); exit;
    }

}

?>