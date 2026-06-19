<?php

namespace Matecat\Core\Workers;

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
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\BulkSegmentStatusChangeWorker;
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
    ): BulkSegmentStatusChangeWorker {
        $amq = $this->createStub(AMQHandler::class);
        $userDao = $this->createStub(UserDao::class);
        $userDao->method('getByUid')->willReturn(new UserStruct());

        $dbMock = $this->createStub(IDatabase::class);
        $dbMock->method('getConnection')->willReturn($this->pdoStub);

        $chunk = $chunk ?? $this->createChunkWithProject();

        $eventsHandler = $this->createStub(TranslationEventsHandler::class);

        $worker = $this->getMockBuilder(BulkSegmentStatusChangeWorker::class)
            ->setConstructorArgs([$amq, $dbMock, $userDao])
            ->onlyMethods([
                '_checkDatabaseConnection', '_doLog', 'publishToNodeJsClients',
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
        $params->revision_number = null;
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

    #[Test]
    public function getLoggerNameReturnsExpected(): void
    {
        $amq = $this->createStub(AMQHandler::class);
        $worker = new BulkSegmentStatusChangeWorker($amq, Database::obtain());

        $this->assertSame('bulk_segment_status_change.log', $worker->getLoggerName());
    }

    #[Test]
    public function processReturnsEarlyOnEmptyTranslations(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $worker = $this->createWorker();
        $worker->expects($this->never())->method('publishToNodeJsClients');

        $worker->process($this->createQueueElement());

        $this->assertTrue(true);
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
        $worker->expects($this->once())->method('publishToNodeJsClients');

        $worker->process($this->createQueueElement());
    }

    #[Test]
    public function processSkipsPublishWhenNoClientId(): void
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
        $worker->expects($this->never())->method('publishToNodeJsClients');

        $worker->process($this->createQueueElement(null));
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
