<?php
set_time_limit( 0 );
include "main.php";

define( "PID_FOLDER", ".pidlist" );
define( "NUM_PROCESSES", 1 );
define( "NUM_PROCESSES_FILE", ".num_processes" );

$amqHandlerSubscriber = new FastAnalysisQueueHandler();
$amqHandlerSubscriber->subscribe();

$amqHandlerPublisher = new FastAnalysisQueueHandler();

Log::$fileName = "tm_analysis.log";

// PROCESS CONTROL FUNCTIONS
function isRunningProcess( $pid ) {
    if ( file_exists( "/proc/$pid" ) ) {
        return true;
    }

    return false;
}

function processFileExists( $pid ) {
    $folder = PID_FOLDER;
//    Log::doLog( __FUNCTION__ . " : $folder/$pid ...." );
    if ( file_exists( "$folder/$pid" ) ) {
//        Log::doLog ( "true" );

        return true;
    }
//    Log::doLog ( "false" );

    return false;
}

$UNIQUID = uniqid( '', true );

$my_pid     = getmypid();
$parent_pid = posix_getppid();
//Log::doLog ( "--- (child $my_pid) : parent pid is $parent_pid" );

$memcacheHandler = MemcacheHandler::getInstance();

$i = 1;

while ( 1 ) {
    if ( !processFileExists($my_pid)) {
        die( "(child $my_pid) :  EXITING!  my file does not exists anymore\n" );
    }

    // control if parent is still running
    if ( !isRunningProcess($parent_pid)) {
        Log::doLog ( "--- (child $my_pid) : EXITING : parent seems to be died." );
        exit ( -1 );
    }

    $msg      = null;
    $objQueue = array();
    try {

        if ( $amqHandlerSubscriber->hasFrameToRead() ) {
            $msg = $amqHandlerSubscriber->readFrame();
        }

        if ( $msg instanceof StompFrame && ( $msg->command == "MESSAGE" || array_key_exists( 'MESSAGE', $msg->headers /* Stomp Client bug... hack */ ) ) ) {

            $i++;
            Log::doLog( "--- (child $my_pid) : processing frame $i" );

            $objQueue = json_decode( $msg->body, true );
            //empty message what to do?? it should not be there, acknowledge and process the next one
            if ( empty( $objQueue ) ) {

                Utils::raiseJsonExceptionError();

//                Log::doLog( $msg );
                $amqHandlerSubscriber->ack( $msg );
                continue;

            }
        }

    } catch ( Exception $e ) {
        Log::doLog( "*** \$this->amqHandler->readFrame() Failed. Continue Execution. ***" );
        Log::doLog( $e->getMessage() );
        Log::doLog( $e->getTraceAsString() );
        continue; /* jump the ack */
    }

    if ( !empty( $objQueue ) ) {

        $failed_segment = null;

        $pid = $objQueue[ 'pid' ];

        if ( empty( $objQueue ) ) {
            Log::doLog ( "--- (child $my_pid) : _-_getNextSegmentAndLock_-_ no segment ready for tm volume analisys: wait 5 seconds" );
            sleep( 5 );
            continue;
        }
        $sid = $objQueue[ 'id_segment' ];
        $jid = $objQueue[ 'id_job' ];
        Log::doLog ( "--- (child $my_pid) : segment $sid-$jid found " );
//        $objQueue = getSegmentForTMVolumeAnalysys( $sid, $jid );

        Log::doLog( "segment found is: " );
//        Log::doLog( $objQueue );

        if ( empty( $objQueue ) ) {
            Log::doLog ( "--- (child $my_pid) : empty segment: no segment ready for tm volume analisys: wait 5 seconds" );
            setSegmentTranslationError( $sid, $jid ); // devo settarli come done e lasciare il vecchio livello di match
            incrementCount( $objQueue[ 'pid' ], 0, 0 );
            $amqHandlerSubscriber->decrementTotalForQID( $pid );
            sleep( 5 );
            continue;
        }

        //get the number of segments in job
        $_existingLock = $memcacheHandler->add( 'project_lock:' . $pid, true ); // lock for 1 month
        if ( $_existingLock !== false ) {

            $total_segs = getProjectSegmentsTranslationSummary( $pid );

            $total_segs = array_pop( $total_segs ); // get the Rollup Value
            Log::doLog( $total_segs );

            $memcacheHandler->add( 'project:' . $pid, $total_segs[ 'project_segments' ] );
            $memcacheHandler->increment( 'num_analyzed:' . $pid, $total_segs[ 'num_analyzed' ] );
            Log::doLog ( "--- (child $my_pid) : found " . $total_segs[ 'project_segments' ] . " segments for PID $pid" );
        } else {
            $_existingPid = $memcacheHandler->get( 'project:' . $pid );
            $_analyzed    = $memcacheHandler->get( 'num_analyzed:' . $pid );
            Log::doLog ( "--- (child $my_pid) : found $_existingPid segments for PID $pid in Memcache" );
            Log::doLog ( "--- (child $my_pid) : analyzed $_analyzed segments for PID $pid in Memcache" );
        }


        Log::doLog ( "--- (child $my_pid) : fetched data for segment $sid-$jid. PID is $pid" );

        //lock segment
        Log::doLog ( "--- (child $my_pid) :  segment $sid-$jid locked" );

        $source           = $objQueue[ 'source' ];
        $target           = $objQueue[ 'target' ];
        $raw_wc           = $objQueue[ 'raw_word_count' ];
        $fast_match_type  = $objQueue[ 'match_type' ];
        $payable_rates    = $objQueue[ 'payable_rates' ];
        $pretranslate_100 = $objQueue[ 'pretranslate_100' ];

        $text = $objQueue[ 'segment' ];

        if ( $raw_wc == 0 ) {
            Log::doLog ( "--- (child $my_pid) : empty segment. deleting lock and continue" );
            setSegmentTranslationError( $sid, $jid ); // SET as DONE
            incrementCount( $pid, 0, 0 );
            $amqHandlerSubscriber->decrementTotalForQID( $pid );
            tryToCloseProject( $pid, $my_pid );
            continue;
        }

        //reset vectors
        $matches   = array();
        $tms_match = array();
        $mt_result = array();

        $_config              = array();
        $_config[ 'segment' ] = $text;
        $_config[ 'source' ]  = $source;
        $_config[ 'target' ]  = $target;
        $_config[ 'email' ]   = "tmanalysis@matecat.com";

        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $objQueue[ 'tm_keys' ], 'r', 'tm' );
        if ( is_array( $tm_keys ) && !empty( $tm_keys ) ) {
            foreach ( $tm_keys as $tm_key ) {
                $_config[ 'id_user' ][ ] = $tm_key->key;
            }
        }

        $_config[ 'num_result' ] = 3;

        $id_mt_engine = $objQueue[ 'id_mt_engine' ];
        $id_tms       = $objQueue[ 'id_tms' ];

        $_TMS = $id_tms; //request

        /**
         * Call Memory Server for matches if it's enabled
         */
        $tms_enabled = false;
        if ( $_TMS == 1 ) {
            /**
             * MyMemory Enabled
             */
            $_config[ 'get_mt' ]  = true;
            $_config[ 'mt_only' ] = false;
            if ( $id_mt_engine != 1 ) {
                /**
                 * Don't get MT contribution from MyMemory ( Custom MT )
                 */
                $_config[ 'get_mt' ] = false;
            }

            $tms_enabled = true;

        } elseif ( $_TMS == 0 && $id_mt_engine == 1 ) {
            /**
             * MyMemory disabled but MT Enabled and it is NOT a Custom one
             * So tell to MyMemory to get MT only
             */
            $_config[ 'get_mt' ]  = true;
            $_config[ 'mt_only' ] = true;

            $_TMS = 1; /* MyMemory */

            $tms_enabled = true;

        }

        /*
         * This will be ever executed without damages because
         * fastAnalysis set Project as DONE when
         * MyMemory is disabled and MT is Disabled Too
         *
         * So don't worry, perform TMS Analysis
         *
         */
        if ( $tms_enabled ) {
            /**
             * @var $tms Engines_MyMemory
             */
            $tms = Engine::getInstance( $_TMS );
            $tms->doLog = false;

            $config = $tms->getConfigStruct();
            $config = array_merge( $config, $_config );

            $tms_match = $tms->get( $config );

            //MyMemory can return null if an error occurs (e.g http response code is 404, 410, 500, 503, etc.. )
            if ( $tms_match !== null ) {
                $tms_match = $tms_match->get_matches_as_array();

            } else {

                Log::doLog ( "--- (child $my_pid) : error from mymemory : set error and continue" ); // ERROR FROM MYMEMORY
                setSegmentTranslationError( $sid, $jid ); // devo settarli come done e lasciare il vecchio livello di match
                incrementCount( $pid, 0, 0 );
                $amqHandlerSubscriber->decrementTotalForQID( $pid );
                tryToCloseProject( $pid, $my_pid );

                $amqHandlerSubscriber->ack( $msg );
                reQueue( $amqHandlerPublisher, $objQueue );

                continue;
            }
        }

        /**
         * Call External MT engine if it is a custom one ( mt not requested from MyMemory )
         */
        if ( $id_mt_engine > 1 /* Request MT Directly */ ) {
            $mt     = Engine::getInstance( $id_mt_engine );
            $config = $mt->getConfigStruct();
            $config = array_merge( $config, $_config );

            $mt_result = $mt->get( $config );

            if ( isset( $mt_result[ 'error' ][ 'code' ] ) ) {
                $mt_result = false;
            }

        }

        if ( !empty( $tms_match ) ) {
            $matches = $tms_match;
        }

        if ( !empty( $mt_result ) ) {
            $matches[ ] = $mt_result;
            usort( $matches, "compareScore" );
        }

        /**
         * Only if No results found
         */
        if ( empty( $matches ) || !is_array( $matches ) ) {
            Log::doLog ( "--- (child $my_pid) : No contribution found : set error and continue" ); // ERROR FROM MYMEMORY
            setSegmentTranslationError( $sid, $jid ); // devo settarli come done e lasciare il vecchio livello di match
            incrementCount( $pid, 0, 0 );
            $amqHandlerSubscriber->decrementTotalForQID( $pid );
            tryToCloseProject( $pid, $my_pid );
            $amqHandlerSubscriber->ack( $msg );
            continue;
        }

        $tm_match_type = $matches[ 0 ][ 'match' ];
        if ( stripos( $matches[ 0 ][ 'created_by' ], "MT" ) !== false ) {
            $tm_match_type = "MT";
        }

        /* New Feature only if this is not a MT and if it is a ( 90 =< MATCH < 100 ) try to realign tag ID*/
        ( isset( $matches[ 0 ][ 'match' ] ) ? $firstMatchVal = floatval( $matches[ 0 ][ 'match' ] ) : null );
        if ( isset( $firstMatchVal ) && $firstMatchVal >= 90 && $firstMatchVal < 100 ) {

            $srcSearch    = strip_tags( $text );
            $segmentFound = strip_tags( $matches[ 0 ][ 'raw_segment' ] );
            $srcSearch    = mb_strtolower( preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $srcSearch ) );
            $segmentFound = mb_strtolower( preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $segmentFound ) );

            $fuzzy = @levenshtein( $srcSearch, $segmentFound ) / log10( mb_strlen( $srcSearch . $segmentFound ) + 1 );

            //levenshtein handle max 255 chars per string and returns -1, so fuzzy var can be less than 0 !!
            if ( $srcSearch == $segmentFound || ( $fuzzy < 2.5 && $fuzzy > 0 ) ) {

                $qaRealign = new QA( $text, html_entity_decode( $matches[ 0 ][ 'raw_translation' ] ) );
                $qaRealign->tryRealignTagID();

                $log_prepend = $UNIQUID . " - SERVER REALIGN IDS PROCEDURE | ";
                if ( !$qaRealign->thereAreErrors() ) {

                    /*
                    Log::doLog( $log_prepend . " - Requested Segment: " . var_export( $objQueue, true ) );
                    Log::doLog( $log_prepend . "Fuzzy: " . $fuzzy . " - Try to Execute Tag ID Realignment." );
                    Log::doLog( $log_prepend . "TMS RAW RESULT:" );
                    Log::doLog( $log_prepend . var_export( $matches[ 0 ], true ) );

                    Log::doLog( $log_prepend . "Realignment Success:" );
                    */
                    $matches[ 0 ][ 'raw_translation' ] = $qaRealign->getTrgNormalized();
                    $matches[ 0 ][ 'match' ]           = ( $fuzzy == 0 ? '100%' : '99%' );
                    //Log::doLog( $log_prepend . "Raw Translation: " . var_export( $matches[ 0 ]['raw_translation'], true ) );

                } else {
                    Log::doLog( $log_prepend . 'Realignment Failed. Skip. Segment: ' . $objQueue[ 'id_segment' ] );
                }

            }

        }
        /* New Feature only if this is not a MT and if it is a ( 90 =< MATCH < 100 ) try to realign tag ID*/

        $suggestion = CatUtils::view2rawxliff( $matches[ 0 ][ 'raw_translation' ] );

        //preg_replace all x tags <x not closed > inside suggestions with correctly closed
        $suggestion = preg_replace( '|<x([^/]*?)>|', '<x\1/>', $suggestion );

        $suggestion_match  = $matches[ 0 ][ 'match' ];
        $suggestion_json   = json_encode( $matches );
        $suggestion_source = $matches[ 0 ][ 'created_by' ];

        $publicTM = ($suggestion_source == "Matecat");

        $equivalentWordMapping = json_decode( $payable_rates, true );

        $new_match_type = getNewMatchType( $tm_match_type, $fast_match_type, $equivalentWordMapping, $publicTM );
        //echo "sid is $sid ";
        $eq_words       = $equivalentWordMapping[ $new_match_type ] * $raw_wc / 100;
        $standard_words = $eq_words;

        //if the first match is MT perform QA realignment
        if ( $new_match_type == 'MT' ) {

            $standard_words = $equivalentWordMapping[ "NO_MATCH" ] * $raw_wc / 100;

            $check = new PostProcess( $matches[ 0 ][ 'raw_segment' ], $suggestion );
            $check->realignMTSpaces();

            //this should every time be ok because MT preserve tags, but we use the check on the errors
            //for logic correctness
            if ( !$check->thereAreErrors() ) {
                $suggestion = CatUtils::view2rawxliff( $check->getTrgNormalized() );
                $err_json   = '';
            } else {
                $err_json = $check->getErrorsJSON();
            }

        } else {

            //try to perform only the tagCheck
            $check = new PostProcess( $text, $suggestion );
            $check->performTagCheckOnly();

            //log::doLog( $check->getErrors() );

            if ( $check->thereAreErrors() ) {
                $err_json = $check->getErrorsJSON();
            } else {
                $err_json = '';
            }

        }

        ( !empty( $matches[ 0 ][ 'sentence_confidence' ] ) ? $mt_qe = floatval( $matches[ 0 ][ 'sentence_confidence' ] ) : $mt_qe = null );

//        Log::doLog ( "--- (child $my_pid) : sid=$sid --- \$tm_match_type=$tm_match_type, \$fast_match_type=$fast_match_type, \$new_match_type=$new_match_type, \$equivalentWordMapping[\$new_match_type]=" . $equivalentWordMapping[ $new_match_type ] . ", \$raw_wc=$raw_wc,\$standard_words=$standard_words,\$eq_words=$eq_words" );

        $tm_data                             = array();
        $tm_data[ 'id_job' ]                 = $jid;
        $tm_data[ 'id_segment' ]             = $sid;
        $tm_data[ 'suggestions_array' ]      = $suggestion_json;
        $tm_data[ 'suggestion' ]             = $suggestion;
        $tm_data[ 'suggestion_match' ]       = $suggestion_match;
        $tm_data[ 'suggestion_source' ]      = $suggestion_source;
        $tm_data[ 'match_type' ]             = $new_match_type;
        $tm_data[ 'eq_word_count' ]          = $eq_words;
        $tm_data[ 'standard_word_count' ]    = $standard_words;
        $tm_data[ 'translation' ]            = $suggestion;
        $tm_data[ 'tm_analysis_status' ]     = "DONE";
        $tm_data[ 'warning' ]                = (int)$check->thereAreErrors();
        $tm_data[ 'serialized_errors_list' ] = $err_json;
        $tm_data[ 'mt_qe' ]                  = $mt_qe;
        $tm_data[ 'pretranslate_100' ]       = $pretranslate_100;

        updateTMValues( $tm_data );

        //unlock segment

        $amqHandlerSubscriber->ack( $msg );

        Log::doLog ( "--- (child $my_pid) : segment $sid-$jid acknowledged" );

        reQueue($amqHandlerPublisher, $failed_segment );

        //set memcache
        incrementCount( $pid, $eq_words, $standard_words );
        $amqHandlerSubscriber->decrementTotalForQID( $pid );
        tryToCloseProject( $pid, $my_pid );

    } else {
//        Log::doLog( 'Empty Frame found. No messages. Skip' );
        sleep( 1 );
    }

}

//}

function reQueue($amqHandlerPublisher, $failed_segment){

    if ( !empty( $failed_segment ) ) {
        Log::doLog( "Failed " . count( $failed_segment ) );
        $amqHandlerPublisher->send( INIT::$QUEUE_NAME, json_encode( $failed_segment ), array( 'persistent' => 'true' ) );
    }

}

function incrementCount( $pid, $eq_words, $standard_words ) {
    $memcacheHandler = MemcacheHandler::getInstance();
    $memcacheHandler->increment( 'eq_wc:' . $pid, $eq_words * 100 );
    $memcacheHandler->increment( 'st_wc:' . $pid, $standard_words * 100 );
    $memcacheHandler->increment( 'num_analyzed:' . $pid, 1 );
}


function tryToCloseProject( $pid, $child_process_id ) {


    $project_totals                       = array();
    $memcacheHandler                      = MemcacheHandler::getInstance();
    $project_totals[ 'project_segments' ] = $memcacheHandler->get( 'project:' . $pid );
    $project_totals[ 'num_analyzed' ]     = $memcacheHandler->get( 'num_analyzed:' . $pid );
    $project_totals[ 'eq_wc' ]            = $memcacheHandler->get( 'eq_wc:' . $pid ) / 100;
    $project_totals[ 'st_wc' ]            = $memcacheHandler->get( 'st_wc:' . $pid ) / 100;

    Log::doLog ( "--- (child $child_process_id) : count segments in project $pid = " . $project_totals[ 'project_segments' ] . "" );
    Log::doLog ( "--- (child $child_process_id) : Analyzed segments in project $pid = " . $project_totals[ 'num_analyzed' ] . "" );

    if ( empty( $project_totals[ 'project_segments' ] ) ) {
        Log::doLog ( "--- (child $child_process_id) : WARNING !!! error while counting segments in projects $pid skipping and continue " );

        return;
    }

    if ( $project_totals[ 'project_segments' ] - $project_totals[ 'num_analyzed' ] == 0 && !$memcacheHandler->get( 'project_done:' . $pid ) ) {

        $_existingLock = $memcacheHandler->add( 'project_done:' . $pid, 1 ); // avoid race conditions, only one instance must pass
        if( $_existingLock === false ){
            return;
        }

        $_analyzed_report = getProjectSegmentsTranslationSummary( $pid );

        $total_segs = array_pop( $_analyzed_report ); //remove Rollup

        Log::doLog ( "--- (child $child_process_id) : analysis project $pid finished : change status to DONE" );

        changeProjectStatus( $pid, Constants_ProjectStatus::STATUS_DONE );
        changeTmWc( $pid, $project_totals[ 'eq_wc' ], $project_totals[ 'st_wc' ] );

        Log::doLog ( "--- (child $child_process_id) : trying to initialize job total word count." );
        foreach ( $_analyzed_report as $job_info ) {
            $counter = new WordCount_Counter();
            $counter->initializeJobWordCount( $job_info[ 'id_job' ], $job_info[ 'password' ] );
        }

    }

}

/**
 * @param string $tm_match_type
 * @param string $fast_match_type
 * @param Array  $equivalentWordMapping
 * @param bool   $publicTM
 *
 * @return string
 */
function getNewMatchType( $tm_match_type, $fast_match_type, $equivalentWordMapping, $publicTM = false ) {

// RATIO : modifico il valore solo se il nuovo match è strettamente migliore (in termini di percentuale pagata per parola) di quello corrente
    $tm_match_cat = "";
    $tm_rate_paid = 0;

    $fast_match_type = strtoupper( $fast_match_type );
    $fast_rate_paid  = $equivalentWordMapping[ $fast_match_type ];


    if ( $tm_match_type == "MT" ) {
        $tm_match_cat = "MT";
        $tm_rate_paid = $equivalentWordMapping[ $tm_match_type ];
    }


    if ( empty( $tm_match_cat ) ) {
        $ind = intval( $tm_match_type );

        if ( $ind == "100" ) {
            $tm_match_cat = ($publicTM) ? "100%_PUBLIC" : "100%";
            $tm_rate_paid = $equivalentWordMapping[ $tm_match_cat ];

        }

        if ( $ind < 50 ) {
            $tm_match_cat = "NO_MATCH";
            $tm_rate_paid = $equivalentWordMapping[ "NO_MATCH" ];
        }

        if ( $ind >= 50 and $ind < 75 ) {
            $tm_match_cat = "50%-74%";
            $tm_rate_paid = $equivalentWordMapping[ "50%-74%" ];
        }

        /*
         * @author Roberto Tucci
         * Jobs before 27th April 2015 had a unique category: 75%-99%
         * From this date the category has been split into 3 categories.
         * this condition grants back-compatibility with old jobs and related analysis
         */
        if( !isset( $equivalentWordMapping[ "75%-99%" ]) ) {
            if( $ind >= 75 && $ind <=84 ){
                $tm_match_cat = "75%-84%";
                $tm_rate_paid = $equivalentWordMapping[ "75%-84%" ];
            }
            elseif( $ind >= 85 && $ind <=94 ){
                $tm_match_cat = "85%-94%";
                $tm_rate_paid = $equivalentWordMapping[ "85%-94%" ];
            }
            elseif( $ind >= 95 && $ind <=99 ){
                $tm_match_cat = "95%-99%";
                $tm_rate_paid = $equivalentWordMapping[ "95%-99%" ];
            }
        }
        elseif ( $ind >= 75 and $ind <= 99 ) {
            $tm_match_cat = "75%-99%";
            $tm_rate_paid = $equivalentWordMapping[ "75%-99%" ];
        }
    }
    //this is because 50%-74% is never returned because it's rate equals NO_MATCH
    if ( $tm_rate_paid < $fast_rate_paid || $fast_match_type == "NO_MATCH" ) {
        return $tm_match_cat;
    }

    return $fast_match_type;
}

function compareScore( $a, $b ) {
    if ( floatval( $a[ 'match' ] ) == floatval( $b[ 'match' ] ) ) {
        return 0;
    }

    return ( floatval( $a[ 'match' ] ) < floatval( $b[ 'match' ] ) ? 1 : -1 ); //SORT DESC !!!!!!! INVERT MINUS SIGN
    //this is necessary since usort sorts is ascending order, thus inverting the ranking
}

function updateTMValues( $tm_data ){

    if ( !empty( $tm_data[ 'suggestion_source' ] ) ) {
        if ( strpos( $tm_data[ 'suggestion_source' ], "MT" ) === false ) {
            $tm_data[ 'suggestion_source' ] = 'TM';
        } else {
            $tm_data[ 'suggestion_source' ] = 'MT';
        }
    }

    //controllare il valore di suggestion_match
    if( $tm_data[ 'suggestion_match' ] == "100%" && $tm_data[ 'pretranslate_100' ]){
        $tm_data[ 'status' ] = Constants_TranslationStatus::STATUS_TRANSLATED;
    }

    //there is not a database filed named pretranslate_100 in segment_translations, this is only a flag
    unset( $tm_data[ 'pretranslate_100' ] );

    $updateRes = setSuggestionUpdate( $tm_data );
    if ( $updateRes < 0 ) {

        $result['errors'][] = array(
                "code" => -5,
                "message" => "error occurred during the storing (UPDATE) of the suggestions for the segment {$tm_data[ 'id_segment' ]}"
        );
        Log::doLog( $result );

    } else {

        //There was not a fast Analysis??? Impossible.
        Log::doLog( "No row found: " . $tm_data[ 'id_segment' ] . "-" . $tm_data[ 'id_job' ] );

    }

}