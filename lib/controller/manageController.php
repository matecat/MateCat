<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include INIT::$UTILS_ROOT . "/langs/languages.class.php";

/**
 * Description of manageController
 *
 * @author andrea
 */
class manageController extends viewcontroller {

	private $jid = "";
	private $pid = "";
	public $notAllCancelled = 0;

	public function __construct() {
		parent::__construct();
		parent::makeTemplate("manage.html");
		$this->jid = $this->get_from_get_post("jid");
		$this->password = $this->get_from_get_post("password");
		$this->lang_handler=Languages::getInstance();
        if (isset($_GET['page'])) {
            $this->page = ($_GET['page'] == '')? 1 : $_GET['page'];
        } else {
            $this->page = 1;
        };
		if(isset($_GET['pn'])) log::doLog('PN: ',$_GET['pn']);		

        if (isset($_GET['filter'])) {
            $this->filter_enabled = true;
        } else {
            $this->filter_enabled = false;
        };
		
        if (isset($_GET['pn'])) {
            $this->search_in_pname = $_GET['pn'];
        } else {
            $this->search_in_pname = false;
        };

        if (isset($_GET['source'])) {
            $this->search_source = $_GET['source'];
        } else {
            $this->search_source = false;
        };

        if (isset($_GET['target'])) {
            $this->search_target = $_GET['target'];
        } else {
            $this->search_target = false;
        };
		
        if (isset($_GET['onlycompleted'])) {
            $this->search_onlycompleted = $_GET['onlycompleted'];
        } else {
            $this->search_onlycompleted = false;
        };
				
        if (isset($_GET['showarchived'])) {
            $this->search_showarchived = $_GET['showarchived'];
        } else {
            $this->search_showarchived = 0;
        };
		
        if (isset($_GET['showcancelled'])) {
            $this->search_showcancelled = $_GET['showcancelled'];
        } else {
            $this->search_showcancelled = 0;
        };
		
		
		log::doLog('PAGE:' . $this->page);

	}

	public function doAction() {

$time_loop_tot=0;
$time_loop=0;
		$step = 100;
		$start = (($this->page - 1) * $step);
		$data = getProjects($start,$step,$this->search_in_pname,$this->search_source,$this->search_target,$this->search_onlycompleted,$this->search_showarchived,$this->search_showcancelled);
//echo "<pre>";print_r($data);exit;
		$projects = array();

		//fetching stats
		//harvest IDs
		foreach ($data as $item) {
			$tmp_jobs_strings = explode(',',$item['job']);
			foreach ($tmp_jobs_strings as $job_string) {
				$tmp_job_array = explode('##',$job_string);
				$jobById[]= $tmp_job_array[0];
			}
		}
		unset($tmp_job_array,$tmp_jobs_strings,$job_string);

		//fetch id_job -> stats map

log::doLog("start fetch"); 
$start_time_fetch=microtime(true);
		$statsByJobId=CatUtils::getStatsForMultipleJobs($jobById,0);
$end_time_fetch=microtime(true);
$time_fetch=round(1000*($end_time_fetch-$start_time_fetch),0);
log::doLog("fetch took ".$time_fetch." msecs"); 

log::doLog("start manage"); 
		foreach ($data as $item) {
$start_time_loop=microtime(true);
			$project = array();
			$project['id'] = $item['pid'];
			$project['name'] = $item['name'];

			$project['jobs'] = array();
			$project['no_active_jobs'] = true;
			$project['has_cancelled'] = 0;
			$project['has_archived'] = 0;
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

			//	$job['stats']=CatUtils::getStatsForJob($job['id']);
				$job['stats']=$statsByJobId[$job['id']];

				$project['id_engine_mt']= $job_array[5];
				$project['private_tm_key']= $job_array[6];
				$job['disabled']= ($job_array[7]=='cancelled')?"disabled":"";
				$job['status']= $job_array[7];
				if($job_array[7]!='cancelled') $project['no_active_jobs'] = false;
				$project['has_cancelled']=($job['status'] == 'cancelled')? 1 : $project['has_cancelled'];
				$project['has_archived']=($job['status'] == 'archived')? 1 : $project['has_archived'];

				$project['jobs'][]=$job;
			}
			$project['no_active_jobs'] = ($project['no_active_jobs'])? ' allCancelled' : '';
			$projects[]=$project;

$end_time_loop=microtime(true);
$time_loop=round(1000*($end_time_loop-$start_time_loop),0);
$time_loop_tot+=$time_loop;
//log::doLog("item took ".$time_loop." msecs"); 
		}
		//    	log::doLog('PROJECTS:' , $projects);
		$this->projects = $projects;
//echo "<pre>";print_r($projects);exit;
log::doLog("manage took $time_loop_tot msec"); 

	}

	public function setTemplateVars() {

		$this->template->projects = $this->projects;
		$this->template->prev_page = ($this->page - 1);
		$this->template->next_page = ($this->page + 1);
		$this->template->languages=$this->lang_handler->getEnabledLanguages('en');
		$this->template->filtered = $this->filter_enabled;
		$this->template->filtered_class = ($this->filter_enabled)? ' open': '';
		$this->template->search_pname = $this->search_in_pname;
		$this->template->search_source = $this->search_source;
		$this->template->search_target = $this->search_target;
		$this->template->search_onlycompleted = $this->search_onlycompleted;
		$this->template->search_showarchived = $this->search_showarchived;
		$this->template->search_showcancelled = $this->search_showcancelled;
//		$this->template->querystring = $_SERVER['QUERY_STRING'];

	}

}

?>
