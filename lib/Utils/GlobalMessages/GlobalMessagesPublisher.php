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
     * @throws Exception
     */
    public static function emit(AMQHandler $queueHandler)
    {
        $message = json_encode( [
            '_type' => 'global_messages',
            'data'  => [
                'payload' => [
                    'messages' => self::messages()
                ],
            ]
        ] );

        $queueHandler->publishToNodeJsClients( INIT::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message( $message ) );
    }

    /**
     * @return array

     * @throws Exception
     */
    private static function messages(): array
    {
        // pull messages from redis
        $redis      = ( new RedisHandler() )->getConnection();
        $ids        = $redis->smembers( self::GLOBAL_MESSAGES_LIST_KEY );
        $retStrings = [];

        foreach ( $ids as $id ) {
            $element = $redis->get( self::GLOBAL_MESSAGES_ELEMENT_KEY . $id );

            if ( $element !== null ) {
                $element = unserialize( $element );

                $resObject = [
                    'id'     => $id,
                    'title'  => $element[ 'title' ],
                    'msg'    => $element[ 'message' ],
                    'level'  => $element[ 'level' ],
                    'token'  => md5( $element[ 'message' ] ),
                    'expire' => ( new DateTime( $element[ 'expire' ] ) )->format( DateTimeInterface::W3C )
                ];

                $retStrings[] = $resObject;
            } else {
                $redis->srem( self::GLOBAL_MESSAGES_LIST_KEY, $id );
            }
        }

        return $retStrings;
    }
}