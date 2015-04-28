<?php
set_time_limit( 0 );
include "main.php";
include INIT::$UTILS_ROOT . "/QA.php";
include INIT::$UTILS_ROOT . "/PostProcess.php";
include INIT::$UTILS_ROOT . "/MemcacheHandler.php";

define( "PID_FOLDER", ".pidlist" );
define( "NUM_PROCESSES", 1 );
define( "NUM_PROCESSES_FILE", ".num_processes" );

$amqHandlerSubscriber = new Stomp( INIT::$QUEUE_BROKER_ADDRESS );
$amqHandlerSubscriber->connect();

$amqHandlerSubscriber->subscribe( INIT::$QUEUE_NAME );
$amqHandlerSubscriber->setReadTimeout( 0, 500 );

$amqHandlerPublisher = new Stomp( INIT::$QUEUE_BROKER_ADDRESS );
$amqHandlerPublisher->connect();

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
    echo __FUNCTION__ . " : $folder/$pid ....";
    if ( file_exists( "$folder/$pid" ) ) {
        echo "true\n\n";

        return true;
    }
    echo "false\n\n";

    return false;
}

$UNIQUID = uniqid( '', true );

$my_pid     = getmypid();
$parent_pid = posix_getppid();
echo "--- (child $my_pid) : parent pid is $parent_pid\n";

$memcacheHandler = MemcacheHandler::getInstance();

while ( 1 ) {
    if (!processFileExists($my_pid)) {
        die( "(child $my_pid) :  EXITING!  my file does not exists anymore\n" );
    }

    // control if parent is still running
    if (!isRunningProcess($parent_pid)) {
        echo "--- (child $my_pid) : EXITING : parent seems to be died.\n";
        exit ( -1 );
    }


    $msg      = null;
    $objQueue = array();
    try {

        if ( $amqHandlerSubscriber->hasFrameToRead() ) {
            $msg = $amqHandlerSubscriber->readFrame();
        }

        if ( $msg instanceof StompFrame && ( $msg->command == "MESSAGE" || array_key_exists( 'MESSAGE', $msg->headers /* Stomp Client bug... hack */ ) ) ) {

            $objQueue = json_decode( $msg->body, true );
            //empty message what to do?? it should not be there, acknowledge and process the next one
            if ( empty( $objQueue ) ) {

                Utils::raiseJsonExceptionError();

                echo( $msg );
                $amqHandlerSubscriber->ack( $msg );
                continue;

            }
        }

    } catch ( Exception $e ) {
        echo( "*** \$this->amqHandler->readFrame() Failed. Continue Execution. ***" );
        echo( $e->getMessage() );
        var_export( $e->getTraceAsString() );
        continue; /* jump the ack */
    }

    if ( !empty( $objQueue ) ) {

        $failed_segment = null;

        $pid = $objQueue[ 'pid' ];

        if ( empty( $objQueue ) ) {
            echo "--- (child $my_pid) : _-_getNextSegmentAndLock_-_ no segment ready for tm volume analisys: wait 5 seconds\n";
            sleep( 5 );
            continue;
        }
        $sid = $objQueue[ 'id_segment' ];
        $jid = $objQueue[ 'id_job' ];
        echo "--- (child $my_pid) : segment $sid-$jid found \n";
//        $objQueue = getSegmentForTMVolumeAnalysys( $sid, $jid );

        echo "segment found is: ";
        print_r( $objQueue );
        echo "\n";

        if ( empty( $objQueue ) ) {
            echo "--- (child $my_pid) : empty segment: no segment ready for tm volume analisys: wait 5 seconds\n";
            setSegmentTranslationError( $sid, $jid ); // devo settarli come done e lasciare il vecchio livello di match
            incrementCount( $objQueue[ 'pid' ], 0, 0 );
            sleep( 5 );
            continue;
        }

        //get the number of segments in job
        $_existingLock = $memcacheHandler->add( 'project_lock:' . $pid, true ); // lock for 1 month
        if ( $_existingLock !== false ) {

            $total_segs = getProjectSegmentsTranslationSummary( $pid );

            $total_segs = array_pop( $total_segs ); // get the Rollup Value
            var_export( $total_segs );

            $memcacheHandler->add( 'project:' . $pid, $total_segs[ 'project_segments' ] );
            $memcacheHandler->increment( 'num_analyzed:' . $pid, $total_segs[ 'num_analyzed' ] );
            echo "--- (child $my_pid) : found " . $total_segs[ 'project_segments' ] . " segments for PID $pid\n";
        } else {
            $_existingPid = $memcacheHandler->get( 'project:' . $pid );
            $_analyzed    = $memcacheHandler->get( 'num_analyzed:' . $pid );
            echo "--- (child $my_pid) : found $_existingPid segments for PID $pid in Memcache\n";
            echo "--- (child $my_pid) : analyzed $_analyzed segments for PID $pid in Memcache\n";
        }


        echo "--- (child $my_pid) : fetched data for segment $sid-$jid. PID is $pid\n";

        //lock segment
        echo "--- (child $my_pid) :  segment $sid-$jid locked\n";

        $source           = $objQueue[ 'source' ];
        $target           = $objQueue[ 'target' ];
        $raw_wc           = $objQueue[ 'raw_word_count' ];
        $fast_match_type  = $objQueue[ 'match_type' ];
        $payable_rates    = $objQueue[ 'payable_rates' ];
        $pretranslate_100 = $objQueue[ 'pretranslate_100' ];

        $text = $objQueue[ 'segment' ];

        if ( $raw_wc == 0 ) {
            echo "--- (child $my_pid) : empty segment. deleting lock and continue\n";
            incrementCount( $pid, 0, 0 );
            setSegmentTranslationError( $sid, $jid ); // SET as DONE
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

            $config = $tms->getConfigStruct();
            $config = array_merge( $config, $_config );

            $tms_match = $tms->get( $config );

            if ( $tms_match !== null ) {
                $tms_match = $tms_match->get_matches_as_array();

            } else {

                echo "--- (child $my_pid) : error from mymemory : set error and continue\n"; // ERROR FROM MYMEMORY
                setSegmentTranslationError( $sid, $jid ); // devo settarli come done e lasciare il vecchio livello di match
                incrementCount( $pid, 0, 0 );
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
            echo "--- (child $my_pid) : No contribution found : set error and continue\n"; // ERROR FROM MYMEMORY
            setSegmentTranslationError( $sid, $jid ); // devo settarli come done e lasciare il vecchio livello di match
            incrementCount( $pid, 0, 0 );
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

        $equivalentWordMapping = json_decode( $payable_rates, true );

        $new_match_type = getNewMatchType( $tm_match_type, $fast_match_type, $equivalentWordMapping );
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

        echo "--- (child $my_pid) : sid=$sid --- \$tm_match_type=$tm_match_type, \$fast_match_type=$fast_match_type, \$new_match_type=$new_match_type, \$equivalentWordMapping[\$new_match_type]=" . $equivalentWordMapping[ $new_match_type ] . ", \$raw_wc=$raw_wc,\$standard_words=$standard_words,\$eq_words=$eq_words\n";

        $ret = CatUtils::addTranslationSuggestion( $sid, $jid, $suggestion_json, $suggestion, $suggestion_match, $suggestion_source, $new_match_type, $eq_words, $standard_words, $suggestion, "DONE", (int)$check->thereAreErrors(), $err_json, $mt_qe, $pretranslate_100 );
        //set memcache

        incrementCount( $pid, $eq_words, $standard_words );

        //unlock segment

        echo "--- (child $my_pid) : segment $sid-$jid unlocked\n";


        $amqHandlerSubscriber->ack( $msg );

        reQueue($amqHandlerPublisher, $failed_segment );

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

    echo "--- (child $child_process_id) : count segments in project $pid = " . $project_totals[ 'project_segments' ] . "\n";
    echo "--- (child $child_process_id) : Analyzed segments in project $pid = " . $project_totals[ 'num_analyzed' ] . "\n";

    if ( empty( $project_totals[ 'project_segments' ] ) ) {
        echo "--- (child $child_process_id) : WARNING !!! error while counting segments in projects $pid skipping and continue \n";

        return;
    }

    if ( $project_totals[ 'project_segments' ] - $project_totals[ 'num_analyzed' ] == 0 ) {

        $_analyzed_report = getProjectSegmentsTranslationSummary( $pid );

        $total_segs = array_pop( $_analyzed_report ); //remove Rollup

        echo "--- (child $child_process_id) : analysis project $pid finished : change status to DONE\n";

        changeProjectStatus( $pid, Constants_ProjectStatus::STATUS_DONE );
        changeTmWc( $pid, $project_totals[ 'eq_wc' ], $project_totals[ 'st_wc' ] );

        echo "--- (child $child_process_id) : trying to initialize job total word count.\n";
        foreach ( $_analyzed_report as $job_info ) {
            $counter = new WordCount_Counter();
            $counter->initializeJobWordCount( $job_info[ 'id_job' ], $job_info[ 'password' ] );
        }

    }
    echo "\n\n";

}

function getNewMatchType( $tm_match_type, $fast_match_type, $equivalentWordMapping ) {

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
            $tm_match_cat = "100%";
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