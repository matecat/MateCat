<?php
namespace Analysis;
use Analysis\Commons\RedisKeys,
        Analysis\Queue\Info, Analysis\Queue\QueuesList;

use \Exception, \Bootstrap;

$root = realpath( dirname( __FILE__ ) . '/../../../' );
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();
include \INIT::$MODEL_ROOT . "/queries.php";

class TMThread {

    /**
     * @var \Analysis\QueueHandler
     */
    protected $_queueHandler;

    /**
     * @var Info
     */
    protected $_mySubscribedQueue;

    /**
     * Parent process ID
     *
     * @var int
     */
    protected $_parentPid = 0;

    /**
     * AMQ frames read
     *
     * @var int
     */
    protected $_frameID = 0;

    /**
     * Matches vector
     *
     * @var array|null
     */
    protected $_matches = null;

    /**
     * @var static
     */
    public static $__INSTANCE;
    public $RUNNING = true;
    public $_tHandlerPID;
    protected static function _TimeStampMsg( $msg ) {
//        \INIT::$DEBUG = false;
        if ( \INIT::$DEBUG ) echo "[" . date( DATE_RFC822 ) . "] " . $msg . "\n";
        \Log::doLog( $msg );
    }

    protected function __construct( Info $queueInfo ) {

        $this->_tHandlerPID = posix_getpid();
        \Log::$fileName = 'tm_analysis.log';

        $this->_mySubscribedQueue = $queueInfo;

        try {

            $this->_parentPid = posix_getppid();
            $this->_queueHandler = new QueueHandler();

            if ( !$this->_queueHandler->getRedisClient()->sadd( $this->_mySubscribedQueue->pid_set_name, $this->_tHandlerPID ) ) {
                throw new \Exception( "(child {$this->_tHandlerPID}) : FATAL !! cannot create my resource ID. Exiting!" );
            } else {
                self::_TimeStampMsg( "(child {$this->_tHandlerPID}) : spawned !!!" );
            }

            $this->_queueHandler->subscribe( $this->_mySubscribedQueue->queue_name );

        } catch ( \Exception $ex ){

            $msg = "****** No REDIS/AMQ instances found. Exiting. ******";
            static::_TimeStampMsg( $msg );
            static::_TimeStampMsg( $ex->getMessage() );
            die();

        }

    }

    /**
     * @param Info $queueInfo
     *
     * @return static
     */
    public static function getInstance( Info $queueInfo ) {

        if ( PHP_SAPI != 'cli' || isset ( $_SERVER [ 'HTTP_HOST' ] ) ) {
            die ( "This script can be run only in CLI Mode.\n\n" );
        }

        declare( ticks = 10 );
        set_time_limit( 0 );

        if ( !extension_loaded( "pcntl" ) && (bool)ini_get( "enable_dl" ) ) {
            dl( "pcntl.so" );
        }
        if ( !function_exists( 'pcntl_signal' ) ) {
            $msg = "****** PCNTL EXTENSION NOT LOADED. KILLING THIS PROCESS COULD CAUSE UNPREDICTABLE ERRORS ******";
            static::_TimeStampMsg( $msg );
        } else {
//            static::_TimeStampMsg( 'registering signal handlers' );

            pcntl_signal( SIGTERM, array( get_called_class(), 'sigSwitch' ) );
            pcntl_signal( SIGINT,  array( get_called_class(), 'sigSwitch' ) );
            pcntl_signal( SIGHUP,  array( get_called_class(), 'sigSwitch' ) );

//            $msg = str_pad( " Signal Handler Installed ", 50, "-", STR_PAD_BOTH );
//            static::_TimeStampMsg( "$msg\n" );
        }

        static::$__INSTANCE = new static( $queueInfo );
        return static::$__INSTANCE;

    }

    public static function sigSwitch( $sig_no ) {

        static::_TimeStampMsg( "Trapped Signal : $sig_no" );

        switch ( $sig_no ) {
            case SIGTERM :
            case SIGINT :
            case SIGHUP :
                static::$__INSTANCE->RUNNING = false;
                break;
            default :
                break;
        }
    }

    public function main( $args = null ) {

        $this->_frameID = 1;
        do {

            //reset matches vector
            $this->_matches = null;

            try {

                // PROCESS CONTROL FUNCTIONS
                if ( !self::_myProcessExists( $this->_tHandlerPID ) ) {
                    self::_TimeStampMsg( "(child " . $this->_tHandlerPID . ") :  EXITING! my pid does not exists anymore, my parent told me to die." );
                    $this->RUNNING = false;
                    break;
                }

                // control if parent is still running
//                if ( !self::_isMyParentRunning( $this->_parentPid ) ) {
//                    self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") : EXITING : my parent seems to be died." );
//                    $this->RUNNING = false;
//                    break;
//                }
                // PROCESS CONTROL FUNCTIONS

                //read Message frame from the queue
                list( $msgFrame, $elementQueue ) = $this->_readAMQFrame();

            } catch ( \Exception $e ) {

                $secs = 3;
//                self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") : Failed to read frame from AMQ. Doing nothing, wait $secs seconds and re-try in next cycle." );
//                self::_TimeStampMsg( $e->getMessage() );
                sleep( $secs );
                continue;

            }

            self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") : Segment {$elementQueue[ 'id_segment' ]} - Job {$elementQueue[ 'id_job' ]} found " );

            try {
                $this->_checkForReQueueEnd( $elementQueue );
            } catch ( \Exception $e ){
                //this message must be considered definitively wrong
                self::_TimeStampMsg( $e->getMessage() );
                $this->_queueHandler->ack( $msgFrame );
                continue;
            }

            //START

            try {
                $this->_queueHandler->initializeTMAnalysis( $elementQueue, $this->_tHandlerPID );
                $this->_checkWordCount( $elementQueue );
            } catch ( \Exception $e ){
                self::_TimeStampMsg( $e->getMessage() );
                $this->_queueHandler->ack( $msgFrame );
                continue;
            }

            try {
                $this->_matches = $this->_getMatches( $elementQueue );
                self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") : Segment {$elementQueue[ 'id_segment' ]} - Job {$elementQueue[ 'id_job' ]} matches retrieved." );
                $this->_tryRealignTagID( $elementQueue );
            } catch ( \Exception  $e ) {

//                self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") : error retrieving Matches. Continue and try again in the next cycle." ); // ERROR FROM MYMEMORY
                self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") : error retrieving Matches. Try again in the next cycle. - " . $e->getMessage() ); // ERROR FROM MYMEMORY
                $this->_queueHandler->ack( $msgFrame ); //ack the message try again next time. Re-queue

                //set/increment the reQueue number
                $elementQueue[ 'reQueueNum' ] = @++$elementQueue[ 'reQueueNum' ];
                $amqHandlerPublisher          = new QueueHandler();
                $amqHandlerPublisher->reQueue( $elementQueue, $this->_mySubscribedQueue );
                $amqHandlerPublisher->disconnect();
                continue;

            }

            try {
                $this->_updateRecord( $elementQueue );
                self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") : Segment {$elementQueue[ 'id_segment' ]} - Job {$elementQueue[ 'id_job' ]} updated." );
            } catch ( \Exception $e ){

                //Failed to update DB record
                self::_TimeStampMsg( $e->getMessage() );
                $this->_queueHandler->ack( $msgFrame ); //ack the message try again next time. Re-queue

                //set/increment the reQueue number
                $elementQueue[ 'reQueueNum' ] = @++$elementQueue[ 'reQueueNum' ];
                $amqHandlerPublisher          = new QueueHandler();
                $amqHandlerPublisher->reQueue( $elementQueue, $this->_mySubscribedQueue );
                $amqHandlerPublisher->disconnect();
                continue;

            }

            //unlock segment
            $this->_queueHandler->ack( $msgFrame );
            self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") : Segment {$elementQueue[ 'id_segment' ]} - Job {$elementQueue[ 'id_job' ]} acknowledged." );

        } while( $this->RUNNING );

        self::cleanShutDown();

    }

    protected function _updateRecord( $elementQueue ){

        $tm_match_type = $this->_matches[ 0 ][ 'match' ];
        if ( stripos( $this->_matches[ 0 ][ 'created_by' ], "MT" ) !== false ) {
            $tm_match_type = "MT";
        }

        $suggestion = \CatUtils::view2rawxliff( $this->_matches[ 0 ][ 'raw_translation' ] );

        //preg_replace all x tags <x not closed > inside suggestions with correctly closed
        $suggestion = preg_replace( '|<x([^/]*?)>|', '<x\1/>', $suggestion );

        $suggestion_match  = $this->_matches[ 0 ][ 'match' ];
        $suggestion_json   = json_encode( $this->_matches );
        $suggestion_source = $this->_matches[ 0 ][ 'created_by' ];

        $equivalentWordMapping = json_decode( $elementQueue[ 'payable_rates' ], true );

        $new_match_type = $this->_getNewMatchType(
                $tm_match_type,
                $elementQueue[ 'match_type' ],
                $equivalentWordMapping,
                /* is Public TM */
                empty( $this->_matches[ 0 ][ 'memory_key' ] )
        );

        $eq_words       = $equivalentWordMapping[ $new_match_type ] * $elementQueue[ 'raw_word_count' ] / 100;
        $standard_words = $eq_words;

        //if the first match is MT perform QA realignment
        if ( $new_match_type == 'MT' ) {

            $standard_words = $equivalentWordMapping[ "NO_MATCH" ] * $elementQueue[ 'raw_word_count' ] / 100;

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

            //try to perform only the tagCheck
            $check = new \PostProcess( $elementQueue[ 'segment' ], $suggestion );
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
        $tm_data[ 'id_job' ]                 = $elementQueue[ 'id_job' ];
        $tm_data[ 'id_segment' ]             = $elementQueue[ 'id_segment' ];
        $tm_data[ 'suggestions_array' ]      = $suggestion_json;
        $tm_data[ 'suggestion' ]             = $suggestion;
        $tm_data[ 'match_type' ]             = $new_match_type;
        $tm_data[ 'eq_word_count' ]          = $eq_words;
        $tm_data[ 'standard_word_count' ]    = $standard_words;
        $tm_data[ 'translation' ]            = $suggestion;
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
        if( $tm_data[ 'suggestion_match' ] == "100%" && $elementQueue[ 'pretranslate_100' ] ){
            $tm_data[ 'status' ] = \Constants_TranslationStatus::STATUS_TRANSLATED;
        }

        $updateRes = setSuggestionUpdate( $tm_data );
        if ( $updateRes < 0 ) {

            throw new \Exception( "**** Error occurred during the storing (UPDATE) of the suggestions for the segment {$tm_data[ 'id_segment' ]}" );

        } elseif( $updateRes == 0 ) {

            //There was not a fast Analysis??? Impossible.
            self::_TimeStampMsg( "No row found: " . $tm_data[ 'id_segment' ] . "-" . $tm_data[ 'id_job' ] );

        } else {

            self::_TimeStampMsg( "Row found: " . $tm_data[ 'id_segment' ] . "-" . $tm_data[ 'id_job' ] . " - UPDATED.");

        }

        //set memcache
        $this->_queueHandler->incrementAnalyzedCount( $elementQueue[ 'pid' ], $eq_words, $standard_words );
        $this->_queueHandler->decSegmentsToAnalyzeOfWaitingProjects( $elementQueue[ 'pid' ], $this->_mySubscribedQueue );
        $this->_queueHandler->tryToCloseProject( $elementQueue[ 'pid' ], $this->_tHandlerPID, $this->_mySubscribedQueue );

    }

    /**
     * @param string $tm_match_type
     * @param string $fast_match_type
     * @param array  $equivalentWordMapping
     * @param bool   $publicTM
     *
     * @return string
     */
    protected function _getNewMatchType( $tm_match_type, $fast_match_type, $equivalentWordMapping, $publicTM = false ) {

        // RATIO : i change the value only if the new match is strictly better
        // ( in terms of percent payed per word )
        // then the actual one
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

    /**
     * Get matches from MyMemory and other engines
     *
     * @param $elementQueue
     *
     * @return array
     * @throws Exception
     */
    protected function _getMatches( $elementQueue ){

        $_config              = array();
        $_config[ 'segment' ] = $elementQueue[ 'segment' ];
        $_config[ 'source' ]  = $elementQueue[ 'source' ];
        $_config[ 'target' ]  = $elementQueue[ 'target' ];
        $_config[ 'email' ]   = \INIT::$MYMEMORY_TM_API_KEY;

        $tm_keys = \TmKeyManagement_TmKeyManagement::getJobTmKeys( $elementQueue[ 'tm_keys' ], 'r', 'tm' );
        if ( is_array( $tm_keys ) && !empty( $tm_keys ) ) {
            foreach ( $tm_keys as $tm_key ) {
                $_config[ 'id_user' ][ ] = $tm_key->key;
            }
        }

        $_config[ 'num_result' ] = 3;

        $id_mt_engine = $elementQueue[ 'id_mt_engine' ];
        $id_tms       = $elementQueue[ 'id_tms' ];

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
             * @var $tms \Engines_MyMemory
             */
            $tms        = \Engine::getInstance( $_TMS );
            $tms->doLog = false;

            $config = $tms->getConfigStruct();
            $config = array_merge( $config, $_config );

            $tms_match = $tms->get( $config );

            /**
             * If No results found. Re-Queue
             *
             * MyMemory can return null if an error occurs (e.g http response code is 404, 410, 500, 503, etc.. )
             */
            if ( $tms_match === null ) {
                throw new \Exception( "--- (child " . $this->_tHandlerPID . ") : Error from MyMemory. NULL received." );
            }

            $tms_match = $tms_match->get_matches_as_array();

        }

        /**
         * Call External MT engine if it is a custom one ( mt not requested from MyMemory )
         */
        if ( $id_mt_engine > 1 /* Request MT Directly */ ) {

            try {
                $mt     = \Engine::getInstance( $id_mt_engine );
                $config = $mt->getConfigStruct();
                $config = array_merge( $config, $_config );

                $mt_result = $mt->get( $config );

                if ( isset( $mt_result[ 'error' ][ 'code' ] ) ) {
                    $mt_result = false;
                }
            } catch ( \Exception $e ){
                self::_TimeStampMsg( $e->getMessage() );
            }

        }

        $matches = array();
        if ( !empty( $tms_match ) ) {
            $matches = $tms_match;
        }

        if ( isset( $mt_result ) && !empty( $mt_result ) ) {
            $matches[ ] = $mt_result;
            usort( $matches, "TMThread::_compareScore" );
        }

        /**
         * If No results found. Re-Queue
         */
        if ( empty( $matches ) || !is_array( $matches ) ) {
            throw new \Exception( "--- (child " . $this->_tHandlerPID . ") : No contribution found : Try again later." );
        }

        return $matches;

    }

    /**
     *  Only if this is not a MT and if it is a ( 90 =< MATCH < 100 ) try to realign tag IDs
     *
     * @param array $elementQueue
     *
     */
    protected function _tryRealignTagID( $elementQueue ){

        //use the first match record
        // ---> $this->_matches[ 0 ];

        ( isset( $this->_matches[ 0 ][ 'match' ] ) ? $firstMatchVal = floatval( $this->_matches[ 0 ][ 'match' ] ) : null );
        if ( isset( $firstMatchVal ) && $firstMatchVal >= 90 && $firstMatchVal < 100 ) {

            $srcSearch    = strip_tags( $elementQueue[ 'segment' ] );
            $segmentFound = strip_tags( $this->_matches[ 0 ][ 'raw_segment' ] );
            $srcSearch    = mb_strtolower( preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $srcSearch ) );
            $segmentFound = mb_strtolower( preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $segmentFound ) );

            $fuzzy = @levenshtein( $srcSearch, $segmentFound ) / log10( mb_strlen( $srcSearch . $segmentFound ) + 1 );

            //levenshtein handle max 255 chars per string and returns -1, so fuzzy var can be less than 0 !!
            if ( $srcSearch == $segmentFound || ( $fuzzy < 2.5 && $fuzzy > 0 ) ) {

                $qaRealign = new \QA( $elementQueue[ 'segment' ], html_entity_decode( $this->_matches[ 0 ][ 'raw_translation' ] ) );
                $qaRealign->tryRealignTagID();

                $log_prepend = uniqid( '', true ) . " - SERVER REALIGN IDS PROCEDURE | ";
                if ( !$qaRealign->thereAreErrors() ) {

                    /*
                        self::_TimeStampMsg( $log_prepend . " - Requested Segment: " . var_export( $elementQueue, true ) );
                        self::_TimeStampMsg( $log_prepend . "Fuzzy: " . $fuzzy . " - Try to Execute Tag ID Realignment." );
                        self::_TimeStampMsg( $log_prepend . "TMS RAW RESULT:" );
                        self::_TimeStampMsg( $log_prepend . var_export( $this->_matches[ 0 ]e, true ) );
                        self::_TimeStampMsg( $log_prepend . "Realignment Success:" );
                    */
                    $this->_matches[ 0 ][ 'raw_translation' ] = $qaRealign->getTrgNormalized();
                    $this->_matches[ 0 ][ 'match' ]           = ( $fuzzy == 0 ? '100%' : '99%' );

                } else {
                    self::_TimeStampMsg( $log_prepend . 'Realignment Failed. Skip. Segment: ' . $elementQueue[ 'id_segment' ] );
                }

            }

        }

    }

    /**
     * Compare match scores between TM records and MT records when they are extern to MyMemory
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
     * @param array $objQueue
     *
     * @throws Exception
     */
    protected function _checkWordCount( Array $objQueue ){

        if ( $objQueue[ 'raw_word_count' ] == 0 ) {
//            self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") : empty segment. acknowledge and continue" );
//            SET as DONE and "decrement counter/close project"
            $this->_queueHandler->forceSetSegmentAnalyzed( $objQueue, $this->_mySubscribedQueue );
            throw new Exception( "--- (child " . $this->_tHandlerPID . ") : empty segment. acknowledge and continue" );
        }

    }

    /**
     * Read frame msg from the queue
     *
     * @return mixed|null
     * @throws \Exception
     */
    protected function _readAMQFrame() {

        $msg = null;
        try {

            $msg = $this->_queueHandler->readFrame();

            if ( $msg instanceof \StompFrame && ( $msg->command == "MESSAGE" || array_key_exists( 'MESSAGE', $msg->headers /* Stomp Client bug... hack */ ) ) ) {

                $this->_frameID++;
                self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") : processing frame {$this->_frameID}" );

                $elementQueue = json_decode( $msg->body, true );
                //empty message what to do?? it should not be there, acknowledge and process the next one
                if ( empty( $elementQueue[ 'pid' ] ) ) {

                    \Utils::raiseJsonExceptionError();
                    $this->_queueHandler->ack( $msg );
                    sleep( 2 );
                    throw new \Exception( "--- (child " . $this->_tHandlerPID . ") : found frame but no valid segment found for tm volume analysis: wait 2 seconds" );

                }

            } else {
                throw new \Exception( "--- (child " . $this->_tHandlerPID . ") : no frame found. Starting next cycle." );
            }

        } catch ( \Exception $e ) {
//            self::_TimeStampMsg( $e->getMessage() );
//            self::_TimeStampMsg( $e->getTraceAsString() );
            throw new \Exception( "*** \$this->amqHandler->readFrame() Failed. Continue Execution. ***" );
            /* jump the ack */
        }

        return array( $msg, $elementQueue );

    }

    protected function _checkForReQueueEnd( $enqueuedMsg ){

        /**
         *
         * check for loop re-queuing
         */
        if ( isset( $enqueuedMsg[ 'reQueueNum' ] ) && $enqueuedMsg[ 'reQueueNum' ] >= 100 ) {
            $this->_queueHandler->forceSetSegmentAnalyzed( $enqueuedMsg, $this->_mySubscribedQueue );
            throw new \Exception( "--- (child " . $this->_tHandlerPID . ") :  Frame Re-queue max value reached, acknowledge and skip." );
        } elseif ( isset( $enqueuedMsg[ 'reQueueNum' ] ) ) {
            self::_TimeStampMsg( "--- (child " . $this->_tHandlerPID . ") :  Frame re-queued {$enqueuedMsg[ 'reQueueNum' ]} times." );
        }

    }

    public static function cleanShutDown() {

        \Database::obtain()->close();
        static::$__INSTANCE->_queueHandler->getRedisClient()->disconnect();
        static::$__INSTANCE->_queueHandler->disconnect();

        //SHUTDOWN
        $msg = str_pad( " CHILD " . getmypid() . " HALTED ", 50, "-", STR_PAD_BOTH );
        self::_TimeStampMsg( $msg );

        die();

    }

    /**
     * @param $parent_pid
     *
     * @return bool
     */
    protected function _isMyParentRunning( $parent_pid ) {

        $redis_parent = $this->_queueHandler->getRedisClient()->get( RedisKeys::VOLUME_ANALYSIS_PID );
        if( !(bool)$redis_parent || $redis_parent != $parent_pid ) return false;
        return true;

    }

    /**
     * @param $pid
     *
     * @return int
     */
    protected function _myProcessExists( $pid ) {

        return $this->_queueHandler->getRedisClient()->sismember( $this->_mySubscribedQueue->pid_set_name, $pid );

    }

}

//$argv = array();
//$argv[ 1 ] = '{"redis_key":"p3_list","queue_name":"analysis_queue_P3","pid_set_name":"ch_pid_set_p3","pid_list":[],"pid_list_len":1,"queue_length":0,"pid_set_perc_break":10}';

//TMThread::getInstance( QueuesList::get()->list[0] )->main();
TMThread::getInstance( Info::build( json_decode( $argv[ 1 ], true ) ) )->main();