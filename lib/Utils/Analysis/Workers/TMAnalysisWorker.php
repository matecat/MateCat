<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */

namespace Analysis\Workers;
use Constants\Ices;
use Engine;
use Jobs_JobDao;
use Projects_ProjectDao;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EmptyElementException;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;

use Analysis\Queue\RedisKeys;
use \Exception;
use \Database, \PDOException;
use Translations_SegmentTranslationDao;

include \INIT::$MODEL_ROOT . "/queries.php";

/**
 * Class TMAnalysisWorker
 * @package Analysis\Workers
 *
 * Concrete worker.
 * This worker handle a queue element ( a segment ) and perform the analysis on it
 */
class TMAnalysisWorker extends AbstractWorker {

    /**
     * Matches vector
     *
     * @var array|null
     */
    protected $_matches = null;

    const ERR_EMPTY_WORD_COUNT = 4;
    const ERR_WRONG_PROJECT    = 5;

    /**
     * @var \FeatureSet
     */
    protected $featureSet ;

    /**
     * Concrete Method to start the activity of the worker
     *
     * @param AbstractElement $queueElement
     *
     * @return void
     *
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws Exception
     */
    public function process( AbstractElement $queueElement ) {
        
        $this->_checkDatabaseConnection();

        /**
         * Ensure we have fresh data from master node
         */
        $this->featureSet = new \FeatureSet() ;
        $this->featureSet->loadFromString( $queueElement->params->features );

        //reset matches vector
        $this->_matches = null;

        /**
         * @var $queueElement QueueElement
         */
        $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Segment {$queueElement->params->id_segment} - Job {$queueElement->params->id_job} found " );

        /**
         * @throws EndQueueException
         */
        $this->_checkForReQueueEnd( $queueElement );

        //START

        $this->_initializeTMAnalysis( $queueElement, $this->_workerPid );

        /**
         * @throws EmptyElementException
         */
        $this->_checkWordCount( $queueElement );

        /**
         * @throws ReQueueException
         * @throws EmptyElementException
         */
        $this->_matches = $this->_getMatches( $queueElement );


        $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Segment {$queueElement->params->id_segment} - Job {$queueElement->params->id_job} matches retrieved." );
        $this->_tryRealignTagID( $queueElement );

        /**
         * @throws ReQueueException
         */
        $this->_updateRecord( $queueElement );
        $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Segment {$queueElement->params->id_segment} - Job {$queueElement->params->id_job} updated." );

        //ack segment
        $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Segment {$queueElement->params->id_segment} - Job {$queueElement->params->id_job} acknowledged." );

    }

    /**
     * Update the record on the database
     *
     * @param QueueElement $queueElement
     *
     * @throws Exception
     * @throws ReQueueException
     */
    protected function _updateRecord( QueueElement $queueElement ){

        $suggestion = \CatUtils::view2rawxliff( $this->_matches[ 0 ][ 'raw_translation' ] );

        // OLD Patch for wrong tag in memories: preg_replace all x tags <x not closed > inside suggestions with correctly closed
        $suggestion = preg_replace( '|<x([^/]*?)>|', '<x\1/>', $suggestion );

        $suggestion_match  = $this->_matches[ 0 ][ 'match' ];
        $suggestion_json   = json_encode( $this->_matches );
        $suggestion_source = $this->_matches[ 0 ][ 'created_by' ];

        $equivalentWordMapping = json_decode( $queueElement->params->payable_rates, true );

        $new_match_type = $this->_getNewMatchType(
                ( stripos( $this->_matches[ 0 ][ 'created_by' ], "MT" ) !== false ? "MT" : $suggestion_match ),
                $queueElement->params->match_type,
                $equivalentWordMapping,
                /* is Public TM */
                empty( $this->_matches[ 0 ][ 'memory_key' ] ),
                isset( $this->_matches[ 0 ][ 'ICE' ] ) && $this->_matches[ 0 ][ 'ICE' ]
        );

        $eq_words       = $equivalentWordMapping[ $new_match_type ] * $queueElement->params->raw_word_count / 100;
        $standard_words = $eq_words;

        /**
         * if the first match is MT perform QA realignment because some MT engines breaks tags
         * also perform a tag ID check and mismatch validation
         */
        if ( $new_match_type == 'MT' ) {

            //Reset the standard word count to be equals to other cat tools which do not have the MT in analysis
            $standard_words = $equivalentWordMapping[ "NO_MATCH" ] * $queueElement->params->raw_word_count / 100;

            $check = new \PostProcess( $this->_matches[ 0 ][ 'raw_segment' ], $suggestion );
            $check->realignMTSpaces();

            //this should every time be ok because MT preserve tags, but we use the check on the errors
            //for logic correctness
            if ( !$check->thereAreErrors() ) {
                $suggestion = \CatUtils::view2rawxliff( $check->getTrgNormalized() );
                $err_json   = '';
            } else {
                $err_json = $check->getErrorsJSON();
            }

        } else {

            /**
             * Otherwise try to perform only the tagCheck
             */
            $check = new \PostProcess( $queueElement->params->segment, $suggestion );
            $check->performTagCheckOnly();

            //_TimeStampMsg( $check->getErrors() );

            if ( $check->thereAreErrors() ) {
                $err_json = $check->getErrorsJSON();
            } else {
                $err_json = '';
            }

        }

        ( !empty( $this->_matches[ 0 ][ 'sentence_confidence' ] ) ?
                    $mt_qe = floatval( $this->_matches[ 0 ][ 'sentence_confidence' ] ) :
                    $mt_qe = null
        );

        $tm_data                             = array();
        $tm_data[ 'id_job' ]                 = $queueElement->params->id_job;
        $tm_data[ 'id_segment' ]             = $queueElement->params->id_segment;
        $tm_data[ 'translation' ]            = $suggestion;
        $tm_data[ 'suggestion' ]             = $suggestion;
        $tm_data[ 'suggestions_array' ]      = $suggestion_json;
        $tm_data[ 'match_type' ]             = $new_match_type;
        $tm_data[ 'eq_word_count' ]          = $eq_words;
        $tm_data[ 'standard_word_count' ]    = $standard_words;
        $tm_data[ 'tm_analysis_status' ]     = "DONE";
        $tm_data[ 'warning' ]                = (int)$check->thereAreErrors();
        $tm_data[ 'serialized_errors_list' ] = $err_json;
        $tm_data[ 'mt_qe' ]                  = $mt_qe;


        $tm_data[ 'suggestion_source' ]      = $suggestion_source;
        if ( !empty( $tm_data[ 'suggestion_source' ] ) ) {
            if ( strpos( $tm_data[ 'suggestion_source' ], "MT" ) === false ) {
                $tm_data[ 'suggestion_source' ] = 'TM';
            } else {
                $tm_data[ 'suggestion_source' ] = 'MT';
            }
        }

        //check the value of suggestion_match
        $tm_data[ 'suggestion_match' ]       = $suggestion_match;

        $tm_data = $this->_iceLockCheck( $tm_data, $queueElement->params );

        $updateRes = Translations_SegmentTranslationDao::setAnalysisValue( $tm_data );
        if ( $updateRes < 0 ) {

            $this->_doLog( "**** Error occurred during the storing (UPDATE) of the suggestions for the segment {$tm_data[ 'id_segment' ]}" );
            throw new ReQueueException( "**** Error occurred during the storing (UPDATE) of the suggestions for the segment {$tm_data[ 'id_segment' ]}", self::ERR_REQUEUE );

        } elseif( $updateRes == 0 ) {

            //There was not a fast Analysis??? Impossible.
            $this->_doLog( "No row found: " . $tm_data[ 'id_segment' ] . "-" . $tm_data[ 'id_job' ] );

        } else {

            $this->_doLog( "Row found: " . $tm_data[ 'id_segment' ] . "-" . $tm_data[ 'id_job' ] . " - UPDATED.");

        }


        //set redis cache
        $this->_incrementAnalyzedCount( $queueElement->params->pid, $eq_words, $standard_words );
        $this->_decSegmentsToAnalyzeOfWaitingProjects( $queueElement->params->pid );
        $this->_tryToCloseProject( $queueElement->params->pid );


        $this->featureSet->run('postTMSegmentAnalyzed', array(
                'tm_data'       => $tm_data,
                'queue_element' => $queueElement
        ));

    }

    protected function _iceLockCheck( $tm_data, $queueElementParams ){

        //Separates the if to make the conditions more readable
        if( stripos( $tm_data[ 'suggestion_match' ], "100%" ) !== false ){

            if( $tm_data[ 'match_type' ] == "ICE" ){

                list( $lang, ) = explode( '-', $queueElementParams->target );

                //i found this language in the list of disabled target language??
                if( array_search( $lang, ICES::$iceLockDisabledForTargetLangs ) === false ){
                    //ice lock enabled, language not found
                    $tm_data[ 'status' ] = \Constants_TranslationStatus::STATUS_APPROVED;
                    $tm_data[ 'locked' ] = true;
                }

                $tm_data = $this->featureSet->filter( 'checkIceLocked', $tm_data, $queueElementParams );

            } elseif( $queueElementParams->pretranslate_100 ) {
                $tm_data[ 'status' ] = \Constants_TranslationStatus::STATUS_TRANSLATED;
                $tm_data[ 'locked' ] = false;
            }

            //custom condition for 100% matches
            $tm_data = $this->featureSet->filter( 'check100MatchLocked', $tm_data, $queueElementParams );

        }

        return $tm_data;

    }

    /**
     * Calculate the new score match by the Equivalent word mapping ( the value is inside the queue element )
     *
     * RATIO : i change the value only if the new match is strictly better
     * ( in terms of percent payed per word )  then the actual one
     *
     *
     * @param string $tm_match_type
     * @param string $fast_match_type
     * @param array  $equivalentWordMapping
     * @param bool   $publicTM
     * @param bool   $isICE
     *
     * @return string
     * @throws Exception
     */
    protected function _getNewMatchType( $tm_match_type, $fast_match_type, &$equivalentWordMapping, $publicTM = false, $isICE = false ) {

        $fast_match_type = strtoupper( $fast_match_type );
        $fast_rate_paid  = $equivalentWordMapping[ $fast_match_type ];

        $tm_match_fuzzy_band = "";
        $tm_rate_paid = 0;

        $tm_match_type = $this->featureSet->filter( 'customizeTMMatches', $tm_match_type );

        if ( stripos( $tm_match_type, "MT" ) !== false ) {

            $tm_match_fuzzy_band = "MT";
            $tm_rate_paid = $equivalentWordMapping[ "MT" ];

        } else {

            $ind = intval( $tm_match_type );

            if ( $ind == "100" ) {

                if( $isICE ){
                    $tm_match_fuzzy_band  = "ICE";
                    $tm_rate_paid = 0;
                    $equivalentWordMapping[ "ICE" ] = 0;
                } else {
                    $tm_match_fuzzy_band = ($publicTM) ? "100%_PUBLIC" : "100%";
                    $tm_rate_paid = $equivalentWordMapping[ $tm_match_fuzzy_band ];
                }

            }

            /**
             * MyMemory never returns matches below 50%, it send them as NO_MATCH
             * So this block of code results unused
             */
            if ( $ind < 50 ) {
                $tm_match_fuzzy_band = "NO_MATCH";
                $tm_rate_paid = $equivalentWordMapping[ "NO_MATCH" ];
            }

            if ( $ind >= 50 and $ind < 75 ) {
                $tm_match_fuzzy_band = "50%-74%";
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
                    $tm_match_fuzzy_band = "75%-84%";
                    $tm_rate_paid = $equivalentWordMapping[ "75%-84%" ];
                }
                elseif( $ind >= 85 && $ind <=94 ){
                    $tm_match_fuzzy_band = "85%-94%";
                    $tm_rate_paid = $equivalentWordMapping[ "85%-94%" ];
                }
                elseif( $ind >= 95 && $ind <=99 ){
                    $tm_match_fuzzy_band = "95%-99%";
                    $tm_rate_paid = $equivalentWordMapping[ "95%-99%" ];
                }
            }
            elseif ( $ind >= 75 and $ind <= 99 ) {
                $tm_match_fuzzy_band = "75%-99%";
                $tm_rate_paid = $equivalentWordMapping[ "75%-99%" ];
            }
        }

        /**
         * Apply the TM discount rate and/or force the value obtained from TM for
         * matches between 50%-74% because is never returned in Fast Analysis; it's rate is set default as equals to NO_MATCH
         */
        if ( $tm_rate_paid < $fast_rate_paid || $fast_match_type == "NO_MATCH" ) {
            return $tm_match_fuzzy_band;
        }

        return $fast_match_type;
    }

    /**
     * Get matches from MyMemory and other engines
     *
     * @param $queueElement QueueElement
     *
     * @return array
     * @throws Exception
     */
    protected function _getMatches( QueueElement $queueElement ){

        $_config                  = [];
        $_config[ 'segment' ]     = $queueElement->params->segment;
        $_config[ 'source' ]      = $queueElement->params->source;
        $_config[ 'target' ]      = $queueElement->params->target;
        $_config[ 'email' ]       = \INIT::$MYMEMORY_TM_API_KEY;

        $_config[ 'context_before' ] = $queueElement->params->context_before;
        $_config[ 'context_after' ]  = $queueElement->params->context_after;

        $tm_keys = \TmKeyManagement_TmKeyManagement::getJobTmKeys( $queueElement->params->tm_keys, 'r', 'tm' );
        if ( is_array( $tm_keys ) && !empty( $tm_keys ) ) {
            foreach ( $tm_keys as $tm_key ) {
                $_config[ 'id_user' ][ ] = $tm_key->key;
            }
        }

        $_config[ 'num_result' ] = 3;

        $id_mt_engine = $queueElement->params->id_mt_engine;
        $id_tms       = $queueElement->params->id_tms;

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

            if( $queueElement->params->only_private ){
                $_config[ 'onlyprivate' ] = true;
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
             * @var $tms \Engines_MyMemory
             */
            $tms        = Engine::getInstance( $_TMS );
            $tms->doLog = true;

            $config = $tms->getConfigStruct();
            $config = array_merge( $config, $_config );

            $tms_match = $tms->get( $config );

            /**
             * If No results found. Re-Queue
             *
             * MyMemory can return null if an error occurs (e.g http response code is 404, 410, 500, 503, etc.. )
             */
            if ( $tms_match === null ) {

                $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Error from MyMemory. NULL received." );
                throw new ReQueueException( "--- (Worker " . $this->_workerPid . ") : Error from MyMemory. NULL received.", self::ERR_REQUEUE );

            }

            $tms_match = $tms_match->get_matches_as_array();

        }

        /**
         * Call External MT engine if it is a custom one ( mt not requested from MyMemory )
         */
        if ( $id_mt_engine > 1 /* Request MT Directly */ ) {

            try {
                $mt     = \Engine::getInstance( $id_mt_engine );

                //tell to the engine that this is the analysis phase ( some engines want to skip the analysis )
                $mt->setAnalysis();

                $config = $mt->getConfigStruct();
                $config = array_merge( $config, $_config );

                $mt_result = $mt->get( $config );

                if ( isset( $mt_result[ 'error' ][ 'code' ] ) ) {
                    $mt_result = false;
                }
            } catch ( \Exception $e ){
                $this->_doLog( $e->getMessage() );
            }

        }

        $matches = array();
        if ( !empty( $tms_match ) ) {
            $matches = $tms_match;
        }

        if ( isset( $mt_result ) && !empty( $mt_result ) ) {
            $matches[ ] = $mt_result;
            usort( $matches, "self::_compareScore" );
        }

        /**
         * If No results found. Re-Queue
         */
        if ( empty( $matches ) || !is_array( $matches ) ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : No contribution found for this segment." );
            $this->_forceSetSegmentAnalyzed( $queueElement );
            throw new EmptyElementException( "--- (Worker " . $this->_workerPid . ") : No contribution found for this segment.", self::ERR_EMPTY_ELEMENT );
        }

        $matches = $this->featureSet->filter( 'modifyMatches', $matches );

        return $matches;

    }

    /**
     *  Only if this is not a MT and if it is a ( 90 =< MATCH < 100 ) try to realign tag IDs
     *
     * @param QueueElement $queueElement
     *
     */
    protected function _tryRealignTagID( QueueElement $queueElement ){

        //use the first match record
        // ---> $this->_matches[ 0 ];

        ( isset( $this->_matches[ 0 ][ 'match' ] ) ? $firstMatchVal = floatval( $this->_matches[ 0 ][ 'match' ] ) : null );
        if ( isset( $firstMatchVal ) && $firstMatchVal >= 90 && $firstMatchVal < 100 ) {

            $srcSearch    = strip_tags( $queueElement->params->segment );
            $segmentFound = strip_tags( $this->_matches[ 0 ][ 'raw_segment' ] );
            $srcSearch    = mb_strtolower( preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $srcSearch ) );
            $segmentFound = mb_strtolower( preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $segmentFound ) );

            $fuzzy = @levenshtein( $srcSearch, $segmentFound ) / log10( mb_strlen( $srcSearch . $segmentFound ) + 1 );

            //levenshtein handle max 255 chars per string and returns -1, so fuzzy var can be less than 0 !!
            if ( $srcSearch == $segmentFound || ( $fuzzy < 2.5 && $fuzzy > 0 ) ) {

                $qaRealign = new \QA( $queueElement->params->segment, html_entity_decode( $this->_matches[ 0 ][ 'raw_translation' ] ) );
                $qaRealign->tryRealignTagID();

                $log_prepend = uniqid( '', true ) . " - SERVER REALIGN IDS PROCEDURE | ";
                if ( !$qaRealign->thereAreErrors() ) {

                    /*
                        $this->_doLog( $log_prepend . " - Requested Segment: " . var_export( $queueElement, true ) );
                        $this->_doLog( $log_prepend . "Fuzzy: " . $fuzzy . " - Try to Execute Tag ID Realignment." );
                        $this->_doLog( $log_prepend . "TMS RAW RESULT:" );
                        $this->_doLog( $log_prepend . var_export( $this->_matches[ 0 ]e, true ) );
                        $this->_doLog( $log_prepend . "Realignment Success:" );
                    */
                    $this->_matches[ 0 ][ 'raw_translation' ] = $qaRealign->getTrgNormalized();
                    $this->_matches[ 0 ][ 'match' ]           = ( $fuzzy == 0 ? '100%' : '99%' );

                } else {
                    $this->_doLog( $log_prepend . 'Realignment Failed. Skip. Segment: ' . $queueElement->params->id_segment );
                }

            }

        }

    }

    /**
     * Compare match scores between TM records and MT records when they are external to MyMemory
     *
     * @param $a
     * @param $b
     *
     * @return int
     */
    protected static function _compareScore( $a, $b ){
        if ( floatval( $a[ 'match' ] ) == floatval( $b[ 'match' ] ) ) {
            return 0;
        }
        return ( floatval( $a[ 'match' ] ) < floatval( $b[ 'match' ] ) ? 1 : -1 ); //SORT DESC !!!!!!! INVERT MINUS SIGN
        //this is necessary since usort sorts is ascending order, thus inverting the ranking
    }

    /**
     * Check for a relevant word count, otherwise de-queue the segment and set as done
     *
     * @param QueueElement $queueElement
     *
     * @throws Exception
     */
    protected function _checkWordCount( QueueElement $queueElement ){

        if ( $queueElement->params->raw_word_count == 0 ) {
//            SET as DONE and "decrement counter/close project"
            $this->_forceSetSegmentAnalyzed( $queueElement );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : empty word count segment. acknowledge and continue." );
            throw new EmptyElementException( "--- (Worker " . $this->_workerPid . ") : empty segment. acknowledge and continue", self::ERR_EMPTY_WORD_COUNT );
        }

    }

    /**
     * Initialize the counter for the analysis.
     * Take the info from the project and initialize it.
     * There is a lock for every project on redis, so only one worker can initialize the counter
     *
     *  - Set project total segments to analyze, and count the analyzed as segments done
     *
     * @param $queueElement QueueElement
     * @param $process_pid int
     */
    protected function _initializeTMAnalysis( QueueElement $queueElement, $process_pid ){

        $sid = $queueElement->params->id_segment;
        $jid = $queueElement->params->id_job;
        $pid = $queueElement->params->pid;

        //get the number of segments in job
        $_acquiredLock = $this->_queueHandler->getRedisClient()->setnx( RedisKeys::PROJECT_INIT_SEMAPHORE . $pid, true ); // lock for 24 hours
        if ( !empty( $_acquiredLock ) ) {

            $this->_queueHandler->getRedisClient()->expire( RedisKeys::PROJECT_INIT_SEMAPHORE . $pid, 60 * 60 * 24 /* 24 hours TTL */ );

            $total_segs = getProjectSegmentsTranslationSummary( $pid );

            $total_segs = array_pop( $total_segs ); // get the Rollup Value
            $this->_doLog( $total_segs );

            $this->_queueHandler->getRedisClient()->setex( RedisKeys::PROJECT_TOT_SEGMENTS . $pid, 60 * 60 * 24 /* 24 hours TTL */, $total_segs[ 'project_segments' ] );
            $this->_queueHandler->getRedisClient()->incrby( RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, $total_segs[ 'num_analyzed' ] );
            $this->_queueHandler->getRedisClient()->expire( RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, 60 * 60 * 24 /* 24 hours TTL */ );
            $this->_doLog ( "--- (Worker $process_pid) : found " . $total_segs[ 'project_segments' ] . " segments for PID $pid" );

        } else {
            $_projectTotSegs = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJECT_TOT_SEGMENTS . $pid );
            $_analyzed       = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid );
            $this->_doLog ( "--- (Worker $process_pid) : found $_projectTotSegs, analyzed $_analyzed segments for PID $pid in Redis" );
        }

        $this->_doLog ( "--- (Worker $process_pid) : fetched data for segment $sid-$jid. Project ID is $pid" );

    }

    /**
     * Increment the analysis counter:
     *  - eq_word_count
     *  - st_word_count
     *  - num_segments_done
     *
     * @param $pid
     * @param $eq_words
     * @param $standard_words
     */
    protected function _incrementAnalyzedCount( $pid, $eq_words, $standard_words ) {
        $this->_queueHandler->getRedisClient()->incrby( RedisKeys::PROJ_EQ_WORD_COUNT . $pid, (int)( $eq_words * 1000 ) );
        $this->_queueHandler->getRedisClient()->incrby( RedisKeys::PROJ_ST_WORD_COUNT . $pid, (int)( $standard_words * 1000 ) );
        $this->_queueHandler->getRedisClient()->incrby( RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, 1 );
    }

    /**
     * Decrement the number of segments that we must wait before that this project starts.
     * There is a list of project ids from witch the interface will read the remaining segments.
     *
     * @param int $project_id
     *
     * @throws Exception
     */
    protected function _decSegmentsToAnalyzeOfWaitingProjects( $project_id ){

        if(  empty( $project_id ) ){
            throw new Exception( 'Can Not send without a Queue ID. \Analysis\QueueHandler::setQueueID ', self::ERR_WRONG_PROJECT );
        }

        $working_jobs = $this->_queueHandler->getRedisClient()->lrange( $this->_myContext->redis_key, 0, -1 );

        /**
         * We have an unordered list of numeric keys [1,3,2,5,4]
         *
         * I want to decrement the key that are positioned in the list after my key.
         *
         * So, if my key is 2, i want not decrement the key 3 in the example because my key is positioned after "3" in the list
         *
         */
        $found = false;
        foreach( $working_jobs as $k => $value ){
            if( $value == $project_id ) {
                $found = true;
            }
            if( $found ){
                $this->_queueHandler->getRedisClient()->decr( RedisKeys::TOTAL_SEGMENTS_TO_WAIT . $value );
            }
        }

    }

    /**
     * Every time one element of the project is taken from the queue, the worker try to finalize the project.
     * Only the last worker can finalize the project by setting a lock on Redis.
     *
     * @param $_project_id
     *
     * @throws ReQueueException
     * @throws \Predis\Connection\ConnectionException
     * @throws EndQueueException
     */
    protected function _tryToCloseProject( $_project_id ) {

        $project_totals                       = array();
        $project_totals[ 'project_segments' ] = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJECT_TOT_SEGMENTS . $_project_id );
        $project_totals[ 'num_analyzed' ]     = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $_project_id );
        $project_totals[ 'eq_wc' ]            = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJ_EQ_WORD_COUNT . $_project_id ) / 1000;
        $project_totals[ 'st_wc' ]            = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJ_ST_WORD_COUNT . $_project_id ) / 1000;

        $this->_doLog ( "--- (Worker $this->_workerPid) : count segments in project $_project_id = " . $project_totals[ 'project_segments' ] . "" );
        $this->_doLog ( "--- (Worker $this->_workerPid) : Analyzed segments in project $_project_id = " . $project_totals[ 'num_analyzed' ] . "" );

        if ( empty( $project_totals[ 'project_segments' ] ) ) {
            $this->_doLog ( "--- (Worker $this->_workerPid) : WARNING !!! error while counting segments in projects $_project_id skipping and continue " );

            return;
        }

        if ( $project_totals[ 'project_segments' ] - $project_totals[ 'num_analyzed' ] == 0 && $this->_queueHandler->getRedisClient()->setnx( RedisKeys::PROJECT_ENDING_SEMAPHORE . $_project_id, 1 ) ) {

            $this->_queueHandler->getRedisClient()->expire( RedisKeys::PROJECT_ENDING_SEMAPHORE . $_project_id, 60 * 60 * 24 /* 24 hours TTL */ );

            try {
                $this->featureSet->run( 'beforeTMAnalysisCloseProject', $_project_id );
            } catch(\Exception $e) {
                $this->_queueHandler->getRedisClient()->del( RedisKeys::PROJECT_ENDING_SEMAPHORE . $_project_id );
                $this->_doLog("Requeueing project_id $_project_id because of error {$e->getMessage()}");
                throw new ReQueueException();
            }

            //TODO use a simplest query to get job id and password
            $_analyzed_report = getProjectSegmentsTranslationSummary( $_project_id );

            $total_segs = array_pop( $_analyzed_report ); //remove Rollup

            $this->_doLog ( "--- (Worker $this->_workerPid) : analysis project $_project_id finished : change status to DONE" );

            changeProjectStatus( $_project_id, \Constants_ProjectStatus::STATUS_DONE );
            changeTmWc( $_project_id, $project_totals[ 'eq_wc' ], $project_totals[ 'st_wc' ] );

            /*
             * Remove this job from the project list
             */
            $this->_queueHandler->getRedisClient()->lrem( $this->_myContext->redis_key, 0, $_project_id );

            $this->_doLog ( "--- (Worker $this->_workerPid) : trying to initialize job total word count." );
            $wordCountStructs = [];

            $database = Database::obtain();
            foreach ( $_analyzed_report as $job_info ) {
                $counter = new \WordCount_Counter();
                $database->begin();
                $wordCountStructs[] = $counter->initializeJobWordCount( $job_info[ 'id_job' ], $job_info[ 'password' ] );
                $database->commit();
            }

            ( new Jobs_JobDao() )->destroyCacheByProjectId( $_project_id );
            Projects_ProjectDao::destroyCacheById( $_project_id );

            try {
                $this->featureSet->run( 'afterTMAnalysisCloseProject', $_project_id, $_analyzed_report );
            } catch(\Exception $e) {
                //ignore Exception the analysis is finished anyway
                $this->_doLog("Ending project_id $_project_id with error {$e->getMessage()} . COMPLETED.");
            }

        }

    }

    /**
     * When a segment has an error or was re-queued too much times we want to force it as analyzed
     *
     * @param $elementQueue QueueElement
     *
     * @throws Exception
     * @throws ReQueueException
     * @throws \Predis\Connection\ConnectionException
     */
    protected function _forceSetSegmentAnalyzed( QueueElement $elementQueue ) {

        $data[ 'tm_analysis_status' ] = "DONE"; // DONE . I don't want it remains in an inconsistent state
        $where                        = " id_segment = {$elementQueue->params->id_segment} and id_job = {$elementQueue->params->id_job} ";

        $db = Database::obtain();
        try {
            $affectedRows = $db->update( 'segment_translations', $data, $where );
        } catch ( PDOException $e ) {
            $this->_doLog( $e->getMessage() );
        }

        $this->_incrementAnalyzedCount( $elementQueue->params->pid, 0, 0 );
        $this->_decSegmentsToAnalyzeOfWaitingProjects( $elementQueue->params->pid );
        $this->_tryToCloseProject( $elementQueue->params->pid );

    }

}
