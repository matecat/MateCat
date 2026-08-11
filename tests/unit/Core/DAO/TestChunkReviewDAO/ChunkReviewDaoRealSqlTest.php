<?php

namespace Matecat\Core\DAO\TestChunkReviewDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Utils\Constants\SourcePages;
use Utils\Registry\AppConfig;
use Utils\Constants\TranslationStatus;

/**
 * Real-SQL coverage for ChunkReviewDao (campaign dao-realsql-90).
 *
 * Every public method runs against the live unittest DB on the single per-test connection. A
 * shared chunk topology (project -> file -> segment -> job -> files_job -> segment_translation,
 * + qa_entry / segment_translation_event / two qa_chunk_reviews) drives the read/count methods;
 * the mutating methods (updatePassword/updateReviewPassword/createRecord/deleteByJobId/
 * passFailCountsAtomicUpdate) build their own isolated fixtures so they never disturb the shared
 * rows. The residue gate asserts whole-table COUNT(*) is unchanged after cleanup (DoD c).
 *
 * passFailCountsAtomicUpdate is exercised end-to-end: getChunk(JobDao) -> getProject(ProjectDao)
 * -> ModelDao::findById resolve against real jobs/projects/qa_models rows. A custom qa_model with
 * pass_options {"limit":[...]} is inserted because the seeded models store a scalar limit that
 * would TypeError in ModelStruct::normalizeLimits().
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class ChunkReviewDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private ChunkReviewDao $dao;

    private int $idProject;
    private int $idJob;
    private string $jobPassword;
    private string $reviewPassword = 'rsq_rev_pwd';
    private int $idSegment;
    private string $ownerEmail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startRealSql([
            'qa_chunk_reviews', 'jobs', 'projects', 'qa_entries', 'qa_categories',
            'segment_translation_events', 'segments', 'segment_translations',
            'files', 'files_job', 'qa_models', 'users',
        ]);

        $user = $this->fixtures->makeUser();
        $this->ownerEmail = $user['email'];
        $project = $this->fixtures->makeProject();
        $this->idProject = $project['id'];
        $file = $this->fixtures->makeFile($this->idProject);
        // raw_word_count populated for getReviewedWordsCountForSecondPass.
        $segment = $this->fixtures->makeSegmentWithWords($file['id'], 10.0);
        $this->idSegment = $segment['id'];

        $job = $this->fixtures->makeJob($this->idProject, [
            'owner'             => $user['email'],
            'job_first_segment' => $this->idSegment,
            'job_last_segment'  => $this->idSegment,
        ]);
        $this->idJob = $job['id'];
        $this->jobPassword = $job['password'];
        $this->fixtures->makeFilesJob($this->idJob, $file['id']);

        // APPROVED + version != 0 so getReviewedWordsCountForSecondPass(REVISION) counts it.
        $this->fixtures->makeSegmentTranslationWithWords(
            $this->idSegment,
            $this->idJob,
            8.0,
            TranslationStatus::STATUS_APPROVED,
            ['version_number' => 1]
        );

        // qa_entry with penalty points on REVISION for getPenaltyPointsForChunk.
        $category = $this->fixtures->makeQaCategory();
        $entry = $this->fixtures->makeQaEntry($this->idSegment, $this->idJob, $category['id'], [
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $this->realSqlDb()->getConnection()
            ->exec("UPDATE qa_entries SET penalty_points = 5 WHERE id = {$entry['id']}");

        // segment_translation_event on REVISION for countTimeToEdit.
        $this->fixtures->makeSegmentTranslationEvent($this->idJob, $this->idSegment, [
            'source_page'  => SourcePages::SOURCE_PAGE_REVISION,
            'time_to_edit' => 1500,
        ]);

        // two qa_chunk_reviews (R1 + R2) for the read/find methods.
        $this->fixtures->makeQaChunkReview($this->idProject, $this->idJob, $this->jobPassword, [
            'review_password' => $this->reviewPassword,
            'source_page'     => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $this->fixtures->makeQaChunkReview($this->idProject, $this->idJob, $this->jobPassword, [
            'review_password' => $this->reviewPassword,
            'source_page'     => SourcePages::SOURCE_PAGE_REVISION_2,
        ]);

        $this->dao = new ChunkReviewDao($this->realSqlDb());
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    /** Insert a qa_model with an array limit so getLimit()/normalizeLimits() resolve. */
    private function makeQaModel(string $passOptions = '{"limit":[15,10]}'): int
    {
        $conn = $this->realSqlDb()->getConnection();
        $stmt = $conn->prepare(
            "INSERT INTO qa_models (uid, label, pass_type, pass_options, `hash`) "
            . "VALUES (NULL, 'rsq_model', 'points_per_thousand', :opts, 1)"
        );
        $stmt->execute(['opts' => $passOptions]);
        $id = (int)$conn->lastInsertId();
        $this->fixtures->trackExisting('qa_models', ['id' => $id]);

        return $id;
    }

    private function chunk(int $idJob, string $password): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = $idJob;
        $chunk->password = $password;

        return $chunk;
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertDaoUsesTestConnection($this->dao);
    }

    // ----------------------------------------------------------------------------- reads

    #[Test]
    public function findByIdJob_returns_all_chunk_reviews_for_the_job(): void
    {
        $rows = $this->dao->findByIdJob($this->idJob);

        $this->assertCount(2, $rows);
        $this->assertContainsOnlyInstancesOf(ChunkReviewStruct::class, $rows);
        $this->assertSame($this->idJob, $rows[0]->id_job);
    }

    #[Test]
    public function findByIdJobAndPasswordAndSourcePage_hit_and_miss(): void
    {
        $hit = $this->dao->findByIdJobAndPasswordAndSourcePage(
            $this->idJob, $this->jobPassword, SourcePages::SOURCE_PAGE_REVISION
        );
        $this->assertInstanceOf(ChunkReviewStruct::class, $hit);
        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION, $hit->source_page);

        $miss = $this->dao->findByIdJobAndPasswordAndSourcePage($this->idJob, 'wrong', 2);
        $this->assertNull($miss);
    }

    #[Test]
    public function findById_hit_and_miss(): void
    {
        $any = $this->dao->findByIdJob($this->idJob)[0];

        $found = $this->dao->findById((int)$any->id);
        $this->assertInstanceOf(ChunkReviewStruct::class, $found);
        $this->assertSame($any->id, $found->id);

        $this->assertNull($this->dao->findById(self::ASSIGNABLE_ID_FLOOR + 123456));
    }

    #[Test]
    public function findChunkReviews_returns_both_source_pages(): void
    {
        $rows = $this->dao->findChunkReviews($this->chunk($this->idJob, $this->jobPassword));

        $this->assertCount(2, $rows);
        $this->assertContainsOnlyInstancesOf(ChunkReviewStruct::class, $rows);
    }

    #[Test]
    public function findChunkReviewsForSourcePage_filters_by_source_page(): void
    {
        $rows = $this->dao->findChunkReviewsForSourcePage(
            $this->chunk($this->idJob, $this->jobPassword),
            SourcePages::SOURCE_PAGE_REVISION_2
        );

        $this->assertCount(1, $rows);
        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION_2, $rows[0]->source_page);
    }

    #[Test]
    public function destroyCacheForFindChunkReviews_returns_bool(): void
    {
        $this->dao->findChunkReviews($this->chunk($this->idJob, $this->jobPassword), 60);
        $this->assertIsBool(
            $this->dao->destroyCacheForFindChunkReviews($this->chunk($this->idJob, $this->jobPassword))
        );
    }

    #[Test]
    public function findByProjectId_and_destroyCache(): void
    {
        $rows = $this->dao->findByProjectId($this->idProject, 60);
        $this->assertCount(2, $rows);
        $this->assertContainsOnlyInstancesOf(ChunkReviewStruct::class, $rows);

        $this->assertIsBool($this->dao->destroyCacheByProjectId($this->idProject));
    }

    #[Test]
    public function findByReviewPasswordAndJobId_hit_and_miss(): void
    {
        $hit = $this->dao->findByReviewPasswordAndJobId($this->reviewPassword, $this->idJob);
        $this->assertInstanceOf(ChunkReviewStruct::class, $hit);

        $this->assertNull($this->dao->findByReviewPasswordAndJobId('no_such_rev', $this->idJob));
    }

    #[Test]
    public function findLastReviewByJobIdPasswordAndSourcePage_hit_and_miss(): void
    {
        $hit = $this->dao->findLastReviewByJobIdPasswordAndSourcePage(
            $this->idJob, $this->jobPassword, SourcePages::SOURCE_PAGE_REVISION
        );
        $this->assertInstanceOf(ChunkReviewStruct::class, $hit);
        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION, $hit->source_page);

        $this->assertNull(
            $this->dao->findLastReviewByJobIdPasswordAndSourcePage($this->idJob, 'wrong', 2)
        );
    }

    #[Test]
    public function findByJobIdReviewPasswordAndSourcePage_hit_miss_and_destroyCache(): void
    {
        $hit = $this->dao->findByJobIdReviewPasswordAndSourcePage(
            $this->idJob, $this->reviewPassword, SourcePages::SOURCE_PAGE_REVISION
        );
        $this->assertInstanceOf(ChunkReviewStruct::class, $hit);

        $miss = $this->dao->findByJobIdReviewPasswordAndSourcePage($this->idJob, 'wrong', 2);
        $this->assertNull($miss);

        $this->assertIsBool(
            $this->dao->destroyCacheForJobIdReviewPasswordAndSourcePage(
                $this->idJob, $this->reviewPassword, SourcePages::SOURCE_PAGE_REVISION
            )
        );
    }

    #[Test]
    public function exists_with_and_without_source_page_and_miss(): void
    {
        $this->assertTrue($this->dao->exists($this->idJob, $this->jobPassword));
        $this->assertTrue(
            $this->dao->exists($this->idJob, $this->jobPassword, SourcePages::SOURCE_PAGE_REVISION)
        );
        $this->assertFalse($this->dao->exists($this->idJob, 'wrong'));
    }

    #[Test]
    public function isTOrR1OrR2_counts_t_r1_r2_when_password_equals_review_password(): void
    {
        // The query binds one :password to BOTH password (t) and review_password (r1/r2),
        // so this dedicated job uses password == review_password to light up all three counts.
        $samePwd = 'rsq_same_pwd';
        $job = $this->fixtures->makeJob($this->idProject, ['password' => $samePwd]);
        $this->fixtures->makeQaChunkReview($this->idProject, $job['id'], $samePwd, [
            'review_password' => $samePwd,
            'source_page'     => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $this->fixtures->makeQaChunkReview($this->idProject, $job['id'], $samePwd, [
            'review_password' => $samePwd,
            'source_page'     => SourcePages::SOURCE_PAGE_REVISION_2,
        ]);

        $res = $this->dao->isTOrR1OrR2($job['id'], $samePwd);

        $this->assertInstanceOf(ShapelessConcreteStruct::class, $res);
        $this->assertSame(2, (int)$res->t);
        $this->assertSame(1, (int)$res->r1);
        $this->assertSame(1, (int)$res->r2);
    }

    // ----------------------------------------------------------------------------- counts

    #[Test]
    public function getPenaltyPointsForChunk_default_revision_and_empty_other_page(): void
    {
        // null source_page defaults to REVISION (2) where the seeded qa_entry lives.
        $this->assertSame(5.0, $this->dao->getPenaltyPointsForChunk($this->chunk($this->idJob, $this->jobPassword)));

        // REVISION_2 has no entries -> SUM(null) -> 0.
        $this->assertSame(
            0.0,
            $this->dao->getPenaltyPointsForChunk(
                $this->chunk($this->idJob, $this->jobPassword),
                SourcePages::SOURCE_PAGE_REVISION_2
            )
        );
    }

    #[Test]
    public function findPenaltyPointsMismatches_flags_only_the_drifted_source_page(): void
    {
        // The shared fixture already drifted: REVISION's qa_chunk_reviews.penalty_points is the
        // struct default (0) while its qa_entries sum to 5; REVISION_2 has no entries, so 0 == 0.
        $mismatches = $this->dao->findPenaltyPointsMismatches();

        $revisionMismatch = null;
        foreach ($mismatches as $row) {
            if ($row['id_job'] === $this->idJob && $row['source_page'] === SourcePages::SOURCE_PAGE_REVISION) {
                $revisionMismatch = $row;
            }
            $this->assertFalse(
                $row['id_job'] === $this->idJob && $row['source_page'] === SourcePages::SOURCE_PAGE_REVISION_2,
                'REVISION_2 has no qa_entries and recorded 0, so it must not be reported as a mismatch'
            );
        }

        $this->assertNotNull($revisionMismatch, 'Expected the seeded REVISION drift to be reported');
        $this->assertSame(0.0, (float)$revisionMismatch['recorded_penalty_points']);
        $this->assertSame(5.0, (float)$revisionMismatch['actual_penalty_points']);
        $this->assertSame($this->jobPassword, $revisionMismatch['password']);
    }

    /**
     * Regression: the recount and the detector must agree on fractional penalties.
     *
     * getPenaltyPointsForChunk() used to return int, so a true sum of 5.50 was recomputed as 5.
     * recountAndUpdatePassFailResult() writes that value as an absolute, while the detector compares
     * ABS(actual - recorded) > 0.005 — so the repair wrote 5, the detector immediately re-flagged the
     * same row, and revision:recount-drifted could never converge.
     */
    #[Test]
    public function fractional_penalties_recount_and_detector_agree(): void
    {
        $category = $this->fixtures->makeQaCategory();
        foreach ([1, 2] as $ignored) {
            $entry = $this->fixtures->makeQaEntry($this->idSegment, $this->idJob, $category['id'], [
                'source_page' => SourcePages::SOURCE_PAGE_REVISION,
            ]);
            $this->realSqlDb()->getConnection()
                ->exec("UPDATE qa_entries SET penalty_points = 2.75 WHERE id = {$entry['id']}");
        }

        // Seeded fixture contributes 5, plus the two 2.75 entries above.
        $recomputed = $this->dao->getPenaltyPointsForChunk($this->chunk($this->idJob, $this->jobPassword));
        $this->assertSame(10.5, $recomputed, 'the fractional part must survive the recompute');

        // Write it back the way recountAndUpdatePassFailResult() does — as an absolute value.
        $this->realSqlDb()->getConnection()->exec(
            "UPDATE qa_chunk_reviews SET penalty_points = {$recomputed}
             WHERE id_job = {$this->idJob} AND source_page = " . SourcePages::SOURCE_PAGE_REVISION
        );

        foreach ($this->dao->findPenaltyPointsMismatches() as $row) {
            $this->assertFalse(
                $row['id_job'] === $this->idJob && $row['source_page'] === SourcePages::SOURCE_PAGE_REVISION,
                'after recomputing, the detector must consider the row settled — it reported '
                . $row['recorded_penalty_points'] . ' vs ' . $row['actual_penalty_points']
            );
        }
    }

    #[Test]
    public function findPenaltyPointsMismatches_excludes_a_row_once_it_matches(): void
    {
        $job = $this->fixtures->makeJob($this->idProject, [
            'password' => 'match_pwd',
            'job_first_segment' => $this->idSegment,
            'job_last_segment' => $this->idSegment,
        ]);
        $this->fixtures->makeQaChunkReview($this->idProject, $job['id'], 'match_pwd', [
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $this->realSqlDb()->getConnection()->exec(
            "UPDATE qa_chunk_reviews SET penalty_points = 0 WHERE id_job = {$job['id']}"
        );

        foreach ($this->dao->findPenaltyPointsMismatches() as $row) {
            $this->assertNotSame($job['id'], $row['id_job'], 'a row with no drift must not be reported');
        }
    }

    #[Test]
    public function findPenaltyPointsMismatches_respects_the_min_job_id_filter(): void
    {
        $allMismatches = $this->dao->findPenaltyPointsMismatches();
        $this->assertNotEmpty($allMismatches, 'sanity check: the shared fixture drift must be present');

        $filtered = $this->dao->findPenaltyPointsMismatches($this->idJob);
        foreach ($filtered as $row) {
            $this->assertGreaterThan($this->idJob, $row['id_job']);
        }
    }

    /**
     * Drift in the other direction — recorded higher than actual — has to be reported too. Every
     * other fixture here drifts downward (recorded 0, actual 5), so an asymmetric predicate such as
     * `actual - recorded > 0.005` would satisfy all of them while silently never reporting a
     * decrement that failed to land, which is the more common production shape.
     */
    #[Test]
    public function findPenaltyPointsMismatches_flags_an_over_recorded_row(): void
    {
        $job = $this->fixtures->makeJob($this->idProject, [
            'password' => 'over_pwd',
            'job_first_segment' => $this->idSegment,
            'job_last_segment' => $this->idSegment,
        ]);
        $this->fixtures->makeQaChunkReview($this->idProject, $job['id'], 'over_pwd', [
            'source_page' => SourcePages::SOURCE_PAGE_TRANSLATE,
        ]);
        // No qa_entries on TRANSLATE, so actual is 0 while recorded says 9.99.
        $this->realSqlDb()->getConnection()->exec(
            "UPDATE qa_chunk_reviews SET penalty_points = 9.99 WHERE id_job = {$job['id']}"
        );

        $reported = array_filter(
            $this->dao->findPenaltyPointsMismatches(),
            fn(array $row): bool => $row['id_job'] === $job['id']
        );

        $this->assertCount(1, $reported, 'an over-recorded row must be reported');
        $row = array_values($reported)[0];
        $this->assertSame(9.99, (float)$row['recorded_penalty_points']);
        $this->assertSame(0.0, (float)$row['actual_penalty_points']);
    }

    /**
     * The page and the total come from the same predicate, so a capped report can state the real
     * total. Without a cap the first run after deploy renders one table row per drifted chunk review
     * into an email, unbounded.
     */
    #[Test]
    public function findPenaltyPointsMismatches_caps_the_page_while_the_count_stays_total(): void
    {
        // The shared fixture already drifts on two source_pages; add a third drifted chunk.
        $job = $this->fixtures->makeJob($this->idProject, [
            'password' => 'limit_pwd',
            'job_first_segment' => $this->idSegment,
            'job_last_segment' => $this->idSegment,
        ]);
        $this->fixtures->makeQaChunkReview($this->idProject, $job['id'], 'limit_pwd', [
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $this->realSqlDb()->getConnection()->exec(
            "UPDATE qa_chunk_reviews SET penalty_points = 42 WHERE id_job = {$job['id']}"
        );

        $total = $this->dao->countPenaltyPointsMismatches();
        $this->assertGreaterThanOrEqual(2, $total);
        $this->assertSame($total, count($this->dao->findPenaltyPointsMismatches()), 'count and unbounded page must agree');

        $this->assertCount(1, $this->dao->findPenaltyPointsMismatches(null, 1));
        $this->assertSame($total, $this->dao->countPenaltyPointsMismatches(), 'the count must ignore the page limit');
    }

    #[Test]
    public function countPenaltyPointsMismatches_respects_the_min_job_id_filter(): void
    {
        $this->assertSame(
            count($this->dao->findPenaltyPointsMismatches($this->idJob)),
            $this->dao->countPenaltyPointsMismatches($this->idJob)
        );
    }

    #[Test]
    public function countTimeToEdit_sum_and_zero_when_no_rows(): void
    {
        $this->assertSame(
            1500,
            $this->dao->countTimeToEdit($this->chunk($this->idJob, $this->jobPassword), SourcePages::SOURCE_PAGE_REVISION)
        );

        $this->assertSame(
            0,
            $this->dao->countTimeToEdit($this->chunk($this->idJob, $this->jobPassword), SourcePages::SOURCE_PAGE_REVISION_2)
        );
    }

    #[Test]
    public function getReviewedWordsCountForSecondPass_match_and_null_status(): void
    {
        // REVISION -> APPROVED status; the seeded translation is APPROVED + version != 0.
        $this->assertSame(
            10,
            $this->dao->getReviewedWordsCountForSecondPass(
                $this->chunk($this->idJob, $this->jobPassword),
                SourcePages::SOURCE_PAGE_REVISION
            )
        );

        // null source_page -> null translation status -> no match -> 0.
        $this->assertSame(
            0,
            $this->dao->getReviewedWordsCountForSecondPass($this->chunk($this->idJob, $this->jobPassword), null)
        );
    }

    // ----------------------------------------------------------------------------- mutations

    #[Test]
    public function updatePassword_changes_matching_rows(): void
    {
        $job = $this->fixtures->makeJob($this->idProject, ['password' => 'old_pwd']);
        $this->fixtures->makeQaChunkReview($this->idProject, $job['id'], 'old_pwd');

        $affected = $this->dao->updatePassword($job['id'], 'old_pwd', 'new_pwd');
        $this->assertSame(1, $affected);

        $this->assertTrue($this->dao->exists($job['id'], 'new_pwd'));
        $this->assertSame(0, $this->dao->updatePassword($job['id'], 'old_pwd', 'x'));
    }

    #[Test]
    public function updateReviewPassword_changes_matching_rows(): void
    {
        $job = $this->fixtures->makeJob($this->idProject, ['password' => 'p_rev']);
        $this->fixtures->makeQaChunkReview($this->idProject, $job['id'], 'p_rev', [
            'review_password' => 'old_rev',
            'source_page'     => SourcePages::SOURCE_PAGE_REVISION,
        ]);

        $affected = $this->dao->updateReviewPassword(
            $job['id'], 'old_rev', 'new_rev', SourcePages::SOURCE_PAGE_REVISION
        );
        $this->assertSame(1, $affected);

        $this->assertInstanceOf(
            ChunkReviewStruct::class,
            $this->dao->findByReviewPasswordAndJobId('new_rev', $job['id'])
        );
        $this->assertSame(
            0,
            $this->dao->updateReviewPassword($job['id'], 'old_rev', 'x', SourcePages::SOURCE_PAGE_REVISION)
        );
    }

    #[Test]
    public function createRecord_inserts_row_with_defaults(): void
    {
        $job = $this->fixtures->makeJob($this->idProject, ['password' => 'cr_pwd']);

        $struct = $this->dao->createRecord([
            'id_project'  => $this->idProject,
            'id_job'      => $job['id'],
            'password'    => 'cr_pwd',
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $this->fixtures->trackExisting('qa_chunk_reviews', ['id' => $struct->id]);

        $this->assertInstanceOf(ChunkReviewStruct::class, $struct);
        $this->assertGreaterThan(0, $struct->id);
        $this->assertNotEmpty($struct->review_password); // setDefaults() filled it
        $this->assertTrue($this->dao->exists($job['id'], 'cr_pwd'));
    }

    /**
     * createRecord() must return the row that now exists, whichever branch of
     * INSERT ... ON DUPLICATE KEY UPDATE ran, because the caller feeds that id straight into
     * recountAndUpdatePassFailResult() (updateStruct keys on the primary key, so a wrong id updates
     * nothing) and into passFailCountsAtomicUpdate() (an unmatched id takes the insert branch and
     * creates a duplicate).
     *
     * This is a contract test, not a regression test: LAST_INSERT_ID() is documented as unreliable
     * on the ODKU update branch, and it does return 0 there in an isolated probe — but on this
     * server and through this code path it happened to report the matched row's id, so the previous
     * implementation passes this test too. The re-read is the version-independent way to be right;
     * what is pinned here is the contract, and the guarantee no longer depends on server behaviour
     * that MySQL does not promise.
     */
    #[Test]
    public function createRecord_returns_the_existing_row_when_it_already_exists(): void
    {
        $job = $this->fixtures->makeJob($this->idProject, ['password' => 'cr_dup']);
        $existing = $this->fixtures->makeQaChunkReview($this->idProject, $job['id'], 'cr_dup', [
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);

        // A later insert on this same connection, so the assertion cannot be satisfied merely by
        // LAST_INSERT_ID() still pointing at the row the fixture just wrote.
        $decoyJob = $this->fixtures->makeJob($this->idProject, ['password' => 'cr_decoy']);
        $decoy = $this->fixtures->makeQaChunkReview($this->idProject, $decoyJob['id'], 'cr_decoy', [
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $this->assertNotSame((int)$existing['id'], (int)$decoy['id'], 'precondition: the decoy is a different row');

        $struct = $this->dao->createRecord([
            'id_project'  => $this->idProject,
            'id_job'      => $job['id'],
            'password'    => 'cr_dup',
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);

        $this->assertSame((int)$existing['id'], $struct->id);
        $this->assertGreaterThan(0, $struct->id);
    }

    #[Test]
    public function deleteByJobId_removes_rows(): void
    {
        $job = $this->fixtures->makeJob($this->idProject, ['password' => 'del_pwd']);
        $this->fixtures->makeQaChunkReview($this->idProject, $job['id'], 'del_pwd');

        $this->assertTrue($this->dao->deleteByJobId($job['id']));
        $this->assertFalse($this->dao->exists($job['id'], 'del_pwd'));
    }

    #[Test]
    public function passFailCountsAtomicUpdate_writes_counters_but_skips_is_pass_when_no_qa_model(): void
    {
        // project without a qa model -> lqaModel null -> counters still write, is_pass stays NULL.
        $project = $this->fixtures->makeProjectDetailed();
        $job = $this->fixtures->makeJob($project['id'], ['password' => 'pf_null', 'owner' => $this->ownerEmail]);

        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = $job['id'];
        $chunkReview->id_project = $project['id'];
        $chunkReview->password = 'pf_null';
        $chunkReview->review_password = 'pf_null_rev';
        // Deliberately not SOURCE_PAGE_REVISION: the test schema declares source_page as
        // `tinyint(3) unsigned NOT NULL DEFAULT '2'`, so a source_page of 2 is indistinguishable
        // from the column default and an unbound column would still assert green.
        $chunkReview->source_page = SourcePages::SOURCE_PAGE_REVISION_2;

        $chunkReviewId = self::ASSIGNABLE_ID_FLOOR + 7001;
        $this->fixtures->trackExisting('qa_chunk_reviews', ['id' => $chunkReviewId]);

        $this->dao->passFailCountsAtomicUpdate($chunkReviewId, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => 5,
            'reviewed_words_count' => 100,
            'total_tte'            => 500,
        ]);

        $row = $this->dao->findById($chunkReviewId);
        $this->assertInstanceOf(ChunkReviewStruct::class, $row);
        $this->assertSame(5.0, $row->penalty_points);
        $this->assertSame(100, $row->reviewed_words_count);
        $this->assertSame(500, $row->total_tte);
        $this->assertNull($row->is_pass);
        // The insert branch omitted source_page entirely. In production the column is
        // `int(11) DEFAULT NULL`, so that wrote NULL — which exempts the row from
        // UNIQUE KEY job_pw_source_page (MySQL treats every NULL as distinct) and makes the drift
        // detector's `e.source_page = r.source_page` join match nothing, so the row is reported as
        // drifted on every scan and no recount can clear it.
        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION_2, $row->source_page);
    }

    /**
     * Two writes carrying different unmatched ids for the same (id_job, password, source_page) must
     * end as one row: neither id matches the primary key, so both take the insert branch and only
     * UNIQUE KEY job_pw_source_page collapses them.
     *
     * Caveat: the production failure this guards is not reproducible here. Production declares
     * source_page `int(11) DEFAULT NULL`, so omitting it wrote NULL and the unique key stopped
     * applying, yielding genuine duplicates. The test schema declares it NOT NULL DEFAULT '2', which
     * cannot hold NULL at all. What this pins is that the caller's source_page reaches the row, so
     * both writes land on the same unique key rather than on the column default.
     */
    #[Test]
    public function passFailCountsAtomicUpdate_insert_branch_cannot_duplicate_a_chunk_review(): void
    {
        $project = $this->fixtures->makeProjectDetailed();
        $job = $this->fixtures->makeJob($project['id'], ['password' => 'pf_dup', 'owner' => $this->ownerEmail]);

        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = $job['id'];
        $chunkReview->id_project = $project['id'];
        $chunkReview->password = 'pf_dup';
        $chunkReview->review_password = 'pf_dup_rev';
        $chunkReview->source_page = SourcePages::SOURCE_PAGE_REVISION_2;

        $data = [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => 2.5,
            'reviewed_words_count' => 10,
            'total_tte'            => 20,
        ];

        foreach ([7201, 7202] as $offset) {
            $id = self::ASSIGNABLE_ID_FLOOR + $offset;
            $this->fixtures->trackExisting('qa_chunk_reviews', ['id' => $id]);
            $this->dao->passFailCountsAtomicUpdate($id, $data);
        }

        $stmt = $this->realSqlDb()->getConnection()->prepare(
            'SELECT COUNT(*) FROM qa_chunk_reviews WHERE id_job = :id_job AND password = :password AND source_page = :source_page'
        );
        $stmt->execute([
            'id_job'      => $job['id'],
            'password'    => 'pf_dup',
            'source_page' => SourcePages::SOURCE_PAGE_REVISION_2,
        ]);

        $this->assertSame(1, (int)$stmt->fetchColumn(), 'the unique key must collapse the second write onto the first row');
    }

    /**
     * lockByJobId refuses to run outside a transaction. This is the one property that made the
     * previous Redis lock useless: it released in a `finally` while the caller's transaction was
     * still open. FOR UPDATE under autocommit fails the same way — the locks are taken and dropped
     * before the caller does its work — so the guard has to be loud rather than silent.
     */
    /**
     * lockByJobId()'s coverage of the split/merge delete-and-recreate window rests entirely on InnoDB
     * gap locking, which only exists under REPEATABLE READ. Nothing in lib/, inc/ or INSTALL/ sets or
     * asserts the isolation level — it is inherited from the server default — so assert it here.
     * Under READ COMMITTED the SELECT would match nothing during the recreate window, take no locks
     * at all, and still return success: the guard would become a silent no-op exactly where it
     * matters, with no failing test to show it. This turns that into a loud failure instead.
     */
    #[Test]
    public function lockByJobId_gap_locking_prerequisite_is_repeatable_read(): void
    {
        $isolation = $this->realSqlDb()->getConnection()
            ->query('SELECT @@session.transaction_isolation')
            ->fetchColumn();

        $this->assertSame(
            'REPEATABLE-READ',
            $isolation,
            'ChunkReviewDao::lockByJobId() relies on gap locking, which READ COMMITTED disables'
        );
    }

    #[Test]
    public function lockByJobId_refuses_to_run_outside_a_transaction(): void
    {
        $conn = $this->realSqlDb()->getConnection();
        $this->assertFalse($conn->inTransaction(), 'precondition: no ambient transaction');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires an open transaction');
        $this->dao->lockByJobId($this->idJob);
    }

    /**
     * Inside a transaction the lock is acquired against the real rows and, critically, is still
     * held after the statement returns — that is what ties release to commit instead of to a TTL.
     */
    #[Test]
    public function lockByJobId_holds_the_rows_until_commit(): void
    {
        $conn = $this->realSqlDb()->getConnection();
        $conn->beginTransaction();

        try {
            $this->dao->lockByJobId($this->idJob);
            $this->assertTrue($conn->inTransaction(), 'the lock must not end the transaction');

            // A second connection must not be able to grab the same rows while we hold them.
            // NOWAIT makes the contention immediate and deterministic instead of timing-dependent
            // (no sleeps, so this cannot flake on loaded CI). The 4-arg form yields a genuinely
            // separate handle rather than the per-test one.
            $other = obtainTestDatabase(
                AppConfig::$DB_SERVER,
                AppConfig::$DB_USER,
                AppConfig::$DB_PASS,
                AppConfig::$DB_DATABASE
            )->getConnection();
            $other->beginTransaction();

            try {
                $stmt = $other->prepare(
                    "SELECT id FROM qa_chunk_reviews WHERE id_job = :id_job FOR UPDATE NOWAIT"
                );
                $stmt->execute(['id_job' => $this->idJob]);
                $stmt->fetchAll();
                $this->fail('the second connection acquired locks we are still holding');
            } catch (PDOException $e) {
                // ER_LOCK_NOWAIT (3572) — proof the rows are genuinely locked by this transaction.
                $this->assertStringContainsString('NOWAIT', $e->getMessage());
            } finally {
                $other->rollBack();
            }
        } finally {
            $conn->rollBack();
        }
    }

    /**
     * The INSERT branch is reachable for a subtract via the deleteByJobId + recreate window in
     * split/merge. Without a clamp in the VALUES list it would create a row with negative
     * penalty_points.
     */
    #[Test]
    public function passFailCountsAtomicUpdate_clamps_a_negative_delta_on_insert(): void
    {
        $project = $this->fixtures->makeProjectDetailed();
        $job = $this->fixtures->makeJob($project['id'], ['password' => 'pf_neg', 'owner' => $this->ownerEmail]);

        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = $job['id'];
        $chunkReview->id_project = $project['id'];
        $chunkReview->password = 'pf_neg';
        $chunkReview->review_password = 'pf_neg_rev';
        $chunkReview->source_page = SourcePages::SOURCE_PAGE_REVISION;

        $chunkReviewId = self::ASSIGNABLE_ID_FLOOR + 7101;
        $this->fixtures->trackExisting('qa_chunk_reviews', ['id' => $chunkReviewId]);

        // no row yet -> INSERT branch, with a subtract
        $this->dao->passFailCountsAtomicUpdate($chunkReviewId, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => -3.5,
            'reviewed_words_count' => -10,
            'total_tte'            => -20,
        ]);

        $row = $this->dao->findById($chunkReviewId);
        $this->assertInstanceOf(ChunkReviewStruct::class, $row);
        $this->assertSame(0.0, $row->penalty_points, 'insert must not persist negative penalty_points');
        $this->assertSame(0, $row->reviewed_words_count);
        $this->assertSame(0, $row->total_tte);
    }

    /**
     * Regression guard for the insert clamp: the ON DUPLICATE KEY UPDATE deltas must stay signed.
     * Reading them back with VALUES(penalty_points) would return the clamped GREATEST(-3, 0) = 0
     * from the VALUES list, so every decrement would silently become a no-op.
     */
    #[Test]
    public function passFailCountsAtomicUpdate_still_applies_a_negative_delta_on_update(): void
    {
        $project = $this->fixtures->makeProjectDetailed();
        $job = $this->fixtures->makeJob($project['id'], ['password' => 'pf_dec', 'owner' => $this->ownerEmail]);

        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = $job['id'];
        $chunkReview->id_project = $project['id'];
        $chunkReview->password = 'pf_dec';
        $chunkReview->review_password = 'pf_dec_rev';
        $chunkReview->source_page = SourcePages::SOURCE_PAGE_REVISION;

        $chunkReviewId = self::ASSIGNABLE_ID_FLOOR + 7102;
        $this->fixtures->trackExisting('qa_chunk_reviews', ['id' => $chunkReviewId]);

        // first call inserts 10.50 / 100 / 500
        $this->dao->passFailCountsAtomicUpdate($chunkReviewId, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => 10.5,
            'reviewed_words_count' => 100,
            'total_tte'            => 500,
        ]);

        // second call hits ON DUPLICATE KEY UPDATE with a subtract
        $this->dao->passFailCountsAtomicUpdate($chunkReviewId, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => -3.5,
            'reviewed_words_count' => -10,
            'total_tte'            => -20,
        ]);

        $row = $this->dao->findById($chunkReviewId);
        $this->assertInstanceOf(ChunkReviewStruct::class, $row);
        $this->assertSame(7.0, $row->penalty_points, 'the decrement must actually apply');
        $this->assertSame(90, $row->reviewed_words_count);
        $this->assertSame(480, $row->total_tte);
    }

    #[Test]
    public function passFailCountsAtomicUpdate_inserts_when_qa_model_present(): void
    {
        $modelId = $this->makeQaModel('{"limit":[15,10]}');
        $project = $this->fixtures->makeProjectDetailed();
        $this->realSqlDb()->getConnection()
            ->exec("UPDATE projects SET id_qa_model = {$modelId} WHERE id = {$project['id']}");
        $job = $this->fixtures->makeJob($project['id'], ['password' => 'pf_ok', 'owner' => $this->ownerEmail]);

        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = $job['id'];
        $chunkReview->id_project = $project['id'];
        $chunkReview->password = 'pf_ok';
        $chunkReview->review_password = 'pf_ok_rev';
        $chunkReview->source_page = SourcePages::SOURCE_PAGE_REVISION;

        $chunkReviewId = self::ASSIGNABLE_ID_FLOOR + 7002;
        $this->fixtures->trackExisting('qa_chunk_reviews', ['id' => $chunkReviewId]);

        $this->dao->passFailCountsAtomicUpdate($chunkReviewId, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => 5,
            'reviewed_words_count' => 200,
            'total_tte'            => 1000,
        ]);

        $row = $this->dao->findById($chunkReviewId);
        $this->assertInstanceOf(ChunkReviewStruct::class, $row);
        $this->assertSame(200, $row->reviewed_words_count);
        $this->assertSame(1000, $row->total_tte);

        // a second call with empty penalty_points exercises the COALESCE / empty-points arm.
        $this->dao->passFailCountsAtomicUpdate($chunkReviewId, [
            'chunkReview'          => $chunkReview,
            'reviewed_words_count' => 50,
            'total_tte'            => 100,
        ]);
        $updated = $this->dao->findById($chunkReviewId);
        $this->assertInstanceOf(ChunkReviewStruct::class, $updated);
        $this->assertSame(250, $updated->reviewed_words_count); // GREATEST(200 + 50)
    }

    #[Test]
    public function passFailCountsAtomicUpdate_preserves_decimal_penalty_points(): void
    {
        // regression: a (int) cast on penalty_points used to truncate 0.5 -> 0 before this INSERT.
        $modelId = $this->makeQaModel('{"limit":[15,10]}');
        $project = $this->fixtures->makeProjectDetailed();
        $this->realSqlDb()->getConnection()
            ->exec("UPDATE projects SET id_qa_model = {$modelId} WHERE id = {$project['id']}");
        $job = $this->fixtures->makeJob($project['id'], ['password' => 'pf_dec', 'owner' => $this->ownerEmail]);

        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = $job['id'];
        $chunkReview->id_project = $project['id'];
        $chunkReview->password = 'pf_dec';
        $chunkReview->review_password = 'pf_dec_rev';
        $chunkReview->source_page = SourcePages::SOURCE_PAGE_REVISION;

        $chunkReviewId = self::ASSIGNABLE_ID_FLOOR + 7003;
        $this->fixtures->trackExisting('qa_chunk_reviews', ['id' => $chunkReviewId]);

        $this->dao->passFailCountsAtomicUpdate($chunkReviewId, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => 0.5,
            'reviewed_words_count' => 100,
            'total_tte'            => 500,
        ]);

        $row = $this->dao->findById($chunkReviewId);
        $this->assertInstanceOf(ChunkReviewStruct::class, $row);
        $this->assertSame(0.5, $row->penalty_points);

        // a second decimal addition should accumulate, not stay truncated at 0.
        $this->dao->passFailCountsAtomicUpdate($chunkReviewId, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => 0.5,
            'reviewed_words_count' => 0,
            'total_tte'            => 0,
        ]);

        $updated = $this->dao->findById($chunkReviewId);
        $this->assertInstanceOf(ChunkReviewStruct::class, $updated);
        $this->assertSame(1.0, $updated->penalty_points);
    }
}
