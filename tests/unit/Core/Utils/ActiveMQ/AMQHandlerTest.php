<?php


namespace Matecat\Core\Utils\ActiveMQ;

use Closure;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stomp\Client;
use Stomp\StatefulStomp;
use Stomp\Transport\Frame;
use Stomp\Transport\Message;
use Utils\ActiveMQ\AMQHandler;
use Utils\Logger\MatecatLogger;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\QueueElement;

class UtilsActiveMQAMQHandlerTest extends AbstractTest
{
    private ?Client $clientStub = null;
    private ?StatefulStomp $stompStub = null;
    private ?AMQHandler $handler = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientStub = $this->createStub(Client::class);
        $this->stompStub = $this->createStub(StatefulStomp::class);
        $this->stompStub->method('getClient')->willReturn($this->clientStub);
        $this->stompStub->method('send')->willReturn(true);

        $this->handler = new AMQHandler(preconfiguredStomp: $this->stompStub);
    }

    protected function tearDown(): void
    {
        $this->handler = null;
        $this->stompStub = null;
        $this->clientStub = null;
        parent::tearDown();
    }

    #[Test]
    public function constantsAreDefined(): void
    {
        $this->assertSame('Publisher', AMQHandler::CLIENT_TYPE_PUBLISHER);
        $this->assertSame('Subscriber', AMQHandler::CLIENT_TYPE_SUBSCRIBER);
    }

    #[Test]
    public function constructorWithPreconfiguredStompDoesNotThrow(): void
    {
        $stomp = $this->createStub(StatefulStomp::class);
        $client = $this->createStub(Client::class);
        $stomp->method('getClient')->willReturn($client);

        $handler = new AMQHandler(preconfiguredStomp: $stomp);

        $this->assertInstanceOf(AMQHandler::class, $handler);
        $handler->getClient(); // smoke test: no exceptions
    }

    #[Test]
    public function getClientReturnsStompClient(): void
    {
        $result = $this->handler->getClient();

        $this->assertSame($this->clientStub, $result);
    }

    #[Test]
    public function ackDelegatesToStatefulStomp(): void
    {
        $frame = $this->createStub(Frame::class);

        $this->handler->ack($frame);

        $this->assertTrue(true);
    }

    #[Test]
    public function nackDelegatesToStatefulStomp(): void
    {
        $frame = $this->createStub(Frame::class);

        $this->handler->nack($frame);

        $this->assertTrue(true);
    }

    #[Test]
    public function readReturnsFrame(): void
    {
        $frame = $this->createStub(Frame::class);
        $this->stompStub->method('read')->willReturn($frame);

        $result = $this->handler->read();

        $this->assertSame($frame, $result);
    }

    #[Test]
    public function readReturnsFalseWhenStatefulStompReturnsFalse(): void
    {
        $this->stompStub->method('read')->willReturn(false);

        $result = $this->handler->read();

        $this->assertFalse($result);
    }

    #[Test]
    public function subscribeReturnsResultFromStatefulStomp(): void
    {
        $this->stompStub->method('subscribe')->willReturn(1);

        $result = $this->handler->subscribe('test-queue');

        $this->assertSame(1, $result);
    }

    #[Test]
    public function publishToQueuesReturnsTrue(): void
    {
        $message = $this->createStub(Message::class);

        $result = $this->handler->publishToQueues('test-queue', $message);

        $this->assertTrue($result);
    }

    #[Test]
    public function publishToNodeJsClientsReturnsTrue(): void
    {
        $message = $this->createStub(Message::class);

        $result = $this->handler->publishToNodeJsClients('test-destination', $message);

        $this->assertTrue($result);
    }

    #[Test]
    public function closeCompletesWithoutError(): void
    {
        $this->handler->close();

        $this->assertTrue(true);
    }

    #[Test]
    public function getActualForQIDThrowsExceptionWithNullQid(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot get values without a Queue ID');

        $this->handler->getActualForQID(null);
    }

    #[Test]
    public function reQueueDelegatesToStompSend(): void
    {
        $failedSegment = $this->createStub(QueueElement::class);
        $failedSegment->method('toArray')->willReturn(['id' => 123]);
        $queueInfo = $this->createStub(Context::class);
        $queueInfo->queue_name = 'test-queue';
        $logger = $this->createStub(MatecatLogger::class);

        $this->handler->reQueue($failedSegment, $queueInfo, $logger);

        $this->assertTrue(true);
    }

    #[Test]
    public function reQueueSendsScheduledDelayFromRequeueCount(): void
    {
        $sent = null;
        $stomp = $this->createMock(StatefulStomp::class);
        $stomp->method('getClient')->willReturn($this->clientStub);
        $stomp->expects($this->once())->method('send')
            ->willReturnCallback(function (string $destination, Message $message) use (&$sent): bool {
                $sent = $message;

                return true;
            });
        $handler = new AMQHandler(preconfiguredStomp: $stomp);

        $failedSegment = new QueueElement();
        $failedSegment->reQueueNum = 3;
        $queueInfo = $this->createStub(Context::class);
        $queueInfo->queue_name = 'test-queue';

        $handler->reQueue($failedSegment, $queueInfo, $this->createStub(MatecatLogger::class));

        $this->assertInstanceOf(Message::class, $sent);
        $this->assertSame('4000', $sent->getHeaders()['AMQ_SCHEDULED_DELAY']);
    }

    #[Test]
    public function reQueueDelayGrowsExponentiallyUpToTheCap(): void
    {
        $this->assertSame(1000, AMQHandler::reQueueDelayMs(0));
        $this->assertSame(1000, AMQHandler::reQueueDelayMs(1));
        $this->assertSame(2000, AMQHandler::reQueueDelayMs(2));
        $this->assertSame(256000, AMQHandler::reQueueDelayMs(9));
        $this->assertSame(AMQHandler::REQUEUE_MAX_DELAY_MS, AMQHandler::reQueueDelayMs(10));
        $this->assertSame(AMQHandler::REQUEUE_MAX_DELAY_MS, AMQHandler::reQueueDelayMs(100));
        $this->assertSame(AMQHandler::REQUEUE_MAX_DELAY_MS, AMQHandler::reQueueDelayMs(PHP_INT_MAX));
    }

    #[Test]
    public function getQueueLengthThrowsExceptionWhenNoQueueName(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No queue name provided.');

        $this->handler->getQueueLength(null);
    }

    #[Test]
    public function getConsumerCountThrowsExceptionWhenNoQueueName(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No queue name provided.');

        $this->handler->getConsumerCount(null);
    }

    #[Test]
    public function getRedisClientReturnsConnectionFromRedisHandler(): void
    {
        $redisClient = new class extends \Predis\Client {
            public function __construct() {}
        };
        $redisHandler = $this->createStub(RedisHandler::class);
        $redisHandler->method('getConnection')->willReturn($redisClient);
        $this->setRedisHandler($redisHandler);

        $result = $this->handler->getRedisClient();

        $this->assertSame($redisClient, $result);
    }

    #[Test]
    public function getActualForQIDReturnsValueFromRedis(): void
    {
        $redisClient = new class extends \Predis\Client {
            public function __construct() {}
            public function get(string $key): string { return '42'; }
        };
        $redisHandler = $this->createStub(RedisHandler::class);
        $redisHandler->method('getConnection')->willReturn($redisClient);
        $this->setRedisHandler($redisHandler);

        $result = $this->handler->getActualForQID(123);

        $this->assertSame('42', $result);
    }

    #[Test]
    public function callAmqJmxThrowsExceptionOnInvalidUrl(): void
    {
        $this->expectException(\Exception::class);

        $this->handler->callAmqJmx('http://127.0.0.1:4/api/');
    }

    /**
     * Rows: the value Jolokia answers, expected size.
     *
     * @return array<string, array{int|string, int}>
     */
    public static function readableQueueSizeProvider(): array
    {
        return [
            'int value'    => [1234, 1234],
            'digit string' => ['1234', 1234],
            'empty queue'  => [0, 0],
        ];
    }

    #[Test]
    #[DataProvider('readableQueueSizeProvider')]
    public function readQueueSizeReturnsTheSizeReadWithTheGivenTimeout(int|string $value, int $expected): void
    {
        $calls = [];
        $handler = $this->handlerAnswering(['value' => $value, 'status' => 200], $calls);

        $this->assertSame($expected, $handler->readQueueSize('analysis_queue_P5', 3));
        $this->assertCount(1, $calls);
        $this->assertStringEndsWith(self::urlTail('analysis_queue_P5', 'QueueSize'), $calls[0][0]);
        $this->assertSame(3, $calls[0][1]);
    }

    /**
     * Rows: what Jolokia answers, or the exception its call throws.
     *
     * @return array<string, array{mixed}>
     */
    public static function unreadableQueueSizeProvider(): array
    {
        return [
            'Jolokia error body'         => [['error' => 'javax.management.InstanceNotFoundException', 'status' => 404]],
            'value not a number'         => [['value' => 'abc']],
            'negative value'             => [['value' => -1]],
            'value too large for an int' => [['value' => '99999999999999999999']],
            'body not a JSON object'     => [5],
            'Jolokia call throws'        => [new Exception('Failed to get response from AMQ JMX')],
        ];
    }

    #[Test]
    #[DataProvider('unreadableQueueSizeProvider')]
    public function readQueueSizeReturnsNullWhenTheSizeCannotBeRead(mixed $reply): void
    {
        $this->assertNull($this->handlerAnswering($reply)->readQueueSize('analysis_queue_P5', 3));
    }

    #[Test]
    public function getQueueLengthReadsTheQueueSizeUrlWithoutATotalTimeout(): void
    {
        $calls = [];
        $handler = $this->handlerAnswering(['value' => 42, 'status' => 200], $calls);

        $this->assertSame(42, $handler->getQueueLength('analysis_queue_P4'));
        $this->assertStringEndsWith(self::urlTail('analysis_queue_P4', 'QueueSize'), $calls[0][0]);
        $this->assertSame(0, $calls[0][1]);
    }

    #[Test]
    public function getConsumerCountReadsTheConsumerCountUrlWithoutATotalTimeout(): void
    {
        $calls = [];
        $handler = $this->handlerAnswering(['value' => 30, 'status' => 200], $calls);

        $this->assertSame(30, $handler->getConsumerCount('analysis_queue_P4'));
        $this->assertStringEndsWith(self::urlTail('analysis_queue_P4', 'ConsumerCount'), $calls[0][0]);
        $this->assertSame(0, $calls[0][1]);
    }

    /**
     * End of the Jolokia URL of a queue attribute.
     *
     * Example: ('analysis_queue_P4', 'QueueSize') gives 'destinationName=<INSTANCE_ID>_analysis_queue_P4/QueueSize'.
     */
    private static function urlTail(string $queueName, string $attribute): string
    {
        return 'destinationName=' . AppConfig::$INSTANCE_ID . '_' . $queueName . '/' . $attribute;
    }

    /**
     * Builds a handler whose Jolokia reads answer $reply (or throw it) with no HTTP call,
     * and records each call's URL and timeout in $calls.
     *
     * @param list<array{string, int}> $calls
     * @param-out list<array{string, int}> $calls
     */
    private function handlerAnswering(mixed $reply, array &$calls = []): AMQHandler
    {
        $jolokia = function (string $url, int $timeoutSeconds) use ($reply, &$calls): mixed {
            $calls[] = [$url, $timeoutSeconds];
            if ($reply instanceof Exception) {
                throw $reply;
            }

            return $reply;
        };

        return new class ($this->stompStub, $jolokia) extends AMQHandler {
            /**
             * @param Closure(string, int): mixed $jolokia
             */
            public function __construct(StatefulStomp $stomp, private readonly Closure $jolokia)
            {
                parent::__construct(preconfiguredStomp: $stomp);
            }

            protected function _jolokiaRead(string $url, int $timeoutSeconds): mixed
            {
                return ($this->jolokia)($url, $timeoutSeconds);
            }
        };
    }

    private function setRedisHandler(RedisHandler $redisHandler): void
    {
        $reflection = new \ReflectionClass(AMQHandler::class);
        $redisProp = $reflection->getProperty('redisHandler');
        $redisProp->setAccessible(true);
        $redisProp->setValue($this->handler, $redisHandler);
    }
}
