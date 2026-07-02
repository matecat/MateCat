<?php

namespace Matecat\Core\DAO\TestJobsTranslatorsDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Jobs\JobStruct;
use Model\Translators\JobsTranslatorsDao;
use Model\Translators\JobsTranslatorsStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-SQL coverage for JobsTranslatorsDao (campaign dao-realsql-90).
 *
 * Both public methods (findByJobsStruct, destroyCacheByJobStruct) are exercised against the real
 * unittest DB across BOTH query branches (with / without job password). jobs_translators has a
 * composite PK (id_job, job_password) and no AUTO_INCREMENT, so rows are inserted directly under
 * an assignable id_job >= ASSIGNABLE_ID_FLOOR and cleaned id_job-scoped; the residue gate asserts
 * whole-table COUNT(*) is unchanged (DoD c).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class JobsTranslatorsDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private JobsTranslatorsDao $dao;
    private int $idJob;
    private string $passwordA;
    private string $passwordB;

    protected function realSqlTableDeps(): array
    {
        return ['jobs_translators'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();

        $this->idJob = self::ASSIGNABLE_ID_FLOOR + 4501;
        $this->passwordA = 'jtp_' . bin2hex(random_bytes(4));
        $this->passwordB = 'jtp_' . bin2hex(random_bytes(4));

        $this->insertRow($this->passwordA);
        $this->insertRow($this->passwordB);

        $this->dao = new JobsTranslatorsDao($this->realSqlDb);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown(function (): void {
            $this->realSqlDb->getConnection()
                ->exec("DELETE FROM jobs_translators WHERE id_job = {$this->idJob}");
        });
        parent::tearDown();
    }

    private function insertRow(string $password): void
    {
        $stmt = $this->realSqlDb->getConnection()->prepare(
            "INSERT INTO jobs_translators (id_job, job_password, added_by, email, source, target) "
            . "VALUES (:id_job, :pwd, :added_by, :email, :source, :target)"
        );
        $stmt->execute([
            'id_job'   => $this->idJob,
            'pwd'      => $password,
            'added_by' => 'rsq_tester',
            'email'    => 'rsq_' . bin2hex(random_bytes(4)) . '@example.test',
            'source'   => 'en-US',
            'target'   => 'it-IT',
        ]);
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertInjectedConnection($this->dao);
    }

    #[Test]
    public function findByJobsStruct_with_password_returns_only_the_matching_row(): void
    {
        $job = new JobStruct();
        $job->id = $this->idJob;
        $job->password = $this->passwordA;

        $rows = $this->dao->findByJobsStruct($job);

        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
        $this->assertInstanceOf(JobsTranslatorsStruct::class, $rows[0]);
        $this->assertSame($this->idJob, (int)$rows[0]->id_job);
        $this->assertSame($this->passwordA, $rows[0]->job_password);
    }

    #[Test]
    public function findByJobsStruct_without_password_returns_all_rows_for_the_job(): void
    {
        $job = new JobStruct();
        $job->id = $this->idJob;
        $job->password = '';

        $rows = $this->dao->findByJobsStruct($job);

        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);
        $passwords = array_map(fn(JobsTranslatorsStruct $s) => $s->job_password, $rows);
        $this->assertContains($this->passwordA, $passwords);
        $this->assertContains($this->passwordB, $passwords);
    }

    #[Test]
    public function findByJobsStruct_returns_empty_when_no_row_matches(): void
    {
        $job = new JobStruct();
        $job->id = $this->idJob;
        $job->password = 'does-not-exist';

        $rows = $this->dao->findByJobsStruct($job);

        $this->assertIsArray($rows);
        $this->assertCount(0, $rows);
    }

    #[Test]
    public function destroyCacheByJobStruct_returns_true_on_both_branches(): void
    {
        // Caching only happens when a TTL is set; prime the cache, then destroy it — both
        // branches of the password check.
        $this->dao->setCacheTTL(60);

        $withPassword = new JobStruct();
        $withPassword->id = $this->idJob;
        $withPassword->password = $this->passwordA;
        $this->dao->findByJobsStruct($withPassword);
        $this->assertTrue($this->dao->destroyCacheByJobStruct($withPassword));

        $withoutPassword = new JobStruct();
        $withoutPassword->id = $this->idJob;
        $withoutPassword->password = '';
        $this->dao->findByJobsStruct($withoutPassword);
        $this->assertTrue($this->dao->destroyCacheByJobStruct($withoutPassword));
    }
}
