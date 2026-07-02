<?php

namespace Matecat\Core\DAO\TestFileDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Files\FileDao;
use Model\Files\FileStruct;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-SQL coverage for FileDao (campaign dao-realsql-90).
 *
 * Every public method runs against the live unittest DB on the single per-test connection.
 * A project + a few complete files rows (and files_job links) drive the reads; the mutating
 * methods build isolated rows. The residue gate asserts whole-table COUNT(*) is unchanged.
 *
 * NOTE: FileStruct types mime_type / sha1_original_file as non-null strings, but the schema and
 * TestFixtureBuilder::makeFile() leave them NULL — FETCH_CLASS hydration would then TypeError.
 * makeFullFile() inserts every column the struct reads so getByJobId/getByProjectId/getById
 * hydrate cleanly.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class FileDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private FileDao $dao;
    private int $idProject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startRealSql(['files', 'files_job', 'projects']);

        $this->idProject = $this->fixtures->makeProject()['id'];
        $this->dao = new FileDao($this->realSqlDb());
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    /** Insert a fully-populated files row (all FileStruct-typed columns) and track it. */
    private function makeFullFile(int $idProject, string $filename = 'rsq_file.txt'): int
    {
        $conn = $this->realSqlDb()->getConnection();
        $stmt = $conn->prepare(
            "INSERT INTO files (id_project, filename, source_language, mime_type, sha1_original_file, is_converted) "
            . "VALUES (:p, :fn, 'en-US', 'text/plain', :sha, 1)"
        );
        $stmt->execute(['p' => $idProject, 'fn' => $filename, 'sha' => sha1($filename)]);
        $id = (int)$conn->lastInsertId();
        $this->fixtures->trackExisting('files', ['id' => $id]);

        return $id;
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertDaoUsesTestConnection($this->dao);
    }

    #[Test]
    public function getById_hit_and_miss(): void
    {
        $idFile = $this->makeFullFile($this->idProject, 'by_id.txt');

        $file = $this->dao->getById($idFile);
        $this->assertInstanceOf(FileStruct::class, $file);
        $this->assertSame($idFile, $file->id);
        $this->assertSame('by_id.txt', $file->filename);
        $this->assertSame($this->idProject, $file->id_project);

        $this->assertNull($this->dao->getById(self::ASSIGNABLE_ID_FLOOR + 999001));
    }

    #[Test]
    public function getByProjectId_returns_project_files_and_empty_for_unknown(): void
    {
        $this->makeFullFile($this->idProject, 'p1.txt');
        $this->makeFullFile($this->idProject, 'p2.txt');

        $files = $this->dao->getByProjectId($this->idProject);
        $this->assertCount(2, $files);
        $this->assertContainsOnlyInstancesOf(FileStruct::class, $files);

        $this->assertSame([], $this->dao->getByProjectId(self::ASSIGNABLE_ID_FLOOR + 999002));
    }

    #[Test]
    public function getByJobId_joins_files_job(): void
    {
        $idFile = $this->makeFullFile($this->idProject, 'job_file.txt');
        $job = $this->fixtures->makeJob($this->idProject);
        $this->fixtures->makeFilesJob($job['id'], $idFile);

        $files = $this->dao->getByJobId($job['id']);
        $this->assertCount(1, $files);
        $this->assertSame($idFile, $files[0]->id);

        $this->assertSame([], $this->dao->getByJobId(self::ASSIGNABLE_ID_FLOOR + 999003));
    }

    #[Test]
    public function isFileInProject_hit_and_miss(): void
    {
        $idFile = $this->makeFullFile($this->idProject, 'in_project.txt');

        $this->assertSame(1, $this->dao->isFileInProject($idFile, $this->idProject));
        $this->assertSame(0, $this->dao->isFileInProject($idFile, self::ASSIGNABLE_ID_FLOOR + 999004));
    }

    #[Test]
    public function updateField_changes_a_column(): void
    {
        $idFile = $this->makeFullFile($this->idProject, 'before.txt');
        $file = $this->dao->getById($idFile);

        $this->assertTrue($this->dao->updateField($file, 'filename', 'after.txt'));

        $reloaded = $this->dao->getById($idFile, 0);
        $this->assertSame('after.txt', $reloaded->filename);
    }

    #[Test]
    public function deleteFailedProjectFiles_empty_is_noop_and_deletes_listed_ids(): void
    {
        // empty arg short-circuits to 0 with no query.
        $this->assertSame(0, $this->dao->deleteFailedProjectFiles([]));

        $a = $this->makeFullFile($this->idProject, 'del_a.txt');
        $b = $this->makeFullFile($this->idProject, 'del_b.txt');

        // multi-id exercises the str_repeat('?,', n-1) placeholder branch.
        $deleted = $this->dao->deleteFailedProjectFiles([$a, $b]);
        $this->assertSame(2, $deleted);

        $this->assertNull($this->dao->getById($a));
        $this->assertNull($this->dao->getById($b));
    }

    #[Test]
    public function insertFilesJob_links_file_to_job(): void
    {
        $idFile = $this->makeFullFile($this->idProject, 'link.txt');
        $job = $this->fixtures->makeJob($this->idProject);

        $this->dao->insertFilesJob($job['id'], $idFile);
        $this->fixtures->trackExisting('files_job', ['id_job' => $job['id'], 'id_file' => $idFile]);

        $files = $this->dao->getByJobId($job['id']);
        $this->assertCount(1, $files);
        $this->assertSame($idFile, $files[0]->id);
    }
}
