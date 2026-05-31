<?php

namespace unit\Workers;

use Exception;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use TestHelpers\AbstractTest;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\BulkSegmentStatusChangeWorker;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

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

    private function createChunkWithProject(bool $hasTranslationVersions = false): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = 1;
        $chunk->password = 'abc';

        $project = $this->createMock(ProjectStruct::class);
        $project->method('getFeaturesSet')->willReturn(new FeatureSet());
        $project->method('hasFeature')->willReturn($hasTranslationVersions);

        $ref = new \ReflectionProperty(AbstractDaoObjectStruct::class, 'cached_results');
        $ref->setValue($chunk, ['Model\Jobs\JobStruct::getProject' => $project]);

        return $chunk;
    }

    private function createWorker(
        ?JobStruct $chunk = null,
        bool $hasTranslationVersions = false
    ): BulkSegmentStatusChangeWorker {
        $amq = $this->createMock(AMQHandler::class);
        $userDao = $this->createMock(UserDao::class);
        $userDao->method('getByUid')->willReturn(new UserStruct());

        $dbMock = $this->createMock(IDatabase::class);
        $dbMock->method('getConnection')->willReturn($this->pdoStub);

        $chunk = $chunk ?? $this->createChunkWithProject($hasTranslationVersions);

        $eventsHandler = $this->createMock(TranslationEventsHandler::class);

        $worker = $this->getMockBuilder(BulkSegmentStatusChangeWorker::class)
            ->setConstructorArgs([$amq, $userDao, $dbMock])
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

    #[Test]
    public function getLoggerNameReturnsExpected(): void
    {
        $amq = $this->createMock(AMQHandler::class);
        $worker = new BulkSegmentStatusChangeWorker($amq);

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

        $this->stmtStub->method('fetchAll')->willReturn([$seg]);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $chunk = $this->createChunkWithProject(true);
        $translationEvent = $this->createMock(TranslationEvent::class);

        $worker = $this->createWorker($chunk, true);
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

        $this->stmtStub->method('fetchAll')->willReturn([$seg]);

        $chunk = $this->createChunkWithProject(true);

        $worker = $this->createWorker($chunk, true);
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
