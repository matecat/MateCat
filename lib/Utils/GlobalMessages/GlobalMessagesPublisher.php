<?php

namespace GlobalMessages;

use AMQHandler;
use DateTime;
use DateTimeInterface;
use Exception;
use INIT;
use RedisHandler;
use Stomp\Transport\Message;

class GlobalMessagesPublisher
{
    const GLOBAL_MESSAGES_LIST_KEY = 'global_message_list_ids';
    const GLOBAL_MESSAGES_ELEMENT_KEY = 'global_message_list_element_';

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