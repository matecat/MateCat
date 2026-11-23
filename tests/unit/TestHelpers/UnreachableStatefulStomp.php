<?php

namespace Matecat\TestHelpers;

use Stomp\Exception\ConnectionException;
use Stomp\Transport\Message;

/**
 * Simulates an unreachable broker: everything up to the send succeeds, and the send throws the
 * exception a real connection failure raises.
 */
class UnreachableStatefulStomp extends RecordingStatefulStomp
{
    public function send($destination, Message $message)
    {
        throw new ConnectionException('Broker unreachable');
    }
}
