<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 30/04/15
 * Time: 19.21
 *
 */

namespace Utils\ActiveMQ;

use Exception;
use Predis;
use ReflectionException;
use Stomp\Client;
use Stomp\Exception\ConnectionException;
use Stomp\Network\Connection;
use Stomp\StatefulStomp;
use Stomp\Transport\Frame;
use Stomp\Transport\Message;
use Utils\AsyncTasks\Workers\Analysis\RedisKeys;
use Utils\Logger\MatecatLogger;
use Utils\Network\MultiCurlHandler;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\Tools\Utils;

class AMQHandler {

    /**
     * @var RedisHandler
     */
    protected RedisHandler $redisHandler;

    /**
     * @var StatefulStomp
     */
    protected StatefulStomp $statefulStomp;
    /**
     * @var Connection
     */
    protected static Connection $staticStompConnection;
    protected ?string           $clientType = null;

    const CLIENT_TYPE_PUBLISHER  = 'Publisher';
    const CLIENT_TYPE_SUBSCRIBER = 'Subscriber';

    public string $persistent = 'true';

    /**
     * Handle a string for the queue name
     * @var string|null
     *
     */
    protected ?string $queueName = null;

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
                    self::$staticStompConnection = new Connection( AppConfig::$QUEUE_BROKER_ADDRESS, 2 );
                }
            }

            $connection = self::$staticStompConnection;

        } else {

            if ( !is_null( $brokerUri ) ) {
                $connection = new Connection( $brokerUri, 2 );
            } else {
                $connection = new Connection( AppConfig::$QUEUE_BROKER_ADDRESS, 2 );
            }

        }

        $connection->setReadTimeout( 2, 500000 );

        $this->statefulStomp = new StatefulStomp( new Client( $connection ) );

    }

    public static function getNewInstanceForDaemons(): AMQHandler {
        return new self( null, false );
    }

    /**
     * @return Client
     */
    public function getClient(): Client {
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
    public function getRedisClient(): Predis\Client {
        if ( empty( $this->redisHandler ) ) {
            $this->redisHandler = new RedisHandler();
        }

        return $this->redisHandler->getConnection();
    }

    /**
     *
     * @param string $destination
     * @param ?mixed $selector
     * @param string $ack
     * @param array  $header
     *
     * @return int
     */
    public function subscribe( string $destination, $selector = null, string $ack = 'client-individual', array $header = [] ): int {

        $this->clientType = self::CLIENT_TYPE_SUBSCRIBER;
        $this->queueName  = $destination;

        return $this->statefulStomp->subscribe( '/queue/' . AppConfig::$INSTANCE_ID . "_" . $destination, $selector, $ack, $header );

    }

    /**
     * @param string  $destination
     * @param Message $message
     *
     * @return bool
     */
    public function publishToQueues( string $destination, Message $message ): bool {

        $this->clientType = self::CLIENT_TYPE_PUBLISHER;

        return $this->statefulStomp->send( '/queue/' . AppConfig::$INSTANCE_ID . "_" . $destination, $message );

    }

    /**
     * Clean connections
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Clean connections
     */
    public function close() {
        $this->statefulStomp->getClient()->disconnect();
    }

    /**
     * @param string  $destination
     * @param Message $message
     *
     * @return bool
     */
    public function publishToNodeJsClients( string $destination, Message $message ): bool {

        $this->clientType = self::CLIENT_TYPE_PUBLISHER;

        return $this->statefulStomp->send( $destination, $message );

    }

    /**
     * Get the queue Length
     *
     * @param string|null $queueName
     *
     * @return mixed
     * @throws Exception
     */
    public function getQueueLength( ?string $queueName = null ) {

        if ( !empty( $queueName ) ) {
            $queue = $queueName;
        } elseif ( !empty( $this->queueName ) ) {
            $queue = $this->queueName;
        } else {
            throw new Exception( 'No queue name provided.' );
        }

        $queue_interface_url = AppConfig::$QUEUE_JMX_ADDRESS . "/api/jolokia/read/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=" . AppConfig::$INSTANCE_ID . "_" . $queue . "/QueueSize";

        return $this->callAmqJmx( $queue_interface_url );

    }

    /**
     * Get the number of consumers for this queue
     *
     * @param string|null $queueName
     *
     * @return mixed
     * @throws Exception
     */
    public function getConsumerCount( ?string $queueName = null ) {

        if ( !empty( $queueName ) ) {
            $queue = $queueName;
        } elseif ( !empty( $this->queueName ) ) {
            $queue = $this->queueName;
        } else {
            throw new Exception( 'No queue name provided.' );
        }

        $queue_interface_url = AppConfig::$QUEUE_JMX_ADDRESS . "/api/jolokia/read/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=" . AppConfig::$INSTANCE_ID . "_" . $queue . "/ConsumerCount";

        return $this->callAmqJmx( $queue_interface_url );

    }

    /**
     * Called from web interface, manage the Exception
     *
     * @param null $qid
     *
     * @return string|null
     * @throws Exception
     */
    public function getActualForQID( $qid = null ): ?string {

        if ( empty( $qid ) ) {
            throw new Exception( 'Can Not get values without a Queue ID. Use ' . AMQHandler::class . '::setQueueID  or pass a queue id to this method' );
        }

        return $this->getRedisClient()->get( RedisKeys::TOTAL_SEGMENTS_TO_WAIT . $qid );

    }

    /**
     * @throws Exception
     */
    public function reQueue( QueueElement $failed_segment, Context $queueInfo, MatecatLogger $logger ) {
        $logger->debug( "Message ReQueue. Failed.", $failed_segment->toArray() );
        $this->publishToQueues( $queueInfo->queue_name, new Message( strval( $failed_segment ), [ 'persistent' => $this->persistent ] ) );
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
                CURLOPT_USERAGENT      => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 5, // a timeout to call itself should not be too much higher :D
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => [ 'Authorization: Basic ' . base64_encode( AppConfig::$QUEUE_CREDENTIALS ) ]
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