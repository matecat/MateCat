<?php
ini_set("memory_limit","2048M");
set_time_limit(0);
include_once 'main.php';

/* Write my pid to a file */
$my_pid = getmypid();

try {
    $redisHandler = new Predis\Client( INIT::$REDIS_SERVERS );
    $redisHandler->rpush( Constants_AnalysisRedisKeys::FAST_PID_LIST, $my_pid );
} catch ( Exception $ex ){
    $msg = "****** No REDIS instances found. Exiting. ******";
    _TimeStampMsg( $msg );
    die();
}

Log::$fileName = "fastAnalysis.log";
$RUNNING = true;

function sigSwitch( $signo ) {

    global $RUNNING;

    switch ($signo) {
        case SIGTERM :
        case SIGINT :
            $RUNNING = false;
            break;
        case SIGHUP :
            $RUNNING = false;
            cleanShutDown();
            break;
        default :
            break;
    }

    $msg = str_pad( " CHILD " . getmypid() . " Caught Signal $signo ", 50, "-", STR_PAD_BOTH );
    _TimeStampMsg( $msg );

}

function cleanShutDown(  ){

    global $redisHandler, $db;

    //SHUTDOWN
    $redisHandler->lrem( Constants_AnalysisRedisKeys::FAST_PID_LIST, 0, getmypid() );
    $redisHandler->disconnect();
    $db->close();

    $msg = str_pad( " FAST ANALYSIS " . getmypid() . " HALTED GRACEFULLY ", 50, "-", STR_PAD_BOTH );
    _TimeStampMsg( $msg );

}

//START EVENTS

do {

    /**
     * @var $ws Engines_MyMemory
     */
    $ws = Engine::getInstance( 1 /* MyMemory */ );

    _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

    $pid_list = getProjectForVolumeAnalysis('fast', 5);
    if (empty($pid_list)) {
        _TimeStampMsg( "No projects: wait 3 seconds", false );
        sleep(3);
        continue;
    }

    _TimeStampMsg( "Projects found: " . var_export( $pid_list, true ) );

    foreach ($pid_list as $pid_res) {
        $pid = $pid_res['id'];
        _TimeStampMsg( "analyzing $pid, querying data..." );

        $segments = getSegmentsForFastVolumeAnalysys($pid);

        _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

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
            _TimeStampMsg( 'Perform Analysis ' . var_export( $perform_Tms_Analysis, true ) );
        } elseif( $num_segments == 0 ){
            //there is no analysis on that file, it is ALL Pre-Translated
            _TimeStampMsg('There is no analysis on that file, it is ALL Pre-Translated');
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
                    $segment['target'],  //now target holds more than one language ex: ( 80415:fr-FR,80416:it-IT )
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

        _TimeStampMsg( "done" );

        _TimeStampMsg( "pid $pid: $num_segments segments" );
        _TimeStampMsg( "sending query to MyMemory analysis..." );

        $ws->doLog = false; //tell to the engine to not log the output
        $fastReport = $ws->fastAnalysis($segments);

        _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );
        unset( $segments );
        _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

        _TimeStampMsg( "done" );
        _TimeStampMsg( "collecting stats..." );
        _TimeStampMsg( "fast $pid result: " . count( $fastReport->responseData )  . " segments" );

        if($fastReport->responseStatus == 200){
            $data = $fastReport->responseData;
        } else {
            _TimeStampMsg( "pid $pid failed fastanalysis");
            $data = array();
        }

        unset( $fastReport );
        _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

        foreach ( $data as $k => $v ) {

            if ( in_array( $v[ 'type' ], array( "50%-74%" ) ) ) {
                $data[ $k ][ 'type' ] = "NO_MATCH";
            }

            list( $sid, $not_needed ) = explode( "-", $k );
            $data[ $k ][ 'id_segment' ]       = $sid;
            $data[ $k ][ 'segment_hash' ]     = $segment_hashes[ $sid ][ 0 ];
            $data[ $k ][ 'segment' ]          = $segment_hashes[ $sid ][ 1 ];
            $data[ $k ][ 'raw_word_count' ]   = $segment_hashes[ $sid ][ 2 ];
            $data[ $k ][ 'source' ]           = $segment_hashes[ $sid ][ 3 ];
            $data[ $k ][ 'target' ]           = $segment_hashes[ $sid ][ 4 ];  //now target holds more than one language ex: ( 80415:fr-FR,80416:it-IT )
            $data[ $k ][ 'payable_rates' ]    = $segment_hashes[ $sid ][ 5 ];
            $data[ $k ][ 'pretranslate_100' ] = $pid_res[ 'pretranslate_100' ];
            $data[ $k ][ 'tm_keys' ]          = $pid_res[ 'tm_keys' ];
            $data[ $k ][ 'id_tms' ]           = $pid_res[ 'id_tms' ];
            $data[ $k ][ 'id_mt_engine' ]     = $pid_res[ 'id_mt_engine' ];
            $data[ $k ][ 'match_type' ]       = mb_strtoupper( $data[ $k ][ 'type' ] );
            unset( $data[ $k ][ 'type' ] );

        }

        unset( $segment_hashes );
        _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

        // INSERT DATA
        _TimeStampMsg( "inserting segments..." );
        $insertReportRes = insertFastAnalysis( $pid, $data, Analysis_PayableRates::$DEFAULT_PAYABLE_RATES, $perform_Tms_Analysis );
        if ( $insertReportRes < 0 ) {
            _TimeStampMsg( "insertFastAnalysis failed...." );
        }
        _TimeStampMsg( "done" );
        // INSERT DATA

        unset( $data );
        _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

        updateProject( $pid, $status );

    }

} while( $RUNNING );

cleanShutDown();



//FAST FUNCTIONS

function updateProject( $pid, $status ){

    _TimeStampMsg( "changing project status..." );

    $change_res = changeProjectStatus($pid, $status);
    if ($change_res < 0) {
    }

    _TimeStampMsg( "done" );

}

function insertFastAnalysis( $pid, &$fastReport, $equivalentWordMapping, $perform_Tms_Analysis = true ) {

    $db   = Database::obtain();
    $data = array();

    $amqHandler = new Analysis_QueueHandler();

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
        $list_id_jobs_password    = $jid_fid[ 1 ];

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

        $list_id_jobs_password = explode( ',', $list_id_jobs_password );
        foreach ( $list_id_jobs_password as $id_job ) {

            list( $id_job, $job_pass ) = explode( ":", $id_job );

            $data[ 'id_job' ]              = (int)$id_job;
            $data[ 'id_segment' ]          = (int)$fastReport[ $k ]['id_segment'];
            $data[ 'segment_hash' ]        = $db->escape( $v[ 'segment_hash' ] );
            $data[ 'match_type' ]          = $db->escape( $v[ 'match_type' ] );
            $data[ 'eq_word_count' ]       = (float)$eq_word;
            $data[ 'standard_word_count' ] = (float)$standard_words;

            $st_values[ ] = " ( '" . implode( "', '", array_values( $data ) ) . "' )";

            if ( $data[ 'eq_word_count' ] > 0 && $perform_Tms_Analysis ) {

                /**
                 *
                 * IMPORTANT
                 * id_job will be taken from languages ( 80415:fr-FR,80416:it-IT )
                 */
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

    unset( $data );

    $chunks_st = array_chunk( $st_values, 200 );

    _TimeStampMsg( 'Insert Segment Translations: ' . count( $st_values ) );

    _TimeStampMsg( 'Queries: ' . count( $chunks_st ) );

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

        _TimeStampMsg( "Executed " . ( $k + 1 )  );

        $err = $db->get_error();
        if ( $err[ 'error_code' ] != 0 ) {
            _TimeStampMsg( $err );

            return $err[ 'error_code' ] * -1;
        }
    }

    _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

    unset( $st_values );
    unset( $chunks_st );

    _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

    /*
     * IF NO TM ANALYSIS, upload the jobs global word count
     */
    if ( !$perform_Tms_Analysis ) {
        $_details = getProjectSegmentsTranslationSummary( $pid );

        _TimeStampMsg( "--- trying to initialize job total word count." );

        $project_details = array_pop( $_details ); //remove rollup

        foreach ( $_details as $job_info ) {
            $counter = new WordCount_Counter();
            $counter->initializeJobWordCount( $job_info[ 'id_job' ], $job_info[ 'password' ] );
        }
    }
    /* IF NO TM ANALYSIS, upload the jobs global word count */

    //_TimeStampMsg( "Done." );

    $data2     = array( 'fast_analysis_wc' => $total_eq_wc );

    $where = " id = $pid";
    $db->update( 'projects', $data2, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {

        $db->query( 'ROLLBACK' );
        $db->query( 'SET autocommit=1' );
        _TimeStampMsg( $err );

        return $errno * -1;
    }

    $db->query( 'COMMIT' );
    $db->query( 'SET autocommit=1' );


    if ( count( $fastReport ) ) {

//        $chunks_st_queue = array_chunk( $fastReport, 10 );

        _TimeStampMsg( 'Insert Segment Translations Queue: ' . count( $fastReport ) );
        _TimeStampMsg( 'Queries: ' . count( $fastReport ) );

        $amqHandler->setTotal( array( 'qid' => $pid, 'queueName' => INIT::$QUEUE_NAME ) );

        $time_start = microtime(true);
        foreach ( $fastReport as $k => $queue_element ) {

            try {

                $languages_job = explode( ",", $queue_element[ 'target' ] );  //now target holds more than one language ex: ( 80415:fr-FR,80416:it-IT )
                //in memory replacement avoid duplication of the segment list
                //send in queue every element * number of languages
                foreach( $languages_job as $_language){

                    list( $id_job, $language ) = explode( ":", $_language );

                    $queue_element[ 'target' ] = $language;
                    $queue_element[ 'id_job' ] = $id_job;

                    $jsonObj = json_encode( $queue_element );
                    Utils::raiseJsonExceptionError();
                    $amqHandler->send( INIT::$QUEUE_NAME, $jsonObj, array( 'persistent' => $amqHandler->persistent ) );
//                _TimeStampMsg( "Executed " . ( $k +1 ) );

                }

            } catch ( Exception $e ){
                Utils::sendErrMailReport( $e->getMessage() . "" . $e->getTraceAsString() , "Fast Analysis set queue failed." );
                _TimeStampMsg(  $e->getMessage() . "" . $e->getTraceAsString() );
            }

        }

        _TimeStampMsg( 'Done in ' . ( microtime(true) - $time_start ) . " seconds." );
        _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

        unset( $fastReport );

        _TimeStampMsg( "Memory: " . ( memory_get_usage( true ) / ( 1024 * 1024 ) ) . "MB" );

    }

    $amqHandler->disconnect();

    return $db->affected_rows;
}