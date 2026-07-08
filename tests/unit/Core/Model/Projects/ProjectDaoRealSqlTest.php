<?php

namespace Matecat\Core\Model\Projects;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\RemoteFiles\RemoteFileServiceNameStruct;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use Utils\Constants\ProjectStatus;

/**
 * Real-SQL coverage for Model\Projects\ProjectDao (plan dao-realsql-90.md, T5 — verify ProjectDao).
 *
 * Every public SQL method is invoked DIRECTLY against the live schema and asserted on real
 * returned data (DoD b). The DAO is constructed with the single per-test connection (C-2);
 * methods that resolve their connection via Database::obtain()->getConnection() hit the same PDO
 * handle the trait seeds. NO wrapping transaction (C-1). All builder rows (projects, jobs, files,
 * segments, segment_translations, files_job, remote_files, connected_services, qa_chunk_reviews)
 * are tracked so the whole-table COUNT(*) residue gate over every declared dep returns to baseline
 * (A-1/A-2/AC-1). No assertion on absolute generated ids (M-3).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class ProjectDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = [
        'segment_translations',
        'segments',
        'remote_files',
        'connected_services',
        'qa_chunk_reviews',
        'files',
        'jobs',
        'projects',
    ];

    private ProjectDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new ProjectDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    /** @return array{id:int,password:string,name:string,id_customer:string} */
    private function project(array $overrides = []): array
    {
        return $this->fixtures->makeProjectDetailed($overrides);
    }

    // ---- findById / exists -----------------------------------------------------------------------

    public function testFindByIdReturnsStruct(): void
    {
        $p = $this->project(['name' => 'find me']);

        $struct = $this->dao->findById($p['id']);

        self::assertInstanceOf(ProjectStruct::class, $struct);
        self::assertSame('find me', $struct->name);
        self::assertSame($p['id'], $struct->id);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        self::assertNull($this->dao->findById($this->fixtures->nextAssignableId()));
    }

    public function testExistsTrueAndFalse(): void
    {
        $p = $this->project();

        self::assertTrue($this->dao->exists($p['id']));
        self::assertFalse($this->dao->exists($this->fixtures->nextAssignableId()));
    }

    // ---- findByIdAndPassword / cache destroy -----------------------------------------------------

    public function testFindByIdAndPasswordReturnsStruct(): void
    {
        $p = $this->project();

        $struct = $this->dao->findByIdAndPassword($p['id'], $p['password']);

        self::assertInstanceOf(ProjectStruct::class, $struct);
        self::assertSame($p['id'], $struct->id);
    }

    public function testFindByIdAndPasswordThrowsWhenNotFound(): void
    {
        $p = $this->project();

        $this->expectException(NotFoundException::class);
        $this->dao->findByIdAndPassword($p['id'], 'wrong-password');
    }

    public function testDestroyCacheByIdAndPassword(): void
    {
        $p = $this->project();
        $this->dao->findByIdAndPassword($p['id'], $p['password'], 3600); // prime

        self::assertTrue($this->dao->destroyCacheByIdAndPassword($p['id'], $p['password']));
    }

    public function testDestroyProjectPasswordCache(): void
    {
        $p = $this->project();
        $this->dao->findByIdAndPassword($p['id'], $p['password'], 3600); // prime

        self::assertTrue($this->dao->destroyProjectPasswordCache($p['id'], $p['password']));
    }

    // ---- findByIdCustomer ------------------------------------------------------------------------

    public function testFindByIdCustomerReturnsProjects(): void
    {
        $customer = 'cust_' . bin2hex(random_bytes(6)) . '@example.test';
        $p1 = $this->project(['id_customer' => $customer]);
        $p2 = $this->project(['id_customer' => $customer]);

        $rows = $this->dao->findByIdCustomer($customer);

        self::assertCount(2, $rows);
        $ids = array_map(static fn(ProjectStruct $p): int => $p->id, $rows);
        self::assertContains($p1['id'], $ids);
        self::assertContains($p2['id'], $ids);
    }

    // ---- getByIdList -----------------------------------------------------------------------------

    public function testGetByIdListReturnsMatchingProjects(): void
    {
        $p1 = $this->project();
        $p2 = $this->project();

        $rows = $this->dao->getByIdList([$p1['id'], $p2['id']]);

        self::assertCount(2, $rows);
    }

    public function testGetByIdListEmptyReturnsEmpty(): void
    {
        self::assertSame([], $this->dao->getByIdList([]));
    }

    // ---- findByTeamId / getTotalCountByTeamId ----------------------------------------------------

    public function testFindByTeamIdReturnsTeamProjects(): void
    {
        $teamId = $this->fixtures->nextAssignableId();
        $this->project(['id_team' => $teamId, 'status_analysis' => ProjectStatus::STATUS_DONE]);
        $this->project(['id_team' => $teamId, 'status_analysis' => ProjectStatus::STATUS_DONE]);

        $rows = $this->dao->findByTeamId($teamId);

        self::assertCount(2, $rows);
    }

    public function testFindByTeamIdWithSearchAndLimitFilters(): void
    {
        $teamId = $this->fixtures->nextAssignableId();
        $a = $this->project(['id_team' => $teamId, 'name' => 'alpha', 'status_analysis' => ProjectStatus::STATUS_DONE]);
        $this->project(['id_team' => $teamId, 'name' => 'beta', 'status_analysis' => ProjectStatus::STATUS_DONE]);

        $byName = $this->dao->findByTeamId($teamId, ['search' => ['name' => 'alpha']]);
        self::assertCount(1, $byName);
        self::assertSame('alpha', $byName[0]->name);

        $byId = $this->dao->findByTeamId($teamId, ['search' => ['id' => $a['id']]]);
        self::assertCount(1, $byId);

        $limited = $this->dao->findByTeamId($teamId, ['limit' => 1, 'offset' => 0]);
        self::assertCount(1, $limited);
    }

    public function testGetTotalCountByTeamId(): void
    {
        $teamId = $this->fixtures->nextAssignableId();
        $this->project(['id_team' => $teamId, 'status_analysis' => ProjectStatus::STATUS_DONE]);
        $this->project(['id_team' => $teamId, 'status_analysis' => ProjectStatus::STATUS_DONE]);

        self::assertSame(2, $this->dao->getTotalCountByTeamId($teamId));

        $named = $this->project(['id_team' => $teamId, 'name' => 'unique-name', 'status_analysis' => ProjectStatus::STATUS_DONE]);
        self::assertSame(1, $this->dao->getTotalCountByTeamId($teamId, ['search' => ['name' => 'unique-name']]));
        self::assertSame(1, $this->dao->getTotalCountByTeamId($teamId, ['search' => ['id' => $named['id']]]));
    }

    // ---- updateField / changeName / changePassword / changeProjectStatus -------------------------

    public function testUpdateFieldPersists(): void
    {
        $p = $this->project(['name' => 'old']);
        $struct = $this->dao->findById($p['id']);

        $updated = $this->dao->updateField($struct, 'name', 'newname');

        self::assertSame('newname', $updated->name);
        self::assertSame('newname', $this->dao->findById($p['id'])->name);
    }

    public function testChangeName(): void
    {
        $p = $this->project(['name' => 'old']);
        $struct = $this->dao->findById($p['id']);

        $this->dao->changeName($struct, 'renamed');

        self::assertSame('renamed', $this->dao->findById($p['id'])->name);
    }

    public function testChangePassword(): void
    {
        $p = $this->project();
        $struct = $this->dao->findById($p['id']);

        $this->dao->changePassword($struct, 'newpass123');

        self::assertNotNull($this->dao->findByIdAndPassword($p['id'], 'newpass123'));
    }

    public function testChangeProjectStatus(): void
    {
        $p = $this->project(['status_analysis' => ProjectStatus::STATUS_NEW]);

        $affected = $this->dao->changeProjectStatus($p['id'], ProjectStatus::STATUS_DONE);

        self::assertSame(1, $affected);
        self::assertSame(ProjectStatus::STATUS_DONE, $this->dao->findById($p['id'])->status_analysis);
    }

    public function testChangeProjectStatusIfNotDoneWritesWhenNotDone(): void
    {
        $p = $this->project(['status_analysis' => ProjectStatus::STATUS_NEW]);

        $affected = $this->dao->changeProjectStatusIfNotDone($p['id'], ProjectStatus::STATUS_FAST_OK);

        self::assertSame(1, $affected);
        self::assertSame(ProjectStatus::STATUS_FAST_OK, $this->dao->findById($p['id'])->status_analysis);
    }

    public function testChangeProjectStatusIfNotDoneNeverOverwritesDone(): void
    {
        // The atomic guard: a concurrent TM-worker completion sets DONE; a late FAST_OK/BUSY write
        // from the fast daemon must NOT overwrite it. 0 affected rows signals the skip.
        $p = $this->project(['status_analysis' => ProjectStatus::STATUS_DONE]);

        $affected = $this->dao->changeProjectStatusIfNotDone($p['id'], ProjectStatus::STATUS_FAST_OK);

        self::assertSame(0, $affected, 'must not overwrite a DONE project');
        self::assertSame(ProjectStatus::STATUS_DONE, $this->dao->findById($p['id'])->status_analysis);
    }

    public function testUpdateAnalysisStatus(): void
    {
        $p = $this->project(['status_analysis' => ProjectStatus::STATUS_NEW]);

        $ok = $this->dao->updateAnalysisStatus($p['id'], ProjectStatus::STATUS_DONE, 123);

        self::assertTrue($ok);
        $struct = $this->dao->findById($p['id']);
        self::assertSame(ProjectStatus::STATUS_DONE, $struct->status_analysis);
        self::assertSame(123.0, $struct->standard_analysis_wc);
    }

    // ---- unassignProjects / massiveSelfAssignment ------------------------------------------------

    public function testUnassignProjects(): void
    {
        $teamId = $this->fixtures->nextAssignableId();
        $uid = $this->fixtures->nextAssignableId();
        $this->project(['id_team' => $teamId, 'id_assignee' => $uid]);
        $this->project(['id_team' => $teamId, 'id_assignee' => $uid]);

        $team = new TeamStruct();
        $team->id = $teamId;
        $user = new UserStruct();
        $user->uid = $uid;

        self::assertSame(2, $this->dao->unassignProjects($team, $user));
    }

    public function testMassiveSelfAssignment(): void
    {
        $teamId = $this->fixtures->nextAssignableId();
        $personalTeamId = $this->fixtures->nextAssignableId();
        $uid = $this->fixtures->nextAssignableId();
        $this->project(['id_team' => $teamId]);

        $team = new TeamStruct();
        $team->id = $teamId;
        $personal = new TeamStruct();
        $personal->id = $personalTeamId;
        $user = new UserStruct();
        $user->uid = $uid;

        self::assertSame(1, $this->dao->massiveSelfAssignment($team, $user, $personal));
    }

    // ---- job-related: getJobIds / findByJobId / getPasswordsMap / getProjectAndJobData -----------

    public function testGetJobIds(): void
    {
        $p = $this->project();
        $j1 = $this->fixtures->makeJob($p['id']);
        $j2 = $this->fixtures->makeJob($p['id']);

        $ids = $this->dao->getJobIds($p['id']);

        self::assertCount(2, $ids);
        $flat = array_map(static fn(array $r): int => (int)$r['id'], $ids);
        self::assertContains($j1['id'], $flat);
        self::assertContains($j2['id'], $flat);
    }

    public function testFindByJobId(): void
    {
        $p = $this->project();
        $j = $this->fixtures->makeJob($p['id']);

        $struct = $this->dao->findByJobId($j['id']);

        self::assertInstanceOf(ProjectStruct::class, $struct);
        self::assertSame($p['id'], $struct->id);
    }

    public function testGetPasswordsMap(): void
    {
        $p = $this->project();
        $j = $this->fixtures->makeJob($p['id']);
        $this->fixtures->makeQaChunkReview($p['id'], $j['id'], $j['password'], ['source_page' => 2]);
        $this->fixtures->makeQaChunkReview($p['id'], $j['id'], $j['password'], ['source_page' => 3]);

        $map = $this->dao->getPasswordsMap($p['id']);

        self::assertCount(1, $map);
        self::assertSame($j['id'], (int)$map[0]['id_job']);
        self::assertSame($j['password'], $map[0]['t_password']);
        self::assertNotNull($map[0]['r_password']);
        self::assertNotNull($map[0]['r2_password']);
    }

    public function testGetProjectAndJobData(): void
    {
        $p = $this->project();
        $j = $this->fixtures->makeJob($p['id']);

        $rows = $this->dao->getProjectAndJobData($p['id']);

        self::assertCount(1, $rows);
        self::assertSame($p['id'], (int)$rows[0]['pid']);
        self::assertSame($j['id'], (int)$rows[0]['jid']);
    }

    // ---- getProjectData / destroyCacheForProjectData (deep join) ---------------------------------

    public function testGetProjectDataReturnsRows(): void
    {
        $p = $this->project();
        $f = $this->fixtures->makeFile($p['id']);
        // create the segment first, then bracket the job's first/last segment around its real id
        // (segment ids are AUTO_INCREMENT; the deep query filters s.id BETWEEN first AND last).
        $seg = $this->fixtures->makeSegment($f['id']);
        $j = $this->fixtures->makeJob($p['id'], ['job_first_segment' => $seg['id'], 'job_last_segment' => $seg['id']]);
        $this->fixtures->makeFilesJob($j['id'], $f['id']);
        $this->fixtures->makeSegmentTranslation($seg['id'], $j['id']);

        $rows = $this->dao->getProjectData($p['id']);

        self::assertNotEmpty($rows);
        self::assertInstanceOf(ShapelessConcreteStruct::class, $rows[0]);
        self::assertSame($p['name'], $rows[0]['name']);
    }

    public function testGetProjectDataWithJobAndPasswordFilters(): void
    {
        $p = $this->project();
        $f = $this->fixtures->makeFile($p['id']);
        $seg = $this->fixtures->makeSegment($f['id']);
        $j = $this->fixtures->makeJob($p['id'], ['job_first_segment' => $seg['id'], 'job_last_segment' => $seg['id']]);
        $this->fixtures->makeFilesJob($j['id'], $f['id']);
        $this->fixtures->makeSegmentTranslation($seg['id'], $j['id']);

        $rows = $this->dao->getProjectData($p['id'], $p['password'], $j['id'], $j['password']);

        self::assertNotEmpty($rows);
    }

    public function testDestroyCacheForProjectData(): void
    {
        $p = $this->project();
        $f = $this->fixtures->makeFile($p['id']);
        $seg = $this->fixtures->makeSegment($f['id']);
        $j = $this->fixtures->makeJob($p['id'], ['job_first_segment' => $seg['id'], 'job_last_segment' => $seg['id']]);
        $this->fixtures->makeFilesJob($j['id'], $f['id']);
        $this->fixtures->makeSegmentTranslation($seg['id'], $j['id']);
        $this->dao->getProjectData($p['id']); // prime

        self::assertIsBool($this->dao->destroyCacheForProjectData($p['id']));
    }

    // ---- remote files: getRemoteFileServiceName / isGDriveProject --------------------------------

    public function testGetRemoteFileServiceName(): void
    {
        $p = $this->project();
        $j = $this->fixtures->makeJob($p['id']);
        $f = $this->fixtures->makeFile($p['id']);
        $cs = $this->fixtures->makeConnectedService($this->fixtures->nextAssignableId(), 'gdrive');
        $this->fixtures->makeRemoteFile($f['id'], $j['id'], $cs['id'], true);

        $rows = $this->dao->getRemoteFileServiceName([$p['id']]);

        self::assertCount(1, $rows);
        self::assertInstanceOf(RemoteFileServiceNameStruct::class, $rows[0]);
        self::assertSame('gdrive', $rows[0]->service);
    }

    public function testIsGDriveProjectTrue(): void
    {
        $p = $this->project();
        $j = $this->fixtures->makeJob($p['id']);
        $f = $this->fixtures->makeFile($p['id']);
        $this->fixtures->makeRemoteFile($f['id'], $j['id'], null, true);

        self::assertTrue($this->dao->isGDriveProject($p['id']));
    }

    public function testIsGDriveProjectFalse(): void
    {
        $p = $this->project();
        $this->fixtures->makeFile($p['id']);

        self::assertFalse($this->dao->isGDriveProject($p['id']));
    }
}
