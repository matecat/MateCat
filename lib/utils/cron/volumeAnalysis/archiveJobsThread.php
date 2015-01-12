<?php
ini_set("memory_limit","2048M");
set_time_limit(0);
include_once 'main.php';

Log::$fileName = "archive_jobs.log";
Log::doLog("[ARCHIVEJOBS] started");
Log::doLog("[ARCHIVEJOBS] inactivity days threshold: ". INIT::JOB_ARCHIVABILITY_THRESHOLD );

//select last max before INIT::JOB_ARCHIVABILITY_THRESHOLD days ago
//with status not archived so we skip them in the next cycle
$last_id = getMaxJobUntilDaysAgo( INIT::JOB_ARCHIVABILITY_THRESHOLD );

Log::doLog("[ARCHIVEJOBS] last job id is: " . $last_id );

//number of rows to be selected for each query,
//after some tests,decide to set the upper bound to 100
$row_interval = 100;

for($i = 1; $i <= $last_id; $i += $row_interval){

	Log::doLog("[ARCHIVEJOBS] searching jobs between $i and ".($i + $row_interval));

    //check for jobs with no new segment translations and take them
    $jobs = getArchivableJobs( range( $i, $i + $row_interval ) );

	if ( $jobs < 0 ) {
		Log::doLog( "[ARCHIVEJOBS] skipping batch.." );
		continue;
	}

    $jobsToBeArchived = count($jobs);

    if( $jobsToBeArchived == 0){
		Log::doLog("[ARCHIVEJOBS] " . $jobsToBeArchived . " found. Skipping batch.");
	} else {

        Log::doLog( "[ARCHIVEJOBS] " . $jobsToBeArchived . " found." );

        $ret = batchArchiveJobs( $jobs, INIT::JOB_ARCHIVABILITY_THRESHOLD );

        if( $ret >= 0 ){
            Log::doLog("[ARCHIVEJOBS] " . $ret . " jobs successfully archived");
        } else {
            Log::doLog("[ARCHIVEJOBS] FAILED !!!");
        }

	}

}

Log::doLog("[ARCHIVEJOBS] Goodbye");