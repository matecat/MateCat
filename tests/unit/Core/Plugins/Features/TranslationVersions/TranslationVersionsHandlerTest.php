<?php

namespace Matecat\Core\Plugins\Features\TranslationVersions;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDao;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\BatchReviewProcessor;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use Plugins\Features\TranslationVersions\Handlers\TranslationVersionsHandler;
use RuntimeException;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

/**
 * Keeps the EVENT-side seams overridable so the fan-out tests do not fire real
 * email/event handlers, while the DAO-level behavior (saveVersionAndIncrement /
 * propagateTranslation) still hits the REAL database built from the injected
 * IDatabase in the production constructor.
 */
class TestableTranslationVersionsHandler extends TranslationVersionsHandler
{
    private ?TranslationEventsHandler $eventsHandlerOverride = null;
    private ?BatchReviewProcessor $batchReviewProcessorOverride = null;
    private TranslationEventDao $translationEventDaoStub;
    private SegmentDao $segmentDaoStub;

    public function setEventsHandlerOverride(TranslationEventsHandler $handler): void
    {
        $this->eventsHandlerOverride = $handler;
    }

    public function setBatchReviewProcessorOverride(BatchReviewProcessor $processor): void
    {
        $this->batchReviewProcessorOverride = $processor;
    }

    public function setTranslationEventDaoStub(TranslationEventDao $dao): void
    {
        $this->translationEventDaoStub = $dao;
    }

    public function setSegmentDaoStub(SegmentDao $dao): void
    {
        $this->segmentDaoStub = $dao;
    }

    protected function createTranslationEvent(
        SegmentTranslationStruct $old_translation,
        SegmentTranslationStruct $translation,
        $user,
        int $source_page_code,
        JobStruct $chunk,
    ): TranslationEvent {
        return new TranslationEvent(
            $old_translation,
            $translation,
            $user,
            $source_page_code,
            $chunk,
            $this->translationEventDaoStub,
            $this->segmentDaoStub,
        );
    }

    protected function createTranslationEventsHandler(JobStruct $chunk): TranslationEventsHandler
    {
        return $this->eventsHandlerOverride ?? parent::createTranslationEventsHandler($chunk);
    }

    protected function createBatchReviewProcessor(): BatchReviewProcessor
    {
        return $this->batchReviewProcessorOverride ?? parent::createBatchReviewProcessor();
    }
}

class TestableEventsHandler extends TranslationEventsHandler
{
    protected function openTransaction(): void
    {
    }

    protected function commitTransaction(): void
    {
    }

    protected function rollbackTransaction(): void
    {
    }
}

#[Group('PersistenceNeeded')]
class TranslationVersionsHandlerTest extends AbstractTest
{
    // High fixed id range to avoid collisions with real/other fixtures.
    private const int PROJECT_ID = 9991001;
    private const int FILE_ID    = 9991002;
    private const int JOB_ID     = 9991003;
    private const int SEGMENT_ID = 9991010;
    // Second segment that shares the same segment_hash, used by propagateTranslation.
    private const int SEGMENT_ID_SIBLING = 9991011;

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
     * Seed the minimal chain the handler DAOs read/write:
     *  - projects             : ProjectDao / MetadataDao word-count-type lookup
     *  - files                : FK parent for segments
     *  - jobs                 : id_job referenced by every version row
     *  - segments             : INNER JOIN target of propagateTranslation totals query
     *  - segment_translations : the propagation candidates (shared segment_hash)
     *
     * segment_translation_versions is intentionally left empty so each
     * saveVersionAndIncrement test starts from a known-clean version table.
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
        )->execute([self::JOB_ID, self::PROJECT_ID, self::SEGMENT_ID, self::SEGMENT_ID_SIBLING]);

        // Two segments with DISTINCT segment_hashes. The propagateTranslation totals
        // query matches by segment_hash AND id_segment != source, so with no sibling
        // sharing the source hash it finds zero propagation candidates -> the
        // WorkerClient::enqueue('PROPAGATION', ...) branch is never reached (keeps the
        // test free of any ActiveMQ dependency) while the real SQL still executes.
        $conn->prepare(
            "INSERT INTO segments (id, id_file, segment, segment_hash, raw_word_count, show_in_cattool)
             VALUES (?, ?, 'hello world', 'sourcehash', 5, 1)"
        )->execute([self::SEGMENT_ID, self::FILE_ID]);

        $conn->prepare(
            "INSERT INTO segments (id, id_file, segment, segment_hash, raw_word_count, show_in_cattool)
             VALUES (?, ?, 'other text', 'siblinghash', 5, 1)"
        )->execute([self::SEGMENT_ID_SIBLING, self::FILE_ID]);

        $conn->prepare(
            "INSERT INTO segment_translations (id_segment, id_job, segment_hash, status, translation, eq_word_count, standard_word_count, match_type, version_number)
             VALUES (?, ?, 'sourcehash', 'TRANSLATED', 'ciao mondo', 5, 5, 'NEW', 0)"
        )->execute([self::SEGMENT_ID, self::JOB_ID]);

        $conn->prepare(
            "INSERT INTO segment_translations (id_segment, id_job, segment_hash, status, translation, eq_word_count, standard_word_count, match_type, version_number)
             VALUES (?, ?, 'siblinghash', 'TRANSLATED', 'altro testo', 5, 5, 'NEW', 0)"
        )->execute([self::SEGMENT_ID_SIBLING, self::JOB_ID]);
    }

    private function cleanFixtures(): void
    {
        $conn = $this->database->getConnection();
        $conn->exec("DELETE FROM segment_translation_versions WHERE id_job = " . self::JOB_ID);
        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . self::JOB_ID);
        $conn->exec("DELETE FROM segments WHERE id_file = " . self::FILE_ID);
        $conn->exec("DELETE FROM jobs WHERE id = " . self::JOB_ID);
        $conn->exec("DELETE FROM files WHERE id = " . self::FILE_ID);
        $conn->exec("DELETE FROM project_metadata WHERE id_project = " . self::PROJECT_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);
    }

    private function makeChunk(): JobStruct
    {
        return new JobStruct([
            'id'                => self::JOB_ID,
            'id_project'        => self::PROJECT_ID,
            'password'          => 'pwdjob',
            'job_first_segment' => self::SEGMENT_ID,
            'job_last_segment'  => self::SEGMENT_ID_SIBLING,
            'source'            => 'en-US',
            'target'            => 'it-IT',
            'create_date'       => '2026-01-01 00:00:00',
            'last_update'       => '2026-01-01 00:00:00',
        ]);
    }

    private function makeProject(): ProjectStruct
    {
        $project     = new ProjectStruct();
        $project->id = self::PROJECT_ID;

        return $project;
    }

    private function makeTranslation(
        string $translation = 'hello',
        string $status = TranslationStatus::STATUS_TRANSLATED,
        ?int $versionNumber = 1,
        int $idSegment = self::SEGMENT_ID,
    ): SegmentTranslationStruct {
        return new SegmentTranslationStruct([
            'id_segment'     => $idSegment,
            'id_job'         => self::JOB_ID,
            'segment_hash'   => 'sourcehash',
            'translation'    => $translation,
            'status'         => $status,
            'version_number' => $versionNumber,
        ]);
    }

    /**
     * Builds a handler bound to the REAL seeded database. The event-side seams
     * are stubbed so DAO behavior is exercised against MySQL but no real
     * TranslationEvent persistence/email side effects fire.
     */
    private function makeHandler(): TestableTranslationVersionsHandler
    {
        $handler = new TestableTranslationVersionsHandler(
            $this->makeChunk(),
            self::SEGMENT_ID,
            $this->makeProject(),
            $this->database,
        );

        $eventDaoStub = $this->createStub(TranslationEventDao::class);
        $eventDaoStub->method('getLatestEventForSegment')->willReturn(null);
        $handler->setTranslationEventDaoStub($eventDaoStub);
        $handler->setSegmentDaoStub($this->createStub(SegmentDao::class));

        return $handler;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchVersionRows(int $idSegment = self::SEGMENT_ID): array
    {
        $stmt = $this->database->getConnection()->prepare(
            "SELECT * FROM segment_translation_versions
             WHERE id_job = :id_job AND id_segment = :id_segment
             ORDER BY version_number ASC"
        );
        $stmt->execute(['id_job' => self::JOB_ID, 'id_segment' => $idSegment]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- constructor contract (throw BEFORE the db is touched) ---

    #[Test]
    public function constructorThrowsOnMissingJobId(): void
    {
        $chunk = new JobStruct([
            'id'                => null,
            'id_project'        => self::PROJECT_ID,
            'password'          => 'pwd',
            'job_first_segment' => 1,
            'job_last_segment'  => 10,
            'source'            => 'en-US',
            'target'            => 'it-IT',
            'create_date'       => '2026-01-01 00:00:00',
            'last_update'       => '2026-01-01 00:00:00',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job id is required');

        new TranslationVersionsHandler($chunk, 10, $this->makeProject(), $this->createStub(IDatabase::class));
    }

    #[Test]
    public function constructorThrowsOnMissingSegmentId(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Segment id is required');

        new TranslationVersionsHandler($this->makeChunk(), null, $this->makeProject(), $this->createStub(IDatabase::class));
    }

    // --- saveVersionAndIncrement() against the real segment_translation_versions table ---

    #[Test]
    public function saveVersionAndIncrementReturnsFalseWhenTranslationUnchanged(): void
    {
        $handler = $this->makeHandler();

        $old = $this->makeTranslation('same text', TranslationStatus::STATUS_DRAFT, 1);
        $new = $this->makeTranslation('same text', TranslationStatus::STATUS_TRANSLATED, 1);

        $result = $handler->saveVersionAndIncrement($new, $old);

        $this->assertFalse($result);
        $this->assertSame(1, $new->version_number);
        // No SQL write happens on the unchanged path.
        $this->assertCount(0, $this->fetchVersionRows());
    }

    #[Test]
    public function saveVersionAndIncrementReturnsTrueAndIncrementsVersionAndInsertsRow(): void
    {
        $handler = $this->makeHandler();

        // version_number 2 is NOT yet present -> insertVersion() INSERT path.
        $old = $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED, 2);
        $new = $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED, 2);

        $result = $handler->saveVersionAndIncrement($new, $old);

        $this->assertTrue($result);
        $this->assertSame(3, $new->version_number);

        // A real version row was written carrying the OLD translation/version.
        $rows = $this->fetchVersionRows();
        $this->assertCount(1, $rows);
        $this->assertSame(2, (int)$rows[0]['version_number']);
        $this->assertSame('old text', $rows[0]['translation']);
        $this->assertSame(TranslationStatus::$DB_STATUSES_MAP[TranslationStatus::STATUS_TRANSLATED], (int)$rows[0]['new_status']);
    }

    /**
     * Regression: before this fix, a concurrent write that caused updateVersion() to return 0
     * ("no rows changed" — identical translation already saved by a racing request) would
     * suppress the version increment. The handler now always returns true when the translation
     * text changed, even when a version row for the same version_number already exists (e.g.
     * written by a racing request) — but it must reconcile onto that row instead of inserting a
     * second one, since segment_translation_versions has no unique key to deduplicate on and a
     * duplicate row corrupts getAllRelevantEvents()'s downstream consumers (see
     * TranslationVersionDaoGetAllRelevantEventsTest::getAllRelevantEventsReturnsOneRowWhenAnExistingVersionIsReconciled).
     */
    #[Test]
    public function saveVersionAndIncrementReconcilesRowWhenVersionAlreadyExists(): void
    {
        // Pre-seed a version row for (id_job, id_segment, version_number=2), simulating a
        // racing request that already wrote this version.
        $this->database->getConnection()->prepare(
            "INSERT INTO segment_translation_versions (id_job, id_segment, translation, version_number, time_to_edit)
             VALUES (?, ?, 'stale translation', 2, 0)"
        )->execute([self::JOB_ID, self::SEGMENT_ID]);

        $handler = $this->makeHandler();

        $old = $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED, 2);
        $new = $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED, 2);

        $result = $handler->saveVersionAndIncrement($new, $old);

        $this->assertTrue($result);
        $this->assertSame(3, $new->version_number);

        // The pre-existing row was updated in place, not duplicated.
        $rows = $this->fetchVersionRows();
        $this->assertCount(1, $rows);
        $this->assertSame(2, (int)$rows[0]['version_number']);
        $this->assertSame('old text', $rows[0]['translation']);
    }

    /**
     * Regression for the exact ReviewExtended interaction Ostico flagged: a reviewer's LQA
     * issue diff writes version 0 with translation=NULL (raw_diff set) via
     * TranslationIssueModel::saveDiff() *before* the translator ever saves. The handler must
     * reconcile onto that row — filling in the real translation — rather than inserting a
     * second version-0 row alongside it.
     */
    #[Test]
    public function saveVersionAndIncrementReconcilesReviewExtendedDiffRow(): void
    {
        // Simulates TranslationIssueModel::saveDiff() inserting a version-0 row with raw_diff
        // set and translation left NULL, before the translator has saved anything.
        $this->database->getConnection()->prepare(
            "INSERT INTO segment_translation_versions (id_job, id_segment, translation, version_number, raw_diff)
             VALUES (?, ?, NULL, 0, '[\"diff\"]')"
        )->execute([self::JOB_ID, self::SEGMENT_ID]);

        $handler = $this->makeHandler();

        $old = $this->makeTranslation('draft text', TranslationStatus::STATUS_DRAFT, 0);
        $new = $this->makeTranslation('translator text', TranslationStatus::STATUS_TRANSLATED, 0);

        $result = $handler->saveVersionAndIncrement($new, $old);

        $this->assertTrue($result);
        $this->assertSame(1, $new->version_number);

        // Exactly one version-0 row remains — the ReviewExtended row was updated in place, not
        // duplicated. It now carries the archived (old) translation text. raw_diff is untouched
        // by updateVersion() (which only writes translation/time_to_edit), so the reviewer's
        // diff is preserved on the same row.
        $rows = $this->fetchVersionRows();
        $this->assertCount(1, $rows);
        $this->assertSame(0, (int)$rows[0]['version_number']);
        $this->assertSame('draft text', $rows[0]['translation']);
        $this->assertSame('["diff"]', $rows[0]['raw_diff']);
    }

    #[Test]
    public function saveVersionAndIncrementHandlesNullVersionNumbers(): void
    {
        $handler = $this->makeHandler();

        // null version numbers are coerced to 0 baseline; no version 0 row exists -> INSERT.
        $old = $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED, null);
        $new = $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED, null);

        $result = $handler->saveVersionAndIncrement($new, $old);

        $this->assertTrue($result);
        $this->assertSame(1, $new->version_number);

        $rows = $this->fetchVersionRows();
        $this->assertCount(1, $rows);
        $this->assertSame(0, (int)$rows[0]['version_number']);
    }

    #[Test]
    public function saveVersionAndIncrementKeepsVersionWhenNotSaved(): void
    {
        $handler = $this->makeHandler();

        $old = $this->makeTranslation('same', TranslationStatus::STATUS_TRANSLATED, 5);
        $new = $this->makeTranslation('same', TranslationStatus::STATUS_TRANSLATED, 5);

        $handler->saveVersionAndIncrement($new, $old);

        $this->assertSame(5, $new->version_number);
        $this->assertCount(0, $this->fetchVersionRows());
    }

    #[Test]
    public function saveVersionAndIncrementKeepsZeroWhenOldVersionNull(): void
    {
        $handler = $this->makeHandler();

        $old = $this->makeTranslation('same', TranslationStatus::STATUS_TRANSLATED, null);
        $new = $this->makeTranslation('same', TranslationStatus::STATUS_TRANSLATED, null);

        $handler->saveVersionAndIncrement($new, $old);

        $this->assertSame(0, $new->version_number);
        $this->assertCount(0, $this->fetchVersionRows());
    }

    // --- propagateTranslation() delegates to the real SegmentTranslationDao SQL ---

    #[Test]
    public function propagateTranslationRunsRealQueryAndReturnsRenderedShape(): void
    {
        $handler = $this->makeHandler();

        $result = $handler->propagateTranslation($this->makeTranslation('ciao mondo'));

        // The real propagation query executed and PropagationApi::render() shaped the result.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('propagated_ids', $result);
        $this->assertArrayHasKey('segments_for_propagation', $result);
    }

    // --- storeTranslationEvent() fan-out (event-side seams, no real version SQL) ---

    #[Test]
    public function storeTranslationEventSavesSourceEvent(): void
    {
        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('insertStruct')->willReturn(1);

        $eventsHandler = new TestableEventsHandler($this->makeChunk(), $eventDao);

        $handler = $this->makeHandler();
        $handler->setEventsHandlerOverride($eventsHandler);
        $handler->setBatchReviewProcessorOverride($this->createStub(BatchReviewProcessor::class));

        $handler->storeTranslationEvent([
            'user'             => null,
            'translation'      => $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED),
            'old_translation'  => $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED),
            'source_page_code' => SourcePages::SOURCE_PAGE_TRANSLATE,
            'chunk'            => $this->makeChunk(),
            'features'         => $this->createStub(FeatureSet::class),
            'project'          => $this->makeProject(),
        ]);

        $this->assertCount(1, $eventsHandler->getEvents());
    }

    #[Test]
    public function storeTranslationEventWithPropagation(): void
    {
        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('insertStruct')->willReturn(1);

        $eventsHandler = new TestableEventsHandler($this->makeChunk(), $eventDao);

        $handler = $this->makeHandler();
        $handler->setEventsHandlerOverride($eventsHandler);
        $handler->setBatchReviewProcessorOverride($this->createStub(BatchReviewProcessor::class));

        $propagatedSegment = $this->makeTranslation('propagated text', TranslationStatus::STATUS_TRANSLATED);

        $handler->storeTranslationEvent([
            'user'             => new UserStruct(['uid' => 42]),
            'translation'      => $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED),
            'old_translation'  => $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED),
            'source_page_code' => SourcePages::SOURCE_PAGE_TRANSLATE,
            'chunk'            => $this->makeChunk(),
            'features'         => $this->createStub(FeatureSet::class),
            'project'          => $this->makeProject(),
            'propagation'      => [
                'segments_for_propagation' => [
                    'propagated' => [
                        'not_ice' => ['object' => [$propagatedSegment]],
                    ],
                ],
            ],
        ]);

        $this->assertCount(2, $eventsHandler->getEvents());
    }

    #[Test]
    public function storeTranslationEventWithIcePropagation(): void
    {
        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('insertStruct')->willReturn(1);

        $eventsHandler = new TestableEventsHandler($this->makeChunk(), $eventDao);

        $handler = $this->makeHandler();
        $handler->setEventsHandlerOverride($eventsHandler);
        $handler->setBatchReviewProcessorOverride($this->createStub(BatchReviewProcessor::class));

        $iceSegment    = $this->makeTranslation('ice text', TranslationStatus::STATUS_TRANSLATED);
        $notIceSegment = $this->makeTranslation('not ice text', TranslationStatus::STATUS_TRANSLATED);

        $handler->storeTranslationEvent([
            'user'             => null,
            'translation'      => $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED),
            'old_translation'  => $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED),
            'source_page_code' => SourcePages::SOURCE_PAGE_TRANSLATE,
            'chunk'            => $this->makeChunk(),
            'features'         => $this->createStub(FeatureSet::class),
            'project'          => $this->makeProject(),
            'propagation'      => [
                'segments_for_propagation' => [
                    'propagated' => [
                        'not_ice' => ['object' => [$notIceSegment]],
                        'ice'     => ['object' => [$iceSegment]],
                    ],
                ],
            ],
        ]);

        $this->assertCount(3, $eventsHandler->getEvents());
    }

    #[Test]
    public function storeTranslationEventWrapsExceptionInRuntimeException(): void
    {
        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('insertStruct')->willThrowException(new \Exception('DB error'));

        $eventsHandler = new TestableEventsHandler($this->makeChunk(), $eventDao);

        $handler = $this->makeHandler();
        $handler->setEventsHandlerOverride($eventsHandler);
        $handler->setBatchReviewProcessorOverride($this->createStub(BatchReviewProcessor::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB error');
        $this->expectExceptionCode(-2000);

        $handler->storeTranslationEvent([
            'user'             => null,
            'translation'      => $this->makeTranslation('new', TranslationStatus::STATUS_TRANSLATED),
            'old_translation'  => $this->makeTranslation('old', TranslationStatus::STATUS_TRANSLATED),
            'source_page_code' => SourcePages::SOURCE_PAGE_TRANSLATE,
            'chunk'            => $this->makeChunk(),
            'features'         => $this->createStub(FeatureSet::class),
            'project'          => $this->makeProject(),
        ]);
    }
}
