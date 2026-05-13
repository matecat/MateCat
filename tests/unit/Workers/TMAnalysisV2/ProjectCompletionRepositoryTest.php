<?php

use Model\Analysis\AnalysisDao;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\WordCount\CounterModel;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\ProjectCompletionRepository;

class ProjectCompletionRepositoryTest extends AbstractTest
{
    private function makeRepository(
        ?IDatabase $db = null,
        ?ProjectDao $projectDao = null,
        ?JobDao $jobDao = null,
        ?AnalysisDao $analysisDao = null,
        ?CounterModel $counterModel = null,
    ): ProjectCompletionRepository {
        $db ??= $this->createStub(IDatabase::class);
        $projectDao ??= $this->createStub(ProjectDao::class);
        $jobDao ??= $this->createStub(JobDao::class);
        $analysisDao ??= $this->createStub(AnalysisDao::class);
        $counterModel ??= $this->createStub(CounterModel::class);

        return new ProjectCompletionRepository($db, $projectDao, $jobDao, $analysisDao, $counterModel);
    }

    #[Test]
    public function test_begin_transaction_calls_injected_database_begin(): void
    {
        $db = $this->createMock(IDatabase::class);
        $db->expects($this->once())->method('begin');

        $repository = $this->makeRepository($db);
        $repository->beginTransaction();
    }

    #[Test]
    public function test_commit_calls_injected_database_commit(): void
    {
        $db = $this->createMock(IDatabase::class);
        $db->expects($this->once())->method('commit');

        $repository = $this->makeRepository($db);
        $repository->commit();
    }

    #[Test]
    public function test_rollback_calls_injected_database_rollback(): void
    {
        $db = $this->createMock(IDatabase::class);
        $db->expects($this->once())->method('rollback');

        $repository = $this->makeRepository($db);
        $repository->rollback();
    }

    #[Test]
    public function test_get_project_segments_translation_summary_uses_injected_connection_and_returns_rows(): void
    {
        $db = $this->createMock(IDatabase::class);
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);

        $db->expects($this->once())
            ->method('getConnection')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'GROUP BY id_job WITH ROLLUP');
            }))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('setFetchMode')
            ->with(\PDO::FETCH_ASSOC);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['pid' => 55]);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['id_job' => 10, 'eq_wc' => '100.0', 'st_wc' => '100.0'],
            ]);

        $repository = $this->makeRepository($db);

        $this->assertSame(
            [['id_job' => 10, 'eq_wc' => '100.0', 'st_wc' => '100.0']],
            $repository->getProjectSegmentsTranslationSummary(55)
        );
    }

    #[Test]
    public function test_get_project_segments_translation_summary_wraps_pdo_exception_in_runtime_exception(): void
    {
        $db = $this->createStub(IDatabase::class);
        $pdo = $this->createStub(\PDO::class);
        $stmt = $this->createStub(\PDOStatement::class);

        $db->method('getConnection')->willReturn($pdo);
        $pdo->method('prepare')->willReturn($stmt);
        $stmt->method('setFetchMode');
        $stmt->method('execute')->willThrowException(new \PDOException('broken'));

        $repository = $this->makeRepository($db);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('broken');

        $repository->getProjectSegmentsTranslationSummary(7);
    }

    #[Test]
    public function test_update_project_analysis_status_uses_project_dao_update_fields(): void
    {
        $projectDao = $this->createMock(ProjectDao::class);
        $projectDao->expects($this->once())
            ->method('updateFields')
            ->with(
                [
                    'status_analysis' => 'DONE',
                    'tm_analysis_wc' => 12.3,
                    'standard_analysis_wc' => 45.6,
                ],
                ['id' => 15]
            )
            ->willReturn(1);

        $repository = $this->makeRepository(null, $projectDao);
        $repository->updateProjectAnalysisStatus(15, 'DONE', 12.3, 45.6);
    }

    #[Test]
    public function test_get_project_job_ids_uses_find_by_id_and_maps_job_pairs(): void
    {
        $project = $this->createStub(ProjectStruct::class);
        $project->method('getChunks')->willReturn([
            (object)['id' => 101, 'password' => 'a'],
            (object)['id' => 202, 'password' => 'b'],
        ]);

        $projectDao = $this->createMock(ProjectDao::class);
        $projectDao->expects($this->once())
            ->method('findById')
            ->with(9, ProjectStruct::class)
            ->willReturn($project);

        $repository = $this->makeRepository(null, $projectDao);

        $this->assertSame(
            [
                ['id' => 101, 'password' => 'a'],
                ['id' => 202, 'password' => 'b'],
            ],
            $repository->getProjectJobIds(9)
        );
    }

    #[Test]
    public function test_update_job_standard_word_count_rounds_and_updates_via_job_dao(): void
    {
        $jobDao = $this->createMock(JobDao::class);
        $jobDao->expects($this->once())
            ->method('updateFields')
            ->with(['standard_analysis_wc' => 11.0], ['id' => 90])
            ->willReturn(1);

        $repository = $this->makeRepository(null, null, $jobDao);
        $repository->updateJobStandardWordCount(90, 10.6);
    }

    #[Test]
    public function test_initialize_job_word_count_uses_injected_counter_model(): void
    {
        $counter = $this->createMock(CounterModel::class);
        $counter->expects($this->once())
            ->method('initializeJobWordCount')
            ->with(333, 'pw');

        $repository = $this->makeRepository(null, null, null, null, $counter);
        $repository->initializeJobWordCount(333, 'pw');
    }

    #[Test]
    public function test_destroy_project_and_job_caches_uses_injected_daos(): void
    {
        $projectDao = $this->createMock(ProjectDao::class);
        $jobDao = $this->createMock(JobDao::class);

        $projectDao->expects($this->once())
            ->method('destroyFindByIdCache')
            ->with(44, ProjectStruct::class)
            ->willReturn(true);

        $jobDao->expects($this->once())
            ->method('destroyCacheByProjectId')
            ->with(44)
            ->willReturn(true);

        $repository = $this->makeRepository(null, $projectDao, $jobDao);
        $repository->destroyProjectAndJobCaches(44);
    }

    #[Test]
    public function test_destroy_all_caches_uses_all_injected_cache_destroyers(): void
    {
        $projectDao = $this->createMock(ProjectDao::class);
        $jobDao = $this->createMock(JobDao::class);
        $analysisDao = $this->createMock(AnalysisDao::class);

        $projectDao->expects($this->once())
            ->method('destroyFindByIdCache')
            ->with(88, ProjectStruct::class)
            ->willReturn(true);
        $jobDao->expects($this->once())
            ->method('destroyCacheByProjectId')
            ->with(88)
            ->willReturn(true);
        $projectDao->expects($this->once())
            ->method('destroyProjectPasswordCache')
            ->with(88, 'proj-pw')
            ->willReturn(true);
        $analysisDao->expects($this->once())
            ->method('destroyAnalysisProjectCache')
            ->with(88)
            ->willReturn(true);

        $repository = $this->makeRepository(null, $projectDao, $jobDao, $analysisDao);
        $repository->destroyAllCaches(88, 'proj-pw');
    }
}
