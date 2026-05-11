<?php

namespace unit\Model\Projects;

use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class ProjectDaoTest extends AbstractTest
{
    private const int TEST_UID = 88880001;
    private const int TEST_TEAM_ID = 88880002;

    private const int PROJECT_ID_1 = 88880011;
    private const int PROJECT_ID_2 = 88880012;
    private const int PROJECT_ID_3 = 88880013;
    private const int PROJECT_ID_4 = 88880014;

    private const int JOB_ID_1 = 88880021;
    private const int JOB_ID_2 = 88880022;
    private const int JOB_ID_3 = 88880023;

    private const int FILE_ID_1 = 88880031;
    private const int FILE_ID_2 = 88880032;

    private const int SEGMENT_ID_1 = 88880041;
    private const int SEGMENT_ID_2 = 88880042;
    private const int SEGMENT_ID_3 = 88880043;

    private Database $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = Database::obtain(
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

        $flusher = new \Predis\Client(AppConfig::$REDIS_SERVERS);
        $flusher->select(AppConfig::$INSTANCE_ID);
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
             VALUES (" . self::TEST_UID . ", 'project-dao@test.local', 'x', 'x', '2024-01-01 00:00:00', 'Project', 'Dao')"
        );

        $conn->exec(
            "INSERT INTO teams (id, name, created_by, type)
             VALUES (" . self::TEST_TEAM_ID . ", 'ProjectDao Team', " . self::TEST_UID . ", 'general')"
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
             (" . self::JOB_ID_1 . ", 'jpass-1', " . self::PROJECT_ID_1 . ", 'en-US', 'it-IT', '[]', 'active', 'general', " . self::SEGMENT_ID_1 . ", " . self::SEGMENT_ID_2 . ", '2024-01-01 00:00:00', 'project-dao@test.local', 'active', 0, '[]'),
             (" . self::JOB_ID_2 . ", 'jpass-2', " . self::PROJECT_ID_1 . ", 'en-US', 'fr-FR', '[]', 'active', 'general', " . self::SEGMENT_ID_3 . ", " . self::SEGMENT_ID_3 . ", '2024-01-01 00:00:00', 'project-dao@test.local', 'active', 0, '[]'),
             (" . self::JOB_ID_3 . ", 'jpass-3', " . self::PROJECT_ID_2 . ", 'en-US', 'de-DE', '[]', 'active', 'general', " . self::SEGMENT_ID_3 . ", " . self::SEGMENT_ID_3 . ", '2024-01-01 00:00:00', 'project-dao@test.local', 'active', 0, '[]')"
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

    #[Test]
    public function updateFieldUpdatesStructAndDatabase(): void
    {
        $dao = new ProjectDao();
        $project = ProjectDao::findById(self::PROJECT_ID_1);

        $this->assertInstanceOf(ProjectStruct::class, $project);

        $updated = $dao->updateField($project, 'name', 'Project Alpha Updated');

        $this->assertSame('Project Alpha Updated', $updated->name);
        $this->assertSame('Project Alpha Updated', ProjectDao::findById(self::PROJECT_ID_1)?->name);
    }

    #[Test]
    public function changePasswordUpdatesProjectPassword(): void
    {
        $dao = new ProjectDao();
        $project = ProjectDao::findById(self::PROJECT_ID_1);

        $this->assertInstanceOf(ProjectStruct::class, $project);

        $dao->changePassword($project, 'ppass-1-new');

        $reloaded = ProjectDao::findByIdAndPassword(self::PROJECT_ID_1, 'ppass-1-new');
        $this->assertSame('ppass-1-new', $reloaded->password);
    }

    #[Test]
    public function changeNameUpdatesProjectName(): void
    {
        $dao = new ProjectDao();
        $project = ProjectDao::findById(self::PROJECT_ID_1);

        $this->assertInstanceOf(ProjectStruct::class, $project);

        $dao->changeName($project, 'Project Alpha Renamed');

        $this->assertSame('Project Alpha Renamed', ProjectDao::findById(self::PROJECT_ID_1)?->name);
    }

    #[Test]
    public function findByIdReturnsExistingAndNullForMissing(): void
    {
        $this->assertInstanceOf(ProjectStruct::class, ProjectDao::findById(self::PROJECT_ID_1));
        $this->assertNull(ProjectDao::findById(88889999));
    }

    #[Test]
    public function findByIdAndPasswordReturnsProjectForExistingPair(): void
    {
        $project = ProjectDao::findByIdAndPassword(self::PROJECT_ID_1, 'ppass-1');

        $this->assertSame(self::PROJECT_ID_1, $project->id);
        $this->assertSame('Project Alpha', $project->name);
    }

    #[Test]
    public function findByIdAndPasswordThrowsNotFoundForMissingPair(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No project found.');

        ProjectDao::findByIdAndPassword(self::PROJECT_ID_1, 'wrong-password');
    }

    #[Test]
    public function existsReturnsTrueForExistingAndFalseForMissing(): void
    {
        $this->assertTrue(ProjectDao::exists(self::PROJECT_ID_1));
        $this->assertFalse(ProjectDao::exists(88889999));
    }

    #[Test]
    public function findByTeamIdSupportsFiltersLimitAndOffset(): void
    {
        $all = ProjectDao::findByTeamId(self::TEST_TEAM_ID);
        $this->assertCount(3, $all);

        $byId = ProjectDao::findByTeamId(self::TEST_TEAM_ID, ['search' => ['id' => self::PROJECT_ID_1]]);
        $this->assertCount(1, $byId);
        $this->assertSame(self::PROJECT_ID_1, $byId[0]->id);

        $byName = ProjectDao::findByTeamId(self::TEST_TEAM_ID, ['search' => ['name' => 'Project Beta']]);
        $this->assertCount(1, $byName);
        $this->assertSame('Project Beta', $byName[0]->name);

        $paged = ProjectDao::findByTeamId(self::TEST_TEAM_ID, ['limit' => 1, 'offset' => 1]);
        $this->assertCount(1, $paged);
    }

    #[Test]
    public function getTotalCountByTeamIdReturnsExpectedCounts(): void
    {
        $this->assertSame(3, ProjectDao::getTotalCountByTeamId(self::TEST_TEAM_ID));
        $this->assertSame(1, ProjectDao::getTotalCountByTeamId(self::TEST_TEAM_ID, ['search' => ['id' => self::PROJECT_ID_2]]));
        $this->assertSame(1, ProjectDao::getTotalCountByTeamId(self::TEST_TEAM_ID, ['search' => ['name' => 'Project Alpha']]));
    }

    #[Test]
    public function findByJobIdReturnsProjectOrNull(): void
    {
        $project = ProjectDao::findByJobId(self::JOB_ID_1);

        $this->assertInstanceOf(ProjectStruct::class, $project);
        $this->assertSame(self::PROJECT_ID_1, $project?->id);
        $this->assertNull(ProjectDao::findByJobId(88889999));
    }

    #[Test]
    public function findByIdCustomerReturnsProjectsForCustomer(): void
    {
        $projects = ProjectDao::findByIdCustomer('customer-a');
        $ids = array_map(static fn(ProjectStruct $project): int => (int)$project->id, $projects);

        $this->assertContains(self::PROJECT_ID_1, $ids);
        $this->assertContains(self::PROJECT_ID_2, $ids);
        $this->assertContains(self::PROJECT_ID_3, $ids);
        $this->assertNotContains(self::PROJECT_ID_4, $ids);
    }

    #[Test]
    public function getByIdListHandlesEmptyAndNonEmptyLists(): void
    {
        $dao = new ProjectDao();

        $this->assertSame([], $dao->getByIdList([]));

        $found = $dao->getByIdList([self::PROJECT_ID_1, self::PROJECT_ID_2]);

        $this->assertCount(2, $found);
        $this->assertSame(self::PROJECT_ID_2, $found[0]->id);
        $this->assertSame(self::PROJECT_ID_1, $found[1]->id);
    }

    #[Test]
    public function getProjectAndJobDataReturnsJoinedRows(): void
    {
        $rows = ProjectDao::getProjectAndJobData(self::PROJECT_ID_1);

        $this->assertCount(2, $rows);
        $this->assertSame((string)self::PROJECT_ID_1, (string)$rows[0]['pid']);
        $this->assertArrayHasKey('jid_jpassword', $rows[0]);
        $this->assertArrayHasKey('lang_pair', $rows[0]);
        $this->assertArrayHasKey('job_url', $rows[0]);
    }

    #[Test]
    public function getJobIdsReturnsIdsForProject(): void
    {
        $dao = new ProjectDao();
        $jobIds = $dao->getJobIds(self::PROJECT_ID_1);

        $this->assertCount(2, $jobIds);
        $this->assertSame((string)self::JOB_ID_1, (string)$jobIds[0]['id']);
        $this->assertSame((string)self::JOB_ID_2, (string)$jobIds[1]['id']);
    }

    #[Test]
    public function getPasswordsMapReturnsTranslatorAndReviewPasswordsMap(): void
    {
        $dao = new ProjectDao();
        $map = $dao->getPasswordsMap(self::PROJECT_ID_1);

        $this->assertCount(2, $map);
        $this->assertSame((string)self::JOB_ID_1, (string)$map[0]['id_job']);
        $this->assertSame('jpass-1', $map[0]['t_password']);
        $this->assertArrayHasKey('r_password', $map[0]);
        $this->assertArrayHasKey('r2_password', $map[0]);
    }

    #[Test]
    public function updateAnalysisStatusUpdatesStatusAndWordCount(): void
    {
        $ok = ProjectDao::updateAnalysisStatus(self::PROJECT_ID_1, 'DONE', 123);

        $this->assertTrue($ok);

        $project = ProjectDao::findById(self::PROJECT_ID_1);
        $this->assertSame('DONE', $project?->status_analysis);
        $this->assertSame(123.0, (float)$project?->standard_analysis_wc);
    }

    #[Test]
    public function changeProjectStatusUpdatesStatusField(): void
    {
        $affected = ProjectDao::changeProjectStatus(self::PROJECT_ID_1, 'BUSY');

        $this->assertSame(1, $affected);
        $this->assertSame('BUSY', ProjectDao::findById(self::PROJECT_ID_1)?->status_analysis);
    }

    #[Test]
    public function getProjectDataSupportsFilterCombinations(): void
    {
        $dao = new ProjectDao();

        $all = $dao->getProjectData(self::PROJECT_ID_1);
        $this->assertCount(2, $all);

        $projectPassword = $dao->getProjectData(self::PROJECT_ID_1, 'ppass-1');
        $this->assertCount(2, $projectPassword);

        $projectPasswordAndJobId = $dao->getProjectData(self::PROJECT_ID_1, 'ppass-1', self::JOB_ID_1);
        $this->assertCount(1, $projectPasswordAndJobId);
        $this->assertSame((string)self::JOB_ID_1, (string)$projectPasswordAndJobId[0]['jid']);

        $onlyJobPassword = $dao->getProjectData(self::PROJECT_ID_1, null, null, 'jpass-2');
        $this->assertCount(1, $onlyJobPassword);
        $this->assertSame((string)self::JOB_ID_2, (string)$onlyJobPassword[0]['jid']);

        $missingPassword = $dao->getProjectData(self::PROJECT_ID_1, 'wrong-project-password');
        $this->assertSame([], $missingPassword);
    }

    #[Test]
    public function cacheDestroyMethodsAreCallableAndReturnBool(): void
    {
        $dao = new ProjectDao();

        $this->assertIsBool(ProjectDao::destroyCacheById(self::PROJECT_ID_1));
        $this->assertIsBool(ProjectDao::destroyCacheByIdAndPassword(self::PROJECT_ID_1, 'ppass-1'));
        $this->assertIsBool($dao->destroyCacheForProjectData(self::PROJECT_ID_1));
    }

    #[Test]
    public function isGDriveProjectReturnsFalseWhenNoRemoteFileRowsExist(): void
    {
        $this->assertFalse(ProjectDao::isGDriveProject(self::PROJECT_ID_1));
    }

    #[Test]
    public function getRemoteFileServiceNameReturnsEmptyArrayWhenNoRemoteFilesAreLinked(): void
    {
        $dao = new ProjectDao();
        $serviceNames = $dao->getRemoteFileServiceName([self::PROJECT_ID_1, self::PROJECT_ID_2]);

        $this->assertSame([], $serviceNames);
    }
}
