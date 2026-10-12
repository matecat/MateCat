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
use JsonException;
use Predis;
use ReflectionException;
use RuntimeException;
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

class AMQHandler
{

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
    protected ?string $clientType = null;

    const string CLIENT_TYPE_PUBLISHER = 'Publisher';
    const string CLIENT_TYPE_SUBSCRIBER = 'Subscriber';

    /**
     * Delay of the first requeue (1 s). Each next requeue doubles it. See reQueueDelayMs().
     */
    const int REQUEUE_BASE_DELAY_MS = 1000;

    /**
     * Longest delay between two requeues (5 minutes). Reached at the 10th requeue.
     */
    const int REQUEUE_MAX_DELAY_MS = 300000;

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
     * @param string|null $brokerUri
     * @param bool $usePersistentConnection
     * @param bool $sync
     * @param StatefulStomp|null $preconfiguredStomp Test-only injection: bypasses all connection setup
     *
     * @throws ConnectionException
     */
    public function __construct(?string $brokerUri = null, bool $usePersistentConnection = true, bool $sync = true, ?StatefulStomp $preconfiguredStomp = null)
    {
        if ($preconfiguredStomp !== null) {
            $this->statefulStomp = $preconfiguredStomp;
            return;
        }

        if ($usePersistentConnection) {
            if (!isset(self::$staticStompConnection)) {
                if (!is_null($brokerUri)) {
                    self::$staticStompConnection = new Connection($brokerUri, 2);
                } else {
                    self::$staticStompConnection = new Connection(AppConfig::$QUEUE_BROKER_ADDRESS, 2);
                }
            }

            $connection = self::$staticStompConnection;
        } elseif (!is_null($brokerUri)) {
            $connection = new Connection($brokerUri, 2);
        } else {
            $connection = new Connection(AppConfig::$QUEUE_BROKER_ADDRESS, 2);
        }

        $connection->setReadTimeout(2, 500000);

        $client = new Client($connection);
        $client->setSync($sync);
        $this->statefulStomp = new StatefulStomp($client);
    }

    /**
     * @throws ConnectionException
     */
    public static function getNewInstanceForDaemons(): AMQHandler
    {
        return new self(null, false, false);
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->statefulStomp->getClient();
    }

    public function ack(Frame $frame): void
    {
        $this->statefulStomp->ack($frame);
    }

    public function nack(Frame $frame): void
    {
        $this->statefulStomp->nack($frame);
    }

    /**
     * @return false|Frame
     */
    public function read(): Frame|false
    {
        return $this->statefulStomp->read();
    }

    /**
     * Lazy connection
     *
     * Get the connection to Redis server and return it
     *
     * @return Predis\Client
     * @throws ReflectionException
     * @throws Exception
     */
    public function getRedisClient(): Predis\Client
    {
        if (empty($this->redisHandler)) {
            $this->redisHandler = new RedisHandler();
        }

        return $this->redisHandler->getConnection();
    }

    /**
     *
     * @param string $destination
     * @param ?string $selector
     * @param string $ack
     * @param array<string, mixed> $header
     *
     * @return int
     */
    public function subscribe(string $destination, ?string $selector = null, string $ack = 'client-individual', array $header = []): int
    {
        $this->clientType = self::CLIENT_TYPE_SUBSCRIBER;
        $this->queueName = $destination;

        return $this->statefulStomp->subscribe('/queue/' . AppConfig::$INSTANCE_ID . "_" . $destination, $selector, $ack, $header);
    }

    /**
     * @param string $destination
     * @param Message $message
     *
     * @return bool
     */
    public function publishToQueues(string $destination, Message $message): bool
    {
        $this->clientType = self::CLIENT_TYPE_PUBLISHER;

        return $this->statefulStomp->send('/queue/' . AppConfig::$INSTANCE_ID . "_" . $destination, $message);
    }

    /**
     * Clean connections
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Clean connections
     */
    public function close(): void
    {
        $this->statefulStomp->getClient()->disconnect();
    }

    /**
     * @param string $destination
     * @param Message $message
     *
     * @return bool
     */
    public function publishToNodeJsClients(string $destination, Message $message): bool
    {
        $this->clientType = self::CLIENT_TYPE_PUBLISHER;

        return $this->statefulStomp->send($destination, $message);
    }

    /**
     * Get the queue Length
     *
     * @param string|null $queueName
     *
     * @return int
     * @throws Exception
     */
    public function getQueueLength(?string $queueName = null): int
    {
        if (!empty($queueName)) {
            $queue = $queueName;
        } elseif (!empty($this->queueName)) {
            $queue = $this->queueName;
        } else {
            throw new Exception('No queue name provided.');
        }

        $queue_interface_url = AppConfig::$QUEUE_JMX_ADDRESS . "/api/jolokia/read/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=" . AppConfig::$INSTANCE_ID . "_" . $queue . "/QueueSize";

        return (int)$this->callAmqJmx($queue_interface_url);
    }

    /**
     * Get the number of consumers for this queue
     *
     * @param string|null $queueName
     *
     * @return int
     * @throws Exception
     */
    public function getConsumerCount(?string $queueName = null): int
    {
        if (!empty($queueName)) {
            $queue = $queueName;
        } elseif (!empty($this->queueName)) {
            $queue = $this->queueName;
        } else {
            throw new Exception('No queue name provided.');
        }

        $queue_interface_url = AppConfig::$QUEUE_JMX_ADDRESS . "/api/jolokia/read/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=" . AppConfig::$INSTANCE_ID . "_" . $queue . "/ConsumerCount";

        return (int)$this->callAmqJmx($queue_interface_url);
    }

    /**
     * Called from web interface, manage the Exception
     *
     * @param ?int $qid
     *
     * @return string|null
     * @throws Exception
     */
    public function getActualForQID(?int $qid = null): ?string
    {
        if (empty($qid)) {
            throw new Exception('Cannot get values without a Queue ID. Use ' . AMQHandler::class . '::setQueueID  or pass a queue id to this method');
        }

        return $this->getRedisClient()->get(RedisKeys::TOTAL_SEGMENTS_TO_WAIT . $qid);
    }

    /**
     * @throws Exception
     */
    public function reQueue(QueueElement $failed_segment, Context $queueInfo, MatecatLogger $logger): void
    {
        $delay = self::reQueueDelayMs($failed_segment->reQueueNum);
        $logger->debug("Message ReQueue. Failed. Delayed by $delay ms.", $failed_segment->toArray());
        $this->publishToQueues($queueInfo->queue_name, new Message(strval($failed_segment), [
            'persistent' => $this->persistent,
            // Needs schedulerSupport="true" on the broker; without it the header is ignored
            // and the message is delivered at once
            'AMQ_SCHEDULED_DELAY' => (string)$delay,
        ]));
    }

    /**
     * Exponential backoff for a requeued message: the delay doubles at each requeue, up to a cap.
     *
     * delay = min(REQUEUE_BASE_DELAY_MS * 2^(reQueueNum - 1), REQUEUE_MAX_DELAY_MS)
     *
     *   reQueueNum | delay     | time since the first failure
     *   -----------|-----------|-----------------------------
     *        1     |    1 s    |    1 s
     *        2     |    2 s    |    3 s
     *        3     |    4 s    |    7 s
     *        4     |    8 s    |   15 s
     *        5     |   16 s    |   31 s
     *        6     |   32 s    |  ~1 min
     *        7     |  ~1 min   |  ~2 min
     *        8     |  ~2 min   |  ~4 min
     *        9     |  ~4 min   |  ~8.5 min
     *       10+    |   5 min   |  +5 min per requeue
     *       99     |   5 min   |  ~7.6 hours
     *
     * With the default AbstractWorker::$maxRequeueNum = 100, a message that keeps failing is
     * dropped about 7.6 hours after its first failure. A worker with a lower limit gives up
     * sooner: BulkSegmentStatusChangeWorker (3) after 3 seconds.
     *
     * reQueueNum is already incremented when this runs, so the first requeue passes 1.
     *
     * @param int $reQueueNum How many times the message has been requeued, this time included
     *
     * @return int The delay in milliseconds, for the AMQ_SCHEDULED_DELAY header
     */
    public static function reQueueDelayMs(int $reQueueNum): int
    {
        // The first requeue waits the base delay, every next one waits twice as long
        $doubling = max($reQueueNum - 1, 0);

        // A large count overflows into a float (up to INF): the cap brings it back to an int
        $delay = self::REQUEUE_BASE_DELAY_MS * 2 ** $doubling;

        return (int)min($delay, self::REQUEUE_MAX_DELAY_MS);
    }

    /**
     * @param string $queue_interface_url
     *
     * @return mixed
     * @throws JsonException
     * @throws RuntimeException
     * @throws Exception
     */
    public function callAmqJmx(string $queue_interface_url): mixed
    {
        $mHandler = new MultiCurlHandler();

        $options = [
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            CURLOPT_CONNECTTIMEOUT => 5, // a timeout to call itself should not be too much higher :D
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode(AppConfig::$QUEUE_CREDENTIALS)]
        ];

        $resource = $mHandler->createResource($queue_interface_url, $options);
        $mHandler->multiExec();
        $result = $mHandler->getSingleContent($resource);
        $mHandler->multiCurlCloseAll();
        if (!is_string($result)) {
            throw new Exception('Failed to get response from AMQ JMX');
        }
        $result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

        return $result['value'];
    }

}