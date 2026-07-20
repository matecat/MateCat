<?php

namespace Matecat\Core\DAO\TestChunkReviewDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Utils\Constants\SourcePages;
use Utils\Registry\AppConfig;

class ChunkReviewDaoTest extends AbstractTest
{
    private IDatabase&Stub $dbStub;
    private PDO&Stub $pdoStub;
    private PDOStatement&Stub $stmtStub;
    private static bool $originalSkipCache;

    protected function setUp(): void
    {
        parent::setUp();

        self::$originalSkipCache = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();

        AppConfig::$SKIP_SQL_CACHE = self::$originalSkipCache;

        parent::tearDown();
    }

    #[Test]
    public function updatePasswordReturnsAffectedRowCount(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(2);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(2, $dao->updatePassword(10, 'old_pass', 'new_pass'));
    }

    #[Test]
    public function updatePasswordReturnsZeroWhenNoMatch(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(0);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(0, $dao->updatePassword(99, 'wrong', 'new'));
    }

    #[Test]
    public function updateReviewPasswordReturnsAffectedRowCount(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(1, $dao->updateReviewPassword(5, 'rev_old', 'rev_new', 2));
    }

    #[Test]
    public function updateReviewPasswordReturnsZeroWhenNoMatch(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(0);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(0, $dao->updateReviewPassword(99, 'x', 'y', 3));
    }

    #[Test]
    public function getPenaltyPointsForChunkDefaultsToRevisionSourcePage(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 10;
        $chunk->password = 'pass';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn([15]);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(15, $dao->getPenaltyPointsForChunk($chunk));
    }

    #[Test]
    public function getPenaltyPointsForChunkUsesProvidedSourcePage(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 10;
        $chunk->password = 'pass';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn([20]);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(20, $dao->getPenaltyPointsForChunk($chunk, SourcePages::SOURCE_PAGE_REVISION_2));
    }

    #[Test]
    public function getPenaltyPointsForChunkReturnsZeroWhenNoResult(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 10;
        $chunk->password = 'pass';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(0, $dao->getPenaltyPointsForChunk($chunk, 3));
    }

    #[Test]
    public function getPenaltyPointsForChunkReturnsZeroWhenNullValue(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 10;
        $chunk->password = 'pass';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn([null]);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(0, $dao->getPenaltyPointsForChunk($chunk, 2));
    }

    #[Test]
    public function countTimeToEditReturnsSumValue(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 20;
        $chunk->password = 'abc';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn([3600]);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(3600, $dao->countTimeToEdit($chunk, 2));
    }

    #[Test]
    public function countTimeToEditReturnsZeroWhenNoResult(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 20;
        $chunk->password = 'abc';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(0, $dao->countTimeToEdit($chunk, 2));
    }

    #[Test]
    public function countTimeToEditReturnsZeroWhenNullValue(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 20;
        $chunk->password = 'abc';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn([null]);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(0, $dao->countTimeToEdit($chunk, 2));
    }

    #[Test]
    public function getReviewedWordsCountForSecondPassReturnsWordCount(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 30;
        $chunk->password = 'pw';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn([500]);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(500, $dao->getReviewedWordsCountForSecondPass($chunk, SourcePages::SOURCE_PAGE_REVISION));
    }

    #[Test]
    public function getReviewedWordsCountForSecondPassReturnsZeroWhenNull(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 30;
        $chunk->password = 'pw';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn([null]);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertSame(0, $dao->getReviewedWordsCountForSecondPass($chunk, SourcePages::SOURCE_PAGE_REVISION));
    }

    #[Test]
    public function findChunkReviewsReturnsArrayOfStructs(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->id = 100;

        $chunk = new JobStruct();
        $chunk->id = 40;
        $chunk->password = 'find_pw';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findChunkReviews($chunk);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ChunkReviewStruct::class, $result[0]);
    }

    #[Test]
    public function findChunkReviewsReturnsEmptyWhenNoResults(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 40;
        $chunk->password = 'find_pw';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findChunkReviews($chunk);

        $this->assertSame([], $result);
    }

    #[Test]
    public function findChunkReviewsForSourcePageReturnsFilteredResults(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->id = 101;

        $chunk = new JobStruct();
        $chunk->id = 41;
        $chunk->password = 'sp_pw';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findChunkReviewsForSourcePage($chunk, SourcePages::SOURCE_PAGE_REVISION);

        $this->assertCount(1, $result);
    }

    #[Test]
    public function isTOrR1OrR2ReturnsShapelessStruct(): void
    {
        $struct = new ShapelessConcreteStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->isTOrR1OrR2(10, 'pwd123');

        $this->assertInstanceOf(ShapelessConcreteStruct::class, $result);
    }

    #[Test]
    public function isTOrR1OrR2ReturnsNullWhenEmpty(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->isTOrR1OrR2(10, 'pwd123');

        $this->assertNull($result);
    }

    #[Test]
    public function findLastReviewReturnsStructWhenFound(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->id = 400;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn($struct);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findLastReviewByJobIdPasswordAndSourcePage(60, 'pw', 2);

        $this->assertInstanceOf(ChunkReviewStruct::class, $result);
        $this->assertSame(400, $result->id);
    }

    #[Test]
    public function findLastReviewReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findLastReviewByJobIdPasswordAndSourcePage(60, 'pw', 2);

        $this->assertNull($result);
    }

    #[Test]
    public function findByJobIdReviewPasswordAndSourcePageReturnsStruct(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->id = 500;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findByJobIdReviewPasswordAndSourcePage(70, 'rev', 2);

        $this->assertInstanceOf(ChunkReviewStruct::class, $result);
    }

    #[Test]
    public function findByJobIdReviewPasswordAndSourcePageReturnsNullWhenEmpty(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findByJobIdReviewPasswordAndSourcePage(70, 'rev', 2);

        $this->assertNull($result);
    }

    #[Test]
    public function existsReturnsTrueWhenRowFound(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn(['id' => 1]);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertTrue($dao->exists(10, 'pw'));
    }

    #[Test]
    public function existsReturnsFalseWhenNoRow(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new ChunkReviewDao($this->dbStub);
        $this->assertFalse($dao->exists(10, 'pw'));
    }

    #[Test]
    public function existsWithSourcePageIncludesCondition(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->queryString = '';
        $stmtMock->expects($this->once())->method('execute')->with([
            'id_job'      => 10,
            'password'    => 'pw',
            'source_page' => 2,
        ])->willReturn(true);
        $stmtMock->method('fetch')->willReturn(['id' => 5]);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('source_page=:source_page'))
            ->willReturn($stmtMock);

        $dbMock = $this->createStub(IDatabase::class);
        $dbMock->method('getConnection')->willReturn($pdoMock);

        $this->setDatabaseInstance($dbMock);

        $dao = new ChunkReviewDao($dbMock);
        $this->assertTrue($dao->exists(10, 'pw', 2));
    }

    #[Test]
    public function existsWithoutSourcePageOmitsCondition(): void
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->queryString = '';
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetch')->willReturn(false);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->logicalNot($this->stringContains('source_page=:source_page')))
            ->willReturn($stmtStub);

        $dbMock = $this->createStub(IDatabase::class);
        $dbMock->method('getConnection')->willReturn($pdoMock);

        $this->setDatabaseInstance($dbMock);

        $dao = new ChunkReviewDao($dbMock);
        $this->assertFalse($dao->exists(10, 'pw', null));
    }

    #[Test]
    public function passFailCountsAtomicUpdateSkipsIsPassClauseWhenLqaModelIsNull(): void
    {
        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->id_qa_model = null;

        $chunkStub = $this->createStub(JobStruct::class);
        $chunkStub->method('getProject')->willReturn($projectStub);

        $chunkReview = $this->createStub(ChunkReviewStruct::class);
        $chunkReview->method('getChunk')->willReturn($chunkStub);
        $chunkReview->source_page = 2;
        $chunkReview->id_job = 5;
        $chunkReview->id_project = 1;
        $chunkReview->password = 'p';
        $chunkReview->review_password = 'rp';

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->queryString = '';
        $stmtMock->expects($this->once())->method('execute')->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->logicalNot($this->stringContains('is_pass')))
            ->willReturn($stmtMock);

        $dbStub = $this->createStub(IDatabase::class);
        $dbStub->method('getConnection')->willReturn($pdoMock);

        $this->setDatabaseInstance($dbStub);

        $dao = new ChunkReviewDao($dbStub);
        $dao->passFailCountsAtomicUpdate(1, [
            'chunkReview'          => $chunkReview,
            'reviewed_words_count' => 100,
            'total_tte'            => 500,
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function passFailCountsAtomicUpdateExecutesWhenLqaModelExists(): void
    {
        $lqaModel = $this->createStub(ModelStruct::class);
        $lqaModel->method('getLimit')->willReturn([10]);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->id_qa_model = 1;

        $chunkStub = $this->createStub(JobStruct::class);
        $chunkStub->method('getProject')->willReturn($projectStub);

        $chunkReview = $this->createStub(ChunkReviewStruct::class);
        $chunkReview->method('getChunk')->willReturn($chunkStub);
        $chunkReview->source_page = 2;
        $chunkReview->id_job = 5;
        $chunkReview->id_project = 1;
        $chunkReview->password = 'p';
        $chunkReview->review_password = 'rp';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$lqaModel]);

        $dao = new ChunkReviewDao($this->dbStub);
        $dao->passFailCountsAtomicUpdate(99, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => 5,
            'reviewed_words_count' => 200,
            'total_tte'            => 1000,
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function passFailCountsAtomicUpdateHandlesEmptyPenaltyPoints(): void
    {
        $lqaModel = $this->createStub(ModelStruct::class);
        $lqaModel->method('getLimit')->willReturn([5, 8]);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->id_qa_model = 1;

        $chunkStub = $this->createStub(JobStruct::class);
        $chunkStub->method('getProject')->willReturn($projectStub);

        $chunkReview = $this->createStub(ChunkReviewStruct::class);
        $chunkReview->method('getChunk')->willReturn($chunkStub);
        $chunkReview->source_page = 3;
        $chunkReview->id_job = 6;
        $chunkReview->id_project = 2;
        $chunkReview->password = 'pp';
        $chunkReview->review_password = 'rrp';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$lqaModel]);

        $dao = new ChunkReviewDao($this->dbStub);
        $dao->passFailCountsAtomicUpdate(100, [
            'chunkReview'          => $chunkReview,
            'reviewed_words_count' => 300,
            'total_tte'            => 2000,
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function passFailCountsAtomicUpdatePreservesDecimalPenaltyPoints(): void
    {
        // regression: penalty_points used to be (int)-cast upstream in ChunkReviewModel before
        // reaching this DAO, truncating e.g. 0.5 -> 0. Assert the bound param stays a float here.
        $lqaModel = $this->createStub(ModelStruct::class);
        $lqaModel->method('getLimit')->willReturn([10]);

        $projectStub = $this->createStub(ProjectStruct::class);
        $projectStub->id_qa_model = 1;

        $chunkStub = $this->createStub(JobStruct::class);
        $chunkStub->method('getProject')->willReturn($projectStub);

        $chunkReview = $this->createStub(ChunkReviewStruct::class);
        $chunkReview->method('getChunk')->willReturn($chunkStub);
        $chunkReview->source_page = 2;
        $chunkReview->id_job = 5;
        $chunkReview->id_project = 1;
        $chunkReview->password = 'p';
        $chunkReview->review_password = 'rp';

        $capturedParams = null;
        $this->stmtStub->method('execute')->willReturnCallback(function (array $params) use (&$capturedParams) {
            if (array_key_exists('penalty_points', $params)) {
                $capturedParams = $params;
            }

            return true;
        });
        $this->stmtStub->method('fetchAll')->willReturn([$lqaModel]);

        $dao = new ChunkReviewDao($this->dbStub);
        $dao->passFailCountsAtomicUpdate(101, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => 0.5,
            'reviewed_words_count' => 200,
            'total_tte'            => 1000,
        ]);

        $this->assertNotNull($capturedParams, 'execute() was never called with a penalty_points param');
        $this->assertSame(0.5, $capturedParams['penalty_points']);
    }

    #[Test]
    public function destroyCacheForFindChunkReviewsDoesNotThrow(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 50;
        $chunk->password = 'dc_pw';

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->destroyCacheForFindChunkReviews($chunk);
        $this->assertIsBool($result);
    }

    #[Test]
    public function destroyCacheForJobIdReviewPasswordAndSourcePageDoesNotThrow(): void
    {
        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->destroyCacheForJobIdReviewPasswordAndSourcePage(10, 'rev', 2);
        $this->assertIsBool($result);
    }

    #[Test]
    public function destroyCachesForBustsFindByProjectIdAndReviewPasswordCaches(): void
    {
        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = 42;
        $chunkReview->id_project = 7;
        $chunkReview->password = 'chunk_pw';
        $chunkReview->review_password = 'rev_pw';
        $chunkReview->source_page = 2;

        $dao = $this->getMockBuilder(ChunkReviewDao::class)
            ->setConstructorArgs([$this->dbStub])
            ->onlyMethods([
                'destroyCacheForFindChunkReviews',
                'destroyCacheByProjectId',
                'destroyCacheForJobIdReviewPasswordAndSourcePage',
            ])
            ->getMock();

        $dao->expects($this->once())
            ->method('destroyCacheForFindChunkReviews')
            ->with($this->callback(fn(JobStruct $chunk) => $chunk->id === 42 && $chunk->password === 'chunk_pw'))
            ->willReturn(true);

        $dao->expects($this->once())
            ->method('destroyCacheByProjectId')
            ->with(7)
            ->willReturn(true);

        $dao->expects($this->once())
            ->method('destroyCacheForJobIdReviewPasswordAndSourcePage')
            ->with(42, 'rev_pw', 2)
            ->willReturn(true);

        $dao->destroyCachesFor($chunkReview);
    }

    #[Test]
    public function destroyCachesForSkipsReviewPasswordCacheWhenNull(): void
    {
        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = 42;
        $chunkReview->id_project = 7;
        $chunkReview->password = 'chunk_pw';
        $chunkReview->review_password = null;
        $chunkReview->source_page = 2;

        $dao = $this->getMockBuilder(ChunkReviewDao::class)
            ->setConstructorArgs([$this->dbStub])
            ->onlyMethods([
                'destroyCacheForFindChunkReviews',
                'destroyCacheByProjectId',
                'destroyCacheForJobIdReviewPasswordAndSourcePage',
            ])
            ->getMock();

        $dao->method('destroyCacheForFindChunkReviews')->willReturn(true);
        $dao->method('destroyCacheByProjectId')->willReturn(true);
        $dao->expects($this->never())->method('destroyCacheForJobIdReviewPasswordAndSourcePage');

        $dao->destroyCachesFor($chunkReview);
    }

    // ──────────────────────────────────────────────────────────────
    // Instance-method specular tests (Step 1 — mirror of static tests)
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function instanceFindByIdJobReturnsArrayOfStructs(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->id = 1;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findByIdJob(42);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ChunkReviewStruct::class, $result[0]);
    }

    #[Test]
    public function instanceFindByIdJobReturnsEmptyArrayWhenNoResults(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findByIdJob(999);

        $this->assertSame([], $result);
    }

    #[Test]
    public function instanceFindByIdJobAndPasswordAndSourcePageReturnsFirstStruct(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->id = 7;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findByIdJobAndPasswordAndSourcePage(1, 'pwd', 2);

        $this->assertInstanceOf(ChunkReviewStruct::class, $result);
        $this->assertSame(7, $result->id);
    }

    #[Test]
    public function instanceFindByIdJobAndPasswordAndSourcePageReturnsNullWhenEmpty(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findByIdJobAndPasswordAndSourcePage(1, 'pwd', 2);

        $this->assertNull($result);
    }

    #[Test]
    public function instanceFindByIdReturnsStructWhenFound(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->id = 55;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findById(55);

        $this->assertInstanceOf(ChunkReviewStruct::class, $result);
    }

    #[Test]
    public function instanceFindByIdReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findById(999);

        $this->assertNull($result);
    }

    #[Test]
    public function instanceFindByProjectIdReturnsArrayOfStructs(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->id = 200;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findByProjectId(77);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ChunkReviewStruct::class, $result[0]);
    }

    #[Test]
    public function instanceFindByProjectIdReturnsEmptyArrayWhenNoResults(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findByProjectId(999);

        $this->assertSame([], $result);
    }

    #[Test]
    public function instanceDestroyCacheByProjectIdDoesNotThrow(): void
    {
        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->destroyCacheByProjectId(88);
        $this->assertIsBool($result);
    }

    #[Test]
    public function instanceFindByReviewPasswordAndJobIdReturnsStructWhenFound(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->id = 300;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findByReviewPasswordAndJobId('rev_pw', 50);

        $this->assertInstanceOf(ChunkReviewStruct::class, $result);
    }

    #[Test]
    public function instanceFindByReviewPasswordAndJobIdReturnsNullWhenEmpty(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->findByReviewPasswordAndJobId('rev_pw', 50);

        $this->assertNull($result);
    }

    #[Test]
    public function instanceCreateRecordReturnsStructWithInsertedId(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->pdoStub->method('lastInsertId')->willReturn('42');

        $data = [
            'id_project'      => 1,
            'id_job'          => 2,
            'password'        => 'test_pw',
            'review_password' => 'rev_pw',
            'source_page'     => 2,
        ];

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->createRecord($data);

        $this->assertInstanceOf(ChunkReviewStruct::class, $result);
        $this->assertSame(42, $result->id);
        $this->assertSame(1, $result->id_project);
        $this->assertSame(2, $result->id_job);
        $this->assertSame('test_pw', $result->password);
        $this->assertSame('rev_pw', $result->review_password);
    }

    #[Test]
    public function instanceCreateRecordSetsDefaultReviewPasswordWhenNull(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->pdoStub->method('lastInsertId')->willReturn('43');

        $data = [
            'id_project'  => 1,
            'id_job'      => 2,
            'password'    => 'test_pw',
            'source_page' => 2,
        ];

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->createRecord($data);

        $this->assertInstanceOf(ChunkReviewStruct::class, $result);
        $this->assertNotNull($result->review_password);
        $this->assertNotEmpty($result->review_password);
    }

    #[Test]
    public function instanceDeleteByJobIdReturnsTrueOnSuccess(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->deleteByJobId(77);

        $this->assertTrue($result);
    }

    #[Test]
    public function instanceDeleteByJobIdReturnsFalseOnFailure(): void
    {
        $this->stmtStub->method('execute')->willReturn(false);

        $dao = new ChunkReviewDao($this->dbStub);
        $result = $dao->deleteByJobId(88);

        $this->assertFalse($result);
    }

    /**
     * Guards the obtain() removal: the DAO must take its PDO connection from the
     * INJECTED IDatabase, never from the obtainTestDatabase() singleton. The
     * singleton stays wired to a distinct stub (setUp) so the pre-fix code path
     * would silently use it instead of the injected mock.
     */
    #[Test]
    public function queriesUseInjectedDatabaseNotSingleton(): void
    {
        $injectedStmt = $this->createStub(PDOStatement::class);
        $injectedStmt->queryString = '';
        $injectedStmt->method('execute')->willReturn(true);
        $injectedStmt->method('rowCount')->willReturn(0);

        $injectedPdo = $this->createStub(PDO::class);
        $injectedPdo->method('prepare')->willReturn($injectedStmt);

        $injectedDb = $this->createMock(IDatabase::class);
        $injectedDb->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($injectedPdo);

        // Poison the singleton: it must NEVER be touched. Any obtainTestDatabase()
        // fallback (full revert OR a partial/mixed path) hits this mock and trips
        // the never() expectation — a clean, deterministic failure that does not
        // depend on the permissive createDatabaseMock stub installed in setUp.
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $dao = new ChunkReviewDao($injectedDb);
        $dao->updatePassword(1, 'old', 'new');
    }
}
