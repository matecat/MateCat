<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\GetSearchController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Projects\MetadataDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Constants\SourcePages;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Testable subclass that skips the validator chain so the test can drive the controller directly.
 */
class ReplaceUndoTestableController extends GetSearchController
{
    public function __construct()
    {
    }
}

/**
 * End-to-end real-SQL coverage for the replace-all / undo-replace-all round trip with the REAL
 * TranslationVersionsHandler (translation_versions feature enabled). Covers:
 *   (c) the first/only replace-all is undoable (cursor 1 -> 0);
 *   (d) forward replace-all then undo restores text + status exactly, one audit event per segment;
 *   the "DummyVersionHandler on replace-all" regression — the real handler must write
 *   segment_translation_versions + segment_translation_events rows (the Dummy handler writes neither).
 *
 * Uses the MySQL ReplaceHistory driver. The replace-history tables live only in migrations (not in the
 * static test schema), so they are created here. A fresh reserved id block is used to avoid stale
 * DaoCacheTrait / feature caches from other suites.
 */
#[AllowMockObjectsWithoutExpectations]
class GetSearchControllerReplaceUndoIntegrationTest extends AbstractTest
{
    private const int TEST_PROJECT_ID = 9971001;
    private const int TEST_JOB_ID = 9971002;
    private const string TEST_JOB_PASSWORD = 'undo_pw_v2';
    private const int TEST_SEGMENT_1 = 9971003;
    private const int TEST_SEGMENT_2 = 9971004;
    private const int TEST_FILE_ID = 9971005;

    private ReflectionClass $reflector;
    private ReplaceUndoTestableController $controller;
    private Response&MockObject $responseMock;
    private string $originalDriver;
    private int $originalTtl;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDriver = AppConfig::$REPLACE_HISTORY_DRIVER;
        $this->originalTtl = AppConfig::$REPLACE_HISTORY_TTL;
        AppConfig::$REPLACE_HISTORY_DRIVER = 'mysql';
        AppConfig::$REPLACE_HISTORY_TTL = 300;

        $this->createReplaceHistoryTables();
        $this->seedTestData();

        // Enable translation_versions so replace-all runs the real TranslationVersionsHandler (not Dummy).
        (new MetadataDao(obtainTestDatabase()))->set(self::TEST_PROJECT_ID, 'features', 'translation_versions');

        $this->controller = new ReplaceUndoTestableController();
        $this->reflector = new ReflectionClass(GetSearchController::class);

        $this->reflector->getProperty('request')->setValue($this->controller, new Request());

        $this->responseMock = $this->createMock(Response::class);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.org';
        $user->first_name = 'Test';
        $user->last_name = 'User';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($this->createStub(IDatabase::class)));
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());

        $chunk = (new JobDao(obtainTestDatabase()))->getByIdAndPassword(self::TEST_JOB_ID, self::TEST_JOB_PASSWORD);
        $chunk->setSourcePage(SourcePages::SOURCE_PAGE_TRANSLATE);
        $this->reflector->getProperty('chunk')->setValue($this->controller, $chunk);
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        $this->dropReplaceHistoryRows();

        AppConfig::$REPLACE_HISTORY_DRIVER = $this->originalDriver;
        AppConfig::$REPLACE_HISTORY_TTL = $this->originalTtl;

        parent::tearDown();
    }

    private function createReplaceHistoryTables(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("CREATE TABLE IF NOT EXISTS `replace_events` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `replace_version` bigint(20) NOT NULL,
            `id_job` bigint(20) NOT NULL,
            `job_password` varchar(45) NOT NULL,
            `id_segment` int(11) NOT NULL,
            `segment_version` int(11),
            `translation_before_replacement` text,
            `translation_after_replacement` text,
            `source` text,
            `target` text,
            `status` varchar(45) NOT NULL,
            `replacement` text,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $conn->exec("CREATE TABLE IF NOT EXISTS `replace_events_current_version` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `id_job` bigint(20) NOT NULL,
            `version` bigint(20) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $this->dropReplaceHistoryRows();
    }

    private function dropReplaceHistoryRows(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM replace_events WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM replace_events_current_version WHERE id_job = " . self::TEST_JOB_ID);
    }

    private function seedTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $this->cleanTestData();

        $conn->exec("INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis) VALUES (" . self::TEST_PROJECT_ID . ", 'test@example.org', 'projpw', 'UndoProj', NOW(), 'DONE')");
        $conn->exec("INSERT INTO files (id, id_project, filename, source_language, mime_type) VALUES (" . self::TEST_FILE_ID . ", " . self::TEST_PROJECT_ID . ", 'u.xliff', 'en-US', 'application/xliff+xml')");
        $conn->exec("INSERT INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled) VALUES (" . self::TEST_JOB_ID . ", '" . self::TEST_JOB_PASSWORD . "', " . self::TEST_PROJECT_ID . ", 'en-US', 'it-IT', " . self::TEST_SEGMENT_1 . ", " . self::TEST_SEGMENT_2 . ", 'test@example.org', '[]', NOW(), 0)");
        $conn->exec("INSERT INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count) VALUES
            (" . self::TEST_SEGMENT_1 . ", " . self::TEST_FILE_ID . ", '1', 'Hello world', 'undo_hash1', 2),
            (" . self::TEST_SEGMENT_2 . ", " . self::TEST_FILE_ID . ", '2', 'Bye', 'undo_hash2', 1)
        ");
        $conn->exec("INSERT INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date) VALUES
            (" . self::TEST_SEGMENT_1 . ", " . self::TEST_JOB_ID . ", 'undo_hash1', 'Ciao mondo', 'TRANSLATED', 0, NOW()),
            (" . self::TEST_SEGMENT_2 . ", " . self::TEST_JOB_ID . ", 'undo_hash2', 'Arrivederci', 'TRANSLATED', 0, NOW())
        ");
        // Link file <-> job: the real TranslationVersionsHandler loads the segment via
        // SegmentDao::getByChunkIdAndSegmentId (segments -> files_job -> jobs join).
        $conn->exec("INSERT IGNORE INTO files_job (id_job, id_file) VALUES (" . self::TEST_JOB_ID . ", " . self::TEST_FILE_ID . ")");
    }

    private function cleanTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM segment_translation_versions WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM segment_translation_events WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM qa_chunk_reviews WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM files_job WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM project_metadata WHERE id_project = " . self::TEST_PROJECT_ID);
        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM segments WHERE id_file = " . self::TEST_FILE_ID);
        $conn->exec("DELETE FROM jobs WHERE id = " . self::TEST_JOB_ID);
        $conn->exec("DELETE FROM files WHERE id = " . self::TEST_FILE_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::TEST_PROJECT_ID);
    }

    private function setRequestParams(array $params): void
    {
        $server = ['REQUEST_URI' => '/api/app/replace-all', 'REQUEST_METHOD' => 'POST'];
        $this->reflector->getProperty('request')->setValue($this->controller, new Request($params, [], [], $server));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $sql): array
    {
        return obtainTestDatabase()->getConnection()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function translationOf(int $segmentId): ?\Model\Translations\SegmentTranslationStruct
    {
        return (new \Model\Translations\SegmentTranslationDao(obtainTestDatabase()))
            ->findBySegmentAndJob($segmentId, self::TEST_JOB_ID);
    }

    private function cursor(): int
    {
        $rows = $this->fetchAll("SELECT version FROM replace_events_current_version WHERE id_job = " . self::TEST_JOB_ID);
        return empty($rows) ? 0 : (int)$rows[0]['version'];
    }

    /**
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private function replaceParams(): array
    {
        return [
            'id_job' => (string)self::TEST_JOB_ID,
            'password' => self::TEST_JOB_PASSWORD,
            'source' => '',
            'target' => 'mondo',
            'replace' => 'universo',
            'token' => 'tok',
            'status' => 'all',
            'matchcase' => '0',
            'exactmatch' => '0',
            'inCurrentChunkOnly' => '0',
        ];
    }

    #[Test]
    public function replace_all_then_undo_restores_state_and_writes_version_history(): void
    {
        // ---- forward replace-all: "mondo" -> "universo" (matches only segment 1) ----
        $this->setRequestParams($this->replaceParams());
        $this->controller->replaceAll();

        // segment text replaced and status demoted to DRAFT (translate page)
        $afterReplace = $this->translationOf(self::TEST_SEGMENT_1);
        $this->assertNotNull($afterReplace);
        $this->assertStringContainsString('universo', $afterReplace->translation);
        $this->assertSame('DRAFT', $afterReplace->status);

        // exactly one replace-history event at version 1, capturing the pre-replacement text + status
        $events = $this->fetchAll("SELECT * FROM replace_events WHERE id_job = " . self::TEST_JOB_ID);
        $this->assertCount(1, $events, 'exactly one replace event for the single matching segment');
        $this->assertSame(self::TEST_SEGMENT_1, (int)$events[0]['id_segment']);
        $this->assertSame('1', (string)$events[0]['replace_version']);
        $this->assertSame('Ciao mondo', $events[0]['translation_before_replacement']);
        $this->assertStringContainsString('universo', $events[0]['translation_after_replacement']);
        $this->assertSame('TRANSLATED', $events[0]['status']);

        // the untouched segment is unchanged
        $this->assertSame('Arrivederci', $this->translationOf(self::TEST_SEGMENT_2)?->translation);

        // cursor advanced once for the whole batch
        $this->assertSame(1, $this->cursor(), 'first replace-all advances the undo cursor to 1');

        // REAL TranslationVersionsHandler ran (not Dummy): it wrote a version row AND an audit event.
        $versions = $this->fetchAll("SELECT * FROM segment_translation_versions WHERE id_job = " . self::TEST_JOB_ID . " AND id_segment = " . self::TEST_SEGMENT_1);
        $stEvents = $this->fetchAll("SELECT * FROM segment_translation_events WHERE id_job = " . self::TEST_JOB_ID . " AND id_segment = " . self::TEST_SEGMENT_1);
        $this->assertNotEmpty($versions, 'real TranslationVersionsHandler must write a segment_translation_versions row (not Dummy)');
        $this->assertNotEmpty($stEvents, 'real TranslationVersionsHandler must write a segment_translation_events row (not Dummy)');

        // ---- undo the (first and only) replace-all ----
        $this->setRequestParams($this->replaceParams());
        $this->controller->undoReplaceAll();

        // text and status restored exactly to the pre-replacement values
        $afterUndo = $this->translationOf(self::TEST_SEGMENT_1);
        $this->assertNotNull($afterUndo);
        $this->assertSame('Ciao mondo', $afterUndo->translation, 'undo restores the original text');
        $this->assertSame('TRANSLATED', $afterUndo->status, 'undo restores the original status');

        // the first/only replace-all is undoable: cursor is back to 0
        $this->assertSame(0, $this->cursor(), 'the first replace-all is fully undoable (cursor back to 0)');
    }
}
