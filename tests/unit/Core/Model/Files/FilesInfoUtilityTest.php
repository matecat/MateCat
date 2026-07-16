<?php

namespace Matecat\Core\Model\Files;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Files\FilesInfoUtility;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class FilesInfoUtilityTest extends AbstractTest
{
    // High fixed id range to avoid collisions with real/other fixtures.
    private const int PROJECT_ID = 9990001;
    private const int FILE_ID = 9990002;
    private const int JOB_ID = 9990003;
    private const int SEGMENT_ID = 9990004;
    private const int FILE_PART_1 = 9990010;

    private IDatabase $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = obtainTestDatabase(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );
        $this->cleanFixtures();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanFixtures();

        $flusher = (new RedisHandler())->getConnection();
        $flusher->flushdb();

        parent::tearDown();
    }

    /**
     * Seed the minimal chain required by the DAOs under test:
     *  - projects        : resolved by JobStruct::getProject() -> ProjectDao::findById()
     *  - files           : FileDao::isFileInProject() gate
     *  - jobs / files_job: JobDao::getFilesInfoInJob() joins
     *  - segments        : getFilesInfoInJob() join (show_in_cattool = 1)
     *  - segment_translations : getFilesInfoInJob() word-count aggregation
     */
    private function seedFixtures(): void
    {
        $conn = $this->database->getConnection();

        $conn->prepare(
            "INSERT INTO projects (id, password, id_customer, name, create_date, status_analysis)
             VALUES (?, 'pwdproj', 'test@matecat.com', 'fixtureProject', NOW(), 'DONE')"
        )->execute([self::PROJECT_ID]);

        $conn->prepare(
            "INSERT INTO files (id, id_project, filename, source_language, mime_type, is_converted)
             VALUES (?, ?, 'fixture.xliff', 'en-US', 'application/xml', 1)"
        )->execute([self::FILE_ID, self::PROJECT_ID]);

        $conn->prepare(
            "INSERT INTO jobs (id, password, id_project, job_first_segment, job_last_segment, tm_keys, create_date, disabled)
             VALUES (?, 'pwdjob', ?, ?, ?, '', NOW(), 0)"
        )->execute([self::JOB_ID, self::PROJECT_ID, self::SEGMENT_ID, self::SEGMENT_ID]);

        $conn->prepare(
            "INSERT INTO files_job (id_job, id_file) VALUES (?, ?)"
        )->execute([self::JOB_ID, self::FILE_ID]);

        $conn->prepare(
            "INSERT INTO segments (id, id_file, segment, segment_hash, raw_word_count, show_in_cattool)
             VALUES (?, ?, 'hello world', 'hash1', 100, 1)"
        )->execute([self::SEGMENT_ID, self::FILE_ID]);

        $conn->prepare(
            "INSERT INTO segment_translations (id_segment, id_job, segment_hash, status, eq_word_count, standard_word_count)
             VALUES (?, ?, 'hash1', 'TRANSLATED', 90, 95)"
        )->execute([self::SEGMENT_ID, self::JOB_ID]);
    }

    private function cleanFixtures(): void
    {
        $conn = $this->database->getConnection();
        $conn->exec("DELETE FROM file_metadata WHERE id_project = " . self::PROJECT_ID);
        $conn->exec("DELETE FROM files_parts WHERE id_file = " . self::FILE_ID);
        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . self::JOB_ID);
        $conn->exec("DELETE FROM segments WHERE id_file = " . self::FILE_ID);
        $conn->exec("DELETE FROM files_job WHERE id_job = " . self::JOB_ID);
        $conn->exec("DELETE FROM jobs WHERE id = " . self::JOB_ID);
        $conn->exec("DELETE FROM files WHERE id = " . self::FILE_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);
    }

    private function insertMetadata(string $key, string $value, ?int $filePartsId = null): void
    {
        $this->database->getConnection()->prepare(
            "INSERT INTO file_metadata (id_project, id_file, `key`, `value`, files_parts_id)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([self::PROJECT_ID, self::FILE_ID, $key, $value, $filePartsId]);
    }

    private function insertFilePart(int $id, string $tagKey = 'k', string $tagValue = 'v'): void
    {
        $this->database->getConnection()->prepare(
            "INSERT INTO files_parts (id, id_file, tag_key, tag_value) VALUES (?, ?, ?, ?)"
        )->execute([$id, self::FILE_ID, $tagKey, $tagValue]);
    }

    /**
     * Builds a real JobStruct pointing at the seeded job/project so that
     * getProject() resolves through ProjectDao::findById().
     */
    private function realChunk(): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = self::JOB_ID;
        $chunk->password = 'pwdjob';
        $chunk->id_project = self::PROJECT_ID;
        $chunk->job_first_segment = self::SEGMENT_ID;
        $chunk->job_last_segment = self::SEGMENT_ID;

        return $chunk;
    }

    /**
     * Anonymous JobStruct that overrides getProject() to bypass the DB.
     * Used ONLY by the constructor tests to control the project id directly.
     */
    private function makeChunk(?int $projectId): JobStruct
    {
        $project = new ProjectStruct();
        $project->id = $projectId;

        return new class($project) extends JobStruct {
            public function __construct(private ProjectStruct $projectStruct)
            {
                $this->job_first_segment = 1;
                $this->job_last_segment  = 10;
            }

            public function getProject(ProjectDao $dao, int $ttl = 86400): ProjectStruct
            {
                return $this->projectStruct;
            }
        };
    }

    // --- constructor contract ---

    #[Test]
    public function constructor_throws_when_project_id_is_null(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Project ID must not be null');

        new FilesInfoUtility($this->makeChunk(null), $this->createStub(IDatabase::class));
    }

    #[Test]
    public function constructor_succeeds_with_valid_project_id(): void
    {
        $utility = new FilesInfoUtility($this->makeChunk(42), $this->database);
        $this->assertInstanceOf(FilesInfoUtility::class, $utility);
    }

    // --- getInstructions() ---

    #[Test]
    public function get_instructions_returns_null_when_file_not_in_project(): void
    {
        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        // 8888888 is not a file of the seeded project.
        $this->assertNull($utility->getInstructions(8888888));
    }

    #[Test]
    public function get_instructions_returns_null_when_no_key_found(): void
    {
        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        $this->assertNull($utility->getInstructions(self::FILE_ID));
    }

    #[Test]
    public function get_instructions_returns_instructions_value(): void
    {
        $this->insertMetadata('instructions', 'Translate carefully');

        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        $this->assertSame(['instructions' => 'Translate carefully'], $utility->getInstructions(self::FILE_ID));
    }

    #[Test]
    public function get_instructions_falls_back_to_mtc_instructions_key(): void
    {
        // Only the mtc:instructions key exists -> the code must fall back to it.
        $this->insertMetadata('mtc:instructions', 'MTC instructions');

        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        $this->assertSame(['instructions' => 'MTC instructions'], $utility->getInstructions(self::FILE_ID));
    }

    // --- setInstructions() ---

    #[Test]
    public function set_instructions_returns_false_when_file_not_in_project(): void
    {
        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        $this->assertFalse($utility->setInstructions(8888888, 'Do this'));
    }

    #[Test]
    public function set_instructions_inserts_when_no_existing_row(): void
    {
        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        $this->assertTrue($utility->setInstructions(self::FILE_ID, 'brand new'));

        $rows = $this->fetchInstructionsRows();
        $this->assertCount(1, $rows);
        $this->assertSame('brand new', $rows[0]['value']);
    }

    #[Test]
    public function set_instructions_updates_when_row_exists(): void
    {
        $this->insertMetadata('instructions', 'old text');

        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        $this->assertTrue($utility->setInstructions(self::FILE_ID, 'new text'));

        // Update branch must NOT create a second row, only mutate the existing one.
        $rows = $this->fetchInstructionsRows();
        $this->assertCount(1, $rows);
        $this->assertSame('new text', $rows[0]['value']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchInstructionsRows(): array
    {
        $stmt = $this->database->getConnection()->prepare(
            "SELECT * FROM file_metadata
             WHERE id_project = :id_project AND id_file = :id_file AND `key` = 'instructions'"
        );
        $stmt->execute(['id_project' => self::PROJECT_ID, 'id_file' => self::FILE_ID]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // --- getInfo() ---

    #[Test]
    public function get_info_without_metadata_returns_rendered_structure(): void
    {
        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        $result = $utility->getInfo(false);

        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('first_segment', $result);
        $this->assertArrayHasKey('last_segment', $result);
        $this->assertSame(self::SEGMENT_ID, $result['first_segment']);
        $this->assertSame(self::SEGMENT_ID, $result['last_segment']);

        // One seeded file is returned. With $showMetadata = false the metadata is never
        // populated, so FilesInfo::render emits a null metadata value for the file.
        $this->assertCount(1, $result['files']);
        $this->assertSame(self::FILE_ID, $result['files'][0]['id']);
        $this->assertNull($result['files'][0]['metadata']);
    }

    #[Test]
    public function get_info_with_flat_metadata_key(): void
    {
        // Flat metadata (files_parts_id IS NULL) is passed straight through.
        $this->insertMetadata('instructions', 'Be careful');

        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        $result = $utility->getInfo(true);

        $this->assertCount(1, $result['files']);
        $this->assertSame('Be careful', $result['files'][0]['metadata']['instructions']);
    }

    #[Test]
    public function get_info_uses_files_parts_dao_when_metadata_is_null(): void
    {
        // No file_metadata rows at all -> code must fall back to FilesPartsDao::getByFileId().
        $this->insertFilePart(self::FILE_PART_1);

        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        $result = $utility->getInfo(true);

        $this->assertSame([['id' => self::FILE_PART_1]], $result['files'][0]['metadata']['files_parts']);
        $this->assertNull($result['files'][0]['metadata']['instructions']);
    }

    #[Test]
    public function get_info_reindexes_files_parts_from_metadata(): void
    {
        // Metadata bound to a file part -> reshaped into files_parts[] keyed by files_parts_id.
        $this->insertFilePart(self::FILE_PART_1, 'color', 'blue');
        $this->insertMetadata('color', 'blue', self::FILE_PART_1);

        $utility = new FilesInfoUtility($this->realChunk(), $this->database);

        $result = $utility->getInfo(true);

        $filesMeta = $result['files'][0]['metadata'];
        $this->assertSame(self::FILE_PART_1, $filesMeta['files_parts'][0]['id']);
        $this->assertSame('blue', $filesMeta['files_parts'][0]['color']);
    }
}
