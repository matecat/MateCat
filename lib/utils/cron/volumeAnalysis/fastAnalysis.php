<?php
ini_set("memory_limit","2048M");
set_time_limit(0);
include_once 'main.php';

/* Write my pid to a file */
$my_pid = getmypid();

if ( !file_exists( Constants_Daemons::PID_FOLDER ) ) {
    mkdir( Constants_Daemons::PID_FOLDER );
}

/**
 * WARNING on 2 frontend web server or in an architecture where the daemons runs in a place different from the web server
 * this should be put in a shared location ( memcache/redis/ntfs/mysql ) and a service should be
 * queried for know that number
 */
file_put_contents( Constants_Daemons::PID_FOLDER . "/" . Constants_Daemons::FAST_PID_FILE, $my_pid );
/**/

$ws = new MyMemoryAnalyzer( 1 /* MyMemory */ );

Log::$fileName = "fastAnalysis.log";

while (1) {

	$pid_list = getProjectForVolumeAnalysis('fast', 5);
	if (empty($pid_list)) {
		echo __FILE__ . ":" . __FUNCTION__ . " no projects ready for fast volume analisys: wait 3 seconds\n";
		Log::doLog( __FILE__ . ":" . __FUNCTION__ . " no projects ready for fast volume analisys: wait 3 seconds" );
		sleep(3);
		continue;
	}

	echo __FILE__ . ":" . __FUNCTION__ . "  projects found\n";
	print_r($pid_list);

	foreach ($pid_list as $pid_res) {
		$pid = $pid_res['id'];
		echo "analyzing $pid, querying data...";
		Log::doLog( "analyzing $pid, querying data..." );

		$segments=getSegmentsForFastVolumeAnalysys($pid);

        //compose a lookup array
        $segment_hashes = array();

		foreach( $segments as $pos => $segment ){
			$segments[$pos]['segment'] = CatUtils::clean_raw_string4fast_word_count( $segment['segment'], $segments[0]['source'] );
            $segment_hashes[ $segment['id'] ] = $segment['segment_hash'];
            unset( $segments[$pos]['id'] );
            unset( $segments[$pos]['segment_hash'] );
		}


		echo "done\n";
		Log::doLog( "done" );

		$num=count($segments);

		echo "pid $pid: $num segments\n";
		Log::doLog( "pid $pid: $num segments" );
		echo "sending query to MyMemory analysis...";
		Log::doLog( "sending query to MyMemory analysis..." );

		$fastReport = $ws->fastAnalysis($segments);

		echo "done\n";
		echo "collecting stats...";
		Log::doLog( "done" );
		Log::doLog( "collecting stats..." );
        echo "fast $pid result: " . count($fastReport['data'])  . " segments\n";
        Log::doLog( "fast $pid result: " . count($fastReport['data'])  . " segments" );

        $data = $fastReport[ 'data' ];
        foreach ( $data as $k => $v ) {

            if ( in_array( $v[ 'type' ], array( "75%-84%", "85%-94%", "95%-99%" ) ) ) {
                $data[ $k ][ 'type' ] = "INTERNAL";
            }

            if ( in_array( $v[ 'type' ], array( "50%-74%" ) ) ) {
                $data[ $k ][ 'type' ] = "NO_MATCH";
            }

            list( $sid, $not_needed )     = explode( "-", $k );
            $data[ $k ][ 'sid' ]          = $sid;
            $data[ $k ][ 'segment_hash' ] = $segment_hashes[ $sid ];

        }

		echo "done\n";
		Log::doLog( "done" );

		$perform_Tms_Analysis = true;
		$status = Constants_ProjectStatus::STATUS_FAST_OK;
		if( $pid_res['id_tms'] == 0 && $pid_res['id_mt_engine'] == 0 ){

			/**
			 * MyMemory disabled and MT Disabled Too
			 * So don't perform TMS Analysis
			 */

			$perform_Tms_Analysis = false;
			$status = Constants_ProjectStatus::STATUS_DONE;
			Log::doLog( 'Perform Analysis ' . var_export( $perform_Tms_Analysis, true ) );
		} elseif( count( $segments ) == 0 ){
            //there is no analysis on that file, it is ALL Pre-Translated
            Log::doLog('There is no analysis on that file, it is ALL Pre-Translated');
            $status = Constants_ProjectStatus::STATUS_DONE;
        }

		echo "inserting segments...\n";
		Log::doLog( "inserting segments..." );

		$insertReportRes = insertFastAnalysis($pid,$data, $equivalentWordMapping, $perform_Tms_Analysis);
		if ($insertReportRes < 0) {
			Log::doLog( "insertFastAnalysis failed...." );
			echo( "insertFastAnalysis failed...." );
			continue;
		}

		echo "done\n";
		Log::doLog( "done" );
		echo "changing project status...";
		Log::doLog( "changing project status..." );

		$change_res = changeProjectStatus($pid, $status);
		if ($change_res < 0) {
		}

		echo "done\n";
		Log::doLog( "done" );
	}
}
