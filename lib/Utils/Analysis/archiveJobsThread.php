<?php
ini_set("memory_limit","2048M");
set_time_limit(0);
include_once 'main.php';

Log::$fileName = "archive_jobs.log";
Log::doJsonLog("[ARCHIVEJOBS] started");
Log::doJsonLog("[ARCHIVEJOBS] inactivity days threshold: ". INIT::JOB_ARCHIVABILITY_THRESHOLD );

$lastArchivedIdFileName = ".last_archived_id";

if(!is_file($lastArchivedIdFileName))
    touch($lastArchivedIdFileName);

//select last max before INIT::JOB_ARCHIVABILITY_THRESHOLD days ago
//with status not archived so we skip them in the next cycle
$first_id = file_get_contents($lastArchivedIdFileName);
$first_id = $first_id * 1;

$last_id = getMaxJobUntilDaysAgo( INIT::JOB_ARCHIVABILITY_THRESHOLD );

Log::doJsonLog("[ARCHIVEJOBS] last job id is: " . $last_id );

//number of rows to be selected for each query,
//after some tests,decide to set the upper bound to 100
$row_interval = 50;

for($i = $first_id; $i <= $last_id; $i += $row_interval){
    echo "[ARCHIVEJOBS] searching jobs between $i and ".($i + $row_interval)."\n";
	Log::doJsonLog("[ARCHIVEJOBS] searching jobs between $i and ".($i + $row_interval));

    //check for jobs with no new segment translations and take them
    $jobs = getArchivableJobs( range( $i, $i + $row_interval ) );

	if ( $jobs < 0 ) {
		Log::doJsonLog( "[ARCHIVEJOBS] skipping batch.." );
		continue;
	}

    $jobsToBeArchived = count($jobs);

    if( $jobsToBeArchived == 0){
        echo "[ARCHIVEJOBS] " . $jobsToBeArchived . " found. Skipping batch.\n";
		Log::doJsonLog("[ARCHIVEJOBS] " . $jobsToBeArchived . " found. Skipping batch.");
	} else {
        echo "[ARCHIVEJOBS] " . $jobsToBeArchived . " found.\n";
        Log::doJsonLog( "[ARCHIVEJOBS] " . $jobsToBeArchived . " found." );

        $ret = batchArchiveJobs( $jobs, INIT::JOB_ARCHIVABILITY_THRESHOLD );

        if( $ret >= 0 ){
            echo "[ARCHIVEJOBS] " . $ret . " jobs successfully archived\n";
            Log::doJsonLog("[ARCHIVEJOBS] " . $ret . " jobs successfully archived");
        } else {
            echo "[ARCHIVEJOBS] FAILED !!!\n";
            Log::doJsonLog("[ARCHIVEJOBS] FAILED !!!");
        }

    }

    sleep(1);

}
file_put_contents($lastArchivedIdFileName, $last_id);

Log::doJsonLog("[ARCHIVEJOBS] Goodbye");