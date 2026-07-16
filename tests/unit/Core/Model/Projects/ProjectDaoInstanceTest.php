<?php

namespace Matecat\Core\Model\Projects;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class ProjectDaoInstanceTest extends AbstractTest
{
    private const int TEST_UID = 88890001;
    private const int TEST_TEAM_ID = 88890002;

    private const int PROJECT_ID_1 = 88890011;
    private const int PROJECT_ID_2 = 88890012;
    private const int PROJECT_ID_3 = 88890013;
    private const int PROJECT_ID_4 = 88890014;

    private const int JOB_ID_1 = 88890021;
    private const int JOB_ID_2 = 88890022;
    private const int JOB_ID_3 = 88890023;

    private const int FILE_ID_1 = 88890031;
    private const int FILE_ID_2 = 88890032;

    private const int SEGMENT_ID_1 = 88890041;
    private const int SEGMENT_ID_2 = 88890042;
    private const int SEGMENT_ID_3 = 88890043;

    private Database $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = obtainTestDatabase(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );

        $this->deleteFixtureRows();
        $this->insertFixtureRows();
    }

    protected function tearDown(): void
    {
        $this->deleteFixtureRows();

        $flusher = (new RedisHandler())->getConnection();
        $flusher->flushdb();

        parent::tearDown();
    }

    private function deleteFixtureRows(): void
    {
        $conn = $this->database->getConnection();

        $segmentIds = implode(',', [self::SEGMENT_ID_1, self::SEGMENT_ID_2, self::SEGMENT_ID_3]);
        $jobIds = implode(',', [self::JOB_ID_1, self::JOB_ID_2, self::JOB_ID_3]);
        $fileIds = implode(',', [self::FILE_ID_1, self::FILE_ID_2]);
        $projectIds = implode(',', [self::PROJECT_ID_1, self::PROJECT_ID_2, self::PROJECT_ID_3, self::PROJECT_ID_4]);

        $conn->exec("DELETE FROM segment_translations WHERE id_segment IN ($segmentIds)");
        $conn->exec("DELETE FROM segments WHERE id IN ($segmentIds)");
        $conn->exec("DELETE FROM files WHERE id IN ($fileIds)");
        $conn->exec("DELETE FROM jobs WHERE id IN ($jobIds)");
        $conn->exec("DELETE FROM projects WHERE id IN ($projectIds)");
        $conn->exec("DELETE FROM teams WHERE id = " . self::TEST_TEAM_ID);
        $conn->exec("DELETE FROM users WHERE uid = " . self::TEST_UID);
    }

    private function insertFixtureRows(): void
    {
        $conn = $this->database->getConnection();

        $conn->exec(
            "INSERT INTO users (uid, email, salt, pass, create_date, first_name, last_name)
             VALUES (" . self::TEST_UID . ", 'project-dao-inst@test.local', 'x', 'x', '2024-01-01 00:00:00', 'Project', 'DaoInst')"
        );

        $conn->exec(
            "INSERT INTO teams (id, name, created_by, type)
             VALUES (" . self::TEST_TEAM_ID . ", 'ProjectDaoInst Team', " . self::TEST_UID . ", 'general')"
        );

        $conn->exec(
            "INSERT INTO projects (id, password, id_customer, name, create_date, id_team, status_analysis, standard_analysis_wc, fast_analysis_wc, tm_analysis_wc)
             VALUES
             (" . self::PROJECT_ID_1 . ", 'ppass-1', 'customer-a', 'Project Alpha', '2024-01-01 00:00:00', " . self::TEST_TEAM_ID . ", 'NEW', 0, 0, 0),
             (" . self::PROJECT_ID_2 . ", 'ppass-2', 'customer-a', 'Project Beta', '2024-01-01 00:00:00', " . self::TEST_TEAM_ID . ", 'DONE', 0, 0, 0),
             (" . self::PROJECT_ID_3 . ", 'ppass-3', 'customer-a', 'Project Hidden', '2024-01-01 00:00:00', " . self::TEST_TEAM_ID . ", 'NOT_TO_ANALYZE', 0, 0, 0),
             (" . self::PROJECT_ID_4 . ", 'ppass-4', 'customer-b', 'Project Delta', '2024-01-01 00:00:00', " . self::TEST_TEAM_ID . ", 'BUSY', 0, 0, 0)"
        );

        $conn->exec(
            "INSERT INTO jobs (id, password, id_project, source, target, payable_rates, status_owner, subject, job_first_segment, job_last_segment, create_date, owner, status, disabled, tm_keys)
             VALUES
             (" . self::JOB_ID_1 . ", 'jpass-1', " . self::PROJECT_ID_1 . ", 'en-US', 'it-IT', '[]', 'active', 'general', " . self::SEGMENT_ID_1 . ", " . self::SEGMENT_ID_2 . ", '2024-01-01 00:00:00', 'project-dao-inst@test.local', 'active', 0, '[]'),
             (" . self::JOB_ID_2 . ", 'jpass-2', " . self::PROJECT_ID_1 . ", 'en-US', 'fr-FR', '[]', 'active', 'general', " . self::SEGMENT_ID_3 . ", " . self::SEGMENT_ID_3 . ", '2024-01-01 00:00:00', 'project-dao-inst@test.local', 'active', 0, '[]'),
             (" . self::JOB_ID_3 . ", 'jpass-3', " . self::PROJECT_ID_2 . ", 'en-US', 'de-DE', '[]', 'active', 'general', " . self::SEGMENT_ID_3 . ", " . self::SEGMENT_ID_3 . ", '2024-01-01 00:00:00', 'project-dao-inst@test.local', 'active', 0, '[]')"
        );

        $conn->exec(
            "INSERT INTO files (id, id_project, filename, source_language, mime_type)
             VALUES
             (" . self::FILE_ID_1 . ", " . self::PROJECT_ID_1 . ", 'alpha-1.xliff', 'en-US', 'application/xliff+xml'),
             (" . self::FILE_ID_2 . ", " . self::PROJECT_ID_1 . ", 'alpha-2.xliff', 'en-US', 'application/xliff+xml')"
        );

        $conn->exec(
            "INSERT INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count)
             VALUES
             (" . self::SEGMENT_ID_1 . ", " . self::FILE_ID_1 . ", '1', 'Hello world', 'hash-1', 10),
             (" . self::SEGMENT_ID_2 . ", " . self::FILE_ID_1 . ", '2', 'How are you?', 'hash-2', 5),
             (" . self::SEGMENT_ID_3 . ", " . self::FILE_ID_2 . ", '1', 'Second file', 'hash-3', 7)"
        );

        $conn->exec(
            "INSERT INTO segment_translations (id_segment, id_job, segment_hash, translation, status, translation_date, time_to_edit, eq_word_count, standard_word_count)
             VALUES
             (" . self::SEGMENT_ID_1 . ", " . self::JOB_ID_1 . ", 'hash-1', 'Ciao mondo', 'DRAFT', NOW(), 10, 4, 6),
             (" . self::SEGMENT_ID_2 . ", " . self::JOB_ID_1 . ", 'hash-2', 'Come stai?', 'DRAFT', NOW(), 10, 2, 3),
             (" . self::SEGMENT_ID_3 . ", " . self::JOB_ID_2 . ", 'hash-3', 'Deuxième fichier', 'DRAFT', NOW(), 10, 3, 4)"
        );
    }

    // ─── findById() ───

    #[Test]
    public function findByIdReturnsExistingAndNullForMissing(): void
    {
        $dao = new ProjectDao($this->database);
        $this->assertInstanceOf(ProjectStruct::class, $dao->findById(self::PROJECT_ID_1));
        $this->assertNull($dao->findById(88889999));
    }

    // ─── findByIdAndPassword() ───

    #[Test]
    public function findByIdAndPasswordReturnsProjectForExistingPair(): void
    {
        $dao = new ProjectDao($this->database);
        $project = $dao->findByIdAndPassword(self::PROJECT_ID_1, 'ppass-1');

        $this->assertSame(self::PROJECT_ID_1, $project->id);
        $this->assertSame('Project Alpha', $project->name);
    }

    #[Test]
    public function findByIdAndPasswordThrowsNotFoundForMissingPair(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No project found.');

        $dao = new ProjectDao($this->database);
        $dao->findByIdAndPassword(self::PROJECT_ID_1, 'wrong-password');
    }

    // ─── exists() ───

    #[Test]
    public function existsReturnsTrueForExistingAndFalseForMissing(): void
    {
        $dao = new ProjectDao($this->database);
        $this->assertTrue($dao->exists(self::PROJECT_ID_1));
        $this->assertFalse($dao->exists(88889999));
    }

    // ─── findByTeamId() ───

    #[Test]
    public function findByTeamIdSupportsFiltersLimitAndOffset(): void
    {
        $dao = new ProjectDao($this->database);

        $all = $dao->findByTeamId(self::TEST_TEAM_ID);
        $this->assertCount(3, $all);

        $byId = $dao->findByTeamId(self::TEST_TEAM_ID, ['search' => ['id' => self::PROJECT_ID_1]]);
        $this->assertCount(1, $byId);
        $this->assertSame(self::PROJECT_ID_1, $byId[0]->id);

        $byName = $dao->findByTeamId(self::TEST_TEAM_ID, ['search' => ['name' => 'Project Beta']]);
        $this->assertCount(1, $byName);
        $this->assertSame('Project Beta', $byName[0]->name);

        $paged = $dao->findByTeamId(self::TEST_TEAM_ID, ['limit' => 1, 'offset' => 1]);
        $this->assertCount(1, $paged);
    }

    // ─── getTotalCountByTeamId() ───

    #[Test]
    public function getTotalCountByTeamIdReturnsExpectedCounts(): void
    {
        $dao = new ProjectDao($this->database);
        $this->assertSame(3, $dao->getTotalCountByTeamId(self::TEST_TEAM_ID));
        $this->assertSame(1, $dao->getTotalCountByTeamId(self::TEST_TEAM_ID, ['search' => ['id' => self::PROJECT_ID_2]]));
        $this->assertSame(1, $dao->getTotalCountByTeamId(self::TEST_TEAM_ID, ['search' => ['name' => 'Project Alpha']]));
    }

    // ─── findByJobId() ───

    #[Test]
    public function findByJobIdReturnsProjectOrNull(): void
    {
        $dao = new ProjectDao($this->database);
        $project = $dao->findByJobId(self::JOB_ID_1);

        $this->assertInstanceOf(ProjectStruct::class, $project);
        $this->assertSame(self::PROJECT_ID_1, $project?->id);
        $this->assertNull($dao->findByJobId(88889999));
    }

    // ─── findByIdCustomer() ───

    #[Test]
    public function findByIdCustomerReturnsProjectsForCustomer(): void
    {
        $dao = new ProjectDao($this->database);
        $projects = $dao->findByIdCustomer('customer-a');
        $ids = array_map(static fn(ProjectStruct $project): int => (int)$project->id, $projects);

        $this->assertContains(self::PROJECT_ID_1, $ids);
        $this->assertContains(self::PROJECT_ID_2, $ids);
        $this->assertContains(self::PROJECT_ID_3, $ids);
        $this->assertNotContains(self::PROJECT_ID_4, $ids);
    }

    // ─── getProjectAndJobData() ───

    #[Test]
    public function getProjectAndJobDataReturnsJoinedRows(): void
    {
        $dao = new ProjectDao($this->database);
        $rows = $dao->getProjectAndJobData(self::PROJECT_ID_1);

        $this->assertCount(2, $rows);
        $this->assertSame((string)self::PROJECT_ID_1, (string)$rows[0]['pid']);
        $this->assertArrayHasKey('jid_jpassword', $rows[0]);
        $this->assertArrayHasKey('lang_pair', $rows[0]);
        $this->assertArrayHasKey('job_url', $rows[0]);
    }

    // ─── updateAnalysisStatus() ───

    #[Test]
    public function updateAnalysisStatusUpdatesStatusAndWordCount(): void
    {
        $dao = new ProjectDao($this->database);
        $ok = $dao->updateAnalysisStatus(self::PROJECT_ID_1, 'DONE', 123);

        $this->assertTrue($ok);

        $project = $dao->findById(self::PROJECT_ID_1);
        $this->assertSame('DONE', $project?->status_analysis);
        $this->assertSame(123.0, (float)$project?->standard_analysis_wc);
    }

    // ─── changeProjectStatus() ───

    #[Test]
    public function changeProjectStatusUpdatesStatusField(): void
    {
        $dao = new ProjectDao($this->database);
        $affected = $dao->changeProjectStatus(self::PROJECT_ID_1, 'BUSY');

        $this->assertSame(1, $affected);
        $this->assertSame('BUSY', $dao->findById(self::PROJECT_ID_1)?->status_analysis);
    }

    // ─── destroyCacheByIdAndPassword() ───

    #[Test]
    public function destroyCacheByIdAndPasswordReturnsBool(): void
    {
        $dao = new ProjectDao($this->database);
        $this->assertIsBool($dao->destroyCacheByIdAndPassword(self::PROJECT_ID_1, 'ppass-1'));
    }

    // ─── isGDriveProject() ───

    #[Test]
    public function isGDriveProjectReturnsFalseWhenNoRemoteFiles(): void
    {
        $dao = new ProjectDao($this->database);
        $this->assertFalse($dao->isGDriveProject(self::PROJECT_ID_1));
    }
}
