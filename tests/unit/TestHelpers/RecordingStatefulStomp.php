<?php

namespace Matecat\TestHelpers;

use ReflectionClass;
use ReflectionException;
use Stomp\StatefulStomp;
use Stomp\Transport\Message;
use Utils\ActiveMQ\AMQHandler;

/**
 * A Stomp transport that records what is sent instead of reaching a broker.
 *
 * A hand-written subclass, not a PHPUnit double: a double of StatefulStomp can be torn down by the
 * cyclic collector before AMQHandler::__destruct() calls getClient() on it, which then returns null
 * and crashes. A plain subclass is freed by ordinary refcounting.
 *
 * Wrap it with {@see self::handler()} to get an AMQHandler that publishes into it.
 */
class RecordingStatefulStomp extends StatefulStomp
{
    /** @var list<array{0: string, 1: Message}> */
    public array $sentMessages = [];

    /**
     * Late-bound, so a subclass gets an instance of itself.
     *
     * @throws ReflectionException
     */
    public static function create(): static
    {
        return (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
    }

    /**
     * @return array{0: AMQHandler, 1: static}
     * @throws ReflectionException
     */
    public static function handler(): array
    {
        $stomp = static::create();

        return [new AMQHandler(preconfiguredStomp: $stomp), $stomp];
    }

    public function send($destination, Message $message)
    {
        $this->sentMessages[] = [$destination, $message];

        return true;
    }

    /**
     * The decoded bodies of the messages sent so far, in order.
     *
     * @return list<array<string, mixed>>
     */
    public function decodedBodies(): array
    {
        return array_map(
            static fn(array $sent): array => json_decode($sent[1]->getBody(), true),
            $this->sentMessages
        );
    }

    public function getClient()
    {
        return (new ReflectionClass(NoopStompClient::class))->newInstanceWithoutConstructor();
    }
}
