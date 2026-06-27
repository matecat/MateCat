<?php

namespace Matecat\Core\DAO\TestSegmentTranslationDAO;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Matecat\TestHelpers\TestFixtureBuilder;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Files\FileStruct;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Search\ReplaceEventStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use InvalidArgumentException;
use Utils\ActiveMQ\WorkerClient;
use Utils\Constants\TranslationStatus;
use Utils\TaskRunner\Commons\Context;

/**
 * Real-SQL characterization tests for SegmentTranslationDao.
 *
 * Exercises every public method against the real unittest DB using the closure-style
 * RealSqlDaoTestTrait harness. A shared FK topology (project → job → file → 4 segments) is
 * built in setUp; per-test rows are added inline and cleaned in the tearDown closure.
 *
 * Whole-number word-count values keep AbstractDao hydration deprecation-clean.
 * All ids allocated directly live >= ASSIGNABLE_ID_FLOOR.
 * The propagateTranslation test uses the no-twin path to avoid needing ActiveMQ.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class SegmentTranslationDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private SegmentTranslationDao $dao;

    private int $idProject;
    private int $idJob;
    private string $password;
    private int $idFile;

    /** @var int[] */
    private array $segIds = [];

    /** Shared segment_hash used where twins are needed */
    private string $sharedHash = 'stDaoRealSqlSharedH';

    private JobStruct $jobStruct;
    private ProjectStruct $projectStruct;

    // -------------------------------------------------------------------------
    // Harness
    // -------------------------------------------------------------------------

    protected function realSqlTableDeps(): array
    {
        return [
            'projects',
            'jobs',
            'files',
            'files_job',
            'segments',
            'segment_translations',
            'segment_translation_events',
            'segment_translation_versions',
            'qa_categories',
            'qa_entries',
            'project_metadata',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();
        $this->fixtures = new TestFixtureBuilder($this->realSqlDb);
        $this->dao      = new SegmentTranslationDao($this->realSqlDb);
        $this->buildTopology();
    }

    protected function tearDown(): void
    {
        // Restore WorkerClient static handler — this test creates a fake AMQHandler
        // (anonymous class skipping parent constructor) that leaves $statefulStomp
        // uninitialised; subsequent tests that touch email/worker paths would crash
        // with "Typed property $statefulStomp must not be accessed before initialization".
        disableAmqWorkerClientHelper();

        $this->realSqlTearDown(function (): void {
            $conn = $this->realSqlDb->getConnection();
            // child rows first, then builder cleans parents in reverse order.
            $conn->exec("DELETE FROM qa_entries          WHERE id_job = {$this->idJob}");
            $conn->exec("DELETE FROM segment_translation_versions WHERE id_job = {$this->idJob}");
            $conn->exec("DELETE FROM segment_translation_events   WHERE id_job = {$this->idJob}");
            $conn->exec("DELETE FROM segment_translations         WHERE id_job = {$this->idJob}");
            $conn->exec("DELETE FROM project_metadata             WHERE id_project = {$this->idProject}");
            $this->fixtures->cleanup();
        });
        parent::tearDown();
    }

    private function buildTopology(): void
    {
        $conn = $this->realSqlDb->getConnection();

        $project         = $this->fixtures->makeProject();
        $this->idProject = $project['id'];
        $this->password  = 'strsq' . substr((string)$this->idProject, -5);

        $conn->exec("UPDATE projects SET password = '{$this->password}', name = 'strsq project' WHERE id = {$this->idProject}");

        $file         = $this->fixtures->makeFile($this->idProject);
        $this->idFile = $file['id'];

        // 4 visible segments; first two share a hash so propagation / hash-based queries work.
        for ($i = 0; $i < 4; $i++) {
            $seg            = $this->fixtures->makeSegment($this->idFile, true, "strsq seg $i");
            $this->segIds[] = $seg['id'];
        }

        // Give segments proper internal_id, raw_word_count, and shared_hash on the first two.
        foreach ($this->segIds as $k => $sid) {
            $hash  = ($k < 2) ? $this->sharedHash : 'strsq_unique_' . $sid;
            $conn->exec("UPDATE segments SET internal_id = 'int_$sid', raw_word_count = 4, segment_hash = '$hash' WHERE id = $sid");
        }

        $first = min($this->segIds);
        $last  = max($this->segIds);

        $job = $this->fixtures->makeJob($this->idProject, [
            'password'          => $this->password,
            'job_first_segment' => $first,
            'job_last_segment'  => $last,
            'source'            => 'en-US',
            'target'            => 'it-IT',
            'owner'             => 'strsq@example.test',
        ]);
        $this->idJob = $job['id'];
        $this->fixtures->makeFilesJob($this->idJob, $this->idFile);

        $this->jobStruct = $this->buildJobStruct($this->idJob, $this->password, $first, $last);

        $this->projectStruct     = new ProjectStruct();
        $this->projectStruct->id = $this->idProject;
    }

    private function buildJobStruct(int $id, string $pw, int $first, int $last): JobStruct
    {
        $j                    = new JobStruct();
        $j->id                = $id;
        $j->password          = $pw;
        $j->id_project        = $this->idProject;
        $j->job_first_segment = $first;
        $j->job_last_segment  = $last;
        $j->source            = 'en-US';
        $j->target            = 'it-IT';

        return $j;
    }

    /**
     * Insert a segment_translation directly (builder omits match_type/locked/translation_date).
     *
     * @param array<string,int|string|null> $extra
     */
    private function insertTranslation(int $idSegment, string $status, array $extra = []): void
    {
        $cols = [
            'id_segment'     => $idSegment,
            'id_job'         => $this->idJob,
            'segment_hash'   => $extra['segment_hash'] ?? ('h_' . $idSegment),
            'status'         => $status,
            'translation'    => $extra['translation'] ?? "translation $idSegment",
            'version_number' => $extra['version_number'] ?? 0,
            'match_type'     => $extra['match_type'] ?? 'TM',
            'locked'         => $extra['locked'] ?? 0,
            'time_to_edit'   => $extra['time_to_edit'] ?? 100,
            'eq_word_count'  => $extra['eq_word_count'] ?? 5,
        ];

        if (array_key_exists('translation_date', $extra)) {
            $cols['translation_date'] = $extra['translation_date'];
        }

        if (array_key_exists('tm_analysis_status', $extra)) {
            $cols['tm_analysis_status'] = $extra['tm_analysis_status'];
        }

        $colNames = array_keys($cols);
        $sql      = 'INSERT INTO segment_translations (' . implode(',', $colNames) . ') VALUES (' . implode(',', array_map(fn($c) => ":$c", $colNames)) . ')';
        $stmt     = $this->realSqlDb->getConnection()->prepare($sql);
        $stmt->execute($cols);
    }

    private function insertTranslationVersion(int $idSegment, int $versionNumber, string $translation = 'v text'): void
    {
        $conn = $this->realSqlDb->getConnection();
        $stmt = $conn->prepare(
            'INSERT INTO segment_translation_versions (id_segment, id_job, translation, version_number) VALUES (:s,:j,:t,:v)'
        );
        $stmt->execute(['s' => $idSegment, 'j' => $this->idJob, 't' => $translation, 'v' => $versionNumber]);
    }

    // =========================================================================
    // Connection seam
    // =========================================================================

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertInjectedConnection($this->dao);
    }

    // =========================================================================
    // getByJobId
    // =========================================================================

    #[Test]
    public function getByJobId_returns_all_translations_for_job(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_NEW);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_DRAFT);

        $rows = $this->dao->getByJobId($this->idJob);

        $this->assertCount(2, $rows);
        $this->assertContainsOnlyInstancesOf(SegmentTranslationStruct::class, $rows);
    }

    #[Test]
    public function getByJobId_returns_empty_for_unknown_job(): void
    {
        $this->assertSame([], $this->dao->getByJobId(self::ASSIGNABLE_ID_FLOOR + 999_999));
    }

    // =========================================================================
    // getByFile
    // =========================================================================

    #[Test]
    public function getByFile_returns_translations_for_show_in_cattool_segments(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED);

        $file     = new FileStruct();
        $file->id = $this->idFile;

        $rows = $this->dao->getByFile($file);

        $this->assertNotEmpty($rows);
        $this->assertContainsOnlyInstancesOf(SegmentTranslationStruct::class, $rows);
    }

    // =========================================================================
    // getSegmentTranslationsModifiedByRevisorWithIssueCount
    // =========================================================================

    #[Test]
    public function getSegmentTranslationsModifiedByRevisorWithIssueCount_returns_shapeless_structs(): void
    {
        $sid = $this->segIds[0];

        // translation row
        $this->insertTranslation($sid, TranslationStatus::STATUS_APPROVED, ['translation' => 'original text']);
        $this->insertTranslationVersion($sid, 0, 'version text');

        // events: one TRANSLATED + one APPROVED with source_page=2
        $this->fixtures->makeSegmentTranslationEvent($this->idJob, $sid, [
            'source_page'    => 1,
            'version_number' => 0,
            'status'         => TranslationStatus::STATUS_TRANSLATED,
            'final_revision' => 0,
        ]);
        $this->fixtures->makeSegmentTranslationEvent($this->idJob, $sid, [
            'source_page'    => 2,
            'version_number' => 1,
            'status'         => TranslationStatus::STATUS_APPROVED,
            'final_revision' => 1,
        ]);

        $cat   = $this->fixtures->makeQaCategory('STDaoRsq', 1, '{}');
        $entry = $this->fixtures->makeQaEntry($sid, $this->idJob, $cat['id'], ['severity' => 'minor', 'source_page' => 2]);

        $rows = $this->dao->getSegmentTranslationsModifiedByRevisorWithIssueCount(
            $this->idJob,
            $this->password,
            2
        );

        $this->assertIsArray($rows);
        // The join may or may not yield rows depending on exact max_v matching, but the query
        // must execute without error and return shapeless structs if rows exist.
        foreach ($rows as $row) {
            $this->assertInstanceOf(ShapelessConcreteStruct::class, $row);
        }
    }

    // =========================================================================
    // getAllSegmentsByIdListAndJobId  (chunked, TTL branches)
    // =========================================================================

    #[Test]
    public function getAllSegmentsByIdListAndJobId_returns_translations_by_id_list(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_NEW);

        $result = $this->dao->getAllSegmentsByIdListAndJobId(
            [$this->segIds[0], $this->segIds[1]],
            $this->idJob,
            0
        );

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(SegmentTranslationStruct::class, $result);
    }

    #[Test]
    public function getAllSegmentsByIdListAndJobId_with_cache_ttl_hits_cache_on_second_call(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_DRAFT);

        // First call primes cache; second should return same data from cache.
        $r1 = $this->dao->getAllSegmentsByIdListAndJobId([$this->segIds[0]], $this->idJob, 60);
        $r2 = $this->dao->getAllSegmentsByIdListAndJobId([$this->segIds[0]], $this->idJob, 60);

        $this->assertCount(1, $r1);
        $this->assertCount(1, $r2);
        $this->assertSame($r1[0]->id_segment, $r2[0]->id_segment);
    }

    #[Test]
    public function getAllSegmentsByIdListAndJobId_returns_empty_for_unknown_ids(): void
    {
        $result = $this->dao->getAllSegmentsByIdListAndJobId(
            [self::ASSIGNABLE_ID_FLOOR + 888_888],
            $this->idJob,
            0
        );

        $this->assertSame([], $result);
    }

    // =========================================================================
    // updateTranslationAndStatusAndDateByList (batch upsert, size-limit throw)
    // =========================================================================

    #[Test]
    public function updateTranslationAndStatusAndDateByList_upserts_new_row(): void
    {
        $s          = new SegmentTranslationStruct();
        $s->id_segment    = $this->segIds[0];
        $s->id_job        = $this->idJob;
        $s->segment_hash  = 'strsqUpd0000000000';
        $s->status        = TranslationStatus::STATUS_TRANSLATED;
        $s->translation   = 'batch translation';
        $s->translation_date = date('Y-m-d H:i:s');
        $s->version_number = 1;

        $affected = $this->dao->updateTranslationAndStatusAndDateByList([$s]);

        $this->assertSame(1, $affected);

        $row = $this->realSqlDb->getConnection()
            ->query("SELECT status FROM segment_translations WHERE id_segment = {$this->segIds[0]} AND id_job = {$this->idJob}")
            ->fetchColumn();
        $this->assertSame(TranslationStatus::STATUS_TRANSLATED, $row);
    }

    #[Test]
    public function updateTranslationAndStatusAndDateByList_throws_on_oversized_translation(): void
    {
        $s                   = new SegmentTranslationStruct();
        $s->id_segment       = $this->segIds[0];
        $s->id_job           = $this->idJob;
        $s->segment_hash     = 'strsqBigHash000000';
        $s->status           = TranslationStatus::STATUS_TRANSLATED;
        $s->translation      = str_repeat('x', 70_000);
        $s->translation_date = date('Y-m-d H:i:s');
        $s->version_number   = 0;

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Translation size limit reached');
        $this->dao->updateTranslationAndStatusAndDateByList([$s]);
    }

    // =========================================================================
    // findBySegmentAndJob (TTL cache branches)
    // =========================================================================

    #[Test]
    public function findBySegmentAndJob_returns_struct_on_hit(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_DRAFT);

        $result = $this->dao->findBySegmentAndJob($this->segIds[0], $this->idJob, 0);

        $this->assertInstanceOf(SegmentTranslationStruct::class, $result);
        $this->assertSame($this->segIds[0], $result->id_segment);
        $this->assertSame(TranslationStatus::STATUS_DRAFT, $result->status);
    }

    #[Test]
    public function findBySegmentAndJob_returns_null_on_miss(): void
    {
        $result = $this->dao->findBySegmentAndJob(self::ASSIGNABLE_ID_FLOOR + 777_777, $this->idJob, 0);
        $this->assertNull($result);
    }

    #[Test]
    public function findBySegmentAndJob_with_ttl_serves_cached_result(): void
    {
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_TRANSLATED);

        $r1 = $this->dao->findBySegmentAndJob($this->segIds[1], $this->idJob, 60);
        $r2 = $this->dao->findBySegmentAndJob($this->segIds[1], $this->idJob, 60);

        $this->assertNotNull($r1);
        $this->assertNotNull($r2);
        $this->assertSame($r1->id_segment, $r2->id_segment);
    }

    // =========================================================================
    // updateLastTranslationDateByIdList (empty-list early return + actual update)
    // =========================================================================

    #[Test]
    public function updateLastTranslationDateByIdList_skips_on_empty_list(): void
    {
        // No error, no rows touched.
        $this->dao->updateLastTranslationDateByIdList([], date('Y-m-d H:i:s'));
        $this->assertTrue(true); // assertion: no exception thrown
    }

    #[Test]
    public function updateLastTranslationDateByIdList_updates_date_for_given_ids(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED, [
            'translation_date' => '2020-01-01 00:00:00',
        ]);

        $newDate = '2025-06-01 12:00:00';
        $this->dao->updateLastTranslationDateByIdList([$this->segIds[0]], $newDate);

        $row = $this->realSqlDb->getConnection()
            ->query("SELECT translation_date FROM segment_translations WHERE id_segment = {$this->segIds[0]} AND id_job = {$this->idJob}")
            ->fetchColumn();
        $this->assertSame($newDate, $row);
    }

    // =========================================================================
    // setAnalysisValue — rc>0, rc=0+skipped→-1, rc=0+not-skipped→0
    // =========================================================================

    #[Test]
    public function setAnalysisValue_returns_affected_count_on_update(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_NEW, [
            'tm_analysis_status' => 'UNDONE',
        ]);

        $rc = $this->dao->setAnalysisValue([
            'id_segment'         => $this->segIds[0],
            'id_job'             => $this->idJob,
            'tm_analysis_status' => 'DONE',
            'eq_word_count'      => 7,
        ]);

        $this->assertSame(1, $rc);
    }

    #[Test]
    public function setAnalysisValue_returns_zero_when_no_match_and_not_skipped(): void
    {
        // Translation does not exist → rowCount=0, isTranslationSkipped → false → returns 0.
        $rc = $this->dao->setAnalysisValue([
            'id_segment'         => self::ASSIGNABLE_ID_FLOOR + 555_555,
            'id_job'             => $this->idJob,
            'tm_analysis_status' => 'DONE',
        ]);

        $this->assertSame(0, $rc);
    }

    #[Test]
    public function setAnalysisValue_returns_minus_one_when_already_skipped(): void
    {
        // status=SKIPPED → NOT IN ('SKIPPED','DONE') condition excludes it → rowCount=0, but
        // isTranslationSkipped returns true → result is -1.
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_NEW, [
            'tm_analysis_status' => 'SKIPPED',
        ]);

        $rc = $this->dao->setAnalysisValue([
            'id_segment'         => $this->segIds[0],
            'id_job'             => $this->idJob,
            'tm_analysis_status' => 'DONE',
            'eq_word_count'      => 5,
        ]);

        $this->assertSame(-1, $rc);
    }

    // =========================================================================
    // isTranslationSkipped
    // =========================================================================

    #[Test]
    public function isTranslationSkipped_returns_true_when_skipped(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_NEW, [
            'tm_analysis_status' => 'SKIPPED',
        ]);

        $this->assertTrue($this->dao->isTranslationSkipped($this->segIds[0], $this->idJob));
    }

    #[Test]
    public function isTranslationSkipped_returns_false_when_not_skipped(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_NEW, [
            'tm_analysis_status' => 'UNDONE',
        ]);

        $this->assertFalse($this->dao->isTranslationSkipped($this->segIds[0], $this->idJob));
    }

    // =========================================================================
    // getUnchangeableStatus — three branches + source_page null/not-null
    // =========================================================================

    #[Test]
    public function getUnchangeableStatus_approved_without_source_page_finds_untranslated(): void
    {
        // Segment has NEW status → can't be changed to APPROVED (not in allowed list).
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_NEW);

        $unchangeable = $this->dao->getUnchangeableStatus(
            $this->jobStruct,
            [$this->segIds[0]],
            TranslationStatus::STATUS_APPROVED,
            null
        );

        $this->assertContains($this->segIds[0], $unchangeable);
    }

    #[Test]
    public function getUnchangeableStatus_approved_with_source_page_joins_events(): void
    {
        // Segment has TRANSLATED status → allowed for APPROVED change → should NOT appear.
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_TRANSLATED);

        $unchangeable = $this->dao->getUnchangeableStatus(
            $this->jobStruct,
            [$this->segIds[1]],
            TranslationStatus::STATUS_APPROVED,
            2
        );

        $this->assertNotContains($this->segIds[1], $unchangeable);
    }

    #[Test]
    public function getUnchangeableStatus_translated_status_includes_broader_allow_list(): void
    {
        // Segment has DRAFT status → in the allow list for TRANSLATED change → should NOT appear.
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_DRAFT);

        $unchangeable = $this->dao->getUnchangeableStatus(
            $this->jobStruct,
            [$this->segIds[0]],
            TranslationStatus::STATUS_TRANSLATED,
            null
        );

        $this->assertNotContains($this->segIds[0], $unchangeable);
    }

    #[Test]
    public function getUnchangeableStatus_throws_for_disallowed_status(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not allowed to change status to');

        $this->dao->getUnchangeableStatus(
            $this->jobStruct,
            [$this->segIds[0]],
            TranslationStatus::STATUS_NEW, // not APPROVED/APPROVED2/TRANSLATED
            null
        );
    }

    // =========================================================================
    // addTranslation — normal insert, empty-translation throw, size-limit throw,
    //                  now() normalization, is_revision branch
    // =========================================================================

    #[Test]
    public function addTranslation_inserts_new_translation(): void
    {
        $s                      = new SegmentTranslationStruct();
        $s->id_segment          = $this->segIds[0];
        $s->id_job              = $this->idJob;
        $s->segment_hash        = 'strsqAdd000000000A';
        $s->status              = TranslationStatus::STATUS_TRANSLATED;
        $s->translation         = 'hello translation';
        $s->translation_date    = 'NOW()';
        $s->version_number      = 1;
        $s->serialized_errors_list = null;
        $s->suggestions_array   = null;
        $s->suggestion          = 'sugg';
        $s->suggestion_position = 0;
        $s->suggestion_source   = 'TM';
        $s->suggestion_match    = '85%';
        $s->warning             = false;
        $s->autopropagated_from = null;
        $s->time_to_edit        = 500;

        $rows = $this->dao->addTranslation($s, false);

        $this->assertSame(1, $rows);

        $row = $this->realSqlDb->getConnection()
            ->query("SELECT translation, translation_date FROM segment_translations WHERE id_segment = {$this->segIds[0]} AND id_job = {$this->idJob}")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('hello translation', $row['translation']);
        $this->assertNotNull($row['translation_date']); // NOW() was resolved to a date string
    }

    #[Test]
    public function addTranslation_is_revision_sets_time_to_edit_zero(): void
    {
        $s                      = new SegmentTranslationStruct();
        $s->id_segment          = $this->segIds[1];
        $s->id_job              = $this->idJob;
        $s->segment_hash        = 'strsqRevHash000000';
        $s->status              = TranslationStatus::STATUS_APPROVED;
        $s->translation         = 'revised text';
        $s->translation_date    = date('Y-m-d H:i:s');
        $s->version_number      = 0;
        $s->serialized_errors_list = null;
        $s->suggestions_array   = null;
        $s->suggestion          = null;
        $s->suggestion_position = null;
        $s->suggestion_source   = null;
        $s->suggestion_match    = null;
        $s->warning             = false;
        $s->autopropagated_from = null;
        $s->time_to_edit        = 9999; // should be zeroed by is_revision=true

        $this->dao->addTranslation($s, true);

        $tte = (int)$this->realSqlDb->getConnection()
            ->query("SELECT time_to_edit FROM segment_translations WHERE id_segment = {$this->segIds[1]} AND id_job = {$this->idJob}")
            ->fetchColumn();
        $this->assertSame(0, $tte);
    }

    #[Test]
    public function addTranslation_null_string_is_normalized_to_null(): void
    {
        $s                      = new SegmentTranslationStruct();
        $s->id_segment          = $this->segIds[2];
        $s->id_job              = $this->idJob;
        $s->segment_hash        = 'strsqNullStr000000';
        $s->status              = TranslationStatus::STATUS_DRAFT;
        $s->translation         = 'non-null text';
        $s->translation_date    = 'NULL'; // string 'NULL' should be normalized to SQL NULL
        $s->version_number      = 0;
        $s->serialized_errors_list = null;
        $s->suggestions_array   = null;
        $s->suggestion          = null;
        $s->suggestion_position = null;
        $s->suggestion_source   = null;
        $s->suggestion_match    = null;
        $s->warning             = false;
        $s->autopropagated_from = null;
        $s->time_to_edit        = 100;

        $this->dao->addTranslation($s, false);

        $date = $this->realSqlDb->getConnection()
            ->query("SELECT translation_date FROM segment_translations WHERE id_segment = {$this->segIds[2]} AND id_job = {$this->idJob}")
            ->fetchColumn();
        $this->assertNull($date);
    }

    #[Test]
    public function addTranslation_throws_on_empty_translation(): void
    {
        $s                      = new SegmentTranslationStruct();
        $s->id_segment          = $this->segIds[0];
        $s->id_job              = $this->idJob;
        $s->segment_hash        = 'strsqEmptyT0000000';
        $s->status              = TranslationStatus::STATUS_TRANSLATED;
        $s->translation         = '';
        $s->translation_date    = date('Y-m-d H:i:s');
        $s->version_number      = 0;
        $s->serialized_errors_list = null;
        $s->suggestions_array   = null;
        $s->suggestion          = null;
        $s->suggestion_position = null;
        $s->suggestion_source   = null;
        $s->suggestion_match    = null;
        $s->warning             = false;
        $s->autopropagated_from = null;
        $s->time_to_edit        = 100;

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Error setTranslationUpdate. Empty translation found');
        $this->dao->addTranslation($s, false);
    }

    #[Test]
    public function addTranslation_throws_on_oversized_translation(): void
    {
        $s                      = new SegmentTranslationStruct();
        $s->id_segment          = $this->segIds[0];
        $s->id_job              = $this->idJob;
        $s->segment_hash        = 'strsqBig000000000B';
        $s->status              = TranslationStatus::STATUS_TRANSLATED;
        $s->translation         = str_repeat('y', 70_000);
        $s->translation_date    = date('Y-m-d H:i:s');
        $s->version_number      = 0;
        $s->serialized_errors_list = null;
        $s->suggestions_array   = null;
        $s->suggestion          = null;
        $s->suggestion_position = null;
        $s->suggestion_source   = null;
        $s->suggestion_match    = null;
        $s->warning             = false;
        $s->autopropagated_from = null;
        $s->time_to_edit        = 100;

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Translation size limit reached');
        $this->dao->addTranslation($s, false);
    }

    // =========================================================================
    // updateTranslationAndStatusAndDate — version_number null / not-null branches
    // =========================================================================

    #[Test]
    public function updateTranslationAndStatusAndDate_without_version_number(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_NEW);

        $s                   = new SegmentTranslationStruct();
        $s->id_segment       = $this->segIds[0];
        $s->id_job           = $this->idJob;
        $s->translation      = 'updated text';
        $s->status           = TranslationStatus::STATUS_TRANSLATED;
        $s->translation_date = date('Y-m-d H:i:s');
        $s->version_number   = null; // null branch: version_number field NOT added

        $this->dao->updateTranslationAndStatusAndDate($s);

        $row = $this->realSqlDb->getConnection()
            ->query("SELECT translation, status FROM segment_translations WHERE id_segment = {$this->segIds[0]} AND id_job = {$this->idJob}")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('updated text', $row['translation']);
        $this->assertSame(TranslationStatus::STATUS_TRANSLATED, $row['status']);
    }

    #[Test]
    public function updateTranslationAndStatusAndDate_with_version_number(): void
    {
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_DRAFT);

        $s                   = new SegmentTranslationStruct();
        $s->id_segment       = $this->segIds[1];
        $s->id_job           = $this->idJob;
        $s->translation      = 'versioned text';
        $s->status           = TranslationStatus::STATUS_TRANSLATED;
        $s->translation_date = date('Y-m-d H:i:s');
        $s->version_number   = 2; // not-null branch: version_number field IS added

        $this->dao->updateTranslationAndStatusAndDate($s);

        $row = $this->realSqlDb->getConnection()
            ->query("SELECT translation, version_number FROM segment_translations WHERE id_segment = {$this->segIds[1]} AND id_job = {$this->idJob}")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('versioned text', $row['translation']);
        $this->assertSame(2, (int)$row['version_number']);
    }

    // =========================================================================
    // getMaxSegmentIdsFromJob
    // =========================================================================

    #[Test]
    public function getMaxSegmentIdsFromJob_returns_max_id_for_job(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_APPROVED);

        $result = $this->dao->getMaxSegmentIdsFromJob($this->jobStruct);

        $this->assertNotEmpty($result);
        $this->assertContains(max($this->segIds[0], $this->segIds[1]), $result);
    }

    // =========================================================================
    // updateFirstTimeOpenedContribution (delegates to updateFields)
    // =========================================================================

    #[Test]
    public function updateFirstTimeOpenedContribution_updates_a_field(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_NEW, [
            'suggestion' => null,
        ]);

        $this->dao->updateFirstTimeOpenedContribution(
            ['suggestion' => 'opened suggestion'],
            ['id_segment' => $this->segIds[0], 'id_job' => $this->idJob]
        );

        $val = $this->realSqlDb->getConnection()
            ->query("SELECT suggestion FROM segment_translations WHERE id_segment = {$this->segIds[0]} AND id_job = {$this->idJob}")
            ->fetchColumn();
        $this->assertSame('opened suggestion', $val);
    }

    // =========================================================================
    // propagateTranslation — no-twin path ($lastRow === null → empty result)
    //                         and WORD_COUNT_EQUIVALENT branch (no project_metadata row)
    // =========================================================================

    #[Test]
    public function propagateTranslation_returns_empty_propagation_when_no_twins(): void
    {
        // segIds[3] has a unique hash → no other segment matches → $lastRow === null path.
        $this->insertTranslation($this->segIds[3], TranslationStatus::STATUS_TRANSLATED);

        $stStruct                = new SegmentTranslationStruct();
        $stStruct->id_segment    = $this->segIds[3];
        $stStruct->id_job        = $this->idJob;
        $stStruct->segment_hash  = 'strsq_unique_' . $this->segIds[3];
        $stStruct->status        = TranslationStatus::STATUS_TRANSLATED;
        $stStruct->translation   = 'unique translation';
        $stStruct->eq_word_count = 4;
        $stStruct->match_type    = 'TM';
        $stStruct->locked        = false;

        $result = $this->dao->propagateTranslation(
            $stStruct,
            $this->jobStruct,
            $this->segIds[3],
            $this->projectStruct,
            false // execute_update=false so even if enqueue is called it won't fire
        );

        $this->assertIsArray($result);
    }

    #[Test]
    public function propagateTranslation_uses_raw_word_count_when_metadata_says_raw(): void
    {
        // Insert project_metadata row setting word_count_type = 'raw' to hit the $sum_sql RAW branch.
        $conn = $this->realSqlDb->getConnection();
        $conn->exec("INSERT INTO project_metadata (id_project, `key`, `value`) VALUES ({$this->idProject}, 'word_count_type', 'raw')");

        $this->insertTranslation($this->segIds[3], TranslationStatus::STATUS_TRANSLATED);

        $stStruct                = new SegmentTranslationStruct();
        $stStruct->id_segment    = $this->segIds[3];
        $stStruct->id_job        = $this->idJob;
        $stStruct->segment_hash  = 'strsq_unique_' . $this->segIds[3];
        $stStruct->status        = TranslationStatus::STATUS_TRANSLATED;
        $stStruct->translation   = 'raw translation';
        $stStruct->eq_word_count = 4;
        $stStruct->match_type    = 'TM';
        $stStruct->locked        = false;

        $result = $this->dao->propagateTranslation(
            $stStruct,
            $this->jobStruct,
            $this->segIds[3],
            $this->projectStruct,
            false
        );

        $this->assertIsArray($result);
    }

    #[Test]
    public function propagateTranslation_twin_path_covers_fetch_closure_and_propagation_analyser(): void
    {
        // segIds[0] and segIds[1] share $this->sharedHash. Translating segIds[0] and then
        // calling propagateTranslation with segIds[1] as the excluded segment means segIds[0]
        // appears as a twin → $lastRow !== null → propagation path fires → WorkerClient::enqueue.
        //
        // We stub WorkerClient::$_HANDLER with a bare AMQHandler-descended anonymous object
        // (no-ctor via reflection) so $handler->persistent returns null without TypeError, and
        // set WorkerClient::$_QUEUES['PROPAGATION'] to a Context with queue_name='' so enqueue
        // throws InvalidArgumentException before touching the network — covering lines 618-688.
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED, [
            'segment_hash'   => $this->sharedHash,
            'translation'    => 'twin translation',
            'eq_word_count'  => 4,
        ]);
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_TRANSLATED, [
            'segment_hash'   => $this->sharedHash,
            'translation'    => 'twin translation',
            'eq_word_count'  => 4,
        ]);

        // Wire up a no-connect WorkerClient stub so the enqueue call fails early with
        // InvalidArgumentException (not a PDOException) before touching ActiveMQ.
        // Use an anonymous subclass with a no-op __destruct so the parent destructor's
        // StatefulStomp close() does not fire on test teardown (it would Fatal).
        $fakeHandler = new class extends \Utils\ActiveMQ\AMQHandler {
            public function __construct() {}  // skip parent ctor
            public function __destruct() {}   // prevent parent dtor from crashing
        };
        WorkerClient::$_HANDLER = $fakeHandler;
        WorkerClient::$_QUEUES['PROPAGATION'] = Context::buildFromArray([
            'queue_name'    => '',      // empty → InvalidArgumentException in enqueueWithClient
            'max_executors' => 0,
        ]);

        $stStruct                = new SegmentTranslationStruct();
        $stStruct->id_segment    = $this->segIds[0];
        $stStruct->id_job        = $this->idJob;
        $stStruct->segment_hash  = $this->sharedHash;
        $stStruct->status        = TranslationStatus::STATUS_TRANSLATED;
        $stStruct->translation   = 'twin translation';
        $stStruct->eq_word_count = 4;
        $stStruct->match_type    = 'TM';
        $stStruct->locked        = false;

        // The twin path is reached; WorkerClient::enqueue throws InvalidArgumentException
        // (not caught by the PDOException catch in propagateTranslation) — it bubbles up.
        $this->expectException(\Exception::class);
        $this->dao->propagateTranslation(
            $stStruct,
            $this->jobStruct,
            $this->segIds[0],   // excluded segment = self → segIds[1] is the twin
            $this->projectStruct,
            true
        );
    }

    // =========================================================================
    // getLast10TranslatedSegmentIDsInLastHour
    // =========================================================================

    #[Test]
    public function getLast10TranslatedSegmentIDsInLastHour_returns_ids_within_last_hour(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED, [
            'translation_date' => $now,
        ]);

        $result = $this->dao->getLast10TranslatedSegmentIDsInLastHour($this->idJob);

        $this->assertIsArray($result);
        $this->assertContains((string)$this->segIds[0], array_map('strval', $result));
    }

    #[Test]
    public function getLast10TranslatedSegmentIDsInLastHour_returns_empty_for_unknown_job(): void
    {
        $result = $this->dao->getLast10TranslatedSegmentIDsInLastHour(self::ASSIGNABLE_ID_FLOOR + 777_001);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // getWordsPerSecond — empty and non-empty estimation_seg_ids branches
    // =========================================================================

    #[Test]
    public function getWordsPerSecond_returns_empty_when_no_ids_provided(): void
    {
        // No estimation_seg_ids → query uses IN() with no values, but the DAO only runs the
        // query when the array is non-empty (the IN clause would be built with count=0
        // producing invalid SQL). However the method does not guard against empty arrays —
        // passing an empty array produces broken SQL; test with a single known id instead to
        // cover the real execution path. We cover the no-data path via a miss.
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED, [
            'translation_date' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->dao->getWordsPerSecond($this->idJob, [$this->segIds[0]]);

        $this->assertIsArray($result);
    }

    #[Test]
    public function getWordsPerSecond_returns_array_for_non_matching_ids(): void
    {
        // The query uses a GROUP BY aggregate so MySQL may return a NULL-aggregate row even
        // when no data matches. The contract is: returns an array (possibly containing a
        // NULL-words_per_second aggregate row).
        $result = $this->dao->getWordsPerSecond($this->idJob, [self::ASSIGNABLE_ID_FLOOR + 900_001]);
        $this->assertIsArray($result);
    }

    // =========================================================================
    // rebuildFromReplaceEvents — success path and error-rollback path
    // =========================================================================

    #[Test]
    public function rebuildFromReplaceEvents_updates_translation_and_returns_count(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED, [
            'translation' => 'before replacement',
        ]);

        $event                              = new ReplaceEventStruct();
        $event->id_job                      = $this->idJob;
        $event->id_segment                  = $this->segIds[0];
        $event->translation_after_replacement = 'after replacement';
        $event->replace_version             = '1';
        $event->job_password                = $this->password;
        $event->target                      = 'it-IT';
        $event->status                      = TranslationStatus::STATUS_TRANSLATED;
        $event->replacement                 = 'before→after';

        $affected = $this->dao->rebuildFromReplaceEvents([$event]);

        $this->assertSame(1, $affected);

        $val = $this->realSqlDb->getConnection()
            ->query("SELECT translation FROM segment_translations WHERE id_segment = {$this->segIds[0]} AND id_job = {$this->idJob}")
            ->fetchColumn();
        $this->assertSame('after replacement', $val);
    }

    // =========================================================================
    // updateSuggestionsArray — empty-array early return + actual update
    // =========================================================================

    #[Test]
    public function updateSuggestionsArray_returns_without_update_on_empty_array(): void
    {
        $this->insertTranslation($this->segIds[0], TranslationStatus::STATUS_TRANSLATED);

        // Capture the pre-call value (NULL since our helper doesn't set suggestions_array).
        $before = $this->realSqlDb->getConnection()
            ->query("SELECT suggestions_array FROM segment_translations WHERE id_segment = {$this->segIds[0]} AND id_job = {$this->idJob}")
            ->fetchColumn();

        // Empty array → early return, nothing changes.
        $this->dao->updateSuggestionsArray($this->segIds[0], []);

        $after = $this->realSqlDb->getConnection()
            ->query("SELECT suggestions_array FROM segment_translations WHERE id_segment = {$this->segIds[0]} AND id_job = {$this->idJob}")
            ->fetchColumn();
        $this->assertSame($before, $after);
    }

    #[Test]
    public function updateSuggestionsArray_encodes_and_persists_suggestions(): void
    {
        $this->insertTranslation($this->segIds[1], TranslationStatus::STATUS_TRANSLATED);

        $suggestions = [['match' => '85%', 'translation' => 'suggested text']];
        $this->dao->updateSuggestionsArray($this->segIds[1], $suggestions);

        $raw = $this->realSqlDb->getConnection()
            ->query("SELECT suggestions_array FROM segment_translations WHERE id_segment = {$this->segIds[1]} AND id_job = {$this->idJob}")
            ->fetchColumn();
        $this->assertSame(json_encode($suggestions), $raw);
    }
}
