<?php

namespace Matecat\Core\Model\Analysis;

use Model\Analysis\AnalysisDao;
use Model\DataAccess\ShapelessConcreteStruct;
use PHPUnit\Framework\Attributes\Group;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;

/**
 * Real-SQL coverage for AnalysisDao (plan dao-realsql-90.md, Wave 6 / T15).
 *
 * Every public SQL method is called DIRECTLY and asserted on real returned data (DoD b):
 *   getProjectStatsVolumeAnalysis, destroyCacheProjectStatsVolumeAnalysis.
 *
 * getProjectStatsVolumeAnalysis is a 6-table JOIN (segment_translations -> segments -> jobs ->
 * projects -> files, LEFT JOIN files_parts). The fixture mirrors that topology so the JOIN and
 * every WHERE predicate resolve against real data (C-3): project.status_analysis in the allowed
 * set, segment id within [job_first_segment, job_last_segment], and a non-zero word count.
 *
 * Single per-test connection for DAO + builder + cleanup (C-2); no wrapping transaction (C-1);
 * whole-table residue gate over every dep (A-1). No assertion on absolute generated id values
 * (M-3) — assertions are on round-tripped row data keyed by the project id under test.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class AnalysisDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = [
        'segment_translations',
        'segments',
        'jobs',
        'projects',
        'files',
        'files_parts',
    ];

    private AnalysisDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new AnalysisDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    /**
     * Build a complete JOIN-resolvable analysis chunk and return the project id under test.
     *
     * @return array{id_project:int,filename_present:bool,tag_key:string}
     */
    private function buildAnalysisChunk(string $statusAnalysis = 'NEW'): array
    {
        $project = $this->fixtures->makeProjectDetailed(['status_analysis' => $statusAnalysis]);
        $file = $this->fixtures->makeFile($project['id']);
        $filePart = $this->fixtures->makeFilesPart($file['id'], 'rsq_tag', 'rsq_val');

        // segment first, so the job can be bounded to its id (BETWEEN predicate).
        $segment = $this->fixtures->makeSegmentDetailed($file['id'], [
            'id_file_part'   => $filePart['id'],
            'raw_word_count' => 12,
        ]);
        $job = $this->fixtures->makeJob($project['id'], [
            'job_first_segment' => $segment['id'],
            'job_last_segment'  => $segment['id'],
        ]);
        $this->fixtures->makeFilesJob($job['id'], $file['id']);
        $this->fixtures->makeSegmentTranslationDetailed($segment['id'], $job['id'], [
            'eq_word_count'       => 8.0,
            'standard_word_count' => 9.0,
        ]);

        return ['id_project' => $project['id'], 'filename_present' => true, 'tag_key' => 'rsq_tag'];
    }

    public function testGetProjectStatsVolumeAnalysisReturnsJoinedRow(): void
    {
        $chunk = $this->buildAnalysisChunk('NEW');

        $rows = $this->dao->getProjectStatsVolumeAnalysis($chunk['id_project'], 0);

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $row);
        // assert on round-tripped joined data (M-3: no absolute generated id assertion)
        $this->assertSame('NEW', $row->status_analysis);
        $this->assertSame(12, (int)$row->raw_word_count);
        $this->assertSame('rsq_tag', $row->tag_key);   // LEFT JOIN files_parts resolved
        $this->assertSame('rsq_val', $row->tag_value);
        $this->assertNotEmpty($row->filename);          // files JOIN resolved
    }

    public function testGetProjectStatsVolumeAnalysisHonoursStatusFilter(): void
    {
        // status_analysis NOT in ('NEW','FAST_OK','DONE') -> filtered out
        $chunk = $this->buildAnalysisChunk('NEW_WAITING_FOR_CHECK');

        $rows = $this->dao->getProjectStatsVolumeAnalysis($chunk['id_project'], 0);

        $this->assertSame([], $rows);
    }

    public function testGetProjectStatsVolumeAnalysisReturnsEmptyForUnknownProject(): void
    {
        $this->assertSame([], $this->dao->getProjectStatsVolumeAnalysis(2_000_333_444, 0));
    }

    public function testGetProjectStatsVolumeAnalysisResolvesDoneStatus(): void
    {
        $chunk = $this->buildAnalysisChunk('DONE');

        $rows = $this->dao->getProjectStatsVolumeAnalysis($chunk['id_project'], 0);

        $this->assertCount(1, $rows);
        $this->assertSame('DONE', $rows[0]->status_analysis);
    }

    /**
     * The one address this DAO caches. The eviction is asserted through a value written behind the
     * cache, so it proves the entry is gone rather than that the call returned true.
     */
    public function testDestroyCacheProjectStatsVolumeAnalysisEvictsTheWarmedEntry(): void
    {
        $chunk = $this->buildAnalysisChunk('NEW');

        $this->assertSame(12, (int)$this->dao->getProjectStatsVolumeAnalysis($chunk['id_project'], 60)[0]->raw_word_count);
        $this->editWordCountBehindTheCache($chunk['id_project'], 34);
        $this->assertSame(12, (int)$this->dao->getProjectStatsVolumeAnalysis($chunk['id_project'], 60)[0]->raw_word_count);

        $this->assertTrue($this->dao->destroyCacheProjectStatsVolumeAnalysis($chunk['id_project']));

        $this->assertSame(34, (int)$this->dao->getProjectStatsVolumeAnalysis($chunk['id_project'], 60)[0]->raw_word_count);
    }

    public function testDestroyCacheProjectStatsVolumeAnalysisLeavesAnotherProjectCached(): void
    {
        $mine = $this->buildAnalysisChunk('NEW');
        $other = $this->buildAnalysisChunk('NEW');

        $this->dao->getProjectStatsVolumeAnalysis($other['id_project'], 60);
        $this->editWordCountBehindTheCache($other['id_project'], 34);

        $this->dao->destroyCacheProjectStatsVolumeAnalysis($mine['id_project']);

        $this->assertSame(12, (int)$this->dao->getProjectStatsVolumeAnalysis($other['id_project'], 60)[0]->raw_word_count);
    }

    /** Change the joined row without going through the DAO, so a stale entry is observable. */
    private function editWordCountBehindTheCache(int $idProject, int $rawWordCount): void
    {
        $this->realSqlDb()->getConnection()->prepare(
            'UPDATE segments s ' .
            ' JOIN files f ON f.id = s.id_file ' .
            ' SET s.raw_word_count = :count WHERE f.id_project = :project'
        )->execute(['count' => $rawWordCount, 'project' => $idProject]);
    }
}
