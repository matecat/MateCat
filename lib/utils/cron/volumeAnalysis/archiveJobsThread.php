<?php
ini_set("memory_limit","2048M");
set_time_limit(0);
include_once 'main.php';

Log::doLog("[ARCHIVEJOBS] started");
Log::doLog("[ARCHIVEJOBS] inactivity days threshold: ". INIT::$JOB_ARCHIVABILITY_THRESHOLD);
$db = Database::obtain();

$last_id_query = "select max(id) as max from jobs";

$last_id = $db->query_first($last_id_query);
$last_id = (int)$last_id['max'];

Log::doLog("[ARCHIVEJOBS] last job id is: ".$last_id);

$query_select_jobs =
	"select
		j.id as jid
	from
		jobs j join
		segment_translations st on j.id = st.id_job
		and j.create_date < (curdate() - interval %d day)
	where
		j.status_owner = 'active'
		and j.id between %d and %d
	group by j.id
	having max(coalesce(translation_date, '1999-01-01 00:00:00' )) < (curdate() - interval %d  day)";

$query_archive_jobs =
	"update jobs
	set status_owner = 'archived'
	where id in (%s)
	and create_date < (curdate() - interval %d day)
	and status_owner = 'active'";

//number of rows to be selected for each query
$row_interval = 5000;

for($i = 1; $i <= $last_id; $i += $row_interval){

	Log::doLog("[ARCHIVEJOBS] searching jobs between $i and ".($i + $row_interval));

	//compose select query
	$q = sprintf(
		$query_select_jobs,
		INIT::$JOB_ARCHIVABILITY_THRESHOLD,
		$i,
		$i + $row_interval,
		INIT::$JOB_ARCHIVABILITY_THRESHOLD
	);
	echo $q."\n";
	//select archivable jobs
	$jobs = $db->fetch_array($q);

	$err     = $db->get_error();
	$errno   = $err[ 'error_code' ];

	if ( $errno != 0 ) {
		Log::doLog( "[ARCHIVEJOBS] $errno: " . var_export( $err, true ) );
		Log::doLog( "[ARCHIVEJOBS] skipping batch.." );
		continue;
	}

	//get job IDs
	if($jobs !== false){
		foreach($jobs as $k=>$job){
			$jobs[$k] = (int)$job['jid'];
		}
	}

	$jobsToBeArchived = count($jobs);


	if( $jobsToBeArchived == 0){
		Log::doLog("[ARCHIVEJOBS] ".count($jobs)." found. Skipping batch.");
	}
	else{
		Log::doLog("[ARCHIVEJOBS] ".count($jobs)." found.");
		$q_archive = sprintf(
			$query_archive_jobs,
			implode(", ", $jobs),
			INIT::$JOB_ARCHIVABILITY_THRESHOLD
		);
		//file_put_contents(INIT::$LOG_REPOSITORY."/archive_queries.txt", $q_archive."\n", FILE_APPEND);
		//file_put_contents(INIT::$LOG_REPOSITORY."/archive_queries.txt", "=============\n", FILE_APPEND);

		$db->query($q_archive);

		Log::doLog("[ARCHIVEJOBS] ".$db->affected_rows." jobs successfully archived");

	}

	sleep(2);
}

Log::doLog("[ARCHIVEJOBS] Goodbye");