<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 30/04/15
 * Time: 19.21
 *
 */

use Analysis\Queue\RedisKeys;
use Predis\Client as PredisClient;
use Predis\PredisException;
use Stomp\Client;
use Stomp\Exception\ConnectionException;
use Stomp\Exception\StompException;
use Stomp\Network\Connection;
use Stomp\StatefulStomp;
use Stomp\Transport\Message;
use TaskRunner\Commons\Context;

class AMQHandler extends StatefulStomp {

    /**
     * @var PredisClient
     */
    protected $redisHandler;
    protected $clientType = null;

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
     * @throws ConnectionException
     */
    public function __construct( $brokerUri = null ) {

        if ( !is_null( $brokerUri ) ) {
            $connection = new Connection( $brokerUri );
        } else {
            $connection = new Connection( INIT::$QUEUE_BROKER_ADDRESS, 2 );
        }

        $connection->setReadTimeout( 0, 250000 );
        parent::__construct( new Client( $connection ) );

    }

    /**
     * Lazy connection
     *
     * Get the connection to Redis server and return it
     *
     * @throws ReflectionException
     * @throws PredisException
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
     * @throws StompException
     */
    public function subscribe( $destination, $selector = null, $ack = 'client-individual', array $header = [] ) {

        $this->clientType = self::CLIENT_TYPE_SUBSCRIBER;
        $this->queueName  = $destination;

        return parent::subscribe( '/queue/' . INIT::$INSTANCE_ID . "_" . $destination, $selector, $ack, $header );

    }

    /**
     * @param string         $destination
     * @param Message $message
     *
     * @return bool
     * @throws StompException
     */
    public function publishToQueues( $destination, Message $message ) {

        $this->clientType = self::CLIENT_TYPE_PUBLISHER;
        return $this->_send( '/queue/' . INIT::$INSTANCE_ID . "_" . $destination, $message );

    }

    /**
     * @param string  $destination
     * @param Message $message
     *
     * @return bool
     * @throws StompException
     */
    private function _send( $destination, Message $message ) {
        return $this->send( $destination, $message );
    }

    /**
     * @param string         $destination
     * @param Message $message
     *
     * @return bool
     * @throws StompException
     */
    public function publishToTopic( $destination, Message $message ) {

        $this->clientType = self::CLIENT_TYPE_PUBLISHER;
        return $this->_send( $destination, $message );

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

        $queue_interface_url = INIT::$QUEUE_JMX_ADDRESS . "/api/jolokia/read/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=" . (int)INIT::$INSTANCE_ID . "_" . $queue . "/QueueSize";

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

        $queue_interface_url = INIT::$QUEUE_JMX_ADDRESS . "/api/jolokia/read/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=" . (int)INIT::$INSTANCE_ID . "_" . $queue . "/ConsumerCount";

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

}