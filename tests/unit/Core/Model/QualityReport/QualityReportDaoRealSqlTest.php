<?php

namespace Matecat\Core\Model\QualityReport;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\QualityReport\QualityReportDao;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-SQL coverage for QualityReportDao (plan dao-realsql-90.md, Wave 1 deep pilot — the
 * multi-table cleanup proof, DoD). The DAO is read-only (4 SELECT methods over a ~9-table join
 * graph); every public SQL method is called DIRECTLY against a builder-seeded chunk and
 * asserted on real returned data (DoD b). Cleanup tears the whole topology back to baseline
 * (residue gate over all 9 tableDeps).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class QualityReportDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    /** Full join graph touched by the four QualityReport queries. */
    private const array TABLE_DEPS = [
        'segment_translations',
        'jobs',
        'segments',
        'files_job',
        'files',
        'segment_translation_versions',
        'qa_entries',
        'qa_entry_comments',
        'qa_categories',
    ];

    private QualityReportDao $dao;
    private int $uid;

    /** @var array{id_project:int,id_file:int,id_job:int,password:string,id_segment:int,id_category:int,id_qa_entry:int,id_qa_entry_comment:int} */
    private array $chunk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new QualityReportDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);

        $this->uid = $this->fixtures->makeUser()['uid'];
        $this->chunk = $this->fixtures->makeQualityReportChunk($this->uid);
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    private function chunkAsJob(): JobStruct
    {
        $job = new JobStruct();
        $job->id = $this->chunk['id_job'];
        $job->password = $this->chunk['password'];

        return $job;
    }

    public function testGetAveragesReturnsRoundedAggregates(): void
    {
        $row = $this->dao->getAverages($this->chunkAsJob());

        $this->assertIsArray($row);
        $this->assertArrayHasKey('avg_time_to_edit', $row);
        $this->assertArrayHasKey('avg_edit_distance', $row);
        // The single seeded segment_translation has time_to_edit=1000, edit_distance=20.
        $this->assertSame(1000.0, (float)$row['avg_time_to_edit']);
        $this->assertSame(20.0, (float)$row['avg_edit_distance']);
    }

    public function testGetAveragesReturnsNullsForJobWithNoSegments(): void
    {
        // A job with no matching translations -> AVG over empty set yields a row of NULLs.
        $project = $this->fixtures->makeProject();
        $job = $this->fixtures->makeJob($project['id'], ['job_first_segment' => 1, 'job_last_segment' => 1]);

        $emptyJob = new JobStruct();
        $emptyJob->id = $job['id'];
        $emptyJob->password = $job['password'];

        $row = $this->dao->getAverages($emptyJob);

        $this->assertIsArray($row);
        $this->assertNull($row['avg_time_to_edit']);
        $this->assertNull($row['avg_edit_distance']);
    }

    public function testGetSegmentsForQualityReportReturnsJoinedRows(): void
    {
        $rows = $this->dao->getSegmentsForQualityReport($this->chunkAsJob());

        $this->assertNotEmpty($rows);
        $first = $rows[0];
        $this->assertSame($this->chunk['id_segment'], (int)$first['segment_id']);
        $this->assertSame($this->chunk['id_file'], (int)$first['file_id']);
        $this->assertArrayHasKey('translation', $first);
        $this->assertArrayHasKey('issue_category', $first);
        // The LEFT JOIN to segment_translation_versions(v0) must resolve the original.
        $this->assertSame('original v0', $first['original_translation']);
    }

    public function testGetSegmentsForQualityReportEmptyForUnknownJob(): void
    {
        $job = new JobStruct();
        $job->id = 2_000_000_777;
        $job->password = 'no-such-pass';

        $this->assertSame([], $this->dao->getSegmentsForQualityReport($job));
    }

    public function testGetIssuesBySegmentsReturnsEntries(): void
    {
        $rows = $this->dao->getIssuesBySegments([$this->chunk['id_segment']], $this->chunk['id_job']);

        $this->assertNotEmpty($rows);
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $rows[0]);
        $this->assertSame($this->chunk['id_segment'], (int)$rows[0]->segment_id);
        $this->assertSame($this->chunk['id_qa_entry'], (int)$rows[0]->issue_id);
    }

    public function testGetIssuesBySegmentsEmptyForUnknownSegment(): void
    {
        $rows = $this->dao->getIssuesBySegments([2_000_000_888], $this->chunk['id_job']);

        $this->assertSame([], $rows);
    }

    public function testGetReviseIssuesByChunkReturnsIssues(): void
    {
        // qa_entry seeded with source_page = 2 (SOURCE_PAGE_REVISION) and id_segment within
        // the job segment bounds.
        $rows = $this->dao->getReviseIssuesByChunk($this->chunk['id_job'], $this->chunk['password'], 2);

        $this->assertNotEmpty($rows);
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $rows[0]);
        $this->assertSame($this->chunk['id_qa_entry'], (int)$rows[0]->issue_id);
    }

    public function testGetReviseIssuesByChunkDefaultsSourcePageWhenNull(): void
    {
        // Default branch (source_page = null -> SOURCE_PAGE_REVISION = 2) must still match.
        $rows = $this->dao->getReviseIssuesByChunk($this->chunk['id_job'], $this->chunk['password']);

        $this->assertNotEmpty($rows);
        $this->assertSame($this->chunk['id_qa_entry'], (int)$rows[0]->issue_id);
    }

    public function testGetReviseIssuesByChunkEmptyForWrongPassword(): void
    {
        $rows = $this->dao->getReviseIssuesByChunk($this->chunk['id_job'], 'wrong-password', 2);

        $this->assertSame([], $rows);
    }
}
