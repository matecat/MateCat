<?

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . "/langs/languages.class.php";

class ManageUtils {

	public static function queryProjects($start,$step,$search_in_pname,$search_source,$search_target,$search_status,$search_onlycompleted,$filter_enabled,$project_id) {

		$data = getProjects($start,$step,$search_in_pname,$search_source,$search_target,$search_status,$search_onlycompleted,$filter_enabled,$project_id);

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

		$lang_handler = Languages::getInstance();

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
			$project['tm_analysis'] = number_format($item['tm_analysis_wc'], 0, ".", ",");

            $project[ 'id_mt_engine' ]   = $item[ 'id_mt_engine' ];
            $project[ 'id_tms' ]         = $item[ 'id_tms' ];
            $project[ 'mt_engine_name' ] = $item[ 'mt_engine_name' ];

			$jobs_strings = explode(',',$item['job']);

            $tmp_job_4_ordering = array();

			foreach ($jobs_strings as $job_string) {
				$job = array();
				$job_array = explode('##',$job_string);

				$job['id']= $job_array[0];

                if( !isset( $tmp_job_4_ordering ) ){
                    $tmp_job_4_ordering[ $job['id'] ] = array();
                }

				$job['source']= $job_array[1];
				$job['target']= $job_array[2];
				$job['sourceTxt'] = $lang_handler->getLocalizedName($job['source']);
				$job['targetTxt'] = $lang_handler->getLocalizedName($job['target']);
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

                $job['job_first_segment'] = $job_array[8];
                $job['job_last_segment']  = $job_array[9];

				$job['password'] = $job_array[4];

				$job['stats'] = $statsByJobId[ $job['id'] . "-" . $job['password'] ];

				$project['id_engine_mt']= $job_array[5];
				$project['private_tm_key']= $job_array[6];
				$job['disabled']= ($job_array[7]=='cancelled')?"disabled":"";
				$job['status']= $job_array[7];
				if($job_array[7]!='cancelled') $project['no_active_jobs'] = false;
				$project['has_cancelled']=($job['status'] == 'cancelled')? 1 : $project['has_cancelled'];
				$project['has_archived']=($job['status'] == 'archived')? 1 : $project['has_archived'];

                $tmp_job_4_ordering[ $job['id'] ][ $job['job_first_segment'] ] = $job;

			}

            /**
             * @var $tmp_j &array
             */
            foreach( $tmp_job_4_ordering as &$tmp_j ){
                ksort( $tmp_j );
            }

            $project['jobs'] = $tmp_job_4_ordering;

			$project['no_active_jobs'] = ($project['no_active_jobs'])? ' allCancelled' : '';
			$projects[]=$project;

		}
		return $projects;
	}
}


?>