<?php

namespace Matecat\Core\Model\Jobs;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-SQL coverage for JobDao (plan dao-realsql-90.md, Wave 2 / T2).
 *
 * Every public SQL method is invoked DIRECTLY against the live unittest_matecat_local schema
 * and asserted on real returned data (DoD b). No mocks. JobDao self-commits (createFromStruct
 * issues begin()/commit()) and updateFields/updateStruct go through the same injected
 * connection, so NO wrapping transaction is used (C-1); cleanup is seed-safe id DELETE driven
 * by TestFixtureBuilder plus trackExisting() for DAO-INSERTed rows (C-1/M-1/M-2). The
 * whole-table COUNT(*) residue gate over TABLE_DEPS (A-1/A-2/AC-1) runs in the trait teardown.
 *
 * No assertion is ever made on an absolute generated id value (M-3): ids are round-tripped.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class JobDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    /** Every table JobDao reads/writes (from T0 inscope-daos.json tableDeps). */
    private const array TABLE_DEPS = [
        'files',
        'files_job',
        'files_parts',
        'job_custom_payable_rates',
        'jobs',
        'segment_translation_events',
        'segment_translations',
        'segments',
        'users',
    ];

    private JobDao $dao;
    private int $uid;
    private string $ownerEmail;
    private int $idProject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new JobDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);

        $user = $this->fixtures->makeUser();
        $this->uid = $user['uid'];
        $this->ownerEmail = $user['email'];
        $this->idProject = $this->fixtures->makeProject()['id'];
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    /**
     * Build one job owned by the test user, returning the JobStruct loaded from the DB so
     * round-tripped data (never absolute ids) drives the assertions.
     *
     * @param array<string,int|string|null> $overrides
     */
    private function seedJob(array $overrides = []): JobStruct
    {
        $made = $this->fixtures->makeJob($this->idProject, array_merge(['owner' => $this->ownerEmail], $overrides));
        $loaded = $this->dao->getByIdAndPassword($made['id'], $made['password']);
        self::assertInstanceOf(JobStruct::class, $loaded);

        return $loaded;
    }

    // ---------------------------------------------------------------------------------------
    // read / cache helpers
    // ---------------------------------------------------------------------------------------

    public function testReadReturnsMatchingJob(): void
    {
        $job = $this->seedJob();

        $rows = $this->dao->read($job);

        self::assertCount(1, $rows);
        self::assertSame($job->id, $rows[0]->id);
        self::assertSame($job->password, $rows[0]->password);
        self::assertSame($this->idProject, $rows[0]->id_project);
    }

    public function testReadReturnsEmptyForWrongPassword(): void
    {
        $job = $this->seedJob();
        $job->password = 'definitely_wrong_pw';

        self::assertSame([], $this->dao->read($job));
    }

    public function testDestroyCacheByIdAndPassword(): void
    {
        $job = $this->seedJob();
        // Prime the cache (ttl>0 so the entry is actually stored), then destroy it.
        $this->dao->getByIdAndPassword($job->id, $job->password, 3600);

        self::assertTrue($this->dao->destroyCacheByIdAndPassword($job));
    }

    public function testDestroyCacheByProjectId(): void
    {
        $job = $this->seedJob();
        $this->dao->getNotDeletedByProjectId($this->idProject, 3600); // prime cache (ttl>0)

        self::assertTrue($this->dao->destroyCacheByProjectId($job->id_project));
    }

    // ---------------------------------------------------------------------------------------
    // getByIdAndPassword / OrFail / getNotDeleted* / getByIdProjectAndIdJob
    // ---------------------------------------------------------------------------------------

    public function testGetByIdAndPasswordRoundTrips(): void
    {
        $job = $this->seedJob();

        $found = $this->dao->getByIdAndPassword($job->id, $job->password);

        self::assertInstanceOf(JobStruct::class, $found);
        self::assertSame($job->id, $found->id);
        self::assertSame($this->idProject, $found->id_project);
    }

    public function testGetByIdAndPasswordReturnsNullWhenAbsent(): void
    {
        self::assertNull($this->dao->getByIdAndPassword(1, 'no_such_pw_value'));
    }

    public function testGetByIdAndPasswordOrFailReturnsJob(): void
    {
        $job = $this->seedJob();

        $found = $this->dao->getByIdAndPasswordOrFail($job->id, $job->password);

        self::assertSame($job->id, $found->id);
    }

    public function testGetByIdAndPasswordOrFailThrowsWhenAbsent(): void
    {
        $this->expectException(\Model\Exceptions\NotFoundException::class);
        $this->dao->getByIdAndPasswordOrFail(1, 'no_such_pw_value');
    }

    public function testGetNotDeletedByProjectIdReturnsActiveJobs(): void
    {
        $job = $this->seedJob();

        $jobs = $this->dao->getNotDeletedByProjectId($this->idProject);

        self::assertCount(1, $jobs);
        self::assertSame($job->id, $jobs[0]->id);
    }

    public function testGetNotDeletedByIdReturnsActiveJob(): void
    {
        $job = $this->seedJob();

        $jobs = $this->dao->getNotDeletedById($job->id);

        self::assertCount(1, $jobs);
        self::assertSame($job->id, $jobs[0]->id);
    }

    public function testGetByIdProjectAndIdJob(): void
    {
        $job = $this->seedJob();

        $jobs = $this->dao->getByIdProjectAndIdJob($this->idProject, $job->id);

        self::assertCount(1, $jobs);
        self::assertSame($job->id, $jobs[0]->id);
    }

    // ---------------------------------------------------------------------------------------
    // updateOwner / getOwnerUid
    // ---------------------------------------------------------------------------------------

    public function testUpdateOwnerUpdatesAllJobsForProject(): void
    {
        $this->seedJob();
        $newUser = $this->fixtures->makeUser();

        $project = new ProjectStruct();
        $project->id = $this->idProject;
        $user = new UserStruct();
        $user->email = $newUser['email'];

        $affected = $this->dao->updateOwner($project, $user);

        self::assertSame(1, $affected);
        self::assertSame($newUser['uid'], $this->dao->getOwnerUid(
            $this->dao->getNotDeletedByProjectId($this->idProject)[0]->id,
            $this->dao->getNotDeletedByProjectId($this->idProject)[0]->password
        ));
    }

    public function testGetOwnerUidResolvesFromEmail(): void
    {
        $job = $this->seedJob();

        self::assertSame($this->uid, $this->dao->getOwnerUid($job->id, $job->password));
    }

    public function testGetOwnerUidReturnsNullWhenNoMatch(): void
    {
        self::assertNull($this->dao->getOwnerUid(1, 'no_such_pw_value'));
    }

    // ---------------------------------------------------------------------------------------
    // changePassword
    // ---------------------------------------------------------------------------------------

    public function testChangePasswordPersistsNewPassword(): void
    {
        $job = $this->seedJob();
        $oldPassword = $job->password;
        $newPassword = 'newpw_' . bin2hex(random_bytes(4));

        $returned = $this->dao->changePassword($job, $newPassword);

        self::assertSame($newPassword, $returned->password);
        self::assertNull($this->dao->getByIdAndPassword($job->id, $oldPassword));
        self::assertInstanceOf(JobStruct::class, $this->dao->getByIdAndPassword($job->id, $newPassword));
    }

    public function testChangePasswordRejectsEmpty(): void
    {
        $job = $this->seedJob();
        $this->expectException(\PDOException::class);
        $this->dao->changePassword($job, '');
    }

    // ---------------------------------------------------------------------------------------
    // status mutations: setJobComplete / updateJobStatus / updateAllJobsStatusesByProjectId
    // ---------------------------------------------------------------------------------------

    public function testSetJobCompleteSetsCompletedFlag(): void
    {
        $job = $this->seedJob();

        $affected = $this->dao->setJobComplete($job);

        self::assertSame(1, $affected);
        self::assertTrue((bool)$this->dao->getByIdAndPassword($job->id, $job->password)->completed);
    }

    public function testUpdateJobStatusChangesStatusOwner(): void
    {
        $job = $this->seedJob();

        $this->dao->updateJobStatus($job, \Utils\Constants\JobStatus::STATUS_ARCHIVED);

        self::assertSame(
            \Utils\Constants\JobStatus::STATUS_ARCHIVED,
            $this->dao->getByIdAndPassword($job->id, $job->password)->status_owner
        );
    }

    public function testUpdateAllJobsStatusesByProjectId(): void
    {
        $job = $this->seedJob();

        $this->dao->updateAllJobsStatusesByProjectId($this->idProject, \Utils\Constants\JobStatus::STATUS_ARCHIVED);

        self::assertSame(
            \Utils\Constants\JobStatus::STATUS_ARCHIVED,
            $this->dao->getByIdAndPassword($job->id, $job->password)->status_owner
        );
    }

    // ---------------------------------------------------------------------------------------
    // word-count mutations: updateStdWcAndTotalWc / updateJobWeightedPeeAndTTE
    // ---------------------------------------------------------------------------------------

    public function testUpdateStdWcAndTotalWc(): void
    {
        $job = $this->seedJob();

        $affected = $this->dao->updateStdWcAndTotalWc($job->id, 111, 222);

        self::assertSame(1, $affected);
        $reloaded = $this->dao->getByIdAndPassword($job->id, $job->password);
        self::assertSame(111, $reloaded->standard_analysis_wc);
        self::assertSame(222, $reloaded->total_raw_wc);
    }

    public function testUpdateJobWeightedPeeAndTTE(): void
    {
        $job = $this->seedJob();
        $job->avg_post_editing_effort = 12.5;
        $job->total_time_to_edit = 9999;

        $this->dao->updateJobWeightedPeeAndTTE($job);

        $reloaded = $this->dao->getByIdAndPassword($job->id, $job->password);
        self::assertEqualsWithDelta(12.5, $reloaded->avg_post_editing_effort, 0.001);
        self::assertSame(9999, $reloaded->total_time_to_edit);
    }

    // ---------------------------------------------------------------------------------------
    // split data / counts (need segment + files_job + segment_translation topology)
    // ---------------------------------------------------------------------------------------

    /**
     * Build a job bounded to a single segment with a files_job link and one translation.
     *
     * @return array{job:JobStruct,id_file:int,id_segment:int}
     */
    private function seedJobWithSegment(array $stOverrides = []): array
    {
        $file = $this->fixtures->makeFile($this->idProject);
        $segment = $this->fixtures->makeSegmentDetailed($file['id'], ['raw_word_count' => 10, 'show_in_cattool' => 1]);
        $job = $this->seedJob([
            'job_first_segment' => $segment['id'],
            'job_last_segment'  => $segment['id'],
        ]);
        $this->fixtures->makeFilesJob($job->id, $file['id']);
        $this->fixtures->makeSegmentTranslationDetailed($segment['id'], $job->id, $stOverrides);

        return ['job' => $job, 'id_file' => $file['id'], 'id_segment' => $segment['id']];
    }

    public function testGetSplitDataAggregatesWordCounts(): void
    {
        $ctx = $this->seedJobWithSegment();

        $rows = $this->dao->getSplitData($ctx['job']->id, $ctx['job']->password);

        // one segment row + the WITH ROLLUP total row
        self::assertGreaterThanOrEqual(1, count($rows));
        $hasSegmentRow = false;
        foreach ($rows as $r) {
            if ((int)($r->id ?? 0) === $ctx['id_segment']) {
                $hasSegmentRow = true;
                self::assertEqualsWithDelta(10.0, (float)$r->raw_word_count, 0.001);
            }
        }
        self::assertTrue($hasSegmentRow, 'getSplitData must return the seeded segment row');
    }

    public function testGetSegmentsCount(): void
    {
        $ctx = $this->seedJobWithSegment();

        self::assertSame(1, $this->dao->getSegmentsCount($ctx['job']->id, $ctx['job']->password));
    }

    public function testGetSegmentsCountReturnsZeroWhenEmpty(): void
    {
        $job = $this->seedJob();

        self::assertSame(0, $this->dao->getSegmentsCount($job->id, $job->password));
    }

    public function testGetSegmentTranslationsCount(): void
    {
        $ctx = $this->seedJobWithSegment();

        self::assertSame(1, $this->dao->getSegmentTranslationsCount([$ctx['job']->id]));
    }

    public function testGetSegmentTranslationsCountReturnsNullWhenEmpty(): void
    {
        $job = $this->seedJob();

        self::assertNull($this->dao->getSegmentTranslationsCount([$job->id]));
    }

    public function testGetFilesInfoInJob(): void
    {
        $ctx = $this->seedJobWithSegment();

        $rows = $this->dao->getFilesInfoInJob($ctx['job']);

        self::assertCount(1, $rows);
        self::assertSame($ctx['id_file'], (int)$rows[0]->id_file);
    }

    // ---------------------------------------------------------------------------------------
    // PEE queries (need translated segments with a measurable edit ratio)
    // ---------------------------------------------------------------------------------------

    public function testGetAllModifiedSegmentsForPee(): void
    {
        $ctx = $this->seedJobWithSegment([
            'status'       => 'TRANSLATED',
            'time_to_edit' => 5000,
        ]);

        $segments = $this->dao->getAllModifiedSegmentsForPee($ctx['job']);

        self::assertCount(1, $segments);
        self::assertSame($ctx['id_segment'], (int)$segments[0]->id);
    }

    public function testGetPeeStatsReturnsStruct(): void
    {
        $ctx = $this->seedJobWithSegment([
            'status'       => 'TRANSLATED',
            'time_to_edit' => 5000,
        ]);

        $stats = $this->dao->getPeeStats($ctx['job']->id, $ctx['job']->password);

        self::assertInstanceOf(ShapelessConcreteStruct::class, $stats);
        self::assertObjectHasProperty('avg_pee', $stats);
    }

    // ---------------------------------------------------------------------------------------
    // getBySegmentTranslation
    // ---------------------------------------------------------------------------------------

    public function testGetBySegmentTranslation(): void
    {
        $ctx = $this->seedJobWithSegment();

        $translation = new SegmentTranslationStruct();
        $translation->id_job = $ctx['job']->id;
        $translation->id_segment = $ctx['id_segment'];

        $job = $this->dao->getBySegmentTranslation($translation);

        self::assertInstanceOf(JobStruct::class, $job);
        self::assertSame($ctx['job']->id, $job->id);
    }

    // ---------------------------------------------------------------------------------------
    // events: getTimeToEdit / getReviewedWordsCountGroupedByFileParts
    // ---------------------------------------------------------------------------------------

    public function testGetTimeToEdit(): void
    {
        $ctx = $this->seedJobWithSegment();
        $this->fixtures->makeSegmentTranslationEvent($ctx['job']->id, $ctx['id_segment'], [
            'status'       => \Utils\Constants\TranslationStatus::STATUS_TRANSLATED,
            'source_page'  => 1,
            'time_to_edit' => 1500,
        ]);

        $tte = $this->dao->getTimeToEdit($ctx['job']->id, 1);

        self::assertInstanceOf(ShapelessConcreteStruct::class, $tte);
        self::assertSame(1500, (int)$tte->tte);
    }

    public function testGetReviewedWordsCountGroupedByFileParts(): void
    {
        $file = $this->fixtures->makeFile($this->idProject);
        $filePart = $this->fixtures->makeFilesPart($file['id'], 'k', 'v');
        $segment = $this->fixtures->makeSegmentDetailed($file['id'], [
            'raw_word_count' => 7,
            'id_file_part'   => $filePart['id'],
            'show_in_cattool' => 1,
        ]);
        $job = $this->seedJob([
            'job_first_segment' => $segment['id'],
            'job_last_segment'  => $segment['id'],
        ]);
        $this->fixtures->makeFilesJob($job->id, $file['id']);
        $this->fixtures->makeSegmentTranslationEvent($job->id, $segment['id'], [
            'source_page'    => 2,
            'final_revision' => 1,
            'status'         => \Utils\Constants\TranslationStatus::STATUS_APPROVED,
        ]);

        $rows = $this->dao->getReviewedWordsCountGroupedByFileParts($job->id, $job->password, 2);

        self::assertCount(1, $rows);
        self::assertSame(7, (int)$rows[0]->reviewed_words_count);
        self::assertSame($filePart['id'], (int)$rows[0]->id_file_part_external_reference);
    }

    // ---------------------------------------------------------------------------------------
    // custom payable rate
    // ---------------------------------------------------------------------------------------

    public function testHasACustomPayableRateTrue(): void
    {
        $job = $this->seedJob();
        $this->fixtures->makeJobCustomPayableRate($job->id);

        self::assertTrue($this->dao->hasACustomPayableRate($job->id));
    }

    public function testHasACustomPayableRateFalse(): void
    {
        $job = $this->seedJob();

        self::assertFalse($this->dao->hasACustomPayableRate($job->id));
    }

    // ---------------------------------------------------------------------------------------
    // getSplitJobPreparedStatement (returns a bound, un-executed PDOStatement)
    // ---------------------------------------------------------------------------------------

    public function testGetSplitJobPreparedStatementExecutesAndUpserts(): void
    {
        $job = $this->seedJob();
        // mutate a value to be persisted by the ON DUPLICATE KEY UPDATE clause
        $job->avg_post_editing_effort = 7.0;
        $job->last_opened_segment = 5;

        $stmt = $this->dao->getSplitJobPreparedStatement($job);
        $stmt->execute();

        $reloaded = $this->dao->getByIdAndPassword($job->id, $job->password);
        self::assertInstanceOf(JobStruct::class, $reloaded);
        self::assertEqualsWithDelta(7.0, $reloaded->avg_post_editing_effort, 0.001);
    }

    // ---------------------------------------------------------------------------------------
    // createFromStruct (self-commit INSERT) — DAO-inserted row registered for cleanup
    // ---------------------------------------------------------------------------------------

    public function testCreateFromStructPersistsJob(): void
    {
        $struct = new JobStruct();
        $struct->password = substr(bin2hex(random_bytes(8)), 0, 12);
        $struct->id_project = $this->idProject;
        $struct->job_first_segment = 1;
        $struct->job_last_segment = 100;
        $struct->source = 'en-US';
        $struct->target = 'it-IT';
        $struct->owner = $this->ownerEmail;
        $struct->create_date = date('Y-m-d H:i:s');

        $created = $this->dao->createFromStruct($struct);
        $this->fixtures->trackExisting('jobs', ['id' => (int)$created->id]);

        self::assertInstanceOf(JobStruct::class, $created);
        self::assertNotNull($created->id);
        self::assertSame($this->idProject, $created->id_project);
        self::assertSame($struct->password, $created->password);
    }

    // ---------------------------------------------------------------------------------------
    // updateForMerge / deleteOnMerge (merge lifecycle)
    // ---------------------------------------------------------------------------------------

    public function testUpdateForMergeChangesPassword(): void
    {
        $job = $this->seedJob();
        $oldPassword = $job->password;
        $newPassword = 'merged_' . bin2hex(random_bytes(4));

        $returned = $this->dao->updateForMerge($job, $newPassword);

        self::assertSame($newPassword, $returned->password);
        self::assertInstanceOf(JobStruct::class, $this->dao->getByIdAndPassword($job->id, $newPassword));
        self::assertNull($this->dao->getByIdAndPassword($job->id, $oldPassword));
    }

    public function testUpdateForMergeWithoutPasswordKeepsIt(): void
    {
        $job = $this->seedJob();
        $original = $job->password;
        $job->subject = 'merged subject';

        $returned = $this->dao->updateForMerge($job, '');

        self::assertSame($original, $returned->password);
        self::assertInstanceOf(JobStruct::class, $this->dao->getByIdAndPassword($job->id, $original));
    }

    public function testDeleteOnMergeRemovesOtherPasswordRow(): void
    {
        // Create a second job in the same project with a DIFFERENT password; deleteOnMerge
        // removes the row matching id but NOT the "first_job_password".
        $job = $this->seedJob();
        $firstJobPassword = 'kept_' . bin2hex(random_bytes(4));

        $merge = new JobStruct();
        $merge->id = $job->id;
        $merge->password = $firstJobPassword; // the surviving password

        $result = $this->dao->deleteOnMerge($merge);

        self::assertTrue($result);
        // the original (different-password) row is gone
        self::assertNull($this->dao->getByIdAndPassword($job->id, $job->password));
    }
}
