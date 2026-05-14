<?php

declare(strict_types=1);

namespace unit\DAO\TestJobDAO;

use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\EditLog\EditLogSegmentStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PDO;
use PDOException;
use PDOStatement;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class JobDaoTest extends AbstractTest
{
    private \PHPUnit\Framework\MockObject\Stub&IDatabase $dbStub;
    private \PHPUnit\Framework\MockObject\Stub&PDO $pdoStub;
    private \PHPUnit\Framework\MockObject\Stub&PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
        $this->dbStub->method('begin')->willReturn($this->pdoStub);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();

        AppConfig::$SKIP_SQL_CACHE = false;

        parent::tearDown();
    }

    private function makeJobStruct(array $overrides = []): JobStruct
    {
        $job = new JobStruct();
        $job->id = $overrides['id'] ?? 1;
        $job->password = $overrides['password'] ?? 'abc123';
        $job->id_project = $overrides['id_project'] ?? 10;
        $job->job_first_segment = $overrides['job_first_segment'] ?? 100;
        $job->job_last_segment = $overrides['job_last_segment'] ?? 200;
        $job->source = $overrides['source'] ?? 'en-US';
        $job->target = $overrides['target'] ?? 'it-IT';

        return $job;
    }

    public function testReadReturnsArrayOfJobStructs(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->read($job);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(JobStruct::class, $result[0]);
    }

    public function testReadReturnsEmptyArrayWhenNoMatch(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->read($job);

        $this->assertSame([], $result);
    }

    public function testDestroyCacheDoesNotThrow(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);
        $dao->destroyCacheByIdAndPassword($job);

        $this->assertIsBool($dao->destroyCacheByIdAndPassword($job));
    }

    public function testDestroyCacheByProjectIdDoesNotThrow(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->destroyCacheByProjectId(10);

        $this->assertIsBool($result);
    }

    public function testGetSplitDataReturnsArrayOfStructs(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->raw_word_count = '100';
        $struct->eq_word_count = '85';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getSplitData(1, 'pass');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $result[0]);
    }

    public function testUpdateOwnerReturnsAffectedRowCount(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(3);

        $project = new ProjectStruct();
        $project->id = 10;

        $user = new UserStruct();
        $user->email = 'test@example.com';

        $dao = new JobDao($this->dbStub);
        $result = $dao->updateOwner($project, $user);

        $this->assertSame(3, $result);
    }

    public function testChangePasswordReturnsUpdatedStruct(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->changePassword($job, 'newpass');

        $this->assertSame('newpass', $result->password);
    }

    public function testChangePasswordThrowsWhenEmpty(): void
    {
        $job = $this->makeJobStruct();

        $dao = new JobDao($this->dbStub);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Invalid empty value: password.');
        $dao->changePassword($job, '');
    }

    public function testGetAllModifiedSegmentsForPeeReturnsArray(): void
    {
        $struct = new EditLogSegmentStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $job = $this->makeJobStruct();

        $dao = new JobDao($this->dbStub);
        $result = $dao->getAllModifiedSegmentsForPee($job);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(EditLogSegmentStruct::class, $result[0]);
    }

    public function testUpdateJobWeightedPeeAndTTEExecutesWithoutError(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $job = $this->makeJobStruct();
        $job->avg_post_editing_effort = 50;
        $job->total_time_to_edit = 1000;

        $dao = new JobDao($this->dbStub);
        $dao->updateJobWeightedPeeAndTTE($job);

        $this->assertTrue(true);
    }

    public function testGetPeeStatsReturnsStruct(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->avg_pee = '0.5';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getPeeStats(1, 'pass');

        $this->assertInstanceOf(ShapelessConcreteStruct::class, $result);
    }

    public function testGetSplitJobPreparedStatementReturnsPdoStatement(): void
    {
        $job = $this->makeJobStruct();
        $job->last_opened_segment = 150;

        $dao = new JobDao($this->dbStub);
        $result = $dao->getSplitJobPreparedStatement($job);

        $this->assertInstanceOf(PDOStatement::class, $result);
    }

    public function testGetTimeToEditReturnsStructForSourcePage1(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->tte = '5000';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getTimeToEdit(1, 1);

        $this->assertInstanceOf(ShapelessConcreteStruct::class, $result);
    }

    public function testGetTimeToEditUsesApprovedStatusForSourcePageNot1(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->tte = '3000';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getTimeToEdit(1, 2);

        $this->assertInstanceOf(ShapelessConcreteStruct::class, $result);
    }

    public function testUpdateStdWcAndTotalWcReturnsRowCount(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $dao = new JobDao($this->dbStub);
        $result = $dao->updateStdWcAndTotalWc(1, 500, 600);

        $this->assertSame(1, $result);
    }

    public function testInstanceGetBySegmentTranslationReturnsJobStruct(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $translation = new SegmentTranslationStruct();
        $translation->id_job = 1;
        $translation->id_segment = 150;

        $dao = new JobDao($this->dbStub);
        $result = $dao->getBySegmentTranslation($translation);

        $this->assertInstanceOf(JobStruct::class, $result);
        $this->assertSame(1, $result->id);
    }

    public function testInstanceGetSegmentsCountReturnsIntWhenResultPresent(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->total = '42';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getSegmentsCount(1, 'pass');

        $this->assertSame(42, $result);
    }

    public function testInstanceGetSegmentsCountReturnsZeroWhenTotalEmpty(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->total = null;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getSegmentsCount(1, 'pass');

        $this->assertSame(0, $result);
    }

    public function testInstanceGetSegmentsCountReturnsZeroWhenNoResult(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getSegmentsCount(1, 'pass');

        $this->assertSame(0, $result);
    }

    public function testInstanceGetOwnerUidReturnsUidWhenFound(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->uid = 99;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getOwnerUid(1, 'pass');

        $this->assertSame(99, $result);
    }

    public function testInstanceGetOwnerUidReturnsNullWhenEmpty(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getOwnerUid(1, 'pass');

        $this->assertNull($result);
    }

    public function testInstanceGetOwnerUidReturnsNullWhenUidNotSet(): void
    {
        $struct = new ShapelessConcreteStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getOwnerUid(1, 'pass');

        $this->assertNull($result);
    }

    public function testInstanceGetByIdAndPasswordReturnsJobStructWhenFound(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getByIdAndPassword(1, 'abc123');

        $this->assertInstanceOf(JobStruct::class, $result);
        $this->assertSame(1, $result->id);
    }

    public function testInstanceGetByIdAndPasswordReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getByIdAndPassword(999, 'nope');

        $this->assertNull($result);
    }

    public function testGetByIdAndPasswordOrFailReturnsJobStructWhenFound(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getByIdAndPasswordOrFail(1, 'abc123');

        $this->assertInstanceOf(JobStruct::class, $result);
        $this->assertSame(1, $result->id);
    }

    public function testGetByIdAndPasswordOrFailThrowsNotFoundExceptionWhenNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);

        $this->expectException(\Model\Exceptions\NotFoundException::class);
        $this->expectExceptionMessage('Job not found');

        $dao->getByIdAndPasswordOrFail(999, 'nope');
    }

    public function testInstanceGetNotDeletedByProjectIdReturnsArrayOfJobs(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getNotDeletedByProjectId(10);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(JobStruct::class, $result[0]);
    }

    public function testInstanceGetNotDeletedByIdReturnsArrayOfJobs(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getNotDeletedById(1);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(JobStruct::class, $result[0]);
    }

    public function testInstanceGetByIdProjectAndIdJobReturnsArray(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getByIdProjectAndIdJob(10, 1);

        $this->assertCount(1, $result);
    }

    public function testInstanceCreateFromStructReturnsJobWithId(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);
        $this->pdoStub->method('lastInsertId')->willReturn('55');

        $dao = new JobDao($this->dbStub);
        $result = $dao->createFromStruct($job);

        $this->assertInstanceOf(JobStruct::class, $result);
    }

    public function testInstanceUpdateForMergeUpdatesPasswordWhenProvided(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->dbStub->method('update')->willReturn(1);

        $dao = new JobDao($this->dbStub);
        $result = $dao->updateForMerge($job, 'newpass');

        $this->assertSame('newpass', $result->password);
    }

    public function testInstanceUpdateForMergeKeepsPasswordWhenEmpty(): void
    {
        $job = $this->makeJobStruct();
        $originalPassword = $job->password;

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->dbStub->method('update')->willReturn(1);

        $dao = new JobDao($this->dbStub);
        $result = $dao->updateForMerge($job, '');

        $this->assertSame($originalPassword, $result->password);
    }

    public function testInstanceDeleteOnMergeReturnsTrue(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $job = $this->makeJobStruct();
        $dao = new JobDao($this->dbStub);
        $result = $dao->deleteOnMerge($job);

        $this->assertTrue($result);
    }

    public function testInstanceDeleteOnMergeReturnsFalse(): void
    {
        $this->stmtStub->method('execute')->willReturn(false);

        $job = $this->makeJobStruct();
        $dao = new JobDao($this->dbStub);
        $result = $dao->deleteOnMerge($job);

        $this->assertFalse($result);
    }

    public function testInstanceGetFilesInfoInJobReturnsArray(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->id_file = 1;
        $struct->first_segment = 100;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $job = $this->makeJobStruct();
        $dao = new JobDao($this->dbStub);
        $result = $dao->getFilesInfoInJob($job);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $result[0]);
    }

    public function testInstanceUpdateAllJobsStatusesByProjectIdExecutes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->dbStub->method('update')->willReturn(1);

        $dao = new JobDao($this->dbStub);
        $dao->updateAllJobsStatusesByProjectId(10, 'cancelled');

        $this->assertTrue(true);
    }

    public function testInstanceSetJobCompleteReturnsRowCount(): void
    {
        $this->dbStub->method('update')->willReturn(1);

        $job = $this->makeJobStruct();
        $dao = new JobDao($this->dbStub);
        $result = $dao->setJobComplete($job);

        $this->assertSame(1, $result);
    }

    public function testInstanceUpdateJobStatusExecutes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->dbStub->method('update')->willReturn(1);

        $job = $this->makeJobStruct();
        $dao = new JobDao($this->dbStub);
        $dao->updateJobStatus($job, 'cancelled');

        $this->assertTrue(true);
    }

    public function testInstanceGetReviewedWordsCountGroupedByFilePartsReturnsArray(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->reviewed_words_count = '500';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getReviewedWordsCountGroupedByFileParts(1, 'pass', 2);

        $this->assertCount(1, $result);
    }

    public function testInstanceGetSegmentTranslationsCountReturnsTotalWhenPresent(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->total = '100';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getSegmentTranslationsCount([1, 2, 3]);

        $this->assertSame(100, $result);
    }

    public function testInstanceGetSegmentTranslationsCountReturnsNullWhenEmpty(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getSegmentTranslationsCount([1, 2]);

        $this->assertNull($result);
    }

    public function testInstanceGetSegmentTranslationsCountReturnsNullWhenTotalEmpty(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->total = null;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->getSegmentTranslationsCount([1, 2]);

        $this->assertNull($result);
    }

    public function testInstanceHasACustomPayableRateReturnsTrueWhenFound(): void
    {
        $struct = new ShapelessConcreteStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->hasACustomPayableRate(1);

        $this->assertTrue($result);
    }

    public function testInstanceHasACustomPayableRateReturnsFalseWhenNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->hasACustomPayableRate(1);

        $this->assertFalse($result);
    }
}
