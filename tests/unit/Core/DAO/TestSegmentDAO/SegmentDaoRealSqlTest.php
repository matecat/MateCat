<?php

namespace Matecat\Core\DAO\TestSegmentDAO;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Matecat\TestHelpers\TestFixtureBuilder;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Files\FileStruct;
use Model\Jobs\JobStruct;
use Model\QualityReport\QualityReportSegmentStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentStruct;
use Model\Segments\SegmentUIStruct;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\TranslationStatus;

/**
 * Real-SQL characterization tests for SegmentDao (campaign dao-realsql-90, SegmentDao leg).
 *
 * Every public SQL method is exercised DIRECTLY against the real unittest DB, traversing as many
 * branches as possible (closure-style harness, RealSqlDaoTestTrait).
 *
 * A rich FK graph is built per test: project -> job (with password + first/last segment bounds)
 * -> file -> files_job -> segments -> segment_translations (varied status/match_type/locked) ->
 * segment_translation_events (varied source_page) -> qa_entries (varied category/severity) plus
 * segment_revisions / segment_translations_splits / segment_original_data / files_parts.
 *
 * All allocated ids for assignable / direct-insert rows live >= ASSIGNABLE_ID_FLOOR (M-2);
 * AUTO_INCREMENT rows created via the builder are auto-cleaned. Rows the test inserts directly
 * are deleted in the tearDown closure and every touched table is declared in realSqlTableDeps()
 * so the whole-table COUNT(*) residue gate (DoD c) returns to baseline.
 *
 * Word-count style columns use whole numbers to keep AbstractDao hydration deprecation-clean.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class SegmentDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private SegmentDao $dao;

    /** Project / job topology shared across most tests. */
    private int $idProject;
    private int $idJob;
    private string $password;
    private int $idFile;
    private int $idFilePart;

    /** Ordered segment ids created in setUp (4 segments inside the job bounds). */
    private array $segIds = [];

    /** The job struct the QR/pagination/mismatch methods consume. */
    private JobStruct $jobStruct;

    protected function realSqlTableDeps(): array
    {
        return [
            'projects',
            'jobs',
            'files',
            'files_job',
            'files_parts',
            'segments',
            'segment_translations',
            'segment_translation_events',
            'segment_translations_splits',
            'segment_original_data',
            'segment_revisions',
            'qa_categories',
            'qa_entries',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();
        // Closure-style realSqlSetUp() does not build a fixtures instance; create one bound to
        // the same per-test connection so the builder helpers + their tracked-row cleanup share
        // the DAO's PDO handle.
        $this->fixtures = new TestFixtureBuilder($this->realSqlDb);
        $this->dao      = new SegmentDao($this->realSqlDb);

        $this->buildTopology();
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown(function (): void {
            $conn = $this->realSqlDb->getConnection();
            // Direct-insert rows (rows the builder did not track) — scoped to this job/segments.
            // Delete child rows first, then let the builder remove its tracked parents in
            // reverse insertion order.
            $conn->exec("DELETE FROM segment_revisions WHERE id_job = {$this->idJob}");
            $conn->exec("DELETE FROM segment_original_data WHERE id_segment >= " . self::ASSIGNABLE_ID_FLOOR);
            $conn->exec("DELETE FROM segment_translations WHERE id_job = {$this->idJob}");
            $conn->exec("DELETE FROM segment_translation_events WHERE id_job = {$this->idJob}");
            $this->fixtures->cleanup();
        });
        parent::tearDown();
    }

    /**
     * Build project -> file -> 4 segments -> job (bounded to span the 4 segments) -> files_job,
     * plus a file part. Segment translations / events / qa rows are added per-test so each test
     * controls its own branch inputs.
     *
     * @throws Exception
     */
    private function buildTopology(): void
    {
        $conn = $this->realSqlDb->getConnection();

        $project         = $this->fixtures->makeProject();
        $this->idProject = $project['id'];
        $this->password  = 'rsqpw' . substr((string)$this->idProject, -5);

        // projects.password is needed by getSegmentsForAnalysisFromIdProjectAndPassword; the
        // builder leaves it NULL, so set it (UPDATE does not change COUNT(*)).
        $conn->exec("UPDATE projects SET password = '{$this->password}', name = 'rsq project' WHERE id = {$this->idProject}");

        $file          = $this->fixtures->makeFile($this->idProject);
        $this->idFile  = $file['id'];

        $filePart          = $this->fixtures->makeFilesPart($this->idFile, 'rsq_tag', 'rsq_val');
        $this->idFilePart  = $filePart['id'];

        // 4 visible segments + 1 hidden (show_in_cattool = 0) to exercise the cattool filter.
        for ($i = 0; $i < 4; $i++) {
            $seg            = $this->fixtures->makeSegment($this->idFile, true, "segment $i");
            $this->segIds[] = $seg['id'];
        }
        $hidden = $this->fixtures->makeSegment($this->idFile, false, 'hidden segment');
        $this->segIds[] = $hidden['id'];

        // makeSegment() leaves internal_id / raw_word_count NULL; SegmentStruct hydration types
        // them non-null, so populate them. Whole-number raw_word_count keeps hydration
        // deprecation-clean. id_file_part links segments to the files_parts row.
        foreach ($this->segIds as $sid) {
            $conn->exec(
                "UPDATE segments SET internal_id = 'int_$sid', raw_word_count = 4, id_file_part = {$this->idFilePart} WHERE id = $sid"
            );
        }

        $first = min($this->segIds);
        $last  = max($this->segIds);

        $job = $this->fixtures->makeJob($this->idProject, [
            'password'          => $this->password,
            'job_first_segment' => $first,
            'job_last_segment'  => $last,
            'source'            => 'en-US',
            'target'            => 'it-IT',
            'owner'             => 'rsq_owner@example.test',
        ]);
        $this->idJob = $job['id'];
        $this->fixtures->makeFilesJob($this->idJob, $this->idFile);

        $this->jobStruct = $this->makeJobStruct($this->idJob, $this->password, $first, $last);
    }

    private function makeJobStruct(int $id, string $password, int $first, int $last): JobStruct
    {
        $j                    = new JobStruct();
        $j->id                = $id;
        $j->password          = $password;
        $j->id_project        = $this->idProject;
        $j->job_first_segment = $first;
        $j->job_last_segment  = $last;
        $j->source            = 'en-US';
        $j->target            = 'it-IT';

        return $j;
    }

    /**
     * Insert a segment_translation directly so match_type / locked / translation_date can be set
     * (the builder helper omits those columns).
     *
     * @param array<string,int|string|null> $extra
     */
    private function insertTranslation(int $idSegment, string $status, array $extra = []): void
    {
        $cols = array_merge([
            'id_segment'     => $idSegment,
            'id_job'         => $this->idJob,
            'segment_hash'   => $extra['segment_hash'] ?? ('h' . $idSegment),
            'status'         => $status,
            'translation'    => $extra['translation'] ?? "translation $idSegment",
            'version_number' => $extra['version_number'] ?? 0,
            'match_type'     => $extra['match_type'] ?? 'TM',
            'locked'         => $extra['locked'] ?? 0,
            'time_to_edit'   => $extra['time_to_edit'] ?? 100,
            'eq_word_count'  => $extra['eq_word_count'] ?? 5,
        ], array_intersect_key($extra, array_flip(['translation_date'])));

        $colNames = array_keys($cols);
        $place    = array_map(fn($c) => ":$c", $colNames);
        $sql      = 'INSERT INTO segment_translations (' . implode(',', $colNames) . ') VALUES (' . implode(',', $place) . ')';
        $stmt     = $this->realSqlDb->getConnection()->prepare($sql);
        $stmt->execute($cols);
    }

    private function insertEvent(int $idSegment, int $sourcePage, int $versionNumber = 0, int $finalRevision = 0): void
    {
        $this->fixtures->makeSegmentTranslationEvent($this->idJob, $idSegment, [
            'source_page'    => $sourcePage,
            'version_number' => $versionNumber,
            'final_revision' => $finalRevision,
        ]);
    }

    private function insertQaEntry(int $idSegment, int $idCategory, string $severity, int $sourcePage): int
    {
        $entry = $this->fixtures->makeQaEntry($idSegment, $this->idJob, $idCategory, [
            'severity'    => $severity,
            'source_page' => $sourcePage,
        ]);

        return $entry['id'];
    }

    // =========================================================================================
    // Connection seam
    // =========================================================================================

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertInjectedConnection($this->dao);
    }

    // =========================================================================================
    // countByFile
    // =========================================================================================

    #[Test]
    public function countByFile_counts_all_segments_of_the_file(): void
    {
        $file     = new FileStruct();
        $file->id = $this->idFile;

        // 5 segments were created in setUp (4 visible + 1 hidden); all belong to this file.
        $this->assertSame(5, $this->dao->countByFile($file));

        $empty     = new FileStruct();
        $empty->id = self::ASSIGNABLE_ID_FLOOR + 999_999;
        $this->assertSame(0, $this->dao->countByFile($empty));
    }

    // =========================================================================================
    // getByChunkIdAndSegmentId
    // =========================================================================================

    #[Test]
    public function getByChunkIdAndSegmentId_returns_the_struct_and_null_for_misses(): void
    {
        $sid = $this->segIds[0];

        $struct = $this->dao->getByChunkIdAndSegmentId($this->idJob, $this->password, $sid, 0);
        $this->assertInstanceOf(SegmentStruct::class, $struct);
        $this->assertSame($sid, $struct->id);
        $this->assertSame($this->idFile, $struct->id_file);

        // wrong password -> no row
        $this->assertNull($this->dao->getByChunkIdAndSegmentId($this->idJob, 'wrong-pw', $sid, 0));
        // unknown segment -> no row
        $this->assertNull($this->dao->getByChunkIdAndSegmentId($this->idJob, $this->password, self::ASSIGNABLE_ID_FLOOR + 1, 0));
    }

    // =========================================================================================
    // getByChunkId
    // =========================================================================================

    #[Test]
    public function getByChunkId_returns_every_segment_in_the_job_bounds(): void
    {
        $rows = $this->dao->getByChunkId($this->idJob, $this->password);

        $this->assertNotEmpty($rows);
        $this->assertContainsOnlyInstancesOf(SegmentStruct::class, $rows);
        // all created segments fall within [first,last]; expect all 5.
        $ids = array_map(fn(SegmentStruct $s) => $s->id, $rows);
        foreach ($this->segIds as $sid) {
            $this->assertContains($sid, $ids);
        }

        $this->assertSame([], $this->dao->getByChunkId($this->idJob, 'wrong-pw'));
    }

    // =========================================================================================
    // getContextAndSegmentByIDs
    // =========================================================================================

    #[Test]
    public function getContextAndSegmentByIDs_maps_before_center_after(): void
    {
        $idList = [
            'id_before'  => $this->segIds[0],
            'id_segment' => $this->segIds[1],
            'id_after'   => $this->segIds[2],
        ];

        $result = $this->dao->getContextAndSegmentByIDs($idList);

        $this->assertIsObject($result);
        $this->assertInstanceOf(SegmentStruct::class, $result->id_before);
        $this->assertInstanceOf(SegmentStruct::class, $result->id_segment);
        $this->assertInstanceOf(SegmentStruct::class, $result->id_after);
        $this->assertSame($this->segIds[1], $result->id_segment->id);
    }

    // =========================================================================================
    // getSegmentsIdForQR — the branchy one.
    // =========================================================================================

    private function seedQrTranslations(): void
    {
        // visible segments get translations; vary status to exercise the status filter + ICE.
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_APPROVED);
        // ICE-eligible row for the union: status matches filter, version 0, match_type ICE, null date.
        $this->insertTranslation($this->segIds[2], TranslationStatus::STATUS_TRANSLATED, [
            'match_type'       => 'ICE',
            'version_number'   => 0,
            'translation_date' => null,
        ]);
        $this->insertTranslation($this->segIds[3], TranslationStatus::STATUS_APPROVED);
    }

    #[Test]
    public function getSegmentsIdForQR_after_returns_ids_greater_than_ref(): void
    {
        $this->seedQrTranslations();
        $ref = $this->segIds[0];

        $ids = $this->dao->getSegmentsIdForQR($this->jobStruct, 100, $ref, 'after');

        $this->assertNotEmpty($ids);
        foreach ($ids as $sid) {
            $this->assertGreaterThan($ref, (int)$sid);
        }
    }

    #[Test]
    public function getSegmentsIdForQR_before_returns_ids_less_than_ref(): void
    {
        $this->seedQrTranslations();
        $ref = $this->segIds[3];

        $ids = $this->dao->getSegmentsIdForQR($this->jobStruct, 100, $ref, 'before');

        $this->assertNotEmpty($ids);
        foreach ($ids as $sid) {
            $this->assertLessThan($ref, (int)$sid);
        }
    }

    #[Test]
    public function getSegmentsIdForQR_center_hits_the_center_arm(): void
    {
        // The 'center' arm of getSegmentsIdForQR is a PRE-EXISTING DAO BUG (not test-induced):
        // sprintf($queryCenter, join, cond, step, step, join, cond, step) supplies 7 args for
        // only 6 conversion specifiers (s,s,u / s,s,u). The 4th arg ($step, an int) is therefore
        // positioned into the SECOND block's join `%s`, emitting a bare "100" where the JOIN
        // clause belongs and producing a MySQL 1064 syntax error near '100\n WHERE ...'. The
        // arm still executes through the match() + sprintf + prepare path (covering lib lines
        // 316-352, 354-361) before MySQL rejects it. We assert the documented failure so the
        // branch is exercised without masking the bug; fixing it is a lib/ concern, out of
        // scope for this test-only work.
        $this->seedQrTranslations();

        $this->expectException(\PDOException::class);
        $this->dao->getSegmentsIdForQR($this->jobStruct, 100, $this->segIds[1], 'center');
    }

    #[Test]
    public function getSegmentsIdForQR_throws_on_unknown_direction(): void
    {
        $this->seedQrTranslations();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No direction selected');
        $this->dao->getSegmentsIdForQR($this->jobStruct, 100, $this->segIds[0], 'sideways');
    }

    #[Test]
    public function getSegmentsIdForQR_status_filter_adds_ice_union(): void
    {
        $this->seedQrTranslations();

        // status filter on TRANSLATED -> activates the union_ice branch.
        $ids = $this->dao->getSegmentsIdForQR($this->jobStruct, 100, min($this->segIds) - 1, 'after', [
            'filter' => ['status' => TranslationStatus::STATUS_TRANSLATED],
        ]);

        $intIds = array_map('intval', $ids);
        // the plain TRANSLATED segment and the ICE segment should be present.
        $this->assertContains($this->segIds[0], $intIds);
        $this->assertContains($this->segIds[2], $intIds);
    }

    #[Test]
    public function getSegmentsIdForQR_issues_in_r_joins_qa_entries(): void
    {
        $this->seedQrTranslations();
        $cat = $this->fixtures->makeQaCategory('RsqQr', 1, '{}');
        // issues_in_r = 1 -> source_page = 2.
        $this->insertQaEntry($this->segIds[0], $cat['id'], 'minor', 2);

        $ids = $this->dao->getSegmentsIdForQR($this->jobStruct, 100, min($this->segIds) - 1, 'after', [
            'filter' => ['issues_in_r' => 1],
        ]);

        $this->assertContains($this->segIds[0], array_map('intval', $ids));
    }

    #[Test]
    public function getSegmentsIdForQR_issue_category_string_and_severity(): void
    {
        $this->seedQrTranslations();
        $cat = $this->fixtures->makeQaCategory('RsqQr2', 1, '{}');
        $this->insertQaEntry($this->segIds[1], $cat['id'], 'critical', 2);

        // single (string) category + severity filter.
        $ids = $this->dao->getSegmentsIdForQR($this->jobStruct, 100, min($this->segIds) - 1, 'after', [
            'filter' => [
                'issue_category' => (string)$cat['id'],
                'severity'       => 'critical',
            ],
        ]);

        $this->assertContains($this->segIds[1], array_map('intval', $ids));
    }

    #[Test]
    public function getSegmentsIdForQR_issue_category_array(): void
    {
        $this->seedQrTranslations();
        $cat = $this->fixtures->makeQaCategory('RsqQr3', 1, '{}');
        $this->insertQaEntry($this->segIds[1], $cat['id'], 'minor', 2);

        // array category -> placeholder IN(...) branch.
        $ids = $this->dao->getSegmentsIdForQR($this->jobStruct, 100, min($this->segIds) - 1, 'after', [
            'filter' => ['issue_category' => [(int)$cat['id']]],
        ]);

        $this->assertContains($this->segIds[1], array_map('intval', $ids));
    }

    #[Test]
    public function getSegmentsIdForQR_issue_category_all(): void
    {
        $this->seedQrTranslations();
        $cat = $this->fixtures->makeQaCategory('RsqQr4', 1, '{}');
        $this->insertQaEntry($this->segIds[0], $cat['id'], 'minor', 2);

        // 'all' -> e.id_category IS NOT NULL branch.
        $ids = $this->dao->getSegmentsIdForQR($this->jobStruct, 100, min($this->segIds) - 1, 'after', [
            'filter' => ['issue_category' => SegmentDao::ISSUE_CATEGORY_ALL],
        ]);

        $this->assertContains($this->segIds[0], array_map('intval', $ids));
    }

    #[Test]
    public function getSegmentsIdForQR_id_segment_filter_narrows_to_one(): void
    {
        $this->seedQrTranslations();

        $ids = $this->dao->getSegmentsIdForQR($this->jobStruct, 100, min($this->segIds) - 1, 'after', [
            'filter' => ['id_segment' => $this->segIds[1]],
        ]);

        $intIds = array_map('intval', $ids);
        $this->assertContains($this->segIds[1], $intIds);
        $this->assertNotContains($this->segIds[0], $intIds);
    }

    // =========================================================================================
    // getSegmentsForQr
    // =========================================================================================

    #[Test]
    public function getSegmentsForQr_returns_quality_report_structs(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED, ['match_type' => 'ICE', 'locked' => 1]);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_NEW);
        $this->insertEvent($this->segIds[0], 2);

        $rows = $this->dao->getSegmentsForQr(
            [$this->segIds[0], $this->segIds[1]],
            $this->idJob,
            $this->password
        );

        $this->assertNotEmpty($rows);
        $this->assertContainsOnlyInstancesOf(QualityReportSegmentStruct::class, $rows);
        $sids = array_map(fn(QualityReportSegmentStruct $s) => (int)$s->sid, $rows);
        $this->assertContains($this->segIds[0], $sids);
    }

    // =========================================================================================
    // createList
    // =========================================================================================

    #[Test]
    public function createList_batch_inserts_segments(): void
    {
        $newId1 = self::ASSIGNABLE_ID_FLOOR + 500_001;
        $newId2 = self::ASSIGNABLE_ID_FLOOR + 500_002;

        $structs = [
            $this->newSegmentStruct($newId1, 'created one'),
            $this->newSegmentStruct($newId2, 'created two'),
        ];

        $this->dao->createList($structs);

        // track for cleanup so the residue gate returns to baseline.
        $this->fixtures->trackExisting('segments', ['id' => $newId1]);
        $this->fixtures->trackExisting('segments', ['id' => $newId2]);

        $count = (int)$this->realSqlDb->getConnection()
            ->query("SELECT COUNT(*) FROM segments WHERE id IN ($newId1, $newId2)")
            ->fetchColumn();
        $this->assertSame(2, $count);
    }

    #[Test]
    public function createList_throws_when_a_segment_exceeds_the_size_limit(): void
    {
        $struct          = $this->newSegmentStruct(self::ASSIGNABLE_ID_FLOOR + 500_010, 'x');
        $struct->segment = str_repeat('a', 70000); // > 65kb limit

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Segment size limit reached');
        $this->dao->createList([$struct]);
    }

    #[Test]
    public function createList_wraps_pdo_errors(): void
    {
        // Duplicate PK against an already-existing segment -> PDOException -> rewrapped Exception.
        $dupId  = $this->segIds[0];
        $struct = $this->newSegmentStruct($dupId, 'dup');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Segment import - DB Error');
        $this->dao->createList([$struct]);
    }

    private function newSegmentStruct(int $id, string $segment): SegmentStruct
    {
        $s                          = new SegmentStruct();
        $s->id                      = $id;
        $s->internal_id             = 'int_' . $id;
        $s->id_file                 = $this->idFile;
        $s->id_file_part            = $this->idFilePart;
        $s->segment                 = $segment;
        $s->segment_hash            = substr(md5((string)$id), 0, 32);
        $s->raw_word_count          = 3;
        $s->xliff_mrk_id            = null;
        $s->xliff_ext_prec_tags     = null;
        $s->xliff_ext_succ_tags     = null;
        $s->show_in_cattool         = true;
        $s->xliff_mrk_ext_prec_tags = null;
        $s->xliff_mrk_ext_succ_tags = null;

        return $s;
    }

    // =========================================================================================
    // getPaginationSegments — switch after / before / center / optional_fields
    // =========================================================================================

    private function seedPaginationTranslations(): void
    {
        foreach ($this->segIds as $i => $sid) {
            $this->insertTranslation($sid, TranslationStatus::STATUS_TRANSLATED);
        }
        // split + original data rows to exercise the LEFT JOINs.
        $this->fixtures->makeSegmentTranslationsSplit($this->segIds[0], $this->idJob);
        $conn = $this->realSqlDb->getConnection();
        $odId = self::ASSIGNABLE_ID_FLOOR + 700_001;
        $stmt = $conn->prepare('INSERT INTO segment_original_data (id, id_segment, map) VALUES (:id, :sid, :map)');
        $stmt->execute(['id' => $odId, 'sid' => $this->segIds[0], 'map' => '{"k":"v"}']);
    }

    #[Test]
    public function getPaginationSegments_center_returns_ui_structs(): void
    {
        $this->seedPaginationTranslations();

        $rows = $this->dao->getPaginationSegments($this->jobStruct, 50, $this->segIds[1], 'center');

        $this->assertNotEmpty($rows);
        $this->assertContainsOnlyInstancesOf(SegmentUIStruct::class, $rows);
    }

    #[Test]
    public function getPaginationSegments_after_and_before(): void
    {
        $this->seedPaginationTranslations();

        $after = $this->dao->getPaginationSegments($this->jobStruct, 50, $this->segIds[0], 'after');
        $this->assertNotEmpty($after);

        $before = $this->dao->getPaginationSegments($this->jobStruct, 50, $this->segIds[3], 'before');
        $this->assertNotEmpty($before);
    }

    #[Test]
    public function getPaginationSegments_with_optional_fields(): void
    {
        $this->seedPaginationTranslations();

        $rows = $this->dao->getPaginationSegments($this->jobStruct, 50, $this->segIds[1], 'center', [
            'optional_fields' => ['st.suggestion'],
        ]);

        $this->assertNotEmpty($rows);
    }

    // =========================================================================================
    // getSegmentsDownload
    // =========================================================================================

    #[Test]
    public function getSegmentsDownload_returns_rows_for_the_file(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_APPROVED);
        // final revision r2 event so the LEFT JOIN + r2 IF column resolves.
        $this->fixtures->makeSegmentTranslationEvent($this->idJob, $this->segIds[0], [
            'source_page'    => 3,
            'version_number' => 0,
            'final_revision' => 1,
        ]);
        $conn = $this->realSqlDb->getConnection();
        $odId = self::ASSIGNABLE_ID_FLOOR + 700_050;
        $stmt = $conn->prepare('INSERT INTO segment_original_data (id, id_segment, map) VALUES (:id, :sid, :map)');
        $stmt->execute(['id' => $odId, 'sid' => $this->segIds[0], 'map' => '{"a":1}']);

        $rows = $this->dao->getSegmentsDownload($this->jobStruct, $this->idFile);

        $this->assertNotEmpty($rows);
        $this->assertIsArray($rows[0]);
        $this->assertArrayHasKey('sid', $rows[0]);
    }

    // =========================================================================================
    // destroyCacheForGlobalTranslationMismatches  +  getTranslationsMismatches
    // =========================================================================================

    #[Test]
    public function destroyCacheForGlobalTranslationMismatches_evicts_a_primed_entry(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED);

        $job     = new JobStruct();
        $job->id = $this->idJob;

        // Prime the cache via the global-aggregation read so a cache entry exists, then destroy.
        $this->dao->setCacheTTL(60)->getTranslationsMismatches($this->idJob, $this->password, null);

        $this->dao->setCacheTTL(60);
        $destroyed = $this->dao->destroyCacheForGlobalTranslationMismatches($job);
        $this->assertTrue($destroyed);
    }

    #[Test]
    public function getTranslationsMismatches_returns_empty_for_unknown_job(): void
    {
        $result = $this->dao->getTranslationsMismatches(self::ASSIGNABLE_ID_FLOOR + 888_888, 'no-pw', null);
        $this->assertSame([], $result);
    }

    #[Test]
    public function getTranslationsMismatches_local_path_with_sid(): void
    {
        // two segments sharing the same hash with different translations -> a mismatch.
        $hash = 'sharedhash000000';
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED, [
            'segment_hash' => $hash,
            'translation'  => 'alpha',
        ]);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_TRANSLATED, [
            'segment_hash' => $hash,
            'translation'  => 'beta',
        ]);
        // segments.segment_hash drives the local subquery; align it.
        $conn = $this->realSqlDb->getConnection();
        $conn->exec("UPDATE segments SET segment_hash = '$hash' WHERE id IN ({$this->segIds[0]}, {$this->segIds[1]})");

        $result = $this->dao->getTranslationsMismatches($this->idJob, $this->password, $this->segIds[0]);
        $this->assertIsArray($result);
    }

    #[Test]
    public function getTranslationsMismatches_global_aggregation_path(): void
    {
        $hash = 'globalhash000000';
        // same hash, two distinct translations, both inside the job bounds -> twin segment.
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED, [
            'segment_hash' => $hash,
            'translation'  => 'one',
        ]);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_TRANSLATED, [
            'segment_hash' => $hash,
            'translation'  => 'two',
        ]);

        $result = $this->dao->getTranslationsMismatches($this->idJob, $this->password, null);
        $this->assertIsArray($result);
    }

    // =========================================================================================
    // getNextSegment — getTranslatedInstead true / false
    // =========================================================================================

    #[Test]
    public function getNextSegment_untranslated_branch(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_NEW);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_DRAFT);

        $rows = $this->dao->getNextSegment($this->segIds[2], $this->idJob, $this->password, false);

        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('id', $rows[0]);
    }

    #[Test]
    public function getNextSegment_translated_instead_branch(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_APPROVED);

        $rows = $this->dao->getNextSegment($this->segIds[3], $this->idJob, $this->password, true);

        $this->assertNotEmpty($rows);
    }

    // =========================================================================================
    // getSegmentsForAnalysisFromIdJobAndPassword / getSegmentsForAnalysisFromIdProjectAndPassword
    // =========================================================================================

    private function seedAnalysisData(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_APPROVED);
        $this->insertEvent($this->segIds[0], 1);
        $this->insertEvent($this->segIds[1], 2);
        // give the segments a file part so the files_parts LEFT JOIN resolves a non-null branch.
        $conn = $this->realSqlDb->getConnection();
        $conn->exec("UPDATE segments SET id_file_part = {$this->idFilePart} WHERE id IN ({$this->segIds[0]}, {$this->segIds[1]})");
    }

    #[Test]
    public function getSegmentsForAnalysisFromIdJobAndPassword_returns_rows(): void
    {
        $this->seedAnalysisData();

        $rows = $this->dao->getSegmentsForAnalysisFromIdJobAndPassword($this->idJob, $this->password, 100, 0, 0);

        $this->assertNotEmpty($rows);
        $this->assertContainsOnlyInstancesOf(ShapelessConcreteStruct::class, $rows);
    }

    #[Test]
    public function getSegmentsForAnalysisFromIdProjectAndPassword_returns_rows(): void
    {
        $this->seedAnalysisData();

        $rows = $this->dao->getSegmentsForAnalysisFromIdProjectAndPassword($this->idProject, $this->password, 100, 0, 0);

        $this->assertNotEmpty($rows);
        $this->assertContainsOnlyInstancesOf(ShapelessConcreteStruct::class, $rows);
    }
}
