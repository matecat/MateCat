<?php


namespace Matecat\Core\Model\RemoteFiles;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\RemoteFiles\RemoteFileDao;
use Model\RemoteFiles\RemoteFileStruct;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class RemoteFileDaoTest extends AbstractTest
{
    protected Database $database;
    protected \Predis\Client $flusher;
    protected int $idJob = 0;
    protected int $idFile = 0;
    protected int $idFile2 = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = obtainTestDatabase(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );

        $this->flusher = (new RedisHandler())->getConnection();
        $this->flusher->flushdb();

        $conn = $this->database->getConnection();

        $conn->exec(
            "INSERT INTO projects (id, password, id_customer, id_team, name, create_date, status_analysis) " .
            "VALUES (999900, 'test', 'test@remotefile.test', 1, 'RemoteFileDaoTest', NOW(), 'NEW')"
        );

        $conn->exec(
            "INSERT INTO jobs (id, password, id_project, job_first_segment, job_last_segment, source, target, create_date, status_owner) " .
            "VALUES (999900, 'testpass', 999900, 1, 1, 'en-US', 'it-IT', NOW(), 'active')"
        );
        $this->idJob = 999900;

        $conn->exec(
            "INSERT INTO files (id, id_project, filename, source_language, mime_type, sha1_original_file) " .
            "VALUES (999900, 999900, 'test1.xliff', 'en-US', 'application/xliff+xml', 'abc123')"
        );
        $this->idFile = 999900;

        $conn->exec(
            "INSERT INTO files (id, id_project, filename, source_language, mime_type, sha1_original_file) " .
            "VALUES (999901, 999900, 'test2.xliff', 'en-US', 'application/xliff+xml', 'abc456')"
        );
        $this->idFile2 = 999901;

        $conn->exec(
            "INSERT INTO files_job (id_job, id_file) VALUES (999900, 999900)"
        );
        $conn->exec(
            "INSERT INTO files_job (id_job, id_file) VALUES (999900, 999901)"
        );
    }

    protected function tearDown(): void
    {
        $conn = $this->database->getConnection();
        $conn->exec("DELETE FROM remote_files WHERE id_job = 999900");
        $conn->exec("DELETE FROM remote_files WHERE id_file IN (999900, 999901)");
        $conn->exec("DELETE FROM files_job WHERE id_job = 999900");
        $conn->exec("DELETE FROM files WHERE id IN (999900, 999901)");
        $conn->exec("DELETE FROM jobs WHERE id = 999900");
        $conn->exec("DELETE FROM projects WHERE id = 999900");

        $this->flusher->flushdb();
        parent::tearDown();
    }

    private function insertRemoteFile(int $idFile, int $idJob, string $remoteId, int $connectedServiceId, int $isOriginal = 0): void
    {
        $this->database->getConnection()->prepare(
            "INSERT INTO remote_files (id_file, id_job, remote_id, connected_service_id, is_original) VALUES (?, ?, ?, ?, ?)"
        )->execute([$idFile, $idJob, $remoteId, $connectedServiceId, $isOriginal]);
    }

    // ─── insert() ───

    #[Test]
    public function test_insert_creates_remote_file_record(): void
    {
        $dao = new RemoteFileDao($this->database);
        $dao->insert($this->idFile, $this->idJob, 'gdrive-abc', 42);

        $stmt = $this->database->getConnection()->prepare(
            "SELECT * FROM remote_files WHERE id_file = ? AND id_job = ? AND is_original = 0"
        );
        $stmt->execute([$this->idFile, $this->idJob]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertEquals('gdrive-abc', $row['remote_id']);
        $this->assertEquals(42, (int)$row['connected_service_id']);
        $this->assertEquals(0, (int)$row['is_original']);
    }

    #[Test]
    public function test_insert_with_is_original_flag(): void
    {
        $dao = new RemoteFileDao($this->database);
        $dao->insert($this->idFile, $this->idJob, 'gdrive-orig', 42, 1);

        $stmt = $this->database->getConnection()->prepare(
            "SELECT * FROM remote_files WHERE id_file = ? AND id_job = ? AND is_original = 1"
        );
        $stmt->execute([$this->idFile, $this->idJob]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertEquals(1, (int)$row['is_original']);
    }

    // ─── getByJobId() ───

    #[Test]
    public function test_get_by_job_id_returns_non_original_files(): void
    {
        $this->insertRemoteFile($this->idFile, $this->idJob, 'remote-1', 10, 0);
        $this->insertRemoteFile($this->idFile2, $this->idJob, 'remote-2', 10, 1);

        $dao = new RemoteFileDao($this->database);
        $results = $dao->getByJobId($this->idJob);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(RemoteFileStruct::class, $results[0]);
        $this->assertEquals('remote-1', $results[0]->remote_id);
    }

    #[Test]
    public function test_get_by_job_id_returns_empty_for_unknown_job(): void
    {
        $dao = new RemoteFileDao($this->database);
        $results = $dao->getByJobId(PHP_INT_MAX);
        $this->assertSame([], $results);
    }

    // ─── getOriginalsByJobId() ───

    #[Test]
    public function test_get_originals_by_job_id_returns_only_originals(): void
    {
        $this->insertRemoteFile($this->idFile, $this->idJob, 'remote-non-orig', 10, 0);
        $this->insertRemoteFile($this->idFile, $this->idJob, 'remote-orig-1', 10, 1);
        $this->insertRemoteFile($this->idFile2, $this->idJob, 'remote-orig-2', 10, 1);

        $dao = new RemoteFileDao($this->database);
        $results = $dao->getOriginalsByJobId($this->idJob);

        $this->assertCount(2, $results);
        foreach ($results as $r) {
            $this->assertInstanceOf(RemoteFileStruct::class, $r);
            $this->assertEquals(1, (int)$r->is_original);
        }
    }

    #[Test]
    public function test_get_originals_by_job_id_returns_empty_when_none(): void
    {
        $this->insertRemoteFile($this->idFile, $this->idJob, 'non-orig', 10, 0);

        $dao = new RemoteFileDao($this->database);
        $results = $dao->getOriginalsByJobId($this->idJob);
        $this->assertSame([], $results);
    }

    // ─── getByFileId() ───

    #[Test]
    public function test_get_by_file_id_returns_non_original_by_default(): void
    {
        $this->insertRemoteFile($this->idFile, $this->idJob, 'r-non', 10, 0);
        $this->insertRemoteFile($this->idFile, $this->idJob, 'r-orig', 10, 1);

        $dao = new RemoteFileDao($this->database);
        $results = $dao->getByFileId($this->idFile);

        $this->assertCount(1, $results);
        $this->assertEquals('r-non', $results[0]->remote_id);
    }

    #[Test]
    public function test_get_by_file_id_with_is_original_flag(): void
    {
        $this->insertRemoteFile($this->idFile, $this->idJob, 'r-orig', 10, 1);

        $dao = new RemoteFileDao($this->database);
        $results = $dao->getByFileId($this->idFile, 1);

        $this->assertCount(1, $results);
        $this->assertEquals('r-orig', $results[0]->remote_id);
    }

    // ─── getByFileAndJob() ───

    #[Test]
    public function test_get_by_file_and_job_returns_struct(): void
    {
        $this->insertRemoteFile($this->idFile, $this->idJob, 'file-job-match', 10, 0);

        $dao = new RemoteFileDao($this->database);
        $result = $dao->getByFileAndJob($this->idFile, $this->idJob);

        $this->assertInstanceOf(RemoteFileStruct::class, $result);
        $this->assertEquals('file-job-match', $result->remote_id);
    }

    #[Test]
    public function test_get_by_file_and_job_returns_null_when_not_found(): void
    {
        $dao = new RemoteFileDao($this->database);
        $result = $dao->getByFileAndJob(PHP_INT_MAX, PHP_INT_MAX);
        $this->assertNull($result);
    }

    #[Test]
    public function test_get_by_file_and_job_ignores_originals(): void
    {
        $this->insertRemoteFile($this->idFile, $this->idJob, 'orig-only', 10, 1);

        $dao = new RemoteFileDao($this->database);
        $result = $dao->getByFileAndJob($this->idFile, $this->idJob);
        $this->assertNull($result);
    }

    // ─── jobHasRemoteFiles() ───

    #[Test]
    public function test_job_has_remote_files_returns_true_when_present(): void
    {
        $this->insertRemoteFile($this->idFile, $this->idJob, 'exists', 10, 0);

        $dao = new RemoteFileDao($this->database);
        $this->assertTrue($dao->jobHasRemoteFiles($this->idJob));
    }

    #[Test]
    public function test_job_has_remote_files_returns_false_when_empty(): void
    {
        $dao = new RemoteFileDao($this->database);
        $this->assertFalse($dao->jobHasRemoteFiles($this->idJob));
    }

    #[Test]
    public function test_job_has_remote_files_ignores_originals(): void
    {
        $this->insertRemoteFile($this->idFile, $this->idJob, 'orig', 10, 1);

        $dao = new RemoteFileDao($this->database);
        $this->assertFalse($dao->jobHasRemoteFiles($this->idJob));
    }
}
