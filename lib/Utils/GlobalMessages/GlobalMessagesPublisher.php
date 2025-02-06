<?php

namespace GlobalMessages;

use AMQHandler;
use INIT;
use Stomp\Transport\Message;

class GlobalMessagesPublisher
{
    /**
     * @param AMQHandler $queueHandler
     * @param array $message
     */
    public static function emit(AMQHandler $queueHandler, array $message)
    {
        $amqMessagePayload = json_encode( [
            '_type' => 'global_messages',
            'data'  => [
                'payload' => [
                    'message' => $message
                ],
            ]
        ] );

        $queueHandler->publishToNodeJsClients( INIT::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message( $amqMessagePayload ) );
    }
}