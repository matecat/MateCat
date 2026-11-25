<?php

namespace Controller\Traits;

use Model\Jobs\JobStruct;
use Stomp\Exception\ConnectionException;
use Stomp\Transport\Message;
use Throwable;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\BulkActionCompletedMessage;
use Utils\Registry\AppConfig;

/**
 * Lets a controller that writes many segments of a job tell every tab open on that job when the
 * write is done. See BulkActionCompletedMessage for the message and who receives it.
 */
trait PublishesBulkActionCompletedTrait
{
    /**
     * Call it once the write is committed: after transaction() returns, never from inside it.
     *
     * The segments are already saved when this runs, so a broker failure must not fail the request:
     * the notification is a best-effort live update and a tab that misses it reads the right state
     * on its next load.
     *
     * @param array<int|string> $segmentIds
     */
    protected function publishBulkActionCompleted(JobStruct $chunk, string $action, array $segmentIds, ?string $status = null): void
    {
        $clientId = $this->request->param('client_id');
        $message = BulkActionCompletedMessage::build(
            $chunk,
            $action,
            $segmentIds,
            $status,
            is_string($clientId) ? $clientId : null,
        );

        try {
            $this->getQueueHandler()->publishToNodeJsClients(
                AppConfig::$SOCKET_NOTIFICATIONS_QUEUE_NAME,
                new Message((string)json_encode($message), ['persistent' => 'false'])
            );
        } catch (Throwable $e) {
            $this->logger->error(
                'Failed to publish the bulk action completion to the socket queue: ' . $e->getMessage(),
                ['action' => $action, 'id_job' => $chunk->id]
            );
        }
    }

    /**
     * Test seam: a Testable subclass injects a queue handler backed by a stubbed transport, so unit
     * tests never open a broker connection.
     *
     * @throws ConnectionException
     */
    protected function getQueueHandler(): AMQHandler
    {
        return new AMQHandler();
    }
}
