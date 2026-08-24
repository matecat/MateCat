<?php

declare(strict_types=1);

namespace Matecat\Core\Plugins\Features\ReviewExtended;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\ChunkReviewModel;
use Utils\Registry\AppConfig;
use Model\Users\UserStruct;

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

    public function getProject(ProjectDao $dao, int $ttl = 86400): ProjectStruct
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

    public function getChunk(JobDao $jobDao): JobStruct
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

    /** ProjectStruct with null id_qa_model */
    private ProjectStruct $nullLqaProject;

    protected function setUp(): void
    {
        parent::setUp();

        self::$originalSkipCache = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        // These tests drive a real ChunkReviewDao, whose write paths take lockByJobId(). In production
        // the callers (SegmentTranslationIssueController, BulkSegmentStatusChangeWorker,
        // CopyAllSourceToTargetController, the repair CLI) all open a transaction first.
        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock(inTransaction: true);

        $this->nullLqaProject = new ProjectStruct();
        $this->nullLqaProject->id = 10;
        $this->nullLqaProject->id_qa_model = null;

        $this->jobStruct = new StubJobStruct([
            'id'       => 1,
            'password' => 'testpw',
        ], $this->nullLqaProject);

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
        // source_page=4, index = 4-2 = 2, but limit only has indices 0,1 -> fallback to last = 5
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

    #[Test]
    public function addPenaltyPointsCallsPassFailAtomicUpdate(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->addPenaltyPoints(3.7, $this->nullLqaProject, new UserStruct(['uid' => 987, 'email' => 'actor@example.org']));
        $this->assertTrue(true);
    }

    #[Test]
    public function subtractPenaltyPointsCallsPassFailAtomicUpdate(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->subtractPenaltyPoints(2.5, $this->nullLqaProject, new UserStruct(['uid' => 987, 'email' => 'actor@example.org']));
        $this->assertTrue(true);
    }

    #[Test]
    public function updateChunkReviewCountersAndPassFailPreservesDecimalPenaltyPoints(): void
    {
        // regression: penalty_points used to be (int)-cast here, truncating 0.5 -> 0 before
        // reaching the DAO's INSERT. A project with a null id_qa_model (like $this->nullLqaProject)
        // makes passFailCountsAtomicUpdate return early without ever building that INSERT, so this
        // test needs a resolvable qa model to actually reach the code path being fixed.
        $lqaModel = new ModelStruct([
            'pass_options' => json_encode(['limit' => [8, 5]]),
            'pass_type'    => 'combined',
            'label'        => 'test',
            'create_date'  => '2024-01-01',
            'hash'         => 'abc',
        ]);

        // passFailCountsAtomicUpdate() resolves its own project via
        // $chunkReview->getChunk()->getProject() (NOT the $projectStruct passed to
        // updateChunkReviewCountersAndPassFail), so the qa model must be reachable from there.
        $project = new ProjectStruct();
        $project->id = 10;
        $project->id_qa_model = 1;

        $jobStruct = new StubJobStruct([
            'id'       => 1,
            'password' => 'testpw',
        ], $project);

        $chunkReviewStruct = new StubChunkReviewStruct([
            'id'                   => 42,
            'id_project'           => 10,
            'id_job'               => 1,
            'password'             => 'testpw',
            'review_password'      => 'rev_pw',
            'source_page'          => 2,
            'reviewed_words_count' => 100,
            'total_tte'            => 0,
        ], $jobStruct);

        $capturedParams = null;
        $this->stmtStub->method('execute')->willReturnCallback(function (array $params) use (&$capturedParams) {
            if (array_key_exists('penalty_points', $params)) {
                $capturedParams = $params;
            }

            return true;
        });
        $this->stmtStub->method('fetchAll')->willReturn([$lqaModel]);

        $model = new ChunkReviewModel($chunkReviewStruct, $this->dbStub);
        $model->updateChunkReviewCountersAndPassFail(0.5, 10, 500, $project, new UserStruct(['uid' => 987, 'email' => 'actor@example.org']));

        $this->assertNotNull($capturedParams, 'execute() was never called with a penalty_points param');
        $this->assertSame(0.5, $capturedParams['penalty_points']);
    }

    #[Test]
    public function updateChunkReviewCountersAndPassFailDispatchesEvent(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        // FeatureSet::forProject will query metadata, then dispatch triggers no listeners
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $project = new ProjectStruct();
        $project->id = 10;
        $project->id_qa_model = null;

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        // The dispatch call will go through FeatureSet::forProject which loads from DB.
        // With empty fetchAll, no features are loaded beyond mandatory ones, so
        // the dispatch will silently succeed (no listeners for ChunkReviewUpdatedEvent).
        $model->updateChunkReviewCountersAndPassFail(1.0, 5, 100, $project, new UserStruct(['uid' => 987, 'email' => 'actor@example.org']));
        $this->assertTrue(true);
    }

    // -----------------------------------------------------------------------
    // recountAndUpdatePassFailResult
    // -----------------------------------------------------------------------

    /**
     * A project with no LQA model has no pass/fail verdict, and NULL is how that is represented —
     * QualitySummary reads NULL as "no score" rather than as a failure, and
     * passFailCountsAtomicUpdate() (the delta writer for the same row) leaves it NULL too. Writing
     * true here asserted a verdict that was never computed and disagreed with that writer.
     */
    #[Test]
    public function recountAndUpdatePassFailResultLeavesIsPassNullWhenProjectHasNoLqaModel(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn([0 => null]);

        // Starts from a stale verdict to prove the recount clears it rather than merely skipping it.
        $this->chunkReviewStruct->is_pass = true;

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->recountAndUpdatePassFailResult($this->nullLqaProject, new UserStruct(['uid' => 987, 'email' => 'actor@example.org']));

        $this->assertNull($this->chunkReviewStruct->is_pass);
    }

    #[Test]
    public function recountAndUpdatePassFailResultWithLqaModelSetsIsPassWhenScoreBelowLimit(): void
    {
        // After DAO calls, penalty_points=0, reviewed_words_count=0 -> score=0 <= limit=8 -> is_pass=true
        $lqaModel = new ModelStruct([
            'pass_options' => json_encode(['limit' => [8, 5]]),
            'pass_type'    => 'combined',
            'label'        => 'test',
            'create_date'  => '2024-01-01',
            'hash'         => 'abc',
        ]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->stmtStub->method('fetchAll')->willReturnOnConsecutiveCalls([], [$lqaModel], [], [], []);
        $this->stmtStub->method('fetch')->willReturn([0 => null]);

        $project = new ProjectStruct();
        $project->id = 10;
        $project->id_qa_model = 1;

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->recountAndUpdatePassFailResult($project, new UserStruct(['uid' => 987, 'email' => 'actor@example.org']));

        $this->assertTrue($this->chunkReviewStruct->is_pass);
    }

    // -----------------------------------------------------------------------
    // recountAndUpdatePassFailResultFromFinalRevisions
    // -----------------------------------------------------------------------

    /**
     * The pass/fail half of the recount is shared between both entry points, so the no-model case
     * has to hold here too: NULL is how "no verdict was computed" is represented, and a stale true
     * must be cleared rather than left standing.
     */
    #[Test]
    public function recountFromFinalRevisionsLeavesIsPassNullWhenProjectHasNoLqaModel(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn([0 => null]);

        $this->chunkReviewStruct->is_pass = true;

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->recountAndUpdatePassFailResultFromFinalRevisions($this->nullLqaProject, $this->operator());

        $this->assertNull($this->chunkReviewStruct->is_pass);
    }

    #[Test]
    public function recountFromFinalRevisionsWithLqaModelSetsIsPassWhenScoreBelowLimit(): void
    {
        $lqaModel = new ModelStruct([
            'pass_options' => json_encode(['limit' => [8, 5]]),
            'pass_type'    => 'combined',
            'label'        => 'test',
            'create_date'  => '2024-01-01',
            'hash'         => 'abc',
        ]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->stmtStub->method('fetchAll')->willReturnOnConsecutiveCalls([], [$lqaModel], [], [], []);
        $this->stmtStub->method('fetch')->willReturn([0 => null]);

        $project = new ProjectStruct();
        $project->id = 10;
        $project->id_qa_model = 1;

        $model = new ChunkReviewModel($this->chunkReviewStruct, $this->dbStub);
        $model->recountAndUpdatePassFailResultFromFinalRevisions($project, $this->operator());

        $this->assertTrue($this->chunkReviewStruct->is_pass);
    }

    /**
     * The one property that distinguishes this entry point, and the one an is_pass assertion cannot
     * see: it must derive the reviewed words from the phase's own final-revision records, not from
     * the segments' current status. The status derivation only answers for the top phase of a job
     * and recounts R1 towards zero as R2 approves — a repair tool writing a wrong value over a right
     * one. Swapping the derivation back would leave every other assertion in this file green, so the
     * query itself is what gets pinned.
     */
    #[Test]
    public function recountFromFinalRevisionsDerivesTheWordsFromTheFinalRevisionRecords(): void
    {
        $captured = $this->captureSqlWhile(
            fn(IDatabase $database) => (new ChunkReviewModel($this->chunkReviewStruct, $database))
                ->recountAndUpdatePassFailResultFromFinalRevisions($this->nullLqaProject, $this->operator())
        );

        $this->assertTrue(
            $this->anyStatementContains($captured, 'ste.final_revision = 1'),
            'the recount must read the phase\'s final revision records'
        );
        $this->assertFalse(
            $this->anyStatementContains($captured, 'st.status = :translation_status'),
            'the recount must not fall back to the segment-status derivation'
        );
    }

    /**
     * The companion guard. The split and merge callers deliberately keep the status derivation, so
     * that re-partitioning a job does not silently change its numbers as a side effect of the fix
     * that added the entry point above. That "unchanged" claim is only worth anything if something
     * fails when it stops being true.
     */
    #[Test]
    public function recountAndUpdatePassFailResultStillDerivesTheWordsFromTheSegmentStatus(): void
    {
        $captured = $this->captureSqlWhile(
            fn(IDatabase $database) => (new ChunkReviewModel($this->chunkReviewStruct, $database))
                ->recountAndUpdatePassFailResult($this->nullLqaProject, $this->operator())
        );

        $this->assertTrue(
            $this->anyStatementContains($captured, 'st.status = :translation_status'),
            'the original recount must still read the segments\' current status'
        );
        $this->assertFalse(
            $this->anyStatementContains($captured, 'ste.final_revision = 1'),
            'the original recount must not have been switched to the final revision derivation'
        );
    }

    private function operator(): UserStruct
    {
        return new UserStruct(['uid' => 987, 'email' => 'actor@example.org']);
    }

    /**
     * Runs $exercise against a database stub that records the SQL of every statement prepared, and
     * returns what it saw. Built here rather than reconfiguring the shared pdoStub, whose prepare()
     * is already stubbed in setUp.
     *
     * @param callable(IDatabase): void $exercise
     *
     * @return string[]
     */
    private function captureSqlWhile(callable $exercise): array
    {
        $captured = [];

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn([0 => null]);

        $pdo = $this->createStub(\PDO::class);
        // lockByJobId() refuses to run outside a transaction; both entry points take it.
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$captured): \PDOStatement {
            $captured[] = $sql;

            return $this->stmtStub;
        });

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);
        $database->method('onCommit')->willReturnCallback(static fn(callable $callback) => $callback());

        $exercise($database);

        return $captured;
    }

    /**
     * @param string[] $statements
     */
    private function anyStatementContains(array $statements, string $needle): bool
    {
        foreach ($statements as $sql) {
            if (str_contains($sql, $needle)) {
                return true;
            }
        }

        return false;
    }
}
