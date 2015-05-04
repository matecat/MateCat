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
 * this should be put in a shared< location ( memcache/redis/ntfs/mysql ) and a service should be
 * queried for know that number
 */
file_put_contents( Constants_Daemons::PID_FOLDER . "/" . Constants_Daemons::FAST_PID_FILE, $my_pid );


Log::$fileName = "fastAnalysis.log";

while (1) {

    /**
     * @var $ws Engines_MyMemory
     */
    $ws = Engine::getInstance( 1 /* MyMemory */ );

    Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

	$pid_list = getProjectForVolumeAnalysis('fast', 5);
	if (empty($pid_list)) {
		echo "no projects ready for fast volume analisys: wait 3 seconds\n";
		Log::doLog( "no projects: wait 3 seconds" );
        sleep(3);
		continue;
	}

	echo __FILE__ . ":" . __FUNCTION__ . "  projects found\n";
	print_r($pid_list);

	foreach ($pid_list as $pid_res) {
		$pid = $pid_res['id'];
		echo "analyzing $pid, querying data...";
		Log::doLog( "analyzing $pid, querying data..." );

		$segments = getSegmentsForFastVolumeAnalysys($pid);

        Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

        $num_segments = count($segments);
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
        } elseif( $num_segments == 0 ){
            //there is no analysis on that file, it is ALL Pre-Translated
            Log::doLog('There is no analysis on that file, it is ALL Pre-Translated');
            $status = Constants_ProjectStatus::STATUS_DONE;
        }

        //compose a lookup array
        $segment_hashes = array();

		foreach( $segments as $pos => $segment ){

            $segment_hashes[ $segment['id'] ] = array(
                    $segment['segment_hash'],
                    $segment['segment'],
                    $segment['raw_word_count'],
                    $segment['source'],
                    $segment['target'],
                    $segment['payable_rates']
            );

            $segments[$pos]['segment'] = CatUtils::clean_raw_string4fast_word_count( $segment['segment'], $segments[0]['source'] );

            //unset because we don't want to pass these keys to Fast Analysis
            unset( $segments[$pos]['id'] );
            unset( $segments[$pos]['segment_hash'] );
            unset( $segment['segment'] );
            unset( $segment['raw_word_count'] );
            unset( $segment['target'] );
            unset( $segment['payable_rates'] );

		}

		echo "done\n";
		Log::doLog( "done" );

		echo "pid $pid: $num_segments segments\n";
		Log::doLog( "pid $pid: $num_segments segments" );
		echo "sending query to MyMemory analysis...";
		Log::doLog( "sending query to MyMemory analysis..." );

        $ws->doLog = false; //tell to the engine to not log the output
		$fastReport = $ws->fastAnalysis($segments);

        Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );
        unset( $segments );
        Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

		echo "done\n";
		echo "collecting stats...";
		Log::doLog( "done" );
		Log::doLog( "collecting stats..." );
        echo "fast $pid result: " . count($fastReport->responseData)  . " segments\n";
        Log::doLog( "fast $pid result: " . count($fastReport->responseData)  . " segments" );

        if($fastReport->responseStatus == 200){
            $data = $fastReport->responseData;
        } else {
            Log::doLog( "pid $pid failed fastanalysis\n");
            $data = array();
        }

        unset( $fastReport );
        Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

        foreach ( $data as $k => $v ) {

//            if ( in_array( $v[ 'type' ], array( "75%-84%", "85%-94%", "95%-99%" ) ) ) {
//                $data[ $k ][ 'type' ] = "INTERNAL";
//            }

            if ( in_array( $v[ 'type' ], array( "50%-74%" ) ) ) {
                $data[ $k ][ 'type' ] = "NO_MATCH";
            }

            list( $sid, $not_needed ) = explode( "-", $k );
            $data[ $k ][ 'id_segment' ]       = $sid;
            $data[ $k ][ 'segment_hash' ]     = $segment_hashes[ $sid ][ 0 ];
            $data[ $k ][ 'segment' ]          = $segment_hashes[ $sid ][ 1 ];
            $data[ $k ][ 'raw_word_count' ]   = $segment_hashes[ $sid ][ 2 ];
            $data[ $k ][ 'source' ]           = $segment_hashes[ $sid ][ 3 ];
            $data[ $k ][ 'target' ]           = $segment_hashes[ $sid ][ 4 ];
            $data[ $k ][ 'payable_rates' ]    = $segment_hashes[ $sid ][ 5 ];
            $data[ $k ][ 'pretranslate_100' ] = $pid_res[ 'pretranslate_100' ];
            $data[ $k ][ 'tm_keys' ]          = $pid_res[ 'tm_keys' ];
            $data[ $k ][ 'id_tms' ]           = $pid_res[ 'id_tms' ];
            $data[ $k ][ 'id_mt_engine' ]     = $pid_res[ 'id_mt_engine' ];
            $data[ $k ][ 'match_type' ]        = mb_strtoupper( $data[ $k ][ 'type' ] );
            unset( $data[ $k ][ 'type' ] );

        }

        unset( $segment_hashes );
        Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

        insertData( $pid, $data, INIT::$DEFAULT_PAYABLE_RATES, $perform_Tms_Analysis );

        unset( $data );
        Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

        $amqHandler = new FastAnalysisQueueHandler();
        $amqHandler->setTotal( array( 'qid' => $pid, 'queueName' => INIT::$QUEUE_NAME ) );

        updateProject( $pid, $status );

	}

}


function insertData( $pid, &$data, $equivalentWordMapping, $perform_Tms_Analysis ){

    echo "inserting segments...\n";
    Log::doLog( "inserting segments..." );

    $insertReportRes = insertFastAnalysis($pid,$data, $equivalentWordMapping, $perform_Tms_Analysis);
    if ($insertReportRes < 0) {
        Log::doLog( "insertFastAnalysis failed...." );
        echo( "insertFastAnalysis failed...." );
    }

    echo "done\n";
    Log::doLog( "done" );

}

function updateProject( $pid, $status ){

    echo "changing project status...";
    Log::doLog( "changing project status..." );

    $change_res = changeProjectStatus($pid, $status);
    if ($change_res < 0) {
    }

    echo "done\n";
    Log::doLog( "done" );

}

function insertFastAnalysis( $pid, &$fastReport, $equivalentWordMapping, $perform_Tms_Analysis = true ) {

    $db   = Database::obtain();
    $data = array();

    $amqHandler = new FastAnalysisQueueHandler();

    $total_eq_wc       = 0;
    $total_standard_wc = 0;

    $data[ 'id_segment' ]          = null;
    $data[ 'id_job' ]              = null;
    $data[ 'segment_hash' ]        = null;
    $data[ 'match_type' ]          = null;
    $data[ 'eq_word_count' ]       = null;
    $data[ 'standard_word_count' ] = null;

    $segment_translations = "INSERT INTO `segment_translations` ( " . implode( ", ", array_keys( $data ) ) . " ) VALUES ";
    $st_values            = array();

    foreach ( $fastReport as $k => $v ) {

        $jid_fid    = explode( "-", $k );

        $id_segment = $jid_fid[ 0 ];
        $id_jobs    = $jid_fid[ 1 ];

        if ( array_key_exists( $v[ 'match_type' ], $equivalentWordMapping ) ) {
            $eq_word = ( $v[ 'wc' ] * $equivalentWordMapping[ $v[ 'match_type' ] ] / 100 );
            if ( $v[ 'match_type' ] == "INTERNAL" ) {
            }
        } else {
            $eq_word = $v[ 'wc' ];
        }

        $total_eq_wc += $eq_word;
        $standard_words = $eq_word;

        if ( $v[ 'match_type' ] == "INTERNAL" or $v[ 'match_type' ] == "MT" ) {
            $standard_words = $v[ 'wc' ] * $equivalentWordMapping[ "NO_MATCH" ] / 100;
        }

        $total_standard_wc += $standard_words;
        unset( $fastReport[ $k ]['wc'] );

        $id_jobs = explode( ',', $id_jobs );
        foreach ( $id_jobs as $id_job ) {

            list( $id_job, $job_pass ) = explode( ":", $id_job );

            $data[ 'id_job' ]              = (int)$id_job;
            $data[ 'id_segment' ]          = (int)$fastReport[ $k ]['id_segment'];
            $data[ 'segment_hash' ]        = $db->escape( $v[ 'segment_hash' ] );
            $data[ 'match_type' ]          = $db->escape( $v[ 'match_type' ] );
            $data[ 'eq_word_count' ]       = (float)$eq_word;
            $data[ 'standard_word_count' ] = (float)$standard_words;

            $st_values[ ] = " ( '" . implode( "', '", array_values( $data ) ) . "' )";

            if ( $data[ 'eq_word_count' ] > 0 && $perform_Tms_Analysis ) {

                $fastReport[ $k ][ 'id_job' ]              = (int)$id_job;
                $fastReport[ $k ][ 'pid' ]                 = (int)$pid;
                $fastReport[ $k ][ 'date_insert' ]         = date_create()->format( 'Y-m-d H:i:s' );
                $fastReport[ $k ][ 'eq_word_count' ]       = (float)$eq_word;
                $fastReport[ $k ][ 'standard_word_count' ] = (float)$standard_words;

            } else {
//                Log::doLog( 'Skipped Fast Segment: ' . var_export( $fastReport[ $k ], true ) );
                unset( $fastReport[ $k ] );
            }
        }
    }

    $chunks_st = array_chunk( $st_values, 200 );

//	echo 'Insert Segment Translations: ' . count($st_values) . "\n";
    Log::doLog( 'Insert Segment Translations: ' . count( $st_values ) );

//	echo 'Queries: ' . count($chunks_st) . "\n";
    Log::doLog( 'Queries: ' . count( $chunks_st ) );

    //USE the MySQL InnoDB isolation Level to protect from thread high concurrency access
    $db->query( 'SET autocommit=0' );
    $db->query( 'START TRANSACTION' );

    foreach ( $chunks_st as $k => $chunk ) {

        $query_st = $segment_translations . implode( ", ", $chunk ) .
                " ON DUPLICATE KEY UPDATE
            match_type = VALUES( match_type ),
                       eq_word_count = VALUES( eq_word_count ),
                       standard_word_count = VALUES( standard_word_count )
                           ";

        $db->query( $query_st );

        //echo "Executed " . ( $k + 1 ) ."\n";
        //Log::doLog(  "Executed " . ( $k + 1 ) );

        $err = $db->get_error();
        if ( $err[ 'error_code' ] != 0 ) {
            Log::doLog( $err );

            return $err[ 'error_code' ] * -1;
        }
    }

    Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

    unset( $st_values );
    unset( $chunks_st );

    Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

    /*
     * IF NO TM ANALYSIS, upload the jobs global word count
     */
    if ( !$perform_Tms_Analysis ) {
        $_details = getProjectSegmentsTranslationSummary( $pid );

        echo "--- trying to initialize job total word count.\n";
        Log::doLog( "--- trying to initialize job total word count." );

        $project_details = array_pop( $_details ); //remove rollup

        foreach ( $_details as $job_info ) {
            $counter = new WordCount_Counter();
            $counter->initializeJobWordCount( $job_info[ 'id_job' ], $job_info[ 'password' ] );
        }
    }
    /* IF NO TM ANALYSIS, upload the jobs global word count */

    //echo "Done.\n";
    //Log::doLog( 'Done.' );

    $data2     = array( 'fast_analysis_wc' => $total_eq_wc );

    $where = " id = $pid";
    $db->update( 'projects', $data2, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {

        $db->query( 'ROLLBACK' );
        $db->query( 'SET autocommit=1' );
        Log::doLog( $err );

        return $errno * -1;
    }

    $db->query( 'COMMIT' );
    $db->query( 'SET autocommit=1' );


    if ( count( $fastReport ) ) {

//        $chunks_st_queue = array_chunk( $fastReport, 10 );
        $chunks_st_queue = &$fastReport;

//		echo 'Insert Segment Translations Queue: ' . count($st_queue_values) . "\n";
        Log::doLog( 'Insert Segment Translations Queue: ' . count( $fastReport ) );

//		echo 'Queries: ' . count($chunks_st_queue) . "\n";
        Log::doLog( 'Queries: ' . count( $chunks_st_queue ) );

        $time_start = microtime(true);
        foreach ( $chunks_st_queue as $k => $queue_chunk ) {

            try {

                $jsonObj = json_encode( $queue_chunk );
                Utils::raiseJsonExceptionError();
                $amqHandler->send( INIT::$QUEUE_NAME, $jsonObj, array( 'persistent' => 'true' ) );
//                Log::doLog( "Executed " . ( $k +1 ) );

            } catch ( Exception $e ){
                Utils::sendErrMailReport( $e->getMessage() . "\n" . $e->getTraceAsString() , "Fast Analysis set queue failed." );
                Log::doLog(  $e->getMessage() . "\n" . $e->getTraceAsString() );
            }

        }

        //echo "Done.\n";
        Log::doLog( 'Done in ' . ( microtime(true) - $time_start ) . " seconds." );
        Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

        unset( $chunks_st_queue );
        unset( $fastReport );

        Log::doLog( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

    }


    return $db->affected_rows;
}