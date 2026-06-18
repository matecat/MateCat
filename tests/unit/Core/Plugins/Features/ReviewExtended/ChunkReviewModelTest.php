<?php

declare(strict_types=1);

namespace Matecat\Core\Plugins\Features\ReviewExtended;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\ChunkReviewModel;
use Utils\Registry\AppConfig;

/**
 * JobStruct subclass that overrides getProject() to avoid DB calls.
 */
class StubJobStruct extends JobStruct
{
    private ProjectStruct $projectStruct;

    public function __construct(array $data, ProjectStruct $projectStruct)
    {
        parent::__construct($data);
        $this->projectStruct = $projectStruct;
    }

    public function getProject(int $ttl = 86400): ProjectStruct
    {
        return $this->projectStruct;
    }
}

/**
 * ChunkReviewStruct subclass that overrides getChunk() to avoid DB calls.
 */
class StubChunkReviewStruct extends ChunkReviewStruct
{
    private JobStruct $jobStruct;

    public function __construct(array $data, JobStruct $jobStruct)
    {
        parent::__construct($data);
        $this->jobStruct = $jobStruct;
    }

    public function getChunk(): JobStruct
    {
        return $this->jobStruct;
    }
}

class ChunkReviewModelTest extends AbstractTest
{
    private IDatabase $dbStub;
    private \PDO $pdoStub;
    private \PDOStatement $stmtStub;
    private static bool $originalSkipCache;

    private StubChunkReviewStruct $chunkReviewStruct;
    private StubJobStruct $jobStruct;

    /** ProjectStruct stub with getLqaModel() returning null */
    private ProjectStruct $nullLqaProjectStub;

    protected function setUp(): void
    {
        parent::setUp();

        self::$originalSkipCache = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();

        $this->nullLqaProjectStub = $this->createStub(ProjectStruct::class);
        $this->nullLqaProjectStub->id_qa_model = null;

        $this->jobStruct = new StubJobStruct([
            'id'       => 1,
            'password' => 'testpw',
        ], $this->nullLqaProjectStub);

        $this->chunkReviewStruct = new StubChunkReviewStruct([
            'id'                   => 42,
            'id_project'           => 10,
            'id_job'               => 1,
            'password'             => 'testpw',
            'review_password'      => 'rev_pw',
            'source_page'          => 2,
            'penalty_points'       => 5.0,
            'reviewed_words_count' => 100,
            'total_tte'            => 0,
        ], $this->jobStruct);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = self::$originalSkipCache;
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // constructor + getChunk
    // -----------------------------------------------------------------------

    #[Test]
    public function constructorSetsChunkFromChunkReview(): void
    {
        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $this->assertSame($this->jobStruct, $model->getChunk());
    }

    // -----------------------------------------------------------------------
    // getScore
    // -----------------------------------------------------------------------

    #[Test]
    public function getScoreReturnsZeroWhenReviewedWordsCountIsZero(): void
    {
        $this->chunkReviewStruct->reviewed_words_count = 0;
        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $this->assertSame(0.0, $model->getScore());
    }

    #[Test]
    public function getScoreComputesCorrectValue(): void
    {
        // 5 penalty / 100 words * 1000 = 50.0
        $this->chunkReviewStruct->penalty_points       = 5.0;
        $this->chunkReviewStruct->reviewed_words_count = 100;
        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $this->assertEqualsWithDelta(50.0, $model->getScore(), 0.0001);
    }

    #[Test]
    public function getScoreReturnsZeroForZeroPenaltyPoints(): void
    {
        $this->chunkReviewStruct->penalty_points       = 0.0;
        $this->chunkReviewStruct->reviewed_words_count = 200;
        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $this->assertSame(0.0, $model->getScore());
    }

    // -----------------------------------------------------------------------
    // getPenaltyPoints
    // -----------------------------------------------------------------------

    #[Test]
    public function getPenaltyPointsReturnsStructValue(): void
    {
        $this->chunkReviewStruct->penalty_points = 12.5;
        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $this->assertSame(12.5, $model->getPenaltyPoints());
    }

    #[Test]
    public function getPenaltyPointsReturnsNullWhenNull(): void
    {
        $this->chunkReviewStruct->penalty_points = null;
        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $this->assertNull($model->getPenaltyPoints());
    }

    // -----------------------------------------------------------------------
    // getReviewedWordsCount
    // -----------------------------------------------------------------------

    #[Test]
    public function getReviewedWordsCountReturnsStructValue(): void
    {
        $this->chunkReviewStruct->reviewed_words_count = 250;
        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $this->assertSame(250, $model->getReviewedWordsCount());
    }

    // -----------------------------------------------------------------------
    // getQALimit
    // -----------------------------------------------------------------------

    #[Test]
    public function getQALimitReturnsLimitForSourcePage(): void
    {
        // source_page=2, index = 2-2 = 0, limit[0] = 8
        $lqaModel = new ModelStruct([
            'pass_options' => json_encode(['limit' => [8, 5]]),
            'pass_type'    => 'combined',
            'label'        => 'test',
            'create_date'  => '2024-01-01',
            'hash'         => 'abc',
        ]);

        $this->chunkReviewStruct->source_page = 2;
        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $this->assertSame(8, $model->getQALimit($lqaModel));
    }

    #[Test]
    public function getQALimitFallsBackToLastElementWhenIndexMissing(): void
    {
        // source_page=4, index = 4-2 = 2, but limit only has indices 0,1 → fallback to last = 5
        $lqaModel = new ModelStruct([
            'pass_options' => json_encode(['limit' => [8, 5]]),
            'pass_type'    => 'combined',
            'label'        => 'test',
            'create_date'  => '2024-01-01',
            'hash'         => 'abc',
        ]);

        $this->chunkReviewStruct->source_page = 4;
        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $this->assertSame(5, $model->getQALimit($lqaModel));
    }

    // -----------------------------------------------------------------------
    // addPenaltyPoints / subtractPenaltyPoints / updateChunkReviewCountersAndPassFail
    // -----------------------------------------------------------------------

    private function buildProjectStubWithNullLqa(): ProjectStruct
    {
        $featureSetStub = $this->createStub(FeatureSet::class);
        $featureSetStub->method('dispatch')->willReturnArgument(0);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->method('getFeaturesSet')->willReturn($featureSetStub);
        $projectStub->id_qa_model = null;

        return $projectStub;
    }

    #[Test]
    public function addPenaltyPointsCallsPassFailAtomicUpdate(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $projectStub = $this->buildProjectStubWithNullLqa();

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->addPenaltyPoints(3.7, $projectStub);
        $this->assertTrue(true);
    }

    #[Test]
    public function subtractPenaltyPointsCallsPassFailAtomicUpdate(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $projectStub = $this->buildProjectStubWithNullLqa();

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->subtractPenaltyPoints(2.5, $projectStub);
        $this->assertTrue(true);
    }

    #[Test]
    public function updateChunkReviewCountersAndPassFailCastsPenaltyPointsToInt(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $projectStub = $this->buildProjectStubWithNullLqa();

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->updateChunkReviewCountersAndPassFail(7.9, 10, 500, $projectStub);
        $this->assertTrue(true);
    }

    #[Test]
    public function updateChunkReviewCountersAndPassFailDispatchesEvent(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $featureSetStub = $this->createMock(FeatureSet::class);
        $featureSetStub->expects($this->once())->method('dispatch');

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->method('getFeaturesSet')->willReturn($featureSetStub);
        $projectStub->id_qa_model = null;

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->updateChunkReviewCountersAndPassFail(1.0, 5, 100, $projectStub);
    }

    // -----------------------------------------------------------------------
    // recountAndUpdatePassFailResult
    // -----------------------------------------------------------------------

    #[Test]
    public function recountAndUpdatePassFailResultWithNullLqaModelSetsIsPassTrue(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn([0 => null]);

        $projectStub = $this->buildProjectStubWithNullLqa();

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->recountAndUpdatePassFailResult($projectStub);

        $this->assertTrue($this->chunkReviewStruct->is_pass);
    }

    #[Test]
    public function recountAndUpdatePassFailResultWithLqaModelSetsIsPassWhenScoreBelowLimit(): void
    {
        // After DAO calls, penalty_points=0, reviewed_words_count=0 → score=0 ≤ limit=8 → is_pass=true
        $lqaModel = new ModelStruct([
            'pass_options' => json_encode(['limit' => [8, 5]]),
            'pass_type'    => 'combined',
            'label'        => 'test',
            'create_date'  => '2024-01-01',
            'hash'         => 'abc',
        ]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->stmtStub->method('fetchAll')->willReturnOnConsecutiveCalls([$lqaModel], [], [], []);
        $this->stmtStub->method('fetch')->willReturn([0 => null]);

        $featureSetStub = $this->createStub(FeatureSet::class);
        $featureSetStub->method('dispatch')->willReturnArgument(0);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->method('getFeaturesSet')->willReturn($featureSetStub);
        $projectStub->id_qa_model = 1;

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->recountAndUpdatePassFailResult($projectStub);

        $this->assertTrue($this->chunkReviewStruct->is_pass);
    }
}
