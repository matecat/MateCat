<?php

namespace Matecat\Core\DAO\TestWarningDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Jobs\JobStruct;
use Model\Jobs\WarningsCountStruct;
use Model\Translations\WarningDao;
use Model\Warnings\GlobalWarningStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\TranslationStatus;

/**
 * Real-SQL coverage for WarningDao (campaign dao-realsql-90).
 *
 * All three public methods (getWarningsByProjectIds, getErrorsByChunk,
 * getWarningsByJobIdAndPassword) run against the real unittest DB. One job + four
 * segment_translations rows (varying warning bitmask and status) drive every filter branch.
 * Rows are inserted directly under assignable ids (>= ASSIGNABLE_ID_FLOOR) and cleaned
 * job-scoped; the residue gate asserts whole-table COUNT(*) is unchanged (DoD c).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class WarningDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private WarningDao $dao;
    private int $jobId;
    private int $idProject;
    private string $password = 'rsq_warn_pwd';
    private int $firstSeg;
    private int $lastSeg;

    protected function realSqlTableDeps(): array
    {
        return ['jobs', 'segment_translations'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();

        $this->jobId = self::ASSIGNABLE_ID_FLOOR + 8801;
        $this->idProject = self::ASSIGNABLE_ID_FLOOR + 8802;
        $this->firstSeg = self::ASSIGNABLE_ID_FLOOR + 8810;
        $this->lastSeg = self::ASSIGNABLE_ID_FLOOR + 8820;

        $this->insertJob();
        // two ERROR rows in a counted status, one no-warning row, one ERROR row in NEW (excluded)
        $this->insertTranslation($this->firstSeg + 1, 1, TranslationStatus::STATUS_TRANSLATED, '{"e":1}');
        $this->insertTranslation($this->firstSeg + 2, 1, TranslationStatus::STATUS_APPROVED, '{"e":2}');
        $this->insertTranslation($this->firstSeg + 3, 0, TranslationStatus::STATUS_TRANSLATED, null);
        $this->insertTranslation($this->firstSeg + 4, 1, TranslationStatus::STATUS_NEW, '{"e":3}');

        $this->dao = new WarningDao($this->realSqlDb);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown(function (): void {
            $conn = $this->realSqlDb->getConnection();
            $conn->exec("DELETE FROM segment_translations WHERE id_job = {$this->jobId}");
            $conn->exec("DELETE FROM jobs WHERE id = {$this->jobId}");
        });
        parent::tearDown();
    }

    private function insertJob(): void
    {
        $stmt = $this->realSqlDb->getConnection()->prepare(
            "INSERT INTO jobs (id, password, id_project, job_first_segment, job_last_segment, tm_keys, create_date, disabled) "
            . "VALUES (:id, :pwd, :pid, :first, :last, '', NOW(), 0)"
        );
        $stmt->execute([
            'id'    => $this->jobId,
            'pwd'   => $this->password,
            'pid'   => $this->idProject,
            'first' => $this->firstSeg,
            'last'  => $this->lastSeg,
        ]);
    }

    private function insertTranslation(int $idSegment, int $warning, string $status, ?string $errors): void
    {
        $stmt = $this->realSqlDb->getConnection()->prepare(
            "INSERT INTO segment_translations (id_segment, id_job, segment_hash, status, warning, serialized_errors_list) "
            . "VALUES (:s, :j, '', :st, :w, :err)"
        );
        $stmt->execute([
            's'   => $idSegment,
            'j'   => $this->jobId,
            'st'  => $status,
            'w'   => $warning,
            'err' => $errors,
        ]);
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertInjectedConnection($this->dao);
    }

    #[Test]
    public function getWarningsByProjectIds_counts_warned_segments_per_job(): void
    {
        $result = $this->dao->getWarningsByProjectIds([$this->idProject]);

        $this->assertCount(1, $result);
        $row = $result[0];
        $this->assertInstanceOf(WarningsCountStruct::class, $row);
        $this->assertSame($this->jobId, (int)$row->id_job);
        $this->assertSame(2, (int)$row->count); // the TRANSLATED + APPROVED ERROR rows
        // segment_list aggregates the two counted segment ids in order
        $this->assertStringContainsString((string)($this->firstSeg + 1), (string)$row->segment_list);
        $this->assertStringContainsString((string)($this->firstSeg + 2), (string)$row->segment_list);
    }

    #[Test]
    public function getWarningsByProjectIds_builds_multi_placeholder_in_clause(): void
    {
        // two project ids exercises the str_repeat(",?") placeholder branch
        $result = $this->dao->getWarningsByProjectIds([$this->idProject, $this->idProject + 999999]);

        $this->assertCount(1, $result);
        $this->assertSame($this->jobId, (int)$result[0]->id_job);
    }

    #[Test]
    public function getErrorsByChunk_counts_error_bitmask_rows_excluding_new(): void
    {
        $chunk = new JobStruct();
        $chunk->id = $this->jobId;
        $chunk->password = $this->password;

        // ERROR bit set on the TRANSLATED + APPROVED rows; the NEW one is excluded by status
        $this->assertSame(2, $this->dao->getErrorsByChunk($chunk));
    }

    #[Test]
    public function getErrorsByChunk_returns_zero_when_no_row_matches(): void
    {
        $chunk = new JobStruct();
        $chunk->id = $this->jobId;
        $chunk->password = 'wrong-password';

        $this->assertSame(0, $this->dao->getErrorsByChunk($chunk));
    }

    #[Test]
    public function getWarningsByJobIdAndPassword_returns_global_warnings(): void
    {
        $rows = $this->dao->getWarningsByJobIdAndPassword($this->jobId, $this->password);

        $this->assertCount(2, $rows);
        $this->assertContainsOnlyInstancesOf(GlobalWarningStruct::class, $rows);
        $segIds = array_map(fn(GlobalWarningStruct $g) => (int)$g->id_segment, $rows);
        $this->assertContains($this->firstSeg + 1, $segIds);
        $this->assertContains($this->firstSeg + 2, $segIds);
    }

    #[Test]
    public function getWarningsByJobIdAndPassword_returns_empty_for_unknown_job(): void
    {
        $rows = $this->dao->getWarningsByJobIdAndPassword($this->jobId, 'wrong-password');

        $this->assertSame([], $rows);
    }
}
