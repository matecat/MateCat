<?php

namespace Matecat\Core\Plugins\TranslationVersions;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\QualityReport\SegmentEventsStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
#[CoversClass(TranslationVersionDao::class)]
class TranslationVersionDaoGetAllRelevantEventsTest extends AbstractTest
{
    private const int JOB_ID = 999801;
    private const int SEGMENT_ID = 999701;

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
    }

    protected function tearDown(): void
    {
        $this->deleteFixtureRows();
        parent::tearDown();
    }

    private function deleteFixtureRows(): void
    {
        $conn = $this->database->getConnection();
        $conn->exec("DELETE FROM segment_translation_events WHERE id_job = " . self::JOB_ID);
        $conn->exec("DELETE FROM segment_translation_versions WHERE id_job = " . self::JOB_ID);
        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . self::JOB_ID);
    }

    private function insertEvent(int $versionNumber, int $sourcePage, int $finalRevision = 0): void
    {
        $this->database->getConnection()->exec(
            "INSERT INTO segment_translation_events
                (id_job, id_segment, uid, version_number, source_page, status, final_revision)
             VALUES
                (" . self::JOB_ID . ", " . self::SEGMENT_ID . ", 0, $versionNumber, $sourcePage, 'TRANSLATED', $finalRevision)"
        );
    }

    private function insertVersion(int $versionNumber, string $translation): void
    {
        $conn = $this->database->getConnection();
        $conn->exec(
            "INSERT INTO segment_translation_versions
                (id_job, id_segment, translation, version_number)
             VALUES
                (" . self::JOB_ID . ", " . self::SEGMENT_ID . ", " . $conn->quote($translation) . ", $versionNumber)"
        );
    }

    private function insertCurrentTranslation(int $versionNumber, string $translation): void
    {
        $conn = $this->database->getConnection();
        $conn->exec(
            "INSERT INTO segment_translations
                (id_segment, id_job, segment_hash, status, translation, version_number)
             VALUES
                (" . self::SEGMENT_ID . ", " . self::JOB_ID . ", 'hash_test', 'APPROVED', " . $conn->quote($translation) . ", $versionNumber)"
        );
    }

    /**
     * Reproduces the production bug: events at v2 and v3 exist but v2 is missing from
     * segment_translation_versions. With RIGHT JOIN + COALESCE, the source_page=1 row must
     * still be returned (with null translation) rather than dropped entirely.
     */
    #[Test]
    public function getAllRelevantEventsReturnsRowWhenVersionRecordMissing(): void
    {
        // Events: v2 (source_page=1), v3 (source_page=2, final_revision=1)
        $this->insertEvent(2, 1, 0);
        $this->insertEvent(3, 2, 1);

        // Versions: v0 and v1 only — v2 is intentionally absent (race condition scenario)
        $this->insertVersion(0, 'initial');
        $this->insertVersion(1, 'first edit');

        // Current state: segment_translations at v3
        $this->insertCurrentTranslation(3, 'approved text');

        $dao = new TranslationVersionDao(obtainTestDatabase());
        $results = $dao->getAllRelevantEvents([self::SEGMENT_ID], self::JOB_ID);

        $this->assertCount(2, $results, 'Both source_page rows must be returned even when version record is missing');

        $sourcePagesReturned = array_map(fn(SegmentEventsStruct $e): int => (int)$e->source_page, $results);
        sort($sourcePagesReturned);
        $this->assertSame([1, 2], $sourcePagesReturned, 'source_page=1 (TRANSLATED) must not be dropped');
    }

    /**
     * Verifies that when the version record is missing, the row still carries the correct
     * segment id (via COALESCE on ste.id_segment), allowing the caller to match it to a segment.
     */
    #[Test]
    public function getAllRelevantEventsReturnsCorrectSegmentIdWhenVersionMissing(): void
    {
        // Event at v2 TRANSLATED, no corresponding version record
        $this->insertEvent(2, 1, 0);
        $this->insertCurrentTranslation(2, 'current text');

        $dao = new TranslationVersionDao(obtainTestDatabase());
        $results = $dao->getAllRelevantEvents([self::SEGMENT_ID], self::JOB_ID);

        $this->assertCount(1, $results);
        $this->assertSame(self::SEGMENT_ID, (int)$results[0]->id_segment,
            'id_segment must be populated from events table when version record is absent');
    }

    /**
     * Happy path: all version records present. The query returns the correct translation text
     * for each source_page using the matching version record.
     */
    #[Test]
    public function getAllRelevantEventsReturnsCorrectTranslationWhenAllVersionsPresent(): void
    {
        // v2 TRANSLATED (source_page=1), v3 APPROVED2 (source_page=3, final_revision=1)
        $this->insertEvent(2, 1, 0);
        $this->insertEvent(3, 3, 1);

        // v2 present in segment_translation_versions
        $this->insertVersion(1, 'v1 text');
        $this->insertVersion(2, 'translator final text');

        // Current: v3
        $this->insertCurrentTranslation(3, 'approved2 text');

        $dao = new TranslationVersionDao(obtainTestDatabase());
        $results = $dao->getAllRelevantEvents([self::SEGMENT_ID], self::JOB_ID);

        $this->assertCount(2, $results);

        $bySourcePage = [];
        foreach ($results as $event) {
            $bySourcePage[(int)$event->source_page] = $event;
        }

        $this->assertArrayHasKey(1, $bySourcePage);
        $this->assertSame('translator final text', $bySourcePage[1]->translation,
            'source_page=1 must carry the translation from segment_translation_versions at MAX(version_number)');

        $this->assertArrayHasKey(3, $bySourcePage);
        $this->assertSame('approved2 text', $bySourcePage[3]->translation,
            'source_page=3 must carry the translation from segment_translations (current version)');
    }

    /**
     * Verifies that an empty result is returned when there are no events at all.
     */
    #[Test]
    public function getAllRelevantEventsReturnsEmptyWhenNoEvents(): void
    {
        $dao = new TranslationVersionDao(obtainTestDatabase());
        $results = $dao->getAllRelevantEvents([self::SEGMENT_ID], self::JOB_ID);

        $this->assertSame([], $results);
    }

    /**
     * Regression for the duplicate-row bug: a reviewer's LQA diff writes a version-0 row with
     * translation=NULL (raw_diff set) via TranslationIssueModel::saveDiff(). The translator's
     * later save must reconcile onto that same row — via getVersionNumberForTranslation() +
     * updateVersion(), as TranslationVersionsHandler::saveVersion() now does — instead of
     * inserting a second version-0 row alongside it. getAllRelevantEvents() must then return
     * exactly one row for source_page=1, carrying the translator's text, not two duplicate rows
     * for the same event.
     */
    #[Test]
    public function getAllRelevantEventsReturnsOneRowWhenAnExistingVersionIsReconciled(): void
    {
        // Event at v0, source_page=1 (TRANSLATE), not final revision.
        $this->insertEvent(0, 1, 0);

        // Simulates TranslationIssueModel::saveDiff(): a reviewer's LQA issue writes version 0
        // with raw_diff set and translation left NULL, before the translator has saved anything.
        $conn = $this->database->getConnection();
        $conn->exec(
            "INSERT INTO segment_translation_versions (id_job, id_segment, translation, version_number, raw_diff)
             VALUES (" . self::JOB_ID . ", " . self::SEGMENT_ID . ", NULL, 0, '[\"diff\"]')"
        );

        // Simulates the fixed TranslationVersionsHandler::saveVersion(): reconcile onto the
        // existing version-0 row instead of inserting a second one.
        $dao = new TranslationVersionDao(obtainTestDatabase());
        $existing = $dao->getVersionNumberForTranslation(self::JOB_ID, self::SEGMENT_ID, 0);
        $this->assertNotFalse($existing, 'precondition: the ReviewExtended row must exist');
        $existing->translation = 'translator text';
        $dao->updateVersion($existing);

        $this->insertCurrentTranslation(1, 'translator text');

        $results = $dao->getAllRelevantEvents([self::SEGMENT_ID], self::JOB_ID);

        $this->assertCount(1, $results,
            'exactly one row must be returned for source_page=1 — no duplicate from the reconciled version-0 row');
        $this->assertSame(1, (int)$results[0]->source_page);
        $this->assertSame('translator text', $results[0]->translation);

        // raw_diff on the underlying row is untouched by updateVersion() — the reviewer's diff
        // survives reconciliation on the same physical row.
        $rawDiff = $conn->query(
            "SELECT raw_diff FROM segment_translation_versions WHERE id_job = " . self::JOB_ID .
            " AND id_segment = " . self::SEGMENT_ID . " AND version_number = 0"
        )->fetchColumn();
        $this->assertSame('["diff"]', $rawDiff);
    }
}
