<?php

declare(strict_types=1);

namespace unit\DAO\TestJobDAO;

use Model\DataAccess\Database;
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
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Utils\Registry\AppConfig;

class JobDaoTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\Stub&IDatabase $dbStub;
    private \PHPUnit\Framework\MockObject\Stub&PDO $pdoStub;
    private \PHPUnit\Framework\MockObject\Stub&PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        $this->stmtStub = $this->createStub(PDOStatement::class);
        $this->stmtStub->queryString = '';

        $this->pdoStub = $this->createStub(PDO::class);
        $this->pdoStub->method('prepare')->willReturn($this->stmtStub);

        $this->dbStub = $this->createStub(IDatabase::class);
        $this->dbStub->method('getConnection')->willReturn($this->pdoStub);
        $this->dbStub->method('begin')->willReturn($this->pdoStub);

        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $this->dbStub);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

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
        $dao->destroyCache($job);

        $this->assertIsBool($dao->destroyCache($job));
    }

    public function testGetBySegmentTranslationReturnsJobStruct(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $translation = new SegmentTranslationStruct();
        $translation->id_job = 1;
        $translation->id_segment = 150;

        $result = JobDao::getBySegmentTranslation($translation);

        $this->assertInstanceOf(JobStruct::class, $result);
        $this->assertSame(1, $result->id);
    }

    public function testGetSegmentsCountReturnsIntWhenResultPresent(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->total = '42';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = JobDao::getSegmentsCount(1, 'pass');

        $this->assertSame(42, $result);
    }

    public function testGetSegmentsCountReturnsZeroWhenTotalEmpty(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->total = null;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = JobDao::getSegmentsCount(1, 'pass');

        $this->assertSame(0, $result);
    }

    public function testGetSegmentsCountReturnsZeroWhenNoResult(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = JobDao::getSegmentsCount(1, 'pass');

        $this->assertSame(0, $result);
    }

    public function testGetOwnerUidReturnsUidWhenFound(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->uid = 99;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = JobDao::getOwnerUid(1, 'pass');

        $this->assertSame(99, $result);
    }

    public function testGetOwnerUidReturnsNullWhenEmpty(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = JobDao::getOwnerUid(1, 'pass');

        $this->assertNull($result);
    }

    public function testGetOwnerUidReturnsNullWhenUidNotSet(): void
    {
        $struct = new ShapelessConcreteStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = JobDao::getOwnerUid(1, 'pass');

        $this->assertNull($result);
    }

    public function testGetByIdAndPasswordReturnsJobStructWhenFound(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $result = JobDao::getByIdAndPassword(1, 'abc123');

        $this->assertInstanceOf(JobStruct::class, $result);
        $this->assertSame(1, $result->id);
    }

    public function testGetByIdAndPasswordReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = JobDao::getByIdAndPassword(999, 'nope');

        $this->assertNull($result);
    }

    public function testDestroyCacheByProjectIdDoesNotThrow(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new JobDao($this->dbStub);
        $result = $dao->destroyCacheByProjectId(10);

        $this->assertIsBool($result);
    }

    public function testGetByProjectIdReturnsArrayOfJobs(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $result = JobDao::getByProjectId(10);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(JobStruct::class, $result[0]);
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

    public function testGetByIdReturnsArrayOfJobs(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $result = JobDao::getById(1);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(JobStruct::class, $result[0]);
    }

    public function testGetByIdProjectAndIdJobReturnsArray(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);

        $result = JobDao::getByIdProjectAndIdJob(10, 1);

        $this->assertCount(1, $result);
    }

    public function testCreateFromStructReturnsJobWithId(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$job]);
        $this->pdoStub->method('lastInsertId')->willReturn('55');

        $result = JobDao::createFromStruct($job);

        $this->assertInstanceOf(JobStruct::class, $result);
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

    public function testUpdateForMergeUpdatesPasswordWhenProvided(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->dbStub->method('update')->willReturn(1);

        $result = JobDao::updateForMerge($job, 'newpass');

        $this->assertSame('newpass', $result->password);
    }

    public function testUpdateForMergeKeepsPasswordWhenEmpty(): void
    {
        $job = $this->makeJobStruct();
        $originalPassword = $job->password;

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->dbStub->method('update')->willReturn(1);

        $result = JobDao::updateForMerge($job, '');

        $this->assertSame($originalPassword, $result->password);
    }

    public function testDeleteOnMergeReturnsTrue(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $job = $this->makeJobStruct();
        $result = JobDao::deleteOnMerge($job);

        $this->assertTrue($result);
    }

    public function testDeleteOnMergeReturnsFalse(): void
    {
        $this->stmtStub->method('execute')->willReturn(false);

        $job = $this->makeJobStruct();
        $result = JobDao::deleteOnMerge($job);

        $this->assertFalse($result);
    }

    public function testGetFirstSegmentOfFilesInJobReturnsArray(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->id_file = 1;
        $struct->first_segment = 100;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $job = $this->makeJobStruct();
        $result = JobDao::getFirstSegmentOfFilesInJob($job);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $result[0]);
    }

    public function testUpdateAllJobsStatusesByProjectIdExecutes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->dbStub->method('update')->willReturn(1);

        JobDao::updateAllJobsStatusesByProjectId(10, 'cancelled');

        $this->assertTrue(true);
    }

    public function testSetJobCompleteReturnsRowCount(): void
    {
        $this->dbStub->method('update')->willReturn(1);

        $job = $this->makeJobStruct();
        $result = JobDao::setJobComplete($job);

        $this->assertSame(1, $result);
    }

    public function testUpdateJobStatusExecutes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->dbStub->method('update')->willReturn(1);

        $job = $this->makeJobStruct();
        JobDao::updateJobStatus($job, 'cancelled');

        $this->assertTrue(true);
    }

    public function testGetReviewedWordsCountGroupedByFilePartsReturnsArray(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->reviewed_words_count = '500';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = JobDao::getReviewedWordsCountGroupedByFileParts(1, 'pass', 2);

        $this->assertCount(1, $result);
    }

    public function testGetSegmentTranslationsCountReturnsTotalWhenPresent(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->total = '100';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = JobDao::getSegmentTranslationsCount([1, 2, 3]);

        $this->assertSame(100, $result);
    }

    public function testGetSegmentTranslationsCountReturnsNullWhenEmpty(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = JobDao::getSegmentTranslationsCount([1, 2]);

        $this->assertNull($result);
    }

    public function testGetSegmentTranslationsCountReturnsNullWhenTotalEmpty(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->total = null;

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = JobDao::getSegmentTranslationsCount([1, 2]);

        $this->assertNull($result);
    }

    public function testHasACustomPayableRateReturnsTrueWhenFound(): void
    {
        $struct = new ShapelessConcreteStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = JobDao::hasACustomPayableRate(1);

        $this->assertTrue($result);
    }

    public function testHasACustomPayableRateReturnsFalseWhenNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = JobDao::hasACustomPayableRate(1);

        $this->assertFalse($result);
    }
}
