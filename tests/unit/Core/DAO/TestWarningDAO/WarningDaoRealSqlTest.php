<?php

namespace Matecat\Core\DAO\TestWarningDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Jobs\JobStruct;
use Model\Jobs\WarningsCountStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use Model\Translations\WarningDao;
use Model\Warnings\GlobalWarningStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\TranslationStatus;

/**
 * Real-SQL coverage for WarningDao (campaign dao-realsql-90).
 *
 * The public read methods (getWarningsCountByJob, getErrorsByChunk,
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
    public function getWarningsCountByJob_counts_warned_segments_of_the_chunk(): void
    {
        $row = $this->dao->getWarningsCountByJob($this->jobId, $this->password);

        $this->assertInstanceOf(WarningsCountStruct::class, $row);
        $this->assertSame($this->jobId, (int)$row->id_job);
        $this->assertSame(2, (int)$row->count); // the TRANSLATED + APPROVED ERROR rows; NEW is not counted
        $this->assertSame(($this->firstSeg + 1) . ',' . ($this->firstSeg + 2), $row->segment_list);
    }

    #[Test]
    public function getWarningsCountByJob_counts_every_status_but_new(): void
    {
        $this->insertTranslation($this->firstSeg + 5, 1, TranslationStatus::STATUS_APPROVED2, '{"e":5}');
        $this->insertTranslation($this->firstSeg + 6, 1, TranslationStatus::STATUS_DRAFT, '{"e":6}');
        $this->insertTranslation($this->firstSeg + 7, 1, TranslationStatus::STATUS_REJECTED, '{"e":7}');
        $this->insertTranslation($this->firstSeg + 8, 1, TranslationStatus::STATUS_FIXED, '{"e":8}');

        $row = $this->dao->getWarningsCountByJob($this->jobId, $this->password);

        // The same segments /warnings lists, so the count and the endpoint never disagree.
        $listed = array_map(
            fn(GlobalWarningStruct $g) => (int)$g->id_segment,
            $this->dao->getWarningsByJobIdAndPassword($this->jobId, $this->password)
        );
        sort($listed);

        $this->assertSame(6, (int)$row?->count);
        $this->assertSame(implode(',', $listed), $row?->segment_list);
    }

    #[Test]
    public function getWarningsCountByJob_counts_any_bitmask_with_the_error_bit(): void
    {
        $this->setWarningDirectly($this->firstSeg + 3, 3);

        $this->assertSame(3, (int)$this->dao->getWarningsCountByJob($this->jobId, $this->password)?->count);
    }

    #[Test]
    public function getWarningsCountByJob_returns_null_for_a_wrong_password(): void
    {
        $this->assertNull($this->dao->getWarningsCountByJob($this->jobId, 'wrong-password'));
    }

    #[Test]
    public function getWarningsCountByJob_is_cached_until_evicted(): void
    {
        $this->assertSame(2, (int)$this->cachedCount()?->count);

        // A write that bypasses the DAO evicts nothing: the cached count must still be served.
        $this->setWarningDirectly($this->firstSeg + 3, 1);
        $this->assertSame(2, (int)$this->cachedCount()?->count);

        $this->dao->destroyCachesByJobId($this->jobId);
        $this->assertSame(3, (int)$this->cachedCount()?->count);
    }

    #[Test]
    public function addTranslation_evicts_the_cached_warnings_count(): void
    {
        $this->assertSame(2, (int)$this->cachedCount()?->count);

        $translation = new SegmentTranslationStruct();
        $translation->id_segment = $this->firstSeg + 3;
        $translation->id_job = $this->jobId;
        $translation->status = TranslationStatus::STATUS_TRANSLATED;
        $translation->translation = 'Ciao';
        $translation->serialized_errors_list = '{"e":4}';
        $translation->warning = true;
        $translation->translation_date = date('Y-m-d H:i:s');

        (new SegmentTranslationDao($this->realSqlDb))->addTranslation($translation, false);

        $this->assertSame(3, (int)$this->cachedCount()?->count);
    }

    #[Test]
    public function updateTranslationAndStatusAndDateByList_evicts_the_cached_warnings_count(): void
    {
        $this->assertSame(2, (int)$this->cachedCount()?->count);

        // The APPROVED row moves back to NEW, the one status the count ignores.
        $translation = new SegmentTranslationStruct();
        $translation->id_segment = $this->firstSeg + 2;
        $translation->id_job = $this->jobId;
        $translation->status = TranslationStatus::STATUS_NEW;
        $translation->translation = 'Ciao';
        $translation->translation_date = date('Y-m-d H:i:s');
        $translation->version_number = 0;

        (new SegmentTranslationDao($this->realSqlDb))->updateTranslationAndStatusAndDateByList([$translation]);

        $this->assertSame(1, (int)$this->cachedCount()?->count);
    }

    private function cachedCount(): ?WarningsCountStruct
    {
        return (new WarningDao($this->realSqlDb))->setCacheTTL(WarningDao::WARNINGS_CACHE_TTL)->getWarningsCountByJob($this->jobId, $this->password);
    }

    /**
     * @return array<int, GlobalWarningStruct>
     */
    private function cachedWarnings(): array
    {
        return (new WarningDao($this->realSqlDb))->setCacheTTL(WarningDao::WARNINGS_CACHE_TTL)->getWarningsByJobIdAndPassword($this->jobId, $this->password);
    }

    private function setWarningDirectly(int $idSegment, int $warning): void
    {
        $stmt = $this->realSqlDb->getConnection()->prepare(
            "UPDATE segment_translations SET warning = :w, serialized_errors_list = COALESCE(serialized_errors_list, '{}') WHERE id_segment = :s AND id_job = :j"
        );
        $stmt->execute(['w' => $warning, 's' => $idSegment, 'j' => $this->jobId]);
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
    public function getWarningsByJobIdAndPassword_is_cached_until_evicted(): void
    {
        $this->assertCount(2, $this->cachedWarnings());

        $this->setWarningDirectly($this->firstSeg + 3, 1);
        $this->assertCount(2, $this->cachedWarnings());

        $this->dao->destroyCachesByJobId($this->jobId);
        $this->assertCount(3, $this->cachedWarnings());
    }

    #[Test]
    public function one_eviction_refreshes_both_the_count_and_the_warnings_list(): void
    {
        $this->assertSame(2, (int)$this->cachedCount()?->count);
        $this->assertCount(2, $this->cachedWarnings());

        $translation = new SegmentTranslationStruct();
        $translation->id_segment = $this->firstSeg + 3;
        $translation->id_job = $this->jobId;
        $translation->status = TranslationStatus::STATUS_APPROVED2;
        $translation->translation = 'Ciao';
        $translation->serialized_errors_list = '{"e":4}';
        $translation->warning = true;
        $translation->translation_date = date('Y-m-d H:i:s');

        (new SegmentTranslationDao($this->realSqlDb))->addTranslation($translation, true);

        $this->assertSame(3, (int)$this->cachedCount()?->count);
        $this->assertCount(3, $this->cachedWarnings());
    }

    #[Test]
    public function getWarningsByJobIdAndPassword_returns_empty_for_unknown_job(): void
    {
        $rows = $this->dao->getWarningsByJobIdAndPassword($this->jobId, 'wrong-password');

        $this->assertSame([], $rows);
    }
}
