<?php
namespace Analysis\Workers;

use \TaskRunner\Commons\AbstractDaemon,
    \TaskRunner\Commons\QueueElement,
    TaskRunner\Commons\Context,
    TaskRunner\Commons\ContextList;

use \Analysis\Queue\RedisKeys;

use \AMQHandler,
    \Constants_ProjectStatus as ProjectStatus,
    \Exception,
    \Analysis_PayableRates as PayableRates,
    \WordCount_Counter,
    \Engine,
    \Database,
    \Utils,
    \PDOException,
    \Log;


include_once \INIT::$MODEL_ROOT . '/queries.php';

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/12/15
 * Time: 13.05
 *
 */
class FastAnalysis extends AbstractDaemon {

    protected static $queueHandler;

    protected $segments;
    protected $segment_hashes;
    protected $actual_project_row;

    protected $_configFile;

    const ERR_NO_SEGMENTS = 127;
    const ERR_TOO_LARGE   = 128;


    /**
     * @var ContextList
     */
    protected $_queueContextList = array();

    /**
     * Reload Configuration every cycle
     *
     */
    protected function _updateConfiguration() {

        $config = @parse_ini_file( $this->_configFile, true );

        if( empty( $this->_configFile ) || !isset( $config[ 'context_definitions' ] ) || empty( $config[ 'context_definitions' ] ) ){
            throw new Exception( 'Wrong configuration file provided.' );
        }

        //First Execution, load build object
        $this->_queueContextList = ContextList::get( $config[ 'context_definitions' ] );

    }

    protected function __construct( $configFile = null ) {

        parent::__construct();

        $this->_configFile = $configFile;
        Log::resetLogger();
        Log::$fileName = 'fastAnalysis.log';

        try {
            self::$queueHandler = new AMQHandler();
            self::$queueHandler->getRedisClient()->rpush( RedisKeys::FAST_PID_LIST, self::$tHandlerPID );

            $this->_updateConfiguration();

        } catch ( Exception $ex ) {

            self::_TimeStampMsg( str_pad( " " . $ex->getMessage() . " ", 60, "*", STR_PAD_BOTH ) );
            self::_TimeStampMsg( str_pad( "EXIT", 60, " ", STR_PAD_BOTH ) );
            die();
        }

    }

    /**
     * @param null $args
     *
     * @return void
     */
    public function main( $args = null ) {

        do {

            $projects_list = getProjectForVolumeAnalysis( 'fast', 5 );
            if ( empty( $projects_list ) ) {
                self::_TimeStampMsg( "No projects: wait 3 seconds." );
                sleep( 3 );
                continue;
            }

            self::_TimeStampMsg( "Projects found: " . var_export( $projects_list ) . "." );

            foreach ( $projects_list as $project_row ) {

                $this->actual_project_row = $project_row;

                $pid = $this->actual_project_row[ 'id' ];
                self::_TimeStampMsg( "Analyzing $pid, querying data..." );

                $perform_Tms_Analysis = true;
                $status               = ProjectStatus::STATUS_FAST_OK;

                // disable TM analysis

                $disable_Tms_Analysis = $this->actual_project_row[ 'id_tms' ] == 0 && $this->actual_project_row[ 'id_mt_engine' ] == 0 ;
                
                if ( $disable_Tms_Analysis ) {

                    /**
                     * MyMemory disabled and MT Disabled Too
                     * So don't perform TMS Analysis ( don't send segments in queue ), only fill segment_translation table
                     */
                    $perform_Tms_Analysis = false;
                    $status               = ProjectStatus::STATUS_DONE;
                    self::_TimeStampMsg( 'Perform Analysis ' . var_export( $perform_Tms_Analysis, true ) );
                }

                try {
                    $fastReport = $this->_fetchMyMemoryFast( $pid );
                    self::_TimeStampMsg( "Fast $pid result: " . count( $fastReport->responseData ) . " segments." );
                } catch ( Exception $e ) {
                    if( $e->getCode() == self::ERR_TOO_LARGE ){
                        self::_updateProject( $pid, ProjectStatus::STATUS_NOT_TO_ANALYZE );
                        //next project
                        continue;
                    } else {
                        $status = ProjectStatus::STATUS_DONE;
                    }
                }

                if ( $fastReport->responseStatus == 200 ) {
                    $fastResultData = $fastReport->responseData;
                } else {
                    self::_TimeStampMsg( "Pid $pid failed fast analysis." );
                    $fastResultData = array();
                }

                unset( $fastReport );

                foreach ( $fastResultData as $k => $v ) {

                    if ( in_array( $v[ 'type' ], array( "50%-74%" ) ) ) {
                        $fastResultData[ $k ][ 'type' ] = "NO_MATCH";
                    }

                    $this->segments[ $this->segment_hashes[ $k ] ][ 'wc' ]         = $fastResultData[ $k ][ 'wc' ];
                    $this->segments[ $this->segment_hashes[ $k ] ][ 'match_type' ] = strtoupper( $fastResultData[ $k ][ 'type' ] );

                }
                //clean the reverse lookup array
                $this->segment_hashes = null;

                // INSERT DATA
                self::_TimeStampMsg( "Inserting segments..." );

                try {
                    $insertReportRes = $this->_insertFastAnalysis( $pid, PayableRates::$DEFAULT_PAYABLE_RATES, $perform_Tms_Analysis );
                } catch ( Exception $e ) {
                    //Logging done and email sent
                    //set to error
                    $insertReportRes = -1;
                }

                if ( $insertReportRes < 0 ) {
                    self::_TimeStampMsg( "InsertFastAnalysis failed...." );
                    self::_TimeStampMsg( "Try next cycle...." );
                    continue;
                }

                self::_TimeStampMsg( "done" );
                // INSERT DATA

                self::_updateProject( $pid, $status );

            }

        } while ( $this->RUNNING );

        self::cleanShutDown();

    }

    /**
     * @param $pid
     *
     * @return \Engines_Results_MyMemory_AnalyzeResponse
     * @throws Exception
     */
    protected function _fetchMyMemoryFast( $pid ) {

        /**
         * @var $myMemory \Engines_MyMemory
         */
        $myMemory = Engine::getInstance( 1 /* MyMemory */ );

        try {
            $this->segments = self::_getSegmentsForFastVolumeAnalysis( $pid );
        } catch( PDOException $e ) {
            throw new Exception( "Error Fetching data for Project. Too large. Skip.", self::ERR_TOO_LARGE );
        }

        if ( count( $this->segments ) == 0 ) {
            //there is no analysis on that file, it is ALL Pre-Translated
            $exceptionMsg = 'There is no analysis on that file, it is ALL Pre-Translated';
            self::_TimeStampMsg( $exceptionMsg );
            throw new Exception( $exceptionMsg, self::ERR_NO_SEGMENTS );
        }

        //TODO Remove when MyMemory FastAnalysis will be rewritten
        if( count( $this->segments ) > 120000 ){
            throw new Exception( "Project too large. Skip.", self::ERR_TOO_LARGE );
        }

        //compose a lookup array
        $this->segment_hashes = array();

        $fastSegmentsRequest = array();
        foreach ( $this->segments as $pos => $segment ) {

            $fastSegmentsRequest[ $pos ][ 'jsid' ]         = $segment[ 'jsid' ];
            $fastSegmentsRequest[ $pos ][ 'segment' ]      = $segment[ 'segment' ];
            $fastSegmentsRequest[ $pos ][ 'segment_hash' ] = $segment[ 'segment_hash' ];
            $fastSegmentsRequest[ $pos ][ 'source' ]       = $segment[ 'source' ];

            //set a reverse lookup array to get the right segment is by its position
            $this->segment_hashes[ $segment[ 'jsid' ] ] = $pos;

        }

        self::_TimeStampMsg( "Done." );

        self::_TimeStampMsg( "Pid $pid: " . count( $this->segments ) . " segments" );
        self::_TimeStampMsg( "Sending query to MyMemory analysis..." );

        $myMemory->doLog = true; //tell to the engine to not log the output

        /**
         * @var $result \Engines_Results_MyMemory_AnalyzeResponse
         */
        $result = $myMemory->fastAnalysis( $fastSegmentsRequest );
        if( isset( $result->error->code ) && $result->error->code == -28 ){ //curl timed out
            throw new Exception( "MyMemory Fast Analysis Failed. {$result->error->message}", self::ERR_TOO_LARGE );
        }
        return $result;

    }

    public static function sigSwitch( $sig_no ) {

        switch ( $sig_no ) {
            case SIGTERM :
            case SIGHUP :
            case SIGINT :
                $run = static::getInstance();
                $run->RUNNING = false;
                break;
            default :
                $msg = str_pad( " FAST ANALYSIS " . getmypid() . " Received Signal $sig_no ", 50, "-", STR_PAD_BOTH );
                self::_TimeStampMsg( $msg );
                break;
        }

        $msg = str_pad( " FAST ANALYSIS " . getmypid() . " Caught Signal $sig_no ", 50, "-", STR_PAD_BOTH );
        self::_TimeStampMsg( $msg );

    }

    public static function cleanShutDown() {

        $run = static::getInstance();
        $run->RUNNING = false;
        self::$tHandlerPID = null;

        //SHUTDOWN
        self::$queueHandler->getRedisClient()->lrem( RedisKeys::FAST_PID_LIST, 0, self::$tHandlerPID );

        $msg = str_pad( " FAST ANALYSIS " . self::$tHandlerPID . " HALTED GRACEFULLY ", 50, "-", STR_PAD_BOTH );
        self::_TimeStampMsg( $msg );

        self::$queueHandler->getRedisClient()->disconnect();

        self::$queueHandler->disconnect();
        self::$queueHandler = null;

    }

    protected function _updateProject( $pid, $status ) {

        self::_TimeStampMsg( "*** Project $pid: Changing status..." );

        changeProjectStatus( $pid, $status );

        self::_TimeStampMsg( "*** Project $pid: $status" );

    }

    protected function _insertFastAnalysis( $pid, $equivalentWordMapping, $perform_Tms_Analysis = true ) {

        $db   = Database::obtain();
        $data = array();

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

        foreach ( $this->segments as $k => $v ) {

            $jid_pass = explode( "-", $v[ 'jsid' ] );

            // only to remember the meaning of $k
            // EX: 21529088-42593:b433193493c6,42594:b4331aacf3d4
            //$id_segment = $jid_fid[ 0 ];

            $list_id_jobs_password = $jid_pass[ 1 ];

            if ( array_key_exists( $v[ 'match_type' ], $equivalentWordMapping ) ) {
                $eq_word = ( $v[ 'wc' ] * $equivalentWordMapping[ $v[ 'match_type' ] ] / 100 );
            } else {
                $eq_word = $v[ 'wc' ];
            }

            $standard_words = $eq_word;
            if ( $v[ 'match_type' ] == "INTERNAL" or $v[ 'match_type' ] == "MT" ) {
                $standard_words = $v[ 'wc' ] * $equivalentWordMapping[ "NO_MATCH" ] / 100;
            }

            $total_eq_wc += $eq_word;
            $total_standard_wc += $standard_words;

            $list_id_jobs_password = explode( ',', $list_id_jobs_password );
            foreach ( $list_id_jobs_password as $id_job ) {

                list( $id_job, $job_pass ) = explode( ":", $id_job );

                $data[ 'id_job' ]       = (int)$id_job;
                $data[ 'id_segment' ]   = (int)$v[ 'id' ];
                $data[ 'segment_hash' ] = $db->escape( $v[ 'segment_hash' ] );
                $data[ 'match_type' ]   = $db->escape( $v[ 'match_type' ] );

                $data[ 'eq_word_count' ]       = (float)$eq_word;
                $data[ 'standard_word_count' ] = (float)$standard_words;

                $st_values[] = " ( '" . implode( "', '", array_values( $data ) ) . "' )";

                //WE TRUST ON THE FAST ANALYSIS RESULTS FOR THE WORD COUNT
                //here we are pruning the segments that must not be sent to the engines for the TM analysis
                //because we multiply the word_count with the equivalentWordMapping ( and this can be 0 for some values )
                //we must check if the value of $fastReport[ $k ]['wc'] and not $data[ 'eq_word_count' ]
                if ( $this->segments[ $k ][ 'wc' ] > 0 && $perform_Tms_Analysis ) {

                    /**
                     *
                     * IMPORTANT
                     * id_job will be taken from languages ( 80415:fr-FR,80416:it-IT )
                     */
                    $this->segments[ $k ][ 'pid' ]                 = (int)$pid;
                    $this->segments[ $k ][ 'date_insert' ]         = date_create()->format( 'Y-m-d H:i:s' );
                    $this->segments[ $k ][ 'eq_word_count' ]       = (float)$eq_word;
                    $this->segments[ $k ][ 'standard_word_count' ] = (float)$standard_words;

                } elseif( $perform_Tms_Analysis ) {

                    Log::doLog( 'Skipped Fast Segment: ' . var_export( $this->segments[ $k ], true ) );
                    // this segment must not be sent to the TM analysis queue
                    unset( $this->segments[ $k ] );

                } else {
                    //In this case the TM analysis is disabled
                    //ALL segments must not be sent to the TM analysis queue
                    //do nothing, but $perform_Tms_Analysis is false, so we want delete all elements after the end of the loop
                }

            }

            //anyway this key must be removed because he is no more needed and we want not to send it to the queue
            unset( $this->segments[ $k ][ 'wc' ] );
            if( !$perform_Tms_Analysis ) unset( $this->segments[ $k ] );

        }

        unset( $data );

        $chunks_st = array_chunk( $st_values, 200 );

        self::_TimeStampMsg( 'Insert Segment Translations: ' . count( $st_values ) );

        self::_TimeStampMsg( 'Queries: ' . count( $chunks_st ) );

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

            try {
                self::_TimeStampMsg( "Executed " . ( $k + 1 ) );
                $db->query( $query_st );
            } catch ( PDOException $e ) {
                self::_TimeStampMsg( $e->getMessage() );

                return $e->getCode() * -1;
            }
        }

        $totalSegmentsToAnalyze = count( $st_values );

        unset( $st_values );
        unset( $chunks_st );

        /*
         * IF NO TM ANALYSIS, update the jobs global word count
         */
        if ( !$perform_Tms_Analysis ) {

            $_details = getProjectSegmentsTranslationSummary( $pid );

            self::_TimeStampMsg( "--- trying to initialize job total word count." );

            $project_details = array_pop( $_details ); //Don't remove, needed to remove rollup row

            foreach ( $_details as $job_info ) {
                $counter = new WordCount_Counter();
                $counter->initializeJobWordCount( $job_info[ 'id_job' ], $job_info[ 'password' ] );
            }

        }
        /* IF NO TM ANALYSIS, upload the jobs global word count */

        //_TimeStampMsg( "Done." );

        $data2 = array( 'fast_analysis_wc' => $total_eq_wc );

        $where = " id = $pid";
        try {
            $db->update( 'projects', $data2, $where );
        } catch ( PDOException $e ) {
            $db->query( 'ROLLBACK' );
            $db->query( 'SET autocommit=1' );
            self::_TimeStampMsg( $e->getMessage() );

            return $e->getCode() * -1;
        }
        $db->query( 'COMMIT' );
        $db->query( 'SET autocommit=1' );


        /*
         *  $fastResultData[0]['id_mt_engine'] is the index of the MT engine we must use,
         *  i take the value from the first element of the list ( the last one is the same for the project )
         *  because surely this value are equal for all the record of the project
         */
        $queueInfo     = $this->_getQueueAddressesByPriority( $totalSegmentsToAnalyze, $this->actual_project_row[ 'id_mt_engine' ] );

        if ( $totalSegmentsToAnalyze ) {

            self::_TimeStampMsg( "Publish Segment Translations to the queue --> {$queueInfo->queue_name}: $totalSegmentsToAnalyze" );
            self::_TimeStampMsg( "Elements: $totalSegmentsToAnalyze" );

            try {
                $this->_setTotal( array( 'pid' => $pid, 'queueInfo' => $queueInfo ) );
            } catch ( Exception $e ) {
                Utils::sendErrMailReport( $e->getMessage() . "" . $e->getTraceAsString(), "Fast Analysis set Total values failed." );
                self::_TimeStampMsg( $e->getMessage() . "" . $e->getTraceAsString() );
                throw $e;
            }

            $time_start = microtime( true );
            foreach ( $this->segments as $k => $queue_element ) {

                $queue_element[ 'id_segment' ]       = $queue_element[ 'id' ];
                $queue_element[ 'pretranslate_100' ] = $this->actual_project_row[ 'pretranslate_100' ];
                $queue_element[ 'tm_keys' ]          = $this->actual_project_row[ 'tm_keys' ];
                $queue_element[ 'id_tms' ]           = $this->actual_project_row[ 'id_tms' ];
                $queue_element[ 'id_mt_engine' ]     = $this->actual_project_row[ 'id_mt_engine' ];

                /**
                 * remove some unuseful fields
                 */
                unset( $queue_element[ 'id' ] );
                unset( $queue_element[ 'jsid' ] );

                try {

                    $languages_job = explode( ",", $queue_element[ 'target' ] );  //now target holds more than one language ex: ( 80415:fr-FR,80416:it-IT )
                    //in memory replacement avoid duplication of the segment list
                    //send in queue every element * number of languages
                    foreach ( $languages_job as $_language ) {

                        list( $id_job, $language ) = explode( ":", $_language );

                        $queue_element[ 'target' ] = $language;
                        $queue_element[ 'id_job' ] = $id_job;

                        $element = new QueueElement();
                        $element->params = $queue_element;
                        $element->classLoad = '\Analysis\Workers\TMAnalysisWorker';

                        self::$queueHandler->send( $queueInfo->queue_name, $element, array( 'persistent' => self::$queueHandler->persistent ) );
                        self::_TimeStampMsg( "AMQ Set Executed " . ( $k + 1 ) . " Language: $language" );

                    }

                } catch ( Exception $e ) {
                    Utils::sendErrMailReport( $e->getMessage() . "" . $e->getTraceAsString(), "Fast Analysis set queue failed." );
                    self::_TimeStampMsg( $e->getMessage() . "" . $e->getTraceAsString() );
                    throw $e;
                }

            }

            self::_TimeStampMsg( 'Done in ' . ( microtime( true ) - $time_start ) . " seconds." );

        }

        return $db->affected_rows;
    }

    /**
     * @param $pid
     *
     * @return array
     * @throws PDOException
     */
    protected static function _getSegmentsForFastVolumeAnalysis( $pid ) {

        //with this query we decide what segments
        //must be inserted in segment_translations table

        //we want segments that we decided to show in cattool
        //and segments that are NOT locked ( already translated )

        $query = "select concat( s.id, '-', group_concat( distinct concat( j.id, ':' , j.password ) ) ) as jsid, s.segment, j.source, s.segment_hash, s.id as id,

		s.raw_word_count,
		group_concat( distinct concat( j.id, ':' , j.target ) ) as target,
		j.payable_rates

		from segments as s
		inner join files_job as fj on fj.id_file=s.id_file
		inner join jobs as j on fj.id_job=j.id
		left join segment_translations as st on st.id_segment = s.id
		where j.id_project='$pid'
		and IFNULL( st.locked, 0 ) = 0
		and show_in_cattool != 0
		group by s.id
		order by s.id";
        $db    = Database::obtain();
        try {
            $results = $db->fetch_array( $query );
        } catch ( PDOException $e ) {
            Log::doLog( $e->getMessage() );
            throw $e;
        }

        return $results;
    }

    /**
     * How much segments are in queue before this?
     *
     * <pre>
     *  $config = array(
     *    'total' => null,
     *    'qid' => null,
     *    'queueInfo' => @var Context
     *  )
     * </pre>
     *
     * @param array $config
     *
     * @throws Exception
     */
    protected function _setTotal( $config = array(
            'total'            => null,
            'pid'              => null,
            'queueInfo'        => null
    ) ) {

        if( empty( $this->queueTotalID ) && empty( $config[ 'pid' ] ) ){
            throw new Exception( 'Can Not set a Total without a Queue ID.' );
        }

        if( !empty( $config[ 'total' ] ) ){
            $_total = $config[ 'total' ];
        } else {

            if( empty( $config[ 'queueInfo' ] ) && empty( $this->queueName ) ){
                throw new Exception( 'Need a queue name to get it\'s total or you must provide one' );
            }

            $queueName = ( !empty( $config[ 'queueInfo' ] ) ? $config[ 'queueInfo' ]->queue_name : $this->queueName );
            $_total = self::$queueHandler->getQueueLength( $queueName );

        }

        if( !empty( $config[ 'pid' ] ) ){
            $_pid = $config[ 'pid' ];
        } else {
            $_pid = $this->queueTotalID;
        }

        self::$queueHandler->getRedisClient()->setex( RedisKeys::TOTAL_SEGMENTS_TO_WAIT . $_pid, 60 * 60 * 24 /* 24 hours TTL */, $_total );
        self::$queueHandler->getRedisClient()->rpush( $config[ 'queueInfo' ]->redis_key, $_pid );

    }

    /**
     * Select the right Queue ( and the associated redis Key ) by it's length ( simplest implementation simple )
     *
     * @param $queueLen int
     * @param $id_mt_engine int
     *
     * @return Context
     */
    protected function _getQueueAddressesByPriority( $queueLen, $id_mt_engine ){

        $mtEngine = null;
        try {
            $mtEngine  = Engine::getInstance( $id_mt_engine );
        } catch ( Exception $e ){
            self::_TimeStampMsg( "Caught Exception: " . $e->getMessage() );
        }

        //anyway take the defaults
        $contextList = $this->_queueContextList->list;
        $context = $contextList[ 'P1' ];

        //use this kind of construct to easy add/remove queues and to disable feature by: comment rows or change the switch flag to false
        switch ( true ) {
            case ( $mtEngine === null && $queueLen >= 10000 ): // means NONE as selected Engine
                $context = $contextList[ 'P2' ];
                break;
            case ( $mtEngine === null ): // means NONE as selected Engine
                $context = $contextList[ 'P1' ];
                break;
            case ( ! $mtEngine instanceof \Engines_MyMemory ):
                $context = $contextList[ 'P3' ];
                break;
            case ( $queueLen >= 10000 ): // at rate of 100 segments/s ( 100 processes ) ~ 2m 30s
                $context = $contextList[ 'P2' ];
                break;
            default:
                $context = $contextList[ 'P1' ];
                break;

        }

        return $context;

    }

}
