<?

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
//include_once INIT::$UTILS_ROOT . "/langs/languages.class.php";

class ManageUtils {

	public static function queryProjects($start,$step,$search_in_pname,$search_source,$search_target,$search_status,$search_onlycompleted,$filter_enabled,$lang_handler,$project_id) {

// TEMPORARY HACK TO ACCESS ALL CURRENT PROJECTS Andrea 26/6/2013
		$pattern = '/^(127.0.0.1|10.30.1|10.3.14|10.3.15|192.168.94.100)/';
		$getAll = preg_match($pattern, $_SERVER['REMOTE_ADDR']);
// END HACK	

		$data = getProjects($start,$step,$search_in_pname,$search_source,$search_target,$search_status,$search_onlycompleted,$filter_enabled,$project_id,$getAll);

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

		$start_time_fetch=microtime(true);
		$statsByJobId = (count($data))? CatUtils::getStatsForMultipleJobs($jobById,0) : '';

		$end_time_fetch=microtime(true);
		$time_fetch=round(1000*($end_time_fetch-$start_time_fetch),0);

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
			$project['tm_analysis'] = number_format($item['tm_analysis_wc'], 0, ".", ",");;
			$jobs_strings = explode(',',$item['job']);
			foreach ($jobs_strings as $job_string) {
				$job = array();
				$job_array = explode('##',$job_string);
				$job['id']= $job_array[0];
				$job['source']= $job_array[1];
				$job['target']= $job_array[2];
				$job['sourceTxt'] = $lang_handler->getLocalizedName($job['source'],'en');
				$job['targetTxt'] = $lang_handler->getLocalizedName($job['target'],'en');
				//raw
				$job['create_date']= $job_array[3];
				//standard
				$job['formatted_create_date']= date('Y D d, H:i',strtotime($job_array[3]));
				//quest'anno
				if(date('Y')==date('Y',strtotime($job_array[3]))){
					$job['formatted_create_date']=date('F d, H:i',strtotime($job_array[3]));
				}
				//questo mese
				if(date('Y-m')==date('Y-m',strtotime($job_array[3]))){
					$job['formatted_create_date']=date('F d I, H:i',strtotime($job_array[3]));
				}
				//ieri
				if(date('Y-m-d',strtotime('yesterday'))==date('Y-m-d',strtotime($job_array[3]))){
					$job['formatted_create_date']='Yesterday, '.date('H:i',strtotime($job_array[3]));
				}
				//oggi
				if(date('Y-m-d')==date('Y-m-d',strtotime($job_array[3]))){
					$job['formatted_create_date']='Today, '.date('H:i',strtotime($job_array[3]));
				}

				$job['password']= $job_array[4];
			
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
//			usort($project['jobs'], "cmp");
			$project['no_active_jobs'] = ($project['no_active_jobs'])? ' allCancelled' : '';
			$projects[]=$project;
			
		}

		return $projects;
	}
}


?>
