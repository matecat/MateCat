<?php

declare(strict_types=1);

namespace Matecat\Core\Plugins\Features\ReviewExtended;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\WordCount\CounterModel;
use Model\WordCount\WordCountStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Plugins\Features\ReviewExtended\BatchReviewProcessor;
use Plugins\Features\ReviewExtended\ChunkReviewModel;
use Plugins\Features\ReviewExtended\ReviewedWordCountModel;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Utils\Registry\AppConfig;

class BatchReviewProcessorStubJobStruct extends JobStruct
{
    private ProjectStruct $projectStruct;

    public function __construct(array $data, ProjectStruct $projectStruct)
    {
        parent::__construct($data);
        $this->projectStruct = $projectStruct;
    }

    public function getProject(ProjectDao $dao, int $ttl = 86400): ProjectStruct
    {
        return $this->projectStruct;
    }
}

class BatchReviewProcessorTest extends AbstractTest
{
    private IDatabase&Stub $dbStub;
    private PDO&Stub $pdoStub;
    private PDOStatement&Stub $stmtStub;
    private ChunkReviewDao&Stub $chunkReviewDaoStub;
    private BatchReviewProcessorStubJobStruct $chunk;
    private static bool $originalSkipCache;

    protected function setUp(): void
    {
        parent::setUp();

        self::$originalSkipCache = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();

        $this->chunkReviewDaoStub = $this->createStub(ChunkReviewDao::class);
        $this->chunkReviewDaoStub->method('getDatabaseHandler')->willReturn($this->dbStub);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->id = 1;

        $this->chunk = new BatchReviewProcessorStubJobStruct([
            'id' => 10,
            'password' => 'test_pw',
            'new_words' => 100.0,
            'draft_words' => 50.0,
            'translated_words' => 30.0,
            'approved_words' => 20.0,
            'rejected_words' => 0.0,
            'approved2_words' => 0.0,
            'new_raw_words' => 100.0,
            'draft_raw_words' => 50.0,
            'translated_raw_words' => 30.0,
            'approved_raw_words' => 20.0,
            'approved2_raw_words' => 0.0,
            'rejected_raw_words' => 0.0,
        ], $projectStub);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = self::$originalSkipCache;
        parent::tearDown();
    }

    #[Test]
    public function constructorAcceptsInjectedDao(): void
    {
        $processor = new BatchReviewProcessor($this->chunkReviewDaoStub);
        $this->assertInstanceOf(BatchReviewProcessor::class, $processor);
    }

    #[Test]
    public function constructorUsesDefaultDaoWhenNoneProvided(): void
    {
        $processor = new BatchReviewProcessor();
        $this->assertInstanceOf(BatchReviewProcessor::class, $processor);
    }

    #[Test]
    public function setChunkReturnsSelf(): void
    {
        $processor = new BatchReviewProcessor($this->chunkReviewDaoStub);
        $result = $processor->setChunk($this->chunk);
        $this->assertSame($processor, $result);
    }

    #[Test]
    public function setPreparedEventsReturnsSelf(): void
    {
        $processor = new BatchReviewProcessor($this->chunkReviewDaoStub);
        $result = $processor->setPreparedEvents([]);
        $this->assertSame($processor, $result);
    }

    #[Test]
    public function processWithExistingChunkReviewsAndNoEvents(): void
    {
        $existingReview = new ChunkReviewStruct();
        $existingReview->id = 100;
        $existingReview->id_job = 10;
        $existingReview->password = 'test_pw';
        $existingReview->source_page = 2;

        $this->chunkReviewDaoStub->method('findChunkReviews')->willReturn([$existingReview]);

        $processor = new BatchReviewProcessor($this->chunkReviewDaoStub);
        $processor->setChunk($this->chunk);
        $processor->setPreparedEvents([]);
        $processor->process();

        $this->assertTrue(true);
    }

    #[Test]
    public function processCreatesChunkReviewWhenNoneExist(): void
    {
        $createdReview = $this->createStub(ChunkReviewStruct::class);
        $createdReview->id = 42;
        $createdReview->id_job = 10;
        $createdReview->id_project = 1;
        $createdReview->password = 'test_pw';
        $createdReview->source_page = 2;

        $createdReview->method('getChunk')->willReturn($this->chunk);

        $this->chunkReviewDaoStub->method('findChunkReviews')->willReturn([]);
        $this->chunkReviewDaoStub->method('createRecord')->willReturn($createdReview);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('setFetchMode')->willReturn(true);

        $processor = new BatchReviewProcessor($this->chunkReviewDaoStub);
        $processor->setChunk($this->chunk);
        $processor->setPreparedEvents([]);
        $processor->process();

        $this->assertTrue(true);
    }

    #[Test]
    public function processCallsCreateRecordWithCorrectData(): void
    {
        $createdReview = $this->createStub(ChunkReviewStruct::class);
        $createdReview->id = 55;
        $createdReview->id_job = 10;
        $createdReview->id_project = 1;
        $createdReview->password = 'test_pw';
        $createdReview->source_page = 2;

        $createdReview->method('getChunk')->willReturn($this->chunk);

        $daoMock = $this->createMock(ChunkReviewDao::class);
        $daoMock->method('getDatabaseHandler')->willReturn($this->dbStub);
        $daoMock->method('findChunkReviews')->willReturn([]);
        $daoMock->expects($this->once())
            ->method('createRecord')
            ->with($this->callback(function (array $data) {
                return $data['id_project'] === 1
                    && $data['id_job'] === 10
                    && $data['password'] === 'test_pw'
                    && $data['source_page'] === 2;
            }))
            ->willReturn($createdReview);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('setFetchMode')->willReturn(true);

        $processor = new BatchReviewProcessor($daoMock);
        $processor->setChunk($this->chunk);
        $processor->setPreparedEvents([]);
        $processor->process();
    }

    // ──────────────────────────────────────────────────────────────
    // TDD RED — new tests for factory injection + setChunk DI
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function constructorAcceptsFactories(): void
    {
        $rwcFactory = fn() => $this->createStub(ReviewedWordCountModel::class);
        $crmFactory = fn() => $this->createStub(ChunkReviewModel::class);

        $processor = new BatchReviewProcessor(
            chunkReviewDao: $this->chunkReviewDaoStub,
            reviewedWordCountModelFactory: $rwcFactory,
            chunkReviewModelFactory: $crmFactory,
        );
        $this->assertInstanceOf(BatchReviewProcessor::class, $processor);
    }

    #[Test]
    public function setChunkAcceptsInjectedCounterModel(): void
    {
        $counterModel = $this->createStub(CounterModel::class);
        $counterModel->method('getValues')->willReturn([]);

        $processor = new BatchReviewProcessor($this->chunkReviewDaoStub);
        $result = $processor->setChunk($this->chunk, $counterModel);

        $this->assertSame($processor, $result);
    }

    #[Test]
    public function processWithEventsCallsReviewedWordCountModel(): void
    {
        $existingReview = new ChunkReviewStruct();
        $existingReview->id = 100;
        $existingReview->id_job = 10;
        $existingReview->password = 'test_pw';
        $existingReview->source_page = 2;

        $this->chunkReviewDaoStub->method('findChunkReviews')->willReturn([$existingReview]);

        $eventStub = $this->createStub(TranslationEvent::class);
        $eventStub->method('getChunkReviewsPartials')->willReturn([]);

        $rwcMock = $this->createMock(ReviewedWordCountModel::class);
        $rwcMock->expects($this->once())->method('evaluateChunkReviewEventTransitions');
        $rwcMock->expects($this->once())->method('deleteIssues');
        $rwcMock->expects($this->once())->method('sendNotificationEmail');
        $rwcMock->method('getEvent')->willReturn($eventStub);

        $rwcFactory = fn() => $rwcMock;

        $counterModel = $this->createStub(CounterModel::class);
        $counterModel->method('getValues')->willReturn([]);

        $processor = new BatchReviewProcessor(
            chunkReviewDao: $this->chunkReviewDaoStub,
            reviewedWordCountModelFactory: $rwcFactory,
        );
        $processor->setChunk($this->chunk, $counterModel);
        $processor->setPreparedEvents([$eventStub]);
        $processor->process();
    }

    #[Test]
    public function processWithEventsCallsChunkReviewModelUpdate(): void
    {
        $existingReview = new ChunkReviewStruct();
        $existingReview->id = 100;
        $existingReview->id_job = 10;
        $existingReview->password = 'test_pw';
        $existingReview->source_page = 2;

        $this->chunkReviewDaoStub->method('findChunkReviews')->willReturn([$existingReview]);

        $partialReview = $this->createStub(ChunkReviewStruct::class);
        $partialReview->penalty_points = 5.0;
        $partialReview->reviewed_words_count = 200;
        $partialReview->total_tte = 1000;
        $partialReview->method('getChunk')->willReturn($this->chunk);

        $eventStub = $this->createStub(TranslationEvent::class);
        $eventStub->method('getChunkReviewsPartials')->willReturn([$partialReview]);

        $rwcStub = $this->createStub(ReviewedWordCountModel::class);
        $rwcStub->method('getEvent')->willReturn($eventStub);

        $crmMock = $this->createMock(ChunkReviewModel::class);
        $crmMock->expects($this->once())->method('updateChunkReviewCountersAndPassFail')
            ->with(5.0, 200, 1000, $this->isInstanceOf(ProjectStruct::class));

        $counterModel = $this->createStub(CounterModel::class);
        $counterModel->method('getValues')->willReturn([]);

        $processor = new BatchReviewProcessor(
            chunkReviewDao: $this->chunkReviewDaoStub,
            reviewedWordCountModelFactory: fn() => $rwcStub,
            chunkReviewModelFactory: fn() => $crmMock,
        );
        $processor->setChunk($this->chunk, $counterModel);
        $processor->setPreparedEvents([$eventStub]);
        $processor->process();
    }

    #[Test]
    public function updateJobWordCounterUpdatesChunkWhenValuesPresent(): void
    {
        $this->chunkReviewDaoStub->method('findChunkReviews')->willReturn([new ChunkReviewStruct()]);

        $newCount = WordCountStruct::loadFromJob(new JobStruct([
            'id' => 10,
            'password' => 'test_pw',
            'new_words' => 80.0,
            'draft_words' => 10.0,
            'translated_words' => 5.0,
            'approved_words' => 3.0,
            'approved2_words' => 1.0,
            'rejected_words' => 1.0,
            'new_raw_words' => 80.0,
            'draft_raw_words' => 10.0,
            'translated_raw_words' => 5.0,
            'approved_raw_words' => 3.0,
            'approved2_raw_words' => 1.0,
            'rejected_raw_words' => 1.0,
        ]));

        $counterModel = $this->createStub(CounterModel::class);
        $counterModel->method('getValues')->willReturn([$newCount]);
        $counterModel->method('updateDB')->willReturn($newCount);

        $processor = new BatchReviewProcessor(
            chunkReviewDao: $this->chunkReviewDaoStub,
            reviewedWordCountModelFactory: fn() => $this->createStub(ReviewedWordCountModel::class),
        );
        $processor->setChunk($this->chunk, $counterModel);
        $processor->setPreparedEvents([]);
        $processor->process();

        $this->assertSame(80.0, $this->chunk->new_words);
        $this->assertSame(10.0, $this->chunk->draft_words);
        $this->assertSame(5.0, $this->chunk->translated_words);
        $this->assertSame(3.0, $this->chunk->approved_words);
        $this->assertSame(1.0, $this->chunk->approved2_words);
        $this->assertSame(1.0, $this->chunk->rejected_words);
        $this->assertSame(10, $this->chunk->draft_raw_words);
        $this->assertSame(80, $this->chunk->new_raw_words);
    }
}
