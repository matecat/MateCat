<?php

namespace Utils\ActiveMQ;

use Model\Jobs\JobStruct;

/**
 * Builds the socket notification sent when an action that writes many segments of a job at once
 * has finished writing them.
 *
 * The browser reloads the segments when it receives it. Reloading on the HTTP response alone is too
 * early for the actions whose write runs in a queue worker (bulk status change, propagation): the
 * worker may not have committed yet, and the reload shows the old state.
 *
 * The message goes to the `id_job` room, so every tab open on the job is told, not only the one
 * that started the action. A split job shares that room across its chunks, so the payload carries
 * the chunk password for the browser to filter on.
 */
final class BulkActionCompletedMessage
{
    public const string TYPE = 'bulk_action_completed';

    public const string ACTION_BULK_STATUS_CHANGE = 'bulk_status_change';
    public const string ACTION_COPY_SOURCE_TO_TARGET = 'copy_source_to_target';
    public const string ACTION_REPLACE_ALL = 'replace_all';
    public const string ACTION_UNDO_REPLACE_ALL = 'undo_replace_all';
    public const string ACTION_PROPAGATION = 'propagation';

    /**
     * @param JobStruct $chunk the chunk the action ran on
     * @param string $action one of the ACTION_* constants
     * @param array<int|string> $segmentIds the segments actually written, which may be none
     * @param string|null $status the status the segments were set to, when the action sets one
     * @param string|null $idClient the browser tab that started the action, when it is known
     *
     * @return array{_type: string, data: array{id_job: int|null, passwords: string|null, payload: array<string, mixed>}}
     */
    public static function build(JobStruct $chunk, string $action, array $segmentIds, ?string $status = null, ?string $idClient = null): array
    {
        return [
            '_type' => self::TYPE,
            'data' => [
                'id_job' => $chunk->id,
                'passwords' => $chunk->password,
                'payload' => [
                    'action' => $action,
                    'id_job' => $chunk->id,
                    'password' => $chunk->password,
                    'id_client' => ($idClient === null || $idClient === '') ? null : $idClient,
                    'segment_ids' => array_values(array_map('intval', $segmentIds)),
                    'status' => $status,
                ],
            ],
        ];
    }
}
