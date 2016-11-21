<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 30/04/15
 * Time: 19.21
 * 
 */

use \TaskRunner\Commons\Context,
    \Analysis\Queue\RedisKeys;

use \Stomp,
    \RedisHandler,
    \INIT,
    \Exception,
    \MultiCurlHandler,
    \Utils,
    \Log
;

class AMQHandler extends Stomp {

    protected $amqHandler;

    /**
     * @var \Predis\Client
     */
    protected $redisHandler;
    protected $clientType = null;

    protected $queueTotalID = null;

    const CLIENT_TYPE_PUBLISHER = 'Publisher';
    const CLIENT_TYPE_SUBSCRIBER = 'Subscriber';

    public $persistent = 'true';

    /**
     * Handle a string for the queue name
     * @var string
     *
     * @throws \StompException
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
     *
     * @throws \Predis\Connection\ConnectionException
     */
    public function getRedisClient( ){
        if( empty( $this->redisHandler ) ){
            $this->redisHandler = new RedisHandler();
        }
        return $this->redisHandler->getConnection();
    }

    /**
     * @param string $queueName
     *
     * @return bool
     * @throws Exception
     * @throws \StompException
     */
    public function subscribe( $queueName = null ){

        if ( empty($queueName) ){
            $queueName = RedisKeys::DEFAULT_QUEUE_NAME;
        }

        if( !empty( $this->clientType ) && $this->clientType != self::CLIENT_TYPE_SUBSCRIBER ){
            throw new Exception( "This client is a $this->clientType. A client can be only publisher or subscriber, not both." );
        } elseif( $this->clientType == self::CLIENT_TYPE_SUBSCRIBER ) {
            //already connected, we want to change the queue
            $this->queueName = $queueName;
            return parent::subscribe( '/queue/' . RedisKeys::DEFAULT_QUEUE_NAME );
        }

        $this->clientType = self::CLIENT_TYPE_SUBSCRIBER;
        $this->connect();
        $this->setReadTimeout( 5, 0 );
        $this->queueName = $queueName;
        return parent::subscribe( '/queue/' . $queueName );

    }

    /**
     * @param string            $destination
     * @param \StompFrame|string $msg
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
        } elseif( !empty( $this->queueName ) ) {
            $queue = $this->queueName;
        } else {
            throw new \Exception( 'No queue name provided.' );
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
     * Get the number of consumers for this queue
     *
     * @param null $queueName
     *
     * @return mixed
     * @throws Exception
     */
    public function getConsumerCount( $queueName = null ){

        if( !empty( $queueName ) ){
            $queue = $queueName;
        } elseif( !empty( $this->queueName ) ) {
            $queue = $this->queueName;
        } else {
            throw new \Exception( 'No queue name provided.' );
        }

        $queue_inteface_url = INIT::$QUEUE_JMX_ADDRESS . "/api/jolokia/read/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=$queue/ConsumerCount";

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
     * Called from web interface, manage the Exception
     *
     * @param null $qid
     *
     * @return mixed
     * @throws Exception
     */
    public function getActualForQID( $qid = null ){

        if( empty( $this->queueTotalID ) && empty( $qid ) ){
            throw new Exception( 'Can Not get values without a Queue ID. Use \AMQHandler::setQueueID or pass a queue id to this method' );
        }

        if( !empty( $qid ) ){
            $_qid = $qid;
        } else {
            $_qid = $this->queueTotalID;
        }

        return $this->getRedisClient()->get( RedisKeys::TOTAL_SEGMENTS_TO_WAIT . $_qid );

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

    public function reQueue( $failed_segment, Context $queueInfo ){

        if ( !empty( $failed_segment ) ) {
            Log::doLog( "Failed " . var_export( $failed_segment, true ) );
            $this->send( $queueInfo->queue_name, json_encode( $failed_segment ), array( 'persistent' => $this->persistent ) );
        }

    }

}