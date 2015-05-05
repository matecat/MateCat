<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 30/04/15
 * Time: 19.21
 * 
 */

class AnalysisQueueHandler extends Stomp {

    protected $amqHandler;
    protected $redisHandler;
    protected $clientType = null;

    protected $queueTotalID = null;

    const CLIENT_TYPE_PUBLISHER = 'Publisher';
    const CLIENT_TYPE_SUBSCRIBER = 'Subscriber';

    /**
     * Handle a string for the queue name
     * @var string
     */
    protected $queueName = null;

    public function __construct( $brokerUri = null ){

        if( !is_null( $brokerUri ) ){
            parent::__construct( $brokerUri );
        } else {
            parent::__construct( INIT::$QUEUE_BROKER_ADDRESS );
        }

    }

    /**
     * Lazy connection
     *
     * Get the connection to Redis server and return it
     */
    protected function _getRedisConnection( ){

        if( $this->redisHandler === null ){
            require_once 'Predis/autoload.php';
            $this->redisHandler = new Predis\Client( INIT::$REDIS_SERVERS );
        }

        return $this->redisHandler;

    }

    /**
     * @param string $queueName
     *
     * @return bool
     * @throws Exception
     * @throws StompException
     */
    public function subscribe( $queueName = null ){

        if ( empty($queueName) ){
            $queueName = INIT::$QUEUE_NAME;
        }

        if( !empty( $this->clientType ) && $this->clientType != self::CLIENT_TYPE_SUBSCRIBER ){
            throw new Exception( "This client is a $this->clientType. A client can only publisher or subscriber, not both." );
        } elseif( $this->clientType == self::CLIENT_TYPE_SUBSCRIBER ) {
            //already connected, we want to change the queue
            $this->queueName = $queueName;
            return parent::subscribe( '/queue/' . INIT::$QUEUE_NAME );
        }

        $this->clientType = self::CLIENT_TYPE_SUBSCRIBER;
        $this->connect();
        $this->setReadTimeout( 0, 100 );
        $this->queueName = $queueName;
        return parent::subscribe( '/queue/' . INIT::$QUEUE_NAME );

    }

    /**
     * @param string            $destination
     * @param StompFrame|string $msg
     * @param array             $properties
     * @param null              $sync
     *
     * @return bool
     * @throws Exception
     */
    public function send( $destination, $msg, $properties = array(), $sync = null ){

        if( !empty( $this->clientType ) && $this->clientType != self::CLIENT_TYPE_PUBLISHER ){
            throw new Exception( "This client is a $this->clientType. A client can be only publisher or subscriber, not both." );
        } elseif( empty( $this->clientType ) ){
            $this->connect();
        }

        $this->clientType = self::CLIENT_TYPE_PUBLISHER;
        return parent::send( '/queue/' . $destination, $msg, $properties, $sync );

    }

    /**
     * Get the queue Length
     *
     * @param $queueName
     *
     * @return mixed
     * @throws Exception
     */
    public function getQueueLength( $queueName = null ){

        if( !empty( $queueName ) ){
            $queue = $queueName;
        } else {
            $queue = $this->queueName;
        }

        $queue_inteface_url = INIT::$QUEUE_JMX_ADDRESS . "/api/jolokia/read/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=$queue/QueueSize";

        $mHandler = new MultiCurlHandler();

        $options = array(
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 5, // a timeout to call itself should not be too much higher :D
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => array( 'Authorization: Basic '. base64_encode("admin:admin") )
        );

        $resource = $mHandler->createResource( $queue_inteface_url, $options );
        $mHandler->multiExec();
        $result = $mHandler->getSingleContent( $resource );
        $mHandler->multiCurlCloseAll();
        $result = json_decode( $result, true );

        Utils::raiseJsonExceptionError();
        return $result[ 'value' ];

    }

    /**
     * <pre>
     *  $config = array(
     *    'total' => null,
     *    'qid' => null,
     *    'queueName' => null
     *  )
     * </pre>
     *
     * @param array $config
     *
     * @throws Exception
     */
    public function setTotal( $config = array(
            'total' => null,
            'qid' => null,
            'queueName' => null
    ) ){

        if( empty( $this->queueTotalID ) && empty( $config[ 'qid' ] ) ){
            throw new Exception( 'Can Not set a Total without a Queue ID.' );
        }

        if( !empty( $config[ 'total' ] ) ){
            $_total = $config[ 'total' ];
        } else {

            if( empty( $config[ 'queueName' ] ) && empty( $this->queueName ) ){
                throw new Exception( 'Need a queue name to get it\'s total or you must provide one' );
            }

            $queueName = ( !empty( $config[ 'queueName' ] ) ? $config[ 'queueName' ] : $this->queueName );
            $_total = $this->getQueueLength( $queueName );

        }

        if( !empty( $config[ 'qid' ] ) ){
            $_qid = $config[ 'qid' ];
        } else {
            $_qid = $this->queueTotalID;
        }

        $this->_getRedisConnection()->setex( Constants_AnalysisRedisKeys::TOTAL_SEGMENTS_TO_WAIT . $_qid, 60 * 60 /* 1 hour TTL */, $_total );
        $this->_getRedisConnection()->rpush( Constants_AnalysisRedisKeys::PROJECTS_QUEUE_LIST, $_qid );

    }

    /**
     * @param null $qid
     *
     * @return mixed
     * @throws Exception
     */
    public function getActualForQID( $qid = null ){

        if( empty( $this->queueTotalID ) && empty( $qid ) ){
            throw new Exception( 'Can Not get values without a Queue ID. AnalysisQueueHandler::setQueueID ' );
        }

        if( !empty( $qid ) ){
            $_qid = $qid;
        } else {
            $_qid = $this->queueTotalID;
        }

        return $this->_getRedisConnection()->get( Constants_AnalysisRedisKeys::TOTAL_SEGMENTS_TO_WAIT . $_qid );

    }

    /**
     * @param null $qid
     *
     * @throws Exception
     */
    public function decrementTotalForWaitingProjects( $qid = null ){

        if( empty( $this->queueTotalID ) && empty( $qid ) ){
            throw new Exception( 'Can Not send without a Queue ID. AnalysisQueueHandler::setQueueID ' );
        }

        if( !empty( $qid ) ){
            $_qid = $qid;
        } else {
            $_qid = $this->queueTotalID;
        }

        $working_jobs = $this->_getRedisConnection()->lrange( Constants_AnalysisRedisKeys::PROJECTS_QUEUE_LIST, 0, -1 );

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
            if( $value == $_qid ) {
                $found = true;
            }
            if( $found ){
                $this->_getRedisConnection()->decr( Constants_AnalysisRedisKeys::TOTAL_SEGMENTS_TO_WAIT . $value );
            }
        }

    }

    public function tryToCloseProject( $pid, $child_process_id ) {

        if( !empty( $pid ) ){
            $_pid = $pid;
        } else {
            $_pid = $this->queueTotalID;
        }

        $project_totals                       = array();
        $project_totals[ 'project_segments' ] = $this->_getRedisConnection()->get( Constants_AnalysisRedisKeys::PROJECT_TOT_SEGMENTS . $pid );
        $project_totals[ 'num_analyzed' ]     = $this->_getRedisConnection()->get( Constants_AnalysisRedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid );
        $project_totals[ 'eq_wc' ]            = $this->_getRedisConnection()->get( Constants_AnalysisRedisKeys::PROJ_EQ_WORD_COUNT . $pid ) / 100;
        $project_totals[ 'st_wc' ]            = $this->_getRedisConnection()->get( Constants_AnalysisRedisKeys::PROJ_ST_WORD_COUNT . $pid ) / 100;

        Log::doLog ( "--- (child $child_process_id) : count segments in project $pid = " . $project_totals[ 'project_segments' ] . "" );
        Log::doLog ( "--- (child $child_process_id) : Analyzed segments in project $pid = " . $project_totals[ 'num_analyzed' ] . "" );

        if ( empty( $project_totals[ 'project_segments' ] ) ) {
            Log::doLog ( "--- (child $child_process_id) : WARNING !!! error while counting segments in projects $pid skipping and continue " );

            return;
        }

        if ( $project_totals[ 'project_segments' ] - $project_totals[ 'num_analyzed' ] == 0 && $this->_getRedisConnection()->setnx( 'project_done:' . $pid, 1 ) ) {

            $this->_getRedisConnection()->expire( 'project_done:' . $pid, 60 * 60 /* 1 hour TTL */ );

            $_analyzed_report = getProjectSegmentsTranslationSummary( $pid );

            $total_segs = array_pop( $_analyzed_report ); //remove Rollup

            Log::doLog ( "--- (child $child_process_id) : analysis project $pid finished : change status to DONE" );

            changeProjectStatus( $pid, Constants_ProjectStatus::STATUS_DONE );
            changeTmWc( $pid, $project_totals[ 'eq_wc' ], $project_totals[ 'st_wc' ] );

            /*
             * Remove this job from the project list
             */
            $this->_getRedisConnection()->lrem( Constants_AnalysisRedisKeys::PROJECTS_QUEUE_LIST, 0, $_pid );

            Log::doLog ( "--- (child $child_process_id) : trying to initialize job total word count." );
            foreach ( $_analyzed_report as $job_info ) {
                $counter = new WordCount_Counter();
                $counter->initializeJobWordCount( $job_info[ 'id_job' ], $job_info[ 'password' ] );
            }

        }

    }

    /**
     * @param $qid
     *
     * @return $this
     */
    public function setQueueID( $qid ){
        $this->queueTotalID = $qid;
        return $this;
    }

    /**
     * @param $objQueue
     * @param $process_pid
     */
    public function initializeTMAnalysis( $objQueue, $process_pid ){

        $sid = $objQueue[ 'id_segment' ];
        $jid = $objQueue[ 'id_job' ];
        $pid = $objQueue[ 'pid' ];

        //get the number of segments in job
        $_acquiredLock = $this->_getRedisConnection()->setnx( 'project_lock:' . $pid, true ); // lock for 1 month
        if ( !empty( $_acquiredLock ) ) {

            $total_segs = getProjectSegmentsTranslationSummary( $pid );

            $total_segs = array_pop( $total_segs ); // get the Rollup Value
            Log::doLog( $total_segs );

            $this->_getRedisConnection()->setex( Constants_AnalysisRedisKeys::PROJECT_TOT_SEGMENTS . $pid, 60 * 60 /* 1 hour TTL */, $total_segs[ 'project_segments' ] );
            $this->_getRedisConnection()->incrby( Constants_AnalysisRedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, $total_segs[ 'num_analyzed' ] );
            $this->_getRedisConnection()->expire( Constants_AnalysisRedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, 60 * 60 /* 1 hour TTL */ );
            Log::doLog ( "--- (child $process_pid) : found " . $total_segs[ 'project_segments' ] . " segments for PID $pid" );

        } else {
            $_existingPid = $this->_getRedisConnection()->get( Constants_AnalysisRedisKeys::PROJECT_TOT_SEGMENTS . $pid );
            $_analyzed    = $this->_getRedisConnection()->get( Constants_AnalysisRedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid );
            Log::doLog ( "--- (child $process_pid) : found $_existingPid segments for PID $pid in Memcache" );
            Log::doLog ( "--- (child $process_pid) : analyzed $_analyzed segments for PID $pid in Memcache" );
        }

        Log::doLog ( "--- (child $process_pid) : fetched data for segment $sid-$jid. Project ID is $pid" );

    }

    /**
     * @param $pid
     * @param $eq_words
     * @param $standard_words
     */
    public function incrementAnalyzedCount( $pid, $eq_words, $standard_words ) {
        $this->_getRedisConnection()->incrby( Constants_AnalysisRedisKeys::PROJ_EQ_WORD_COUNT . $pid, $eq_words * 100 );
        $this->_getRedisConnection()->incrby( Constants_AnalysisRedisKeys::PROJ_ST_WORD_COUNT . $pid, $standard_words * 100 );
        $this->_getRedisConnection()->incrby( Constants_AnalysisRedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, 1 );
    }


    function reQueue( $failed_segment ){

        if ( !empty( $failed_segment ) ) {
            Log::doLog( "Failed " . count( $failed_segment ) );
            $this->send( INIT::$QUEUE_NAME, json_encode( $failed_segment ), array( 'persistent' => 'true' ) );
        }

    }

}