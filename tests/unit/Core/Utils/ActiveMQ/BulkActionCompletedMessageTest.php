<?php

namespace Matecat\Core\Utils\ActiveMQ;

use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\ActiveMQ\BulkActionCompletedMessage;

class BulkActionCompletedMessageTest extends AbstractTest
{
    private function chunk(): JobStruct
    {
        return new JobStruct(['id' => 42, 'password' => 'chunkpw', 'id_project' => 7]);
    }

    /**
     * The node server routes on `data.id_job` and forwards only `data.payload`, so the payload has
     * to carry everything the browser needs: the password to tell the chunks of a split job apart,
     * and the id_client to recognise its own action.
     */
    #[Test]
    public function buildsTheEnvelopeTheNodeServerRoutesToTheJobRoom(): void
    {
        $message = BulkActionCompletedMessage::build(
            $this->chunk(),
            BulkActionCompletedMessage::ACTION_BULK_STATUS_CHANGE,
            [3, 1, 2],
            'APPROVED',
            'tab-1',
        );

        $this->assertSame([
            '_type' => 'bulk_action_completed',
            'data' => [
                'id_job' => 42,
                'passwords' => 'chunkpw',
                'payload' => [
                    'action' => 'bulk_status_change',
                    'id_job' => 42,
                    'password' => 'chunkpw',
                    'id_client' => 'tab-1',
                    'segment_ids' => [3, 1, 2],
                    'status' => 'APPROVED',
                ],
            ],
        ], $message);
    }

    #[Test]
    public function defaultsStatusAndClientToNull(): void
    {
        $payload = BulkActionCompletedMessage::build(
            $this->chunk(),
            BulkActionCompletedMessage::ACTION_COPY_SOURCE_TO_TARGET,
            [],
        )['data']['payload'];

        $this->assertNull($payload['status']);
        $this->assertNull($payload['id_client']);
        $this->assertSame([], $payload['segment_ids']);
    }

    /**
     * A request without the parameter reads it as an empty string; the browser should see that as
     * "no tab", not as a tab whose id is empty.
     */
    #[Test]
    public function treatsAnEmptyClientIdAsUnknown(): void
    {
        $payload = BulkActionCompletedMessage::build(
            $this->chunk(),
            BulkActionCompletedMessage::ACTION_REPLACE_ALL,
            [1],
            null,
            '',
        )['data']['payload'];

        $this->assertNull($payload['id_client']);
    }

    /**
     * Ids read back from a queue message or a DB row arrive as strings, and a filtered array keeps
     * its holes; the browser gets a plain list of integers either way.
     */
    #[Test]
    public function normalisesSegmentIdsToAListOfIntegers(): void
    {
        $payload = BulkActionCompletedMessage::build(
            $this->chunk(),
            BulkActionCompletedMessage::ACTION_PROPAGATION,
            [5 => '10', 9 => 20],
            'TRANSLATED',
        )['data']['payload'];

        $this->assertSame([10, 20], $payload['segment_ids']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function actions(): array
    {
        return [
            'bulk status change' => [BulkActionCompletedMessage::ACTION_BULK_STATUS_CHANGE, 'bulk_status_change'],
            'copy source to target' => [BulkActionCompletedMessage::ACTION_COPY_SOURCE_TO_TARGET, 'copy_source_to_target'],
            'replace all' => [BulkActionCompletedMessage::ACTION_REPLACE_ALL, 'replace_all'],
            'undo replace all' => [BulkActionCompletedMessage::ACTION_UNDO_REPLACE_ALL, 'undo_replace_all'],
            'propagation' => [BulkActionCompletedMessage::ACTION_PROPAGATION, 'propagation'],
        ];
    }

    /**
     * The action names are a contract with the browser, so a rename must break a test.
     */
    #[Test]
    #[DataProvider('actions')]
    public function keepsTheActionNamesTheBrowserMatchesOn(string $action, string $wireName): void
    {
        $this->assertSame($wireName, $action);
        $this->assertSame(
            $wireName,
            BulkActionCompletedMessage::build($this->chunk(), $action, [])['data']['payload']['action']
        );
    }
}
