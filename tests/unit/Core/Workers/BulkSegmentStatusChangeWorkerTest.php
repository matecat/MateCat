<?php

namespace Matecat\Core\Workers;

use ArrayObject;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataStruct;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use Stomp\Exception\ConnectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\BulkActionCompletedMessage;
use Utils\AsyncTasks\Workers\BulkSegmentStatusChangeWorker;
use Utils\Constants\SourcePages;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

#[AllowMockObjectsWithoutExpectations]
class BulkSegmentStatusChangeWorkerTest extends AbstractTest
{
    private \PDO $pdoStub;
    private \PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;
        [, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function createChunkWithProject(): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = 1;
        $chunk->password = 'abc';

        $project = new ProjectStruct();
        $project->id = 10;

        $ref = new \ReflectionProperty(AbstractDaoObjectStruct::class, 'cached_results');
        $ref->setValue($chunk, ['Model\Jobs\JobStruct::getProject' => $project]);

        return $chunk;
    }

    private function createWorker(
        ?JobStruct $chunk = null,
        bool $userResolves = true,
    ): BulkSegmentStatusChangeWorker {
        $amq = $this->createStub(AMQHandler::class);
        $userDao = $this->createStub(UserDao::class);
        $userDao->method('getByUid')->willReturn($userResolves ? new UserStruct() : null);

        $dbMock = $this->createStub(IDatabase::class);
        $dbMock->method('getConnection')->willReturn($this->pdoStub);
        // process() does its work inside a scope; unconfigured, transaction() would return null
        // without ever running the body and every assertion here would pass against nothing.
        $dbMock->method('transaction')->willReturnCallback(static fn(callable $work) => $work());

        $chunk = $chunk ?? $this->createChunkWithProject();

        $eventsHandler = $this->createStub(TranslationEventsHandler::class);

        $worker = $this->getMockBuilder(BulkSegmentStatusChangeWorker::class)
            ->setConstructorArgs([$amq, $dbMock, $userDao])
            ->onlyMethods([
                '_checkDatabaseConnection', 'publishToNodeJsClients',
                'createJobStruct', 'createTranslationEventsHandler',
                'createTranslationEvent'
            ])
            ->getMock();

        $worker->method('createJobStruct')->willReturn($chunk);
        $worker->method('createTranslationEventsHandler')->willReturn($eventsHandler);

        return $worker;
    }

    private function createQueueElement(?string $clientId = 'client1'): QueueElement
    {
        $params = new Params();
        $params->chunk = ['id' => 1, 'password' => 'abc'];
        $params->destination_status = 'TRANSLATED';
        $params->client_id = $clientId;
        $params->id_user = 5;
        $params->source_page = SourcePages::SOURCE_PAGE_TRANSLATE;
        $params->segment_ids = [10, 20, 30];

        $queueElement = new QueueElement();
        $queueElement->params = $params;
        $queueElement->reQueueNum = 0;

        return $queueElement;
    }

    /**
     * Configure the stmtStub to return a metadata row with translation_versions
     * as the first fetchAll call (for loadForProject), then the given segment
     * rows for subsequent calls.
     *
     * @param list<SegmentTranslationStruct> $segmentRows
     */
    private function configureStmtForTranslationVersions(array $segmentRows = []): void
    {
        $metadataRow = new MetadataStruct();
        $metadataRow->key = 'features';
        $metadataRow->value = 'translation_versions';
        $metadataRow->id_project = 10;

        $this->stmtStub->method('fetchAll')->willReturnOnConsecutiveCalls(
            [$metadataRow],    // loadForProject metadata query
            $segmentRows       // getAllSegmentsByIdListAndJobId
        );
    }

    /**
     * Records every message the worker publishes, in order.
     *
     * @return ArrayObject<int, array<string, mixed>>
     */
    private function recordPublishes(BulkSegmentStatusChangeWorker&MockObject $worker): ArrayObject
    {
        $published = new ArrayObject();
        $worker->method('publishToNodeJsClients')->willReturnCallback(
            static function (mixed $message) use ($published): void {
                $published[] = $message;
            }
        );

        return $published;
    }

    /**
     * Nothing to change still completes the action, so the job room is told, with no segments, and
     * a tab waiting on it is released. The per-client message stays silent, as it always was.
     */
    #[Test]
    public function processAnnouncesAnEmptyCompletionOnEmptyTranslations(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $worker = $this->createWorker();
        $published = $this->recordPublishes($worker);

        $worker->process($this->createQueueElement());

        $this->assertCount(1, $published);
        $this->assertSame(BulkActionCompletedMessage::TYPE, $published[0]['_type']);
        $this->assertSame([], $published[0]['data']['payload']['segment_ids']);
    }

    #[Test]
    public function processUpdatesTranslationsAndPublishes(): void
    {
        $seg = new SegmentTranslationStruct();
        $seg->id_segment = 10;
        $seg->id_job = 1;
        $seg->status = 'DRAFT';
        $seg->segment_hash = 'abc';

        $this->stmtStub->method('fetchAll')->willReturn([$seg]);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $worker = $this->createWorker();
        $published = $this->recordPublishes($worker);

        $worker->process($this->createQueueElement());

        $this->assertCount(2, $published);

        [$completed, $legacy] = $published->getArrayCopy();
        $this->assertSame([
            '_type' => BulkActionCompletedMessage::TYPE,
            'data' => [
                'id_job' => 1,
                'passwords' => 'abc',
                'payload' => [
                    'action' => BulkActionCompletedMessage::ACTION_BULK_STATUS_CHANGE,
                    'id_job' => 1,
                    'password' => 'abc',
                    'id_client' => 'client1',
                    'segment_ids' => [10, 20, 30],
                    'status' => 'TRANSLATED',
                ],
            ],
        ], $completed);

        // The per-client message the editor already listens to is unchanged.
        $this->assertSame('bulk_segment_status_change', $legacy['_type']);
        $this->assertSame('client1', $legacy['data']['id_client']);
        $this->assertSame(['segment_ids' => [10, 20, 30], 'status' => 'TRANSLATED'], $legacy['data']['payload']);
    }

    #[Test]
    public function processAnnouncesOnlyTheCompletionWhenNoClientId(): void
    {
        $seg = new SegmentTranslationStruct();
        $seg->id_segment = 10;
        $seg->id_job = 1;
        $seg->status = 'DRAFT';
        $seg->segment_hash = 'abc';

        $this->stmtStub->method('fetchAll')->willReturn([$seg]);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $worker = $this->createWorker();
        $published = $this->recordPublishes($worker);

        $worker->process($this->createQueueElement(null));

        $this->assertCount(1, $published);
        $this->assertSame(BulkActionCompletedMessage::TYPE, $published[0]['_type']);
        $this->assertNull($published[0]['data']['payload']['id_client']);
        $this->assertSame([10, 20, 30], $published[0]['data']['payload']['segment_ids']);
    }

    /**
     * The status change is committed when the notification goes out. A broker failure that escaped
     * would requeue the message and book every revision event a second time.
     */
    #[Test]
    public function processSwallowsABrokerFailureAfterTheCommit(): void
    {
        $seg = new SegmentTranslationStruct();
        $seg->id_segment = 10;
        $seg->id_job = 1;
        $seg->status = 'DRAFT';
        $seg->segment_hash = 'abc';

        $this->stmtStub->method('fetchAll')->willReturn([$seg]);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $worker = $this->createWorker();
        $worker->method('publishToNodeJsClients')->willThrowException(new ConnectionException('Broker unreachable'));

        $worker->process($this->createQueueElement(null));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function processWithTranslationVersionsAddsEvents(): void
    {
        $seg = new SegmentTranslationStruct();
        $seg->id_segment = 10;
        $seg->id_job = 1;
        $seg->status = 'DRAFT';
        $seg->segment_hash = 'abc';

        $this->configureStmtForTranslationVersions([$seg]);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $chunk = $this->createChunkWithProject();
        $translationEvent = $this->createStub(TranslationEvent::class);

        $worker = $this->createWorker($chunk);
        $worker->method('createTranslationEvent')->willReturn($translationEvent);

        $worker->process($this->createQueueElement());

        $this->assertTrue(true);
    }

    /**
     * Every chunk-review update the batch performs is booked to this user, so the message fails
     * rather than going out unattributed. Before the actor was threaded, a vanished user surfaced
     * downstream as uid 0, indistinguishable from a legitimate update.
     */
    #[Test]
    public function processThrowsEndQueueWhenTheEnqueuingUserNoLongerExists(): void
    {
        $worker = $this->createWorker(userResolves: false);

        $this->expectException(EndQueueException::class);
        $this->expectExceptionMessage('Cannot resolve the user 5 that enqueued this bulk status change');
        $worker->process($this->createQueueElement());
    }

    /**
     * A message published before the phase travelled in the payload carries no source_page at all.
     * Assuming the translate page there would book its revision events to the wrong phase, so the
     * message has to fail.
     */
    #[Test]
    public function processThrowsEndQueueWhenTheSourcePageIsMissingFromThePayload(): void
    {
        $worker = $this->createWorker();

        $queueElement = $this->createQueueElement();
        unset($queueElement->params->source_page);

        $this->expectException(EndQueueException::class);
        $this->expectExceptionMessage('Missing the source_page of this bulk status change');
        $worker->process($queueElement);
    }

    #[Test]
    public function processThrowsEndQueueOnTranslationEventException(): void
    {
        $seg = new SegmentTranslationStruct();
        $seg->id_segment = 10;
        $seg->id_job = 1;
        $seg->status = 'DRAFT';
        $seg->segment_hash = 'abc';

        $this->configureStmtForTranslationVersions([$seg]);

        $chunk = $this->createChunkWithProject();

        $worker = $this->createWorker($chunk);
        $worker->method('createTranslationEvent')->willThrowException(new Exception('Job deleted'));

        $this->expectException(EndQueueException::class);
        $worker->process($this->createQueueElement());
    }

    #[Test]
    public function processThrowsEndQueueOnMaxRequeue(): void
    {
        $worker = $this->createWorker();

        $queueElement = $this->createQueueElement();
        $queueElement->reQueueNum = 3;

        $this->expectException(EndQueueException::class);
        $worker->process($queueElement);
    }
}
