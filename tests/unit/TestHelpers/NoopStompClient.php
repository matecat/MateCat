<?php

namespace Matecat\TestHelpers;

use Stomp\Client;

/**
 * The client {@see RecordingStatefulStomp} hands to AMQHandler::close(): there is no connection to
 * drop, so disconnecting does nothing.
 */
class NoopStompClient extends Client
{
    public function disconnect($sync = false)
    {
        return true;
    }
}
