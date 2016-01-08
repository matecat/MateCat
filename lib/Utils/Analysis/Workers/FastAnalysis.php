<?php
namespace Analysis\Workers;

use \TaskRunner\Commons\AbstractDaemon,
    \TaskRunner\Commons\QueueElement;

use \Analysis\Queue\RedisKeys,
    \Analysis\Queue\QueueInfo,
    \Analysis\Queue\QueuesList;

use \AMQHandler,
    \Constants_ProjectStatus as ProjectStatus,
    \Exception,
    \Analysis_PayableRates as PayableRates,
    \WordCount_Counter,
    \Engine,
    \Database,
    \CatUtils,
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

    protected $_configFile;

    const ERR_NO_SEGMENTS = 127;
    const ERR_TOO_LARGE   = 128;


    /**
     * @var QueuesList
     */
    protected $_queueContextList = array();

    /**
     * Reload Configuration every cycle
     *
     */
    protected function _updateConfiguration() {

        $config = @parse_ini_file( $this->_configFile, true );
        Utils::raiseJsonExceptionError();
        if( empty( $this->_configFile ) || !isset( $config[ 'context_definitions' ] ) || empty( $config[ 'context_definitions' ] ) ){
            throw new Exception( 'Wrong configuration file provided.' );
        }

        //First Execution, load build object
        $this->_queueContextList = QueuesList::get( $config[ 'context_definitions' ] );

    }

    protected function __construct( $configFile = null ) {

        parent::__construct();

        $this->_configFile = $configFile;

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

                $pid = $project_row[ 'id' ];
                self::_TimeStampMsg( "Analyzing $pid, querying data..." );

                $perform_Tms_Analysis = true;
                $status               = ProjectStatus::STATUS_FAST_OK;
                if ( $project_row[ 'id_tms' ] == 0 && $project_row[ 'id_mt_engine' ] == 0 ) {

                    /**
                     * MyMemory disabled and MT Disabled Too
                     * So don't perform TMS Analysis ( don't send segments in queue ), only fill segment_translation table
                     */
                    $perform_Tms_Analysis = false;
                    $status               = ProjectStatus::STATUS_DONE;
                    self::_TimeStampMsg( 'Perform Analysis ' . var_export( $perform_Tms_Analysis, true ) );
                }

                try {
                    $fastReport = self::_fetchMyMemoryFast( $pid );
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

                self::_TimeStampMsg( "Clean old memory cycle" );
                $this->segments = null;
                self::_TimeStampMsg( "Done" );

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

                    list( $sid, ) = explode( "-", $k );
                    $fastResultData[ $k ][ 'id_segment' ]       = $sid;
                    $fastResultData[ $k ][ 'segment_hash' ]     = $this->segment_hashes[ $sid ][ 0 ];
                    $fastResultData[ $k ][ 'segment' ]          = $this->segment_hashes[ $sid ][ 1 ];
                    $fastResultData[ $k ][ 'raw_word_count' ]   = $this->segment_hashes[ $sid ][ 2 ];
                    $fastResultData[ $k ][ 'source' ]           = $this->segment_hashes[ $sid ][ 3 ];
                    $fastResultData[ $k ][ 'target' ]           = $this->segment_hashes[ $sid ][ 4 ];  //now target holds more than one language ex: ( 80415:fr-FR,80416:it-IT )
                    $fastResultData[ $k ][ 'payable_rates' ]    = $this->segment_hashes[ $sid ][ 5 ];
                    $fastResultData[ $k ][ 'pretranslate_100' ] = $project_row[ 'pretranslate_100' ];
                    $fastResultData[ $k ][ 'tm_keys' ]          = $project_row[ 'tm_keys' ];
                    $fastResultData[ $k ][ 'id_tms' ]           = $project_row[ 'id_tms' ];
                    $fastResultData[ $k ][ 'id_mt_engine' ]     = $project_row[ 'id_mt_engine' ];
                    $fastResultData[ $k ][ 'match_type' ]       = mb_strtoupper( $fastResultData[ $k ][ 'type' ] );
                    unset( $fastResultData[ $k ][ 'type' ] );

                }

                unset( $segment_hashes );

                // INSERT DATA
                self::_TimeStampMsg( "Inserting segments..." );

                try {
                    $insertReportRes = $this->_insertFastAnalysis( $pid, $fastResultData, PayableRates::$DEFAULT_PAYABLE_RATES, $perform_Tms_Analysis );
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

                unset( $fastResultData );

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

        $this->segments = self::_getSegmentsForFastVolumeAnalysis( $pid );

        if ( count( $this->segments ) == 0 ) {
            //there is no analysis on that file, it is ALL Pre-Translated
            $exceptionMsg = 'There is no analysis on that file, it is ALL Pre-Translated';
            self::_TimeStampMsg( $exceptionMsg );
            throw new Exception( $exceptionMsg, self::ERR_NO_SEGMENTS );
        }

        if( count( $this->segments ) > 200000 ){
            throw new Exception( "Project too large. Skip.", self::ERR_TOO_LARGE );
        }

        //compose a lookup array
        $this->segment_hashes = array();

        foreach ( $this->segments as $pos => $segment ) {

            $this->segment_hashes[ $segment[ 'id' ] ] = array(
                    $segment[ 'segment_hash' ],
                    $segment[ 'segment' ],
                    $segment[ 'raw_word_count' ],
                    $segment[ 'source' ],
                    $segment[ 'target' ], //now target holds more than one language ex: ( 80415:fr-FR,80416:it-IT )
                    $segment[ 'payable_rates' ]
            );

            $segments[ $pos ][ 'segment' ] = CatUtils::clean_raw_string4fast_word_count( $segment[ 'segment' ], $this->segments[ 0 ][ 'source' ] );

            //unset because we don't want to pass these keys to Fast Analysis
            unset( $segments[ $pos ][ 'id' ] );
            unset( $segments[ $pos ][ 'segment_hash' ] );
            unset( $segment[ 'segment' ] );
            unset( $segment[ 'raw_word_count' ] );
            unset( $segment[ 'target' ] );
            unset( $segment[ 'payable_rates' ] );

        }

        self::_TimeStampMsg( "Done." );

        self::_TimeStampMsg( "Pid $pid: " . count( $this->segments ) . " segments" );
        self::_TimeStampMsg( "Sending query to MyMemory analysis..." );

        $myMemory->doLog = true; //tell to the engine to not log the output
        return $myMemory->fastAnalysis( $this->segments );

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

    protected function _insertFastAnalysis( $pid, &$fastResultData, $equivalentWordMapping, $perform_Tms_Analysis = true ) {

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

        foreach ( $fastResultData as $k => $v ) {

            $jid_pass = explode( "-", $k );

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
                $data[ 'id_segment' ]   = (int)$fastResultData[ $k ][ 'id_segment' ];
                $data[ 'segment_hash' ] = $db->escape( $v[ 'segment_hash' ] );
                $data[ 'match_type' ]   = $db->escape( $v[ 'match_type' ] );

                $data[ 'eq_word_count' ]       = (float)$eq_word;
                $data[ 'standard_word_count' ] = (float)$standard_words;

                $st_values[] = " ( '" . implode( "', '", array_values( $data ) ) . "' )";

                //WE TRUST ON THE FAST ANALYSIS RESULTS FOR THE WORD COUNT
                //here we are pruning the segments that must not be sent to the engines for the TM analysis
                //because we multiply the word_count with the equivalentWordMapping ( and this can be 0 for some values )
                //we must check if the value of $fastReport[ $k ]['wc'] and not $data[ 'eq_word_count' ]
                if ( $fastResultData[ $k ][ 'wc' ] > 0 && $perform_Tms_Analysis ) {

                    /**
                     *
                     * IMPORTANT
                     * id_job will be taken from languages ( 80415:fr-FR,80416:it-IT )
                     */
                    $fastResultData[ $k ][ 'pid' ]                 = (int)$pid;
                    $fastResultData[ $k ][ 'date_insert' ]         = date_create()->format( 'Y-m-d H:i:s' );
                    $fastResultData[ $k ][ 'eq_word_count' ]       = (float)$eq_word;
                    $fastResultData[ $k ][ 'standard_word_count' ] = (float)$standard_words;

                } else {
//                Log::doLog( 'Skipped Fast Segment: ' . var_export( $fastReport[ $k ], true ) );
                    // this segment must not be sent to the TM analysis queue
                    unset( $fastResultData[ $k ] );
                }

            }

            //anyway this key must be removed because he is no more needed and we want not to send it to the queue
            unset( $fastResultData[ $k ][ 'wc' ] );

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

        unset( $st_values );
        unset( $chunks_st );

        /*
         * IF NO TM ANALYSIS, upload the jobs global word count
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


        $totalSegmentsToAnalyze = count( $fastResultData );

        /*
         *  $fastResultData[0]['id_mt_engine'] is the index of the MT engine we must use,
         *  i take the value from the first element of the list ( the last one is the same for the project )
         *  because surely this value are equal for all the record of the project
         */
        $first_element = reset( $fastResultData );
        $queueInfo     = $this->_getQueueAddressesByPriority( $totalSegmentsToAnalyze, $first_element[ 'id_mt_engine' ] );

        if ( $totalSegmentsToAnalyze ) {

            self::_TimeStampMsg( "Publish Segment Translations to the queue --> {$queueInfo->queue_name}: " . count( $fastResultData ) );
            self::_TimeStampMsg( 'Elements: ' . count( $fastResultData ) );

            try {
                $this->_setTotal( array( 'pid' => $pid, 'queueInfo' => $queueInfo ) );
            } catch ( Exception $e ) {
                Utils::sendErrMailReport( $e->getMessage() . "" . $e->getTraceAsString(), "Fast Analysis set Total values failed." );
                self::_TimeStampMsg( $e->getMessage() . "" . $e->getTraceAsString() );
                throw $e;
            }

            $time_start = microtime( true );
            foreach ( $fastResultData as $k => $queue_element ) {

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

                        Utils::raiseJsonExceptionError();
                        self::$queueHandler->send( $queueInfo->queue_name, $element, array( 'persistent' => self::$queueHandler->persistent ) );
                        self::_TimeStampMsg( "AMQ Set Executed " . ( $k + 1 ) );

                    }

                } catch ( Exception $e ) {
                    Utils::sendErrMailReport( $e->getMessage() . "" . $e->getTraceAsString(), "Fast Analysis set queue failed." );
                    self::_TimeStampMsg( $e->getMessage() . "" . $e->getTraceAsString() );
                    throw $e;
                }

            }

            self::_TimeStampMsg( 'Done in ' . ( microtime( true ) - $time_start ) . " seconds." );
            unset( $fastResultData );

        }

        return $db->affected_rows;
    }

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

            return $e->getCode() * -1;
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
     *    'queueInfo' => @var QueueInfo
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
     * @return QueueInfo
     */
    protected function _getQueueAddressesByPriority( $queueLen, $id_mt_engine ){

        $mtEngine  = Engine::getInstance( $id_mt_engine );

        //anyway take the defaults
        $queueList = $this->_queueContextList->list;
        $queueName = $queueList[ 'P1' ];

        //use this kind of construct to easy add/remove queues and to disable feature by: comment rows or change the switch flag to false
        switch ( true ) {
            case ( ! $mtEngine instanceof \Engines_MyMemory ):
                $queueName = $queueList[ 'P3' ];
                break;
            case ( $queueLen >= 50000 ):
                $queueName = $queueList[ 'P3' ];
                break;
            case ( $queueLen >= 10000 ): // at rate of 100 segments/s ( 100 processes ) ~ 2m 30s
                $queueName = $queueList[ 'P2' ];
                break;
            default:
                $queueName = $queueList[ 'P1' ];
                break;

        }

        return $queueName;

    }

}
