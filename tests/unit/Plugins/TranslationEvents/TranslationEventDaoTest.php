<?php

namespace unit\Plugins\TranslationEvents;

use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use Plugins\Features\TranslationEvents\Model\TranslationEventStruct;
use TestHelpers\AbstractTest;
use Utils\Constants\TranslationStatus;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class TranslationEventDaoTest extends AbstractTest
{
    private const int JOB_ID = 999901;
    private const int SEGMENT_ID_1 = 999801;
    private const int SEGMENT_ID_2 = 999802;
    private const int SEGMENT_ID_3 = 999803;
    private const int UID = 999001;
    private const int SOURCE_PAGE_TRANSLATION = 1;
    private const int SOURCE_PAGE_REVISION = 2;

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
    }

    protected function tearDown(): void
    {
        $this->deleteFixtureRows();
        parent::tearDown();
    }

    private function deleteFixtureRows(): void
    {
        $this->database->getConnection()->exec(
            "DELETE FROM segment_translation_events WHERE id_job = " . self::JOB_ID
        );
    }

    private function insertEvent(
        int $idSegment,
        string $status,
        int $sourcePage = self::SOURCE_PAGE_TRANSLATION,
        int $finalRevision = 0,
        ?int $timeToEdit = 1000,
        int $versionNumber = 1,
    ): int {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(
            "INSERT INTO segment_translation_events 
                (id_job, id_segment, uid, version_number, source_page, status, create_date, final_revision, time_to_edit)
             VALUES 
                (:id_job, :id_segment, :uid, :version_number, :source_page, :status, NOW(), :final_revision, :time_to_edit)"
        );
        $stmt->execute([
            'id_job'         => self::JOB_ID,
            'id_segment'     => $idSegment,
            'uid'            => self::UID,
            'version_number' => $versionNumber,
            'source_page'    => $sourcePage,
            'status'         => $status,
            'final_revision' => $finalRevision,
            'time_to_edit'   => $timeToEdit,
        ]);

        return (int) $conn->lastInsertId();
    }

    // ─── getLatestEventForSegment ────────────────────────────────────────────────

    #[Test]
    public function getLatestEventForSegmentReturnsLatestNonDraft(): void
    {
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_TRANSLATED);
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_APPROVED);
        // Draft should be excluded
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_DRAFT);

        $dao = new TranslationEventDao();
        $result = $dao->getLatestEventForSegment(self::JOB_ID, self::SEGMENT_ID_1);

        $this->assertInstanceOf(TranslationEventStruct::class, $result);
        $this->assertEquals(TranslationStatus::STATUS_APPROVED, $result->status);
    }

    #[Test]
    public function getLatestEventForSegmentReturnsNullWhenNoEvents(): void
    {
        $dao = new TranslationEventDao();
        $result = $dao->getLatestEventForSegment(self::JOB_ID, self::SEGMENT_ID_1);

        $this->assertNull($result);
    }

    #[Test]
    public function getLatestEventForSegmentReturnsNullWhenOnlyDrafts(): void
    {
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_DRAFT);
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_DRAFT);

        $dao = new TranslationEventDao();
        $result = $dao->getLatestEventForSegment(self::JOB_ID, self::SEGMENT_ID_1);

        $this->assertNull($result);
    }

    // ─── getAllFinalRevisionsForSegment ───────────────────────────────────────────

    #[Test]
    public function getAllFinalRevisionsForSegmentReturnsFinalOnly(): void
    {
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_APPROVED, self::SOURCE_PAGE_REVISION, 1);
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_APPROVED, self::SOURCE_PAGE_REVISION, 0);
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_TRANSLATED, self::SOURCE_PAGE_TRANSLATION, 1);
        // Draft with final_revision=1 should be excluded
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_DRAFT, self::SOURCE_PAGE_REVISION, 1);

        $dao = new TranslationEventDao();
        $results = $dao->getAllFinalRevisionsForSegment(self::JOB_ID, self::SEGMENT_ID_1);

        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(TranslationEventStruct::class, $results);
        foreach ($results as $event) {
            $this->assertEquals(1, $event->final_revision);
            $this->assertNotEquals(TranslationStatus::STATUS_DRAFT, $event->status);
        }
    }

    #[Test]
    public function getAllFinalRevisionsForSegmentReturnsEmptyWhenNone(): void
    {
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_TRANSLATED, self::SOURCE_PAGE_TRANSLATION, 0);

        $dao = new TranslationEventDao();
        $results = $dao->getAllFinalRevisionsForSegment(self::JOB_ID, self::SEGMENT_ID_1);

        $this->assertSame([], $results);
    }

    // ─── getLatestEventsInSegmentInterval ────────────────────────────────────────

    #[Test]
    public function getLatestEventsInSegmentIntervalReturnsLatestPerSegment(): void
    {
        // Segment 1: two events, should return latest (higher id)
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_TRANSLATED);
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_APPROVED);
        // Segment 2: one event
        $this->insertEvent(self::SEGMENT_ID_2, TranslationStatus::STATUS_TRANSLATED);
        // Segment 3: outside range — should not appear
        $this->insertEvent(self::SEGMENT_ID_3, TranslationStatus::STATUS_TRANSLATED);

        $dao = new TranslationEventDao();
        $results = $dao->getLatestEventsInSegmentInterval(self::JOB_ID, self::SEGMENT_ID_1, self::SEGMENT_ID_2);

        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(TranslationEventStruct::class, $results);

        // Results ordered by id_segment
        $this->assertEquals(self::SEGMENT_ID_1, $results[0]->id_segment);
        $this->assertEquals(TranslationStatus::STATUS_APPROVED, $results[0]->status);
        $this->assertEquals(self::SEGMENT_ID_2, $results[1]->id_segment);
    }

    #[Test]
    public function getLatestEventsInSegmentIntervalReturnsEmptyWhenNoEvents(): void
    {
        $dao = new TranslationEventDao();
        $results = $dao->getLatestEventsInSegmentInterval(self::JOB_ID, self::SEGMENT_ID_1, self::SEGMENT_ID_2);

        $this->assertSame([], $results);
    }

    // ─── getTteForSegments ───────────────────────────────────────────────────────

    #[Test]
    public function getTteForSegmentsSumsTimeToEditGroupedBySegmentAndSourcePage(): void
    {
        // Segment 1, translation page: 1000 + 2000 = 3000
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_TRANSLATED, self::SOURCE_PAGE_TRANSLATION, 0, 1000);
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_APPROVED, self::SOURCE_PAGE_TRANSLATION, 0, 2000);
        // Segment 1, revision page: 500
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_APPROVED, self::SOURCE_PAGE_REVISION, 1, 500);
        // Segment 2, translation page: 1500
        $this->insertEvent(self::SEGMENT_ID_2, TranslationStatus::STATUS_TRANSLATED, self::SOURCE_PAGE_TRANSLATION, 0, 1500);

        $dao = new TranslationEventDao();
        $results = $dao->getTteForSegments([self::SEGMENT_ID_1, self::SEGMENT_ID_2], self::JOB_ID);

        $this->assertNotNull($results);
        $this->assertCount(3, $results); // seg1/page1, seg1/page2, seg2/page1
        $this->assertContainsOnlyInstancesOf(ShapelessConcreteStruct::class, $results);

        // Results ordered by id_segment, source_page
        $this->assertEquals(self::SEGMENT_ID_1, $results[0]->id_segment);
        $this->assertEquals(self::SOURCE_PAGE_TRANSLATION, $results[0]->source_page);
        $this->assertEquals(3000, (int) $results[0]->tte);

        $this->assertEquals(self::SEGMENT_ID_1, $results[1]->id_segment);
        $this->assertEquals(self::SOURCE_PAGE_REVISION, $results[1]->source_page);
        $this->assertEquals(500, (int) $results[1]->tte);

        $this->assertEquals(self::SEGMENT_ID_2, $results[2]->id_segment);
        $this->assertEquals(self::SOURCE_PAGE_TRANSLATION, $results[2]->source_page);
        $this->assertEquals(1500, (int) $results[2]->tte);
    }

    #[Test]
    public function getTteForSegmentsReturnsNullWhenNoEvents(): void
    {
        $dao = new TranslationEventDao();
        $results = $dao->getTteForSegments([self::SEGMENT_ID_1], self::JOB_ID);

        $this->assertNull($results);
    }

    // ─── unsetFinalRevisionFlag ──────────────────────────────────────────────────

    #[Test]
    public function unsetFinalRevisionFlagUpdatesMatchingRows(): void
    {
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_APPROVED, self::SOURCE_PAGE_REVISION, 1);
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_APPROVED, self::SOURCE_PAGE_TRANSLATION, 1);
        $this->insertEvent(self::SEGMENT_ID_2, TranslationStatus::STATUS_APPROVED, self::SOURCE_PAGE_REVISION, 1);

        $dao = new TranslationEventDao();
        $rowCount = $dao->unsetFinalRevisionFlag(
            self::JOB_ID,
            [self::SEGMENT_ID_1, self::SEGMENT_ID_2],
            [self::SOURCE_PAGE_REVISION]
        );

        $this->assertEquals(2, $rowCount);

        // Verify the flags were actually unset
        $rows = $this->database->getConnection()
            ->query(
                "SELECT id_segment, source_page, final_revision FROM segment_translation_events 
                 WHERE id_job = " . self::JOB_ID . " ORDER BY id_segment, source_page"
            )
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(3, $rows);

        // Segment 1, translation page (source_page=1): unchanged
        $this->assertEquals(1, (int) $rows[0]['final_revision']);
        $this->assertEquals(self::SOURCE_PAGE_TRANSLATION, (int) $rows[0]['source_page']);

        // Segment 1, revision page (source_page=2): unset
        $this->assertEquals(0, (int) $rows[1]['final_revision']);
        $this->assertEquals(self::SOURCE_PAGE_REVISION, (int) $rows[1]['source_page']);

        // Segment 2, revision page (source_page=2): unset
        $this->assertEquals(0, (int) $rows[2]['final_revision']);
        $this->assertEquals(self::SOURCE_PAGE_REVISION, (int) $rows[2]['source_page']);
    }

    #[Test]
    public function unsetFinalRevisionFlagReturnsZeroWhenNoMatch(): void
    {
        $this->insertEvent(self::SEGMENT_ID_1, TranslationStatus::STATUS_TRANSLATED, self::SOURCE_PAGE_TRANSLATION, 0);

        $dao = new TranslationEventDao();
        $rowCount = $dao->unsetFinalRevisionFlag(
            self::JOB_ID,
            [self::SEGMENT_ID_1],
            [self::SOURCE_PAGE_REVISION]
        );

        $this->assertEquals(0, $rowCount);
    }
}
