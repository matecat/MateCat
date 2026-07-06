<?php

namespace Matecat\Core\DAO\TestChunkReviewDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\SourcePages;
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
        $this->assertSame(5, $this->dao->getPenaltyPointsForChunk($this->chunk($this->idJob, $this->jobPassword)));

        // REVISION_2 has no entries -> SUM(null) -> 0.
        $this->assertSame(
            0,
            $this->dao->getPenaltyPointsForChunk(
                $this->chunk($this->idJob, $this->jobPassword),
                SourcePages::SOURCE_PAGE_REVISION_2
            )
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

    #[Test]
    public function deleteByJobId_removes_rows(): void
    {
        $job = $this->fixtures->makeJob($this->idProject, ['password' => 'del_pwd']);
        $this->fixtures->makeQaChunkReview($this->idProject, $job['id'], 'del_pwd');

        $this->assertTrue($this->dao->deleteByJobId($job['id']));
        $this->assertFalse($this->dao->exists($job['id'], 'del_pwd'));
    }

    #[Test]
    public function passFailCountsAtomicUpdate_updates_counters_and_forces_pass_when_no_qa_model(): void
    {
        // project without a qa model -> lqaModel null -> counters still written, is_pass forced to 1.
        //
        // The chunk review row is pre-created via createRecord() first, exactly like production
        // does when a review round starts: passFailCountsAtomicUpdate's INSERT then collides on
        // the (id_job, password, source_page) unique key and takes the ON DUPLICATE KEY UPDATE
        // branch, which is the only branch that actually computes is_pass (it is not part of the
        // plain INSERT column list).
        $project = $this->fixtures->makeProjectDetailed();
        $job = $this->fixtures->makeJob($project['id'], ['password' => 'pf_null', 'owner' => $this->ownerEmail]);

        $created = $this->dao->createRecord([
            'id_project'  => $project['id'],
            'id_job'      => $job['id'],
            'password'    => 'pf_null',
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $this->fixtures->trackExisting('qa_chunk_reviews', ['id' => $created->id]);

        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = $job['id'];
        $chunkReview->id_project = $project['id'];
        $chunkReview->password = 'pf_null';
        $chunkReview->review_password = $created->review_password;
        $chunkReview->source_page = SourcePages::SOURCE_PAGE_REVISION;

        $this->dao->passFailCountsAtomicUpdate($created->id, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => 12,
            'reviewed_words_count' => 100,
            'total_tte'            => 500,
        ]);

        $row = $this->dao->findById($created->id);
        $this->assertInstanceOf(ChunkReviewStruct::class, $row);
        $this->assertSame(12, (int)$row->penalty_points);
        $this->assertSame(100, $row->reviewed_words_count);
        $this->assertSame(500, $row->total_tte);
        $this->assertSame(1, (int)$row->is_pass);
    }

    #[Test]
    public function passFailCountsAtomicUpdate_updates_counters_and_forces_pass_when_qa_model_is_stale(): void
    {
        // id_qa_model points at a non-existent qa_models row -> ModelDao::findById returns null,
        // same forced-pass fallback as the no-model case must apply even though the score
        // (30 points / 100 words * 1000 = 300) would fail any real limit.
        $project = $this->fixtures->makeProjectDetailed();
        $staleModelId = self::ASSIGNABLE_ID_FLOOR + 999999;
        $this->realSqlDb()->getConnection()
            ->exec("UPDATE projects SET id_qa_model = {$staleModelId} WHERE id = {$project['id']}");
        $job = $this->fixtures->makeJob($project['id'], ['password' => 'pf_stale', 'owner' => $this->ownerEmail]);

        $created = $this->dao->createRecord([
            'id_project'  => $project['id'],
            'id_job'      => $job['id'],
            'password'    => 'pf_stale',
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $this->fixtures->trackExisting('qa_chunk_reviews', ['id' => $created->id]);

        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = $job['id'];
        $chunkReview->id_project = $project['id'];
        $chunkReview->password = 'pf_stale';
        $chunkReview->review_password = $created->review_password;
        $chunkReview->source_page = SourcePages::SOURCE_PAGE_REVISION;

        $this->dao->passFailCountsAtomicUpdate($created->id, [
            'chunkReview'          => $chunkReview,
            'penalty_points'       => 30,
            'reviewed_words_count' => 100,
            'total_tte'            => 500,
        ]);

        $row = $this->dao->findById($created->id);
        $this->assertInstanceOf(ChunkReviewStruct::class, $row);
        $this->assertSame(30, (int)$row->penalty_points);
        $this->assertSame(1, (int)$row->is_pass);
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
}
