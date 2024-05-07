<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 30/04/15
 * Time: 19.21
 *
 */

use Analysis\Queue\RedisKeys;
use Stomp\Client;
use Stomp\Exception\ConnectionException;
use Stomp\Network\Connection;
use Stomp\StatefulStomp;
use Stomp\Transport\Frame;
use Stomp\Transport\Message;
use TaskRunner\Commons\Context;

class AMQHandler {

    /**
     * @var Predis\Client
     */
    protected $redisHandler;

    /**
     * @var StatefulStomp
     */
    protected $statefulStomp;
    /**
     * @var Connection
     */
    protected static $staticStompConnection;
    protected        $clientType = null;

    protected $queueTotalID = null;

    const CLIENT_TYPE_PUBLISHER  = 'Publisher';
    const CLIENT_TYPE_SUBSCRIBER = 'Subscriber';

    public $persistent = 'true';

    /**
     * Handle a string for the queue name
     * @var string
     *
     */
    protected $queueName = null;

    /**
     * Singleton implementation of StatefulStomp in a not static constructor
     *
     * @throws ConnectionException
     */
    public function __construct( $brokerUri = null, $usePersistentConnection = true ) {

        if ( $usePersistentConnection ) {

            if ( !isset( self::$staticStompConnection ) ) {
                if ( !is_null( $brokerUri ) ) {
                    self::$staticStompConnection = new Connection( $brokerUri, 2 );
                } else {
                    self::$staticStompConnection = new Connection( INIT::$QUEUE_BROKER_ADDRESS, 2 );
                }
            }

            $connection = self::$staticStompConnection;

        } else {

            if ( !is_null( $brokerUri ) ) {
                $connection = new Connection( $brokerUri, 2 );
            } else {
                $connection = new Connection( INIT::$QUEUE_BROKER_ADDRESS, 2 );
            }

        }

        $this->statefulStomp = new StatefulStomp( new Client( $connection ) );

    }

    public static function getNewInstanceForDaemons() {
        return new self( null, false );
    }

    /**
     * @return Client
     */
    public function getClient() {
        return $this->statefulStomp->getClient();
    }

    public function ack( Frame $frame ) {
        $this->statefulStomp->ack( $frame );
    }

    public function nack( Frame $frame ) {
        $this->statefulStomp->nack( $frame );
    }

    /**
     * @return false|Frame
     */
    public function read() {
        return $this->statefulStomp->read();
    }

    /**
     * Lazy connection
     *
     * Get the connection to Redis server and return it
     *
     * @return Predis\Client
     * @throws ReflectionException
     */
    public function getRedisClient() {
        if ( empty( $this->redisHandler ) ) {
            $this->redisHandler = new RedisHandler();
        }

        return $this->redisHandler->getConnection();
    }

    /**
     *
     * @param string $destination
     * @param null   $selector
     * @param string $ack
     * @param array  $header
     *
     * @return int
     */
    public function subscribe( $destination, $selector = null, $ack = 'client-individual', array $header = [] ) {

        $this->clientType = self::CLIENT_TYPE_SUBSCRIBER;
        $this->queueName  = $destination;

        return $this->statefulStomp->subscribe( '/queue/' . INIT::$INSTANCE_ID . "_" . $destination, $selector, $ack, $header );

    }

    /**
     * @param string  $destination
     * @param Message $message
     *
     * @return bool
     */
    public function publishToQueues( $destination, Message $message ) {

        $this->clientType = self::CLIENT_TYPE_PUBLISHER;

        return $this->statefulStomp->send( '/queue/' . INIT::$INSTANCE_ID . "_" . $destination, $message );

    }

    /**
     * Clean connections
     */
    public function __destruct() {
        $this->statefulStomp->getClient()->disconnect();
    }


    /**
     * @param string  $destination
     * @param Message $message
     *
     * @return bool
     */
    public function publishToTopic( $destination, Message $message ) {

        $this->clientType = self::CLIENT_TYPE_PUBLISHER;

        return $this->statefulStomp->send( $destination, $message );

    }

    /**
     * Get the queue Length
     *
     * @param $queueName
     *
     * @return mixed
     * @throws Exception
     */
    public function getQueueLength( $queueName = null ) {

        if ( !empty( $queueName ) ) {
            $queue = $queueName;
        } elseif ( !empty( $this->queueName ) ) {
            $queue = $this->queueName;
        } else {
            throw new Exception( 'No queue name provided.' );
        }

        $queue_interface_url = INIT::$QUEUE_JMX_ADDRESS . "/api/jolokia/read/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=" . INIT::$INSTANCE_ID . "_" . $queue . "/QueueSize";

        return $this->callAmqJmx( $queue_interface_url );

    }

    /**
     * Get the number of consumers for this queue
     *
     * @param null $queueName
     *
     * @return mixed
     * @throws Exception
     */
    public function getConsumerCount( $queueName = null ) {

        if ( !empty( $queueName ) ) {
            $queue = $queueName;
        } elseif ( !empty( $this->queueName ) ) {
            $queue = $this->queueName;
        } else {
            throw new Exception( 'No queue name provided.' );
        }

        $queue_interface_url = INIT::$QUEUE_JMX_ADDRESS . "/api/jolokia/read/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=" . INIT::$INSTANCE_ID . "_" . $queue . "/ConsumerCount";

        return $this->callAmqJmx( $queue_interface_url );

    }

    /**
     * Called from web interface, manage the Exception
     *
     * @param null $qid
     *
     * @return mixed
     * @throws Exception
     */
    public function getActualForQID( $qid = null ) {

        if ( empty( $this->queueTotalID ) && empty( $qid ) ) {
            throw new Exception( 'Can Not get values without a Queue ID. Use \AMQHandler::setQueueID or pass a queue id to this method' );
        }

        if ( !empty( $qid ) ) {
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
    public function setQueueID( $qid ) {
        $this->queueTotalID = $qid;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reQueue( $failed_segment, Context $queueInfo ) {

        if ( !empty( $failed_segment ) ) {
            Log::doJsonLog( "Failed " . var_export( $failed_segment, true ) );
            $this->publishToQueues( $queueInfo->queue_name, new Message( strval( $failed_segment ), [ 'persistent' => $this->persistent ] ) );
        }

    }

    /**
     * @param $queue_interface_url
     *
     * @return mixed
     * @throws Exception
     */
    public function callAmqJmx( $queue_interface_url ) {
        $mHandler = new MultiCurlHandler();

        $options = [
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 5, // a timeout to call itself should not be too much higher :D
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => [ 'Authorization: Basic ' . base64_encode( INIT::$QUEUE_CREDENTIALS ) ]
        ];

        $resource = $mHandler->createResource( $queue_interface_url, $options );
        $mHandler->multiExec();
        $result = $mHandler->getSingleContent( $resource );
        $mHandler->multiCurlCloseAll();
        $result = json_decode( $result, true );

        Utils::raiseJsonExceptionError();

        return $result[ 'value' ];
    }

}