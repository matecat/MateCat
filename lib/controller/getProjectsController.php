<?php
include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$UTILS_ROOT . "/cat.class.php";
include INIT::$UTILS_ROOT . "/langs/languages.class.php";

/**
 * Description of manageController
 *
 * @author andrea
 */

class getProjectsController extends ajaxcontroller {

	private $jid = "";
	private $pid = "";
	public $notAllCancelled = 0;

	public function __construct() {
		parent::__construct();

		$this->lang_handler=Languages::getInstance();
        if (isset($_POST['page'])) {

            $this->page = ($_POST['page'] == '')? 1 : $_POST['page'];
        } else {
            $this->page = 1;
        };
		if(isset($_POST['pn'])) log::doLog('PN: ',$_POST['pn']);		

        if (isset($_POST['filter'])) {
            $this->filter_enabled = true;
        } else {
            $this->filter_enabled = false;
        };
		
        if (isset($_POST['pn'])) {
            $this->search_in_pname = $_POST['pn'];
        } else {
            $this->search_in_pname = false;
        };

        if (isset($_POST['source'])) {
            $this->search_source = $_POST['source'];
        } else {
            $this->search_source = false;
        };

        if (isset($_POST['target'])) {
            $this->search_target = $_POST['target'];
        } else {
            $this->search_target = false;
        };

        if (isset($_POST['status'])) {
            $this->search_status = $_POST['status'];
        } else {
            $this->search_status = 'ongoing';
        };
		
        if (isset($_POST['onlycompleted'])) {
            $this->search_onlycompleted = $_POST['onlycompleted'];
        } else {
            $this->search_onlycompleted = false;
        };
/*				
        if (isset($_POST['showarchived'])) {
            $this->search_showarchived = $_POST['showarchived'];
        } else {
            $this->search_showarchived = 0;
        };
		
        if (isset($_POST['showcancelled'])) {
            $this->search_showcancelled = $_POST['showcancelled'];
        } else {
            $this->search_showcancelled = 0;
        };
*/		
	}

	public function doAction() {

		$time_loop_tot=0;
		$time_loop=0;
		$step = 100;
		$start = (($this->page - 1) * $step);
log::doLog("PAGE: ",$this->page);

		$data = getProjects($start,$step,$this->search_in_pname,$this->search_source,$this->search_target,$this->search_status,$this->search_onlycompleted,$this->filter_enabled);
//		$data = getProjects($start,$step,$this->search_in_pname,$this->search_source,$this->search_target,$this->search_onlycompleted,$this->search_showarchived,$this->search_showcancelled,$this->filter_enabled);
//echo "<pre>";print_r($data);exit;
		
		
		$projects = array();

		//fetching stats
		//harvest IDs
log::doLog("NUM ITEM: ",count($data));
		foreach ($data as $item) {
			$tmp_jobs_strings = explode(',',$item['job']);
			foreach ($tmp_jobs_strings as $job_string) {

				$tmp_job_array = explode('##',$job_string);
				$jobById[]= $tmp_job_array[0];
//				log::doLog("JOB BY ID: ",$jobById);
			}
		}
		unset($tmp_job_array,$tmp_jobs_strings,$job_string);

		//fetch id_job -> stats map

		log::doLog("start fetch"); 
		$start_time_fetch=microtime(true);
		$statsByJobId = (count($data))? CatUtils::getStatsForMultipleJobs($jobById,0) : '';
//				log::doLog("STATS BY JOB ID (NEW): ",$statsByJobId);

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
			$project['password'] = $item['password'];
			$project['tm_analysis'] = $item['tm_analysis_wc'];
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
//log::doLog("JOB STATS (NEW): ",$job['stats']);

				$project['id_engine_mt']= $job_array[5];
				$project['private_tm_key']= $job_array[6];
				$job['disabled']= ($job_array[7]=='cancelled')?"disabled":"";
				$job['status']= $job_array[7];
				if($job_array[7]!='cancelled') $project['no_active_jobs'] = false;
				$project['has_cancelled']=($job['status'] == 'cancelled')? 1 : $project['has_cancelled'];
				$project['has_archived']=($job['status'] == 'archived')? 1 : $project['has_archived'];

				$project['jobs'][]=$job;
			}
//			usort($project['jobs'], "cmp");
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

		$projnum = getProjectsNumber($start,$step,$this->search_in_pname,$this->search_source,$this->search_target,$this->search_status,$this->search_onlycompleted,$this->filter_enabled);

		$this->result['data'] = json_encode($projects);
		$this->result['page'] = $this->page;
		$this->result['pnumber'] = $projnum[0]['c'];


	}


	public function cmp($a, $b) {
    	return strcmp($a["id"], $b["id"]);
	}



}

?>