<?php

namespace Analysis\Workers;

use AMQHandler;
use Analysis\Queue\RedisKeys;
use Analysis_PayableRates as PayableRates;
use Constants_ProjectStatus;
use Constants_ProjectStatus as ProjectStatus;
use Database;
use Engine;
use Exception;
use FeatureSet;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use INIT;
use Log;
use PDO;
use PDOException;
use Projects_MetadataDao;
use Projects_ProjectDao;
use TaskRunner\Commons\AbstractDaemon;
use TaskRunner\Commons\Context;
use TaskRunner\Commons\ContextList;
use TaskRunner\Commons\QueueElement;
use UnexpectedValueException;
use Utils;
use WordCount_CounterModel;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/12/15
 * Time: 13.05
 *
 */
class FastAnalysis extends AbstractDaemon {

    use ProjectWordCount;

    protected static $queueHandler;

    protected $segments;
    protected $segment_hashes;
    protected $actual_project_row;

    protected $_configFile;

    protected $_tHandlerPID;
    protected $_executor_instance_id;

    /**
     * @var AbstractFilesStorage
     */
    protected $files_storage;

    const ERR_NO_SEGMENTS    = 127;
    const ERR_TOO_LARGE      = 128;
    const ERR_500            = 129;
    const ERR_EMPTY_RESPONSE = 130;
    const ERR_FILE_NOT_FOUND = 131;

    /**
     * @var ContextList
     */
    protected $_queueContextList = [];

    /**
     * Reload Configuration every cycle
     *
     */
    protected function _updateConfiguration() {

        $config = @parse_ini_file( $this->_configFile, true );

        if ( empty( $this->_configFile ) || !isset( $config[ 'context_definitions' ] ) || empty( $config[ 'context_definitions' ] ) ) {
            throw new Exception( 'Wrong configuration file provided.' );
        }

        //First Execution, load build object
        $this->_queueContextList = ContextList::get( $config[ 'context_definitions' ] );

    }

    protected function _checkDatabaseConnection() {

        $db = Database::obtain();
        try {
            $db->ping();
//            self::_TimeStampMsg(  "--- Database connection active. " );
        } catch ( PDOException $e ) {
            self::_TimeStampMsg( $e->getMessage() . " - Trying to close and reconnect." );
            $db->close();
            //reconnect
            $db->getConnection();
        }

    }

    protected function __construct( $configFile = null ) {

        parent::__construct();

        $this->_configFile = $configFile;
        Log::resetLogger();
        Log::$fileName = 'fastAnalysis.log';

        try {
            self::$queueHandler = new AMQHandler();
            self::$queueHandler->getRedisClient()->sadd( RedisKeys::FAST_PID_SET, self::$tHandlerPID . ":" . gethostname() . ":" . (int)INIT::$INSTANCE_ID );

            $this->_updateConfiguration();

        } catch ( Exception $ex ) {

            self::_TimeStampMsg( str_pad( " " . $ex->getMessage() . " ", 60, "*", STR_PAD_BOTH ) );
            self::_TimeStampMsg( str_pad( "EXIT", 60, " ", STR_PAD_BOTH ) );
            die();
        }

        $this->files_storage = FilesStorageFactory::create();

    }

    /**
     * @param null $args
     *
     * @return void
     * @throws \Predis\Connection\ConnectionException
     */
    public function main( $args = null ) {

        do {

            try {
                $this->_checkDatabaseConnection();
                $projects_list = $this->_getLockProjectForVolumeAnalysis( 5 );
            } catch ( PDOException $e ) {
                self::_TimeStampMsg( $e->getMessage() . " - Error again. Try to reconnect in next cycle." );
                sleep( 3 ); // wait for reconnection
                continue; // next cycle, reload projects.
            }

            if ( empty( $projects_list ) ) {
                self::_TimeStampMsg( "No projects: wait 3 seconds." );
                sleep( 3 );
                continue;
            }

            self::_TimeStampMsg( "Projects found: " . var_export( $projects_list ) . "." );

            $featureSet = new FeatureSet();

            foreach ( $projects_list as $project_row ) {

                $this->actual_project_row = $project_row;

                $pid = $this->actual_project_row[ 'id' ];
                self::_TimeStampMsg( "Analyzing $pid, querying data..." );

                $perform_Tms_Analysis = true;
                $status               = ProjectStatus::STATUS_FAST_OK;

                // disable TM analysis

                $disable_Tms_Analysis = $this->actual_project_row[ 'id_tms' ] == 0 && $this->actual_project_row[ 'id_mt_engine' ] == 0;

                if ( $disable_Tms_Analysis ) {

                    /**
                     * MyMemory disabled and MT Disabled Too
                     * So don't perform TMS Analysis ( don't send segments in queue ), only fill segment_translation table
                     */
                    $perform_Tms_Analysis = false;
                    $status               = ProjectStatus::STATUS_DONE;

                    $featureSet->run( 'fastAnalysisDisabled', $pid );

                    self::_TimeStampMsg( 'Perform Analysis ' . var_export( $perform_Tms_Analysis, true ) );

                }

                try {
                    $fastReport = $this->_fetchMyMemoryFast( $pid );
                    self::_TimeStampMsg( "Fast $pid result: " . count( $fastReport->responseData ) . " segments." );
                } catch ( Exception $e ) {
                    if ( $e->getCode() == self::ERR_TOO_LARGE ) {
                        self::_updateProject( $pid, ProjectStatus::STATUS_NOT_TO_ANALYZE );
                        //next project
                        continue;
                    } elseif ( $e->getCode() == self::ERR_500 ) {
                        self::_updateProject( $pid, ProjectStatus::STATUS_NOT_TO_ANALYZE );
                        //next project
                        continue;
                    } elseif ( $e->getCode() == self::ERR_EMPTY_RESPONSE ) {
                        // NOTE: This exception code is NO MORE used ( keep the code to remember how to reset the status )
                        self::_TimeStampMsg( $e->getMessage() );
                        self::_updateProject( $pid, ProjectStatus::STATUS_NEW );
                        self::$queueHandler->getRedisClient()->del( [ '_fPid:' . $pid ] );
                        sleep( 3 );
                        continue;
                    } else {
                        $status = ProjectStatus::STATUS_DONE;
                    }
                }

                if ( $fastReport->responseStatus == 200 ) {
                    $fastResultData = $fastReport->responseData;
                } else {
                    self::_TimeStampMsg( "Pid $pid failed fast analysis." );
                    $fastResultData = [];
                }

                unset( $fastReport );

                foreach ( $fastResultData as $k => $v ) {

                    if ( $v[ 'type' ] == "50%-74%" ) {
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
                    $insertReportRes = $this->_insertFastAnalysis( $pid, PayableRates::$DEFAULT_PAYABLE_RATES, $featureSet, $perform_Tms_Analysis );
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

                $featureSet->run( 'fastAnalysisComplete', $this->segments, $this->actual_project_row );

                self::_TimeStampMsg( "done" );
                // INSERT DATA

                self::_updateProject( $pid, $status );
                $fs = $this->files_storage;
                $fs::deleteFastAnalysisFile( $pid );

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

        $fs = $this->files_storage;

        try {

            self::_TimeStampMsg( "Fetching data from disk" );
            $this->segments = $fs::getFastAnalysisData( $pid );

        } catch ( UnexpectedValueException $e ) {

            self::_TimeStampMsg( "Error Fetching data from disk. Fallback to database." );

            try {
                $this->segments = self::_getSegmentsForFastVolumeAnalysis( $pid );
            } catch ( PDOException $e ) {
                throw new Exception( "Error Fetching data for Project. Too large. Skip.", self::ERR_TOO_LARGE );
            }

        }

        if ( count( $this->segments ) == 0 ) {
            //there is no analysis on that file, it is ALL Pre-Translated
            $exceptionMsg = 'There is no analysis on that file, it is ALL Pre-Translated';
            self::_TimeStampMsg( $exceptionMsg );
            throw new Exception( $exceptionMsg, self::ERR_NO_SEGMENTS );
        }

        //compose a lookup array
        $this->segment_hashes = [];

        $total_source_words  = 0;
        $fastSegmentsRequest = [];
        foreach ( $this->segments as $pos => $segment ) {

            $fastSegmentsRequest[ $pos ][ 'jsid' ]         = $segment[ 'jsid' ];
            $fastSegmentsRequest[ $pos ][ 'segment' ]      = $segment[ 'segment' ];
            $fastSegmentsRequest[ $pos ][ 'segment_hash' ] = $segment[ 'segment_hash' ];
            $fastSegmentsRequest[ $pos ][ 'source' ]       = $segment[ 'source' ];
            $fastSegmentsRequest[ $pos ][ 'count' ]        = $segment[ 'raw_word_count' ];

            //set a reverse lookup array to get the right segment is by its position
            $this->segment_hashes[ $segment[ 'jsid' ] ] = $pos;

            $total_source_words += $segment[ 'raw_word_count' ];
            if ( $total_source_words > INIT::$MAX_SOURCE_WORDS ) {
                throw new Exception( "Project too large. Skip.", self::ERR_TOO_LARGE );
            }

        }

        self::_TimeStampMsg( "Done." );
        self::_TimeStampMsg( "Pid $pid: " . count( $this->segments ) . " segments" );
        self::_TimeStampMsg( "Sending query to MyMemory analysis..." );

        /**
         * @var $result \Engines_Results_MyMemory_AnalyzeResponse
         */
        $result = $myMemory->fastAnalysis( $fastSegmentsRequest );

        if ( isset( $result->error->code ) && $result->error->code == -28 ) { //curl timed out
            throw new Exception( "MyMemory Fast Analysis Failed. {$result->error->message}", self::ERR_TOO_LARGE );
        } elseif ( $result->responseStatus == 504 ) { //Gateway time out
            throw new Exception( "MyMemory Fast Analysis Failed. {$result->error->message}", self::ERR_TOO_LARGE );
        } elseif ( $result->responseStatus == 500 || $result->responseStatus == 502 ) { // server error, could depend on request
            throw new Exception( "MyMemory Internal Server Error. Pid: " . $pid, self::ERR_500 );
        }

        return $result;

    }

    public static function sigSwitch( $sig_no ) {

        switch ( $sig_no ) {
            case SIGTERM :
            case SIGHUP :
            case SIGINT :
                $run          = static::getInstance();
                $run->RUNNING = false;
                break;
            default :
                $msg = str_pad( " FAST ANALYSIS " . getmypid() . ":" . gethostname() . ":" . INIT::$INSTANCE_ID . " Received Signal $sig_no ", 50, "-", STR_PAD_BOTH );
                self::_TimeStampMsg( $msg );
                break;
        }

        $msg = str_pad( " FAST ANALYSIS " . getmypid() . ":" . gethostname() . ":" . INIT::$INSTANCE_ID . " Caught Signal $sig_no ", 50, "-", STR_PAD_BOTH );
        self::_TimeStampMsg( $msg );

    }

    public static function cleanShutDown() {

        $run               = static::getInstance();
        $run->RUNNING      = false;
        self::$tHandlerPID = null;

        //SHUTDOWN
        self::$queueHandler->getRedisClient()->srem( RedisKeys::FAST_PID_SET, getmypid() . ":" . gethostname() . ":" . (int)INIT::$INSTANCE_ID );

        $msg = str_pad( " FAST ANALYSIS " . getmypid() . ":" . gethostname() . ":" . INIT::$INSTANCE_ID . " HALTED GRACEFULLY ", 50, "-", STR_PAD_BOTH );
        self::_TimeStampMsg( $msg );

        self::$queueHandler->getRedisClient()->disconnect();

        self::$queueHandler->disconnect();
        self::$queueHandler = null;

    }

    protected function _updateProject( $pid, $status ) {

        Database::obtain()->begin();
        $project = Projects_ProjectDao::findById( $pid );
        if ( $project->status_analysis != ProjectStatus::STATUS_DONE ) { // avoid concurrency between fast and tm daemons ( they set DONE when complete )
            self::_TimeStampMsg( "*** Project $pid: Changing status..." );
            Projects_ProjectDao::changeProjectStatus( $pid, $status );
            self::_TimeStampMsg( "*** Project $pid: $status" );
        } else {
            self::_TimeStampMsg( "*** Project $pid: TM Analysis already completed. Skip update..." );
        }
        Database::obtain()->commit();

    }

    /**
     * @param $tuple_list
     * @param $bind_values
     *
     * @throws PDOException
     */
    protected function _executeInsert( $tuple_list, $bind_values ) {

        $db       = Database::obtain();
        $query_st = "INSERT INTO `segment_translations` ( 
                                      id_job, 
                                      id_segment, 
                                      segment_hash, 
                                      match_type, 
                                      eq_word_count, 
                                      standard_word_count 
                                 ) VALUES "
                . implode( ", ", $tuple_list ) .
                " ON DUPLICATE KEY UPDATE
                        match_type = VALUES( match_type ),
                        eq_word_count = VALUES( eq_word_count ),
                        standard_word_count = VALUES( standard_word_count )
                ";

        self::_TimeStampMsg( "Executed " . ( count( $tuple_list ) ) );
        $stmt = $db->getConnection()->prepare( $query_st );
        $stmt->execute( $bind_values );
        $stmt->closeCursor();

    }

    /**
     * @param      $pid
     * @param      $equivalentWordMapping
     * @param bool $perform_Tms_Analysis
     *
     * @return mixed
     * @throws Exception
     */
    protected function _insertFastAnalysis( $pid, $equivalentWordMapping, FeatureSet $featureSet, $perform_Tms_Analysis = true ) {

        /**
         * Ensure we have fresh data from master node
         */
        Database::obtain()->getConnection()->beginTransaction();
        $projectStruct         = \Projects_ProjectDao::findById( $pid );
        $projectFeaturesString = $projectStruct->getMetadataValue( Projects_MetadataDao::FEATURES_KEY );
        Database::obtain()->getConnection()->commit();

        $total_eq_wc       = 0;
        $total_standard_wc = 0;

        $tuple_list             = [];
        $bind_values            = [];
        $totalSegmentsToAnalyze = 0;
        foreach ( $this->segments as $k => $v ) {

            $jid_pass = explode( "-", $v[ 'jsid' ] );

            // only to remember the meaning of $k
            // EX: 21529088-42593:b433193493c6,42594:b4331aacf3d4
            //$id_segment = $jid_fid[ 0 ];

            $list_id_jobs_password = $jid_pass[ 1 ];

            list( $eq_word, $standard_words, $match_type ) = $this->_getWordCountForSegment( $v, $equivalentWordMapping );

            $total_eq_wc       += $eq_word;
            $total_standard_wc += $standard_words;

            $list_id_jobs_password = explode( ',', $list_id_jobs_password );
            foreach ( $list_id_jobs_password as $id_job ) {

                list( $id_job, $job_pass ) = explode( ":", $id_job );

                $segment = ( new \Segments_SegmentDao() )->getById( $v[ 'id' ] );

                $bind_values[] = (int)$id_job;
                $bind_values[] = (int)$v[ 'id' ];
                $bind_values[] = $v[ 'segment_hash' ];
                $bind_values[] = $match_type;
                $bind_values[] = ( (float)$eq_word > $segment->raw_word_count ) ? $segment->raw_word_count : (float)$eq_word;
                $bind_values[] = ( (float)$standard_words > $segment->raw_word_count ) ? $segment->raw_word_count : (float)$standard_words;

                $tuple_list[] = "( ?,?,?,?,?,? )";
                $totalSegmentsToAnalyze++;

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
                    $this->segments[ $k ][ 'pid' ]           = (int)$pid;
                    $this->segments[ $k ][ 'ppassword' ]     = $projectStruct->password;
                    $this->segments[ $k ][ 'date_insert' ]   = date_create()->format( 'Y-m-d H:i:s' );
                    $this->segments[ $k ][ 'eq_word_count' ] = ( (float)$eq_word > $segment->raw_word_count ) ? $segment->raw_word_count : (float)$eq_word;;
                    $this->segments[ $k ][ 'standard_word_count' ] = ( (float)$standard_words > $segment->raw_word_count ) ? $segment->raw_word_count : (float)$standard_words;

                } elseif ( $perform_Tms_Analysis ) {

                    Log::doJsonLog( 'Skipped Fast Segment: ' . var_export( $this->segments[ $k ], true ) );
                    // this segment must not be sent to the TM analysis queue
                    unset( $this->segments[ $k ] );

                } else {
                    //In this case the TM analysis is disabled
                    //ALL segments must not be sent to the TM analysis queue
                    //do nothing, but $perform_Tms_Analysis is false, so we want delete all elements after the end of the loop
                }

                if ( ( $totalSegmentsToAnalyze % 200 ) == 0 ) {
                    try {
                        $this->_executeInsert( $tuple_list, $bind_values );
                    } catch ( PDOException $e ) {
                        self::_TimeStampMsg( $e->getMessage() );

                        return $e->getCode() * -1;
                    }
                    $tuple_list  = [];
                    $bind_values = [];
                }

            }

            //anyway this key must be removed because he is no more needed and we want not to send it to the queue
            unset( $this->segments[ $k ][ 'wc' ] );
            if ( !$perform_Tms_Analysis ) {
                unset( $this->segments[ $k ] );
            }

        }

        if ( ( $totalSegmentsToAnalyze % 200 ) != 0 ) {
            try {
                $this->_executeInsert( $tuple_list, $bind_values );
            } catch ( PDOException $e ) {
                self::_TimeStampMsg( $e->getMessage() );

                return $e->getCode() * -1;
            }
        }

        unset( $data );
        unset( $tuple_list );
        unset( $chunks_bind_values );
        unset( $chunks_st );

        /*
         * IF NO TM ANALYSIS, update the jobs global word count
         */
        if ( !$perform_Tms_Analysis ) {

            $_details = $this->getProjectSegmentsTranslationSummary( $pid );

            self::_TimeStampMsg( "--- trying to initialize job total word count." );

            $project_details = array_pop( $_details ); //Don't remove, needed to remove rollup row

            foreach ( $_details as $job_info ) {
                $counter = new WordCount_CounterModel();
                $counter->initializeJobWordCount( $job_info[ 'id_job' ], $job_info[ 'password' ] );
            }

        }
        /* IF NO TM ANALYSIS, upload the jobs global word count */

        //_TimeStampMsg( "Done." );

        $data2 = [ 'fast_analysis_wc' => $total_eq_wc ];
        $where = [ "id" => $pid ];


        try {
            $db                       = Database::obtain();
            $project_creation_success = $db->update( 'projects', $data2, $where );
        } catch ( PDOException $e ) {
            self::_TimeStampMsg( $e->getMessage() );

            return $e->getCode() * -1;
        }

        $featureSet->run( 'beforeSendSegmentsToTheQueue', array_values( $this->segments ), $this->actual_project_row );

        /*
         *  $fastResultData[0]['id_mt_engine'] is the index of the MT engine we must use,
         *  i take the value from the first element of the list ( the last one is the same for the project )
         *  because surely this value are equal for all the record of the project
         */
        $queueInfo = $this->_getQueueAddressesByPriority( $totalSegmentsToAnalyze, $this->actual_project_row[ 'id_mt_engine' ] );

        if ( $totalSegmentsToAnalyze ) {

            self::_TimeStampMsg( "Publish Segment Translations to the queue --> {$queueInfo->queue_name}: $totalSegmentsToAnalyze" );
            self::_TimeStampMsg( "Elements: $totalSegmentsToAnalyze" );

            try {
                $this->_setTotal( [ 'pid' => $pid, 'queueInfo' => $queueInfo ] );
            } catch ( Exception $e ) {
                Utils::sendErrMailReport( $e->getMessage() . "" . $e->getTraceAsString(), "Fast Analysis set Total values failed." );
                self::_TimeStampMsg( $e->getMessage() . "" . $e->getTraceAsString() );
                throw $e;
            }

            $time_start = microtime( true );

            /**
             * Reset the indexes of the list to get the context easily
             */
            $this->segments = array_values( $this->segments );
            foreach ( $this->segments as $k => $queue_element ) {

                $queue_element[ 'id_segment' ]       = $queue_element[ 'id' ];
                $queue_element[ 'pretranslate_100' ] = $this->actual_project_row[ 'pretranslate_100' ];
                $queue_element[ 'tm_keys' ]          = $this->actual_project_row[ 'tm_keys' ];
                $queue_element[ 'id_tms' ]           = $this->actual_project_row[ 'id_tms' ];
                $queue_element[ 'id_mt_engine' ]     = $this->actual_project_row[ 'id_mt_engine' ];
                $queue_element[ 'features' ]         = $projectFeaturesString;
                $queue_element[ 'only_private' ]     = $this->actual_project_row[ 'only_private_tm' ];

                $queue_element[ 'context_before' ] = @$this->segments[ $k - 1 ][ 'segment' ];
                $queue_element[ 'context_after' ]  = @$this->segments[ $k + 1 ][ 'segment' ];

                /**
                 * remove some unuseful fields
                 */
                unset( $queue_element[ 'id' ] );
                unset( $queue_element[ 'jsid' ] );

                try {

                    //store the payable_rates array
                    $jobs_payable_rates = $queue_element[ 'payable_rates' ];

                    $languages_job = explode( ",", $queue_element[ 'target' ] );  //now target holds more than one language ex: ( 80415:fr-FR,80416:it-IT )
                    //in memory replacement avoid duplication of the segment list
                    //send in queue every element * number of languages
                    foreach ( $languages_job as $_language ) {

                        list( $id_job, $language ) = explode( ":", $_language );

                        $queue_element[ 'target' ]        = $language;
                        $queue_element[ 'id_job' ]        = $id_job;
                        $queue_element[ 'payable_rates' ] = $jobs_payable_rates[ $id_job ]; // assign the right payable rate for the current job

                        $element            = new QueueElement();
                        $element->params    = $queue_element;
                        $element->classLoad = '\Analysis\Workers\TMAnalysisWorker';

                        self::$queueHandler->send( $queueInfo->queue_name, $element, [ 'persistent' => self::$queueHandler->persistent ] );
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

        return $project_creation_success;
    }

    protected function _getWordCountForSegment( $segmentArray, $equivalentWordMapping ) {

        switch ( $segmentArray[ 'match_type' ] ) {
            case '75%-84%':
            case '85%-94%':
            case '95%-99%':
                $eq_word    = ( $segmentArray[ 'wc' ] * $equivalentWordMapping[ 'INTERNAL' ] / 100 );
                $match_type = 'INTERNAL';
                break;
            case( array_key_exists( $segmentArray[ 'match_type' ], $equivalentWordMapping ) ):
                $eq_word    = ( $segmentArray[ 'wc' ] * $equivalentWordMapping[ $segmentArray[ 'match_type' ] ] / 100 );
                $match_type = $segmentArray[ 'match_type' ];
                break;
            default:
                $eq_word    = $segmentArray[ 'wc' ];
                $match_type = "NO_MATCH";
                break;
        }

        //Set NO_MATCH word count multiplier for internal fuzzy matches on standard_words
        $standard_words = $eq_word;
        if ( $match_type == "INTERNAL" ) {
            $standard_words = $segmentArray[ 'wc' ] * $equivalentWordMapping[ "NO_MATCH" ] / 100;
        }

        return [ $eq_word, $standard_words, $match_type ];

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

        $query = <<<HD
            SELECT concat( s.id, '-', group_concat( distinct concat( j.id, ':' , j.password ) ) ) AS jsid, s.segment, 
                j.source, s.segment_hash, 
                s.id as id,
                s.raw_word_count,
                GROUP_CONCAT( DISTINCT CONCAT( j.id, ':' , j.target ) ) AS target,
                CONCAT( "{", GROUP_CONCAT( DISTINCT CONCAT( '"', j.id, '"', ':' , j.payable_rates ) SEPARATOR ',' ), "}" ) AS payable_rates
            FROM segments AS s
            INNER JOIN files_job AS fj ON fj.id_file = s.id_file
            INNER JOIN jobs as j ON fj.id_job = j.id
            LEFT JOIN segment_translations AS st ON st.id_segment = s.id
                WHERE j.id_project = ?
                AND IFNULL( st.locked, 0 ) = 0
                AND IFNULL( st.match_type, 'NO_MATCH' ) != 'ICE'
                AND show_in_cattool != 0
            GROUP BY s.id
            ORDER BY s.id
HD;

        $db = Database::obtain();
        try {
            $stmt = $db->getConnection()->prepare( $query );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute( [ $pid ] );
            $results = $stmt->fetchAll();
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );
            throw $e;
        }

        $results = array_map( function ( $segment ) {
            $segment[ 'payable_rates' ] = array_map( function ( $rowPayable ) {
                return json_encode( $rowPayable );
            }, json_decode( $segment[ 'payable_rates' ], true ) );

            return $segment;
        }, $results );

        return $results;
    }

    /**
     * How much segments are in queue before this?
     *
     * <pre>
     *  $config = array(
     *    'total' => null,
     *    'qid' => null,
     *    'queueInfo' => @param array $config
     *
     * @throws Exception
     * @var Context
     *  )
     *  </pre>
     *
     */
    protected function _setTotal( array $config = [
            'total'     => null,
            'pid'       => null,
            'queueInfo' => null
    ] ) {

        if ( empty( $this->queueTotalID ) && empty( $config[ 'pid' ] ) ) {
            throw new Exception( 'Can Not set a Total without a Queue ID.' );
        }

        if ( !empty( $config[ 'total' ] ) ) {
            $_total = $config[ 'total' ];
        } else {

            if ( empty( $config[ 'queueInfo' ] ) && empty( $this->queueName ) ) {
                throw new Exception( 'Need a queue name to get it\'s total or you must provide one' );
            }

            $queueName = ( !empty( $config[ 'queueInfo' ] ) ? $config[ 'queueInfo' ]->queue_name : $this->queueName );
            $_total    = self::$queueHandler->getQueueLength( $queueName );

        }

        if ( !empty( $config[ 'pid' ] ) ) {
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
     * @param $queueLen     int
     * @param $id_mt_engine int
     *
     * @return Context
     */
    protected function _getQueueAddressesByPriority( $queueLen, $id_mt_engine ) {

        $mtEngine = null;
        try {
            $mtEngine = Engine::getInstance( $id_mt_engine );
        } catch ( Exception $e ) {
            self::_TimeStampMsg( "Caught Exception: " . $e->getMessage() );
        }

        //anyway take the defaults
        $contextList = $this->_queueContextList->list;

        //use this kind of construct to easy add/remove queues and to disable feature by: comment rows or change the switch flag to false
        switch ( true ) {
            case ( !$mtEngine instanceof \Engines_MyMemory && !$mtEngine instanceof \Engines_NONE ):
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

    protected function _getLockProjectForVolumeAnalysis( $limit = 1 ) {

        $bindParams = [ 'project_status' => Constants_ProjectStatus::STATUS_NEW ];

        $and_InstanceId = null;
        if ( !is_null( INIT::$INSTANCE_ID ) ) {
            $and_InstanceId              = ' AND instance_id = :instance_id ';
            $bindParams[ 'instance_id' ] = (int)INIT::$INSTANCE_ID;
        }

        $query = "
        SELECT p.id, id_tms, id_mt_engine, tm_keys, p.pretranslate_100, GROUP_CONCAT( DISTINCT j.id ) AS jid_list, j.only_private_tm, p.id_customer
            FROM projects p
            INNER JOIN jobs j ON j.id_project=p.id
            WHERE status_analysis = :project_status $and_InstanceId
            GROUP BY 1
        ORDER BY id LIMIT " . (int)$limit;

        $db = Database::obtain();
        //Needed to address the query to the master database if exists
        \Database::obtain()->begin();

        $stmt = $db->getConnection()->prepare( $query );
        $stmt->execute( $bindParams );
        $results = $stmt->fetchAll( PDO::FETCH_ASSOC );

        $db->getConnection()->commit();

        foreach ( $results as $position => $project ) {
            //acquire a lock
            $valid = self::$queueHandler->getRedisClient()->setnx( '_fPid:' . $project[ 'id' ], 1 );
            if ( !$valid ) {
                unset( $results[ $position ] );
            } else {
                self::$queueHandler->getRedisClient()->expire( '_fPid:' . $project[ 'id' ], 60 * 60 * 24 );

                try {
                    $this->_updateProject( $project[ 'id' ], Constants_ProjectStatus::STATUS_BUSY );
                } catch ( PDOException $ex ) {
                    self::$queueHandler->getRedisClient()->del( '_fPid:' . $project[ 'id' ] );
                }

            }
        }

        return $results;

    }

}
