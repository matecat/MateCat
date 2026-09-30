<?php

namespace Matecat\Core\Plugins\TranslationVersions;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\QualityReport\HistoryElementStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use Utils\Registry\AppConfig;

/**
 * historyEvents() feeds QualityReportSegmentModel::_populateHistory(), which is called once per
 * page of the quality report with the whole batch of segment ids. Every test here seeds more than
 * the one segment or the one job the assertion is about, because that is the shape the production
 * call has and the shape the query used to get wrong.
 */
#[Group('PersistenceNeeded')]
#[CoversClass(TranslationVersionDao::class)]
class TranslationVersionDaoHistoryEventsTest extends AbstractTest
{
    private const int JOB_ID = 999811;

    /** A second job of the same project: another target language over the same segments. */
    private const int OTHER_JOB_ID = 999812;

    private const int SEGMENT_ID = 999711;
    private const int OTHER_SEGMENT_ID = 999712;

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
        $jobs = self::JOB_ID . ', ' . self::OTHER_JOB_ID;
        $conn->exec("DELETE FROM segment_translation_events WHERE id_job IN ( $jobs )");
        $conn->exec("DELETE FROM segment_translation_versions WHERE id_job IN ( $jobs )");
        $conn->exec("DELETE FROM segment_translations WHERE id_job IN ( $jobs )");
    }

    private function insertEvent(int $segmentId, int $versionNumber, int $sourcePage, string $status, int $jobId = self::JOB_ID): void
    {
        $this->database->getConnection()->exec(
            "INSERT INTO segment_translation_events
                (id_job, id_segment, uid, version_number, source_page, status, create_date)
             VALUES
                ($jobId, $segmentId, 0, $versionNumber, $sourcePage, '$status', '2026-03-0$sourcePage 10:00:00')"
        );
    }

    private function insertVersion(int $segmentId, int $versionNumber, ?string $translation, int $jobId = self::JOB_ID): void
    {
        $conn = $this->database->getConnection();
        $text = $translation === null ? 'NULL' : $conn->quote($translation);
        $conn->exec(
            "INSERT INTO segment_translation_versions
                (id_job, id_segment, translation, version_number, creation_date, raw_diff)
             VALUES
                ($jobId, $segmentId, $text, $versionNumber, '2026-02-01 08:00:00', '[\"diff\"]')"
        );
    }

    private function insertCurrentTranslation(int $segmentId, int $versionNumber, string $translation, int $jobId = self::JOB_ID): void
    {
        $conn = $this->database->getConnection();
        $conn->exec(
            "INSERT INTO segment_translations
                (id_segment, id_job, segment_hash, status, translation, version_number)
             VALUES
                ($segmentId, $jobId, 'hash_test', 'APPROVED', " . $conn->quote($translation) . ", $versionNumber)"
        );
    }

    /**
     * Seeds one segment edited twice and then approved: version 0 and 1 are history, version 2 is
     * current, and an event exists for each of the two saves.
     */
    private function seedTwiceEditedSegment(int $segmentId, string $label): void
    {
        $this->insertVersion($segmentId, 0, "$label initial");
        $this->insertVersion($segmentId, 1, "$label v1");
        $this->insertCurrentTranslation($segmentId, 2, "$label current");
        $this->insertEvent($segmentId, 1, 1, 'TRANSLATED');
        $this->insertEvent($segmentId, 2, 2, 'APPROVED');
    }

    /**
     * The query carries no ORDER BY — the panel sorts — so every assertion orders by version
     * number itself rather than leaning on the order the UNION happens to return.
     *
     * @param HistoryElementStruct[] $rows
     *
     * @return list<HistoryElementStruct>
     */
    private function rowsOfSegment(array $rows, int $segmentId): array
    {
        $segmentRows = array_values(array_filter($rows, fn(HistoryElementStruct $row): bool => (int)$row->id_segment === $segmentId));
        usort($segmentRows, fn(HistoryElementStruct $a, HistoryElementStruct $b): int => (int)$a->version_number <=> (int)$b->version_number);

        return $segmentRows;
    }

    /**
     * The quality report asks for a page of segments at a time. The GROUP BY used to omit
     * id_segment, so two segments sharing a (version_number, source_page) pair — which every
     * segment of a job does, they all start at version 1 on source page 1 — collapsed into a
     * single row and every segment but one lost its history.
     */
    #[Test]
    public function historyEventsKeepsTheHistoryOfEverySegmentInTheBatch(): void
    {
        $this->seedTwiceEditedSegment(self::SEGMENT_ID, 'first');
        $this->seedTwiceEditedSegment(self::OTHER_SEGMENT_ID, 'second');

        $dao = new TranslationVersionDao(obtainTestDatabase());
        $rows = $dao->historyEvents([self::SEGMENT_ID, self::OTHER_SEGMENT_ID], self::JOB_ID);

        foreach ([self::SEGMENT_ID => 'first', self::OTHER_SEGMENT_ID => 'second'] as $segmentId => $label) {
            $segmentRows = $this->rowsOfSegment($rows, $segmentId);

            $this->assertCount(3, $segmentRows, "segment $segmentId must keep version 0 and both of its events");
            $this->assertSame(
                [0, 1, 2],
                array_map(fn(HistoryElementStruct $row): int => (int)$row->version_number, $segmentRows)
            );
            $this->assertSame(
                ["$label initial", "$label v1", "$label current"],
                array_map(fn(HistoryElementStruct $row): string => $row->translation, $segmentRows),
                "segment $segmentId must carry its own translations, not another segment's"
            );
        }
    }

    /**
     * Every target-language job of a project is linked to the same files, so it is the same
     * id_segment in both. The event branch used to filter on id_segment alone, which let the other
     * language's events in — carrying this job's translation text, because the join to the version
     * rows was not scoped by id_job either.
     */
    #[Test]
    public function historyEventsIgnoresTheEventsOfAnotherJobSharingTheSegment(): void
    {
        $this->seedTwiceEditedSegment(self::SEGMENT_ID, 'this job');

        // The same segment in the other target language, approved at a step this job never reached.
        $this->insertVersion(self::SEGMENT_ID, 1, 'other language v1', self::OTHER_JOB_ID);
        $this->insertCurrentTranslation(self::SEGMENT_ID, 2, 'other language current', self::OTHER_JOB_ID);
        $this->insertEvent(self::SEGMENT_ID, 1, 3, 'APPROVED2', self::OTHER_JOB_ID);

        $dao = new TranslationVersionDao(obtainTestDatabase());
        $rows = $this->rowsOfSegment($dao->historyEvents([self::SEGMENT_ID], self::JOB_ID), self::SEGMENT_ID);

        $this->assertSame([null, 'TRANSLATED', 'APPROVED'], array_map(fn(HistoryElementStruct $row): ?string => $row->status, $rows));
        $this->assertSame(
            ['this job initial', 'this job v1', 'this job current'],
            array_map(fn(HistoryElementStruct $row): string => $row->translation, $rows),
            "the other language's event must not appear in this job's history"
        );
    }

    /**
     * Version 0 is the text as it stood before the first save, and no event points at it — events
     * carry the version number the save produced. It is emitted with a null source_page, status
     * and create_date, which is what the panel filters on to leave it out of the status list.
     */
    #[Test]
    public function historyEventsReturnsTheFirstVersionThatNoEventCovers(): void
    {
        $this->insertVersion(self::SEGMENT_ID, 0, 'the pre-translated text');
        $this->insertCurrentTranslation(self::SEGMENT_ID, 1, 'the translated text');
        $this->insertEvent(self::SEGMENT_ID, 1, 1, 'TRANSLATED');

        $dao = new TranslationVersionDao(obtainTestDatabase());
        $rows = $this->rowsOfSegment($dao->historyEvents([self::SEGMENT_ID], self::JOB_ID), self::SEGMENT_ID);

        $this->assertCount(2, $rows);

        $firstVersion = $rows[0];
        $this->assertSame(0, (int)$firstVersion->version_number);
        $this->assertSame('the pre-translated text', $firstVersion->translation);
        $this->assertNull($firstVersion->source_page);
        $this->assertNull($firstVersion->status);
        $this->assertNull($firstVersion->create_date);
        $this->assertSame('2026-02-01 08:00:00', $firstVersion->creation_date);
    }

    /**
     * TranslationIssueModel::saveDiff() writes a version row for the version that is still current
     * in segment_translations, with raw_diff set and translation left NULL. The inner UNION then
     * returns two rows for that version and the collapsed row used to be able to take the null
     * one — which is not merely an empty history entry: HistoryElementStruct::$translation is a
     * non-nullable string, so PDO::FETCH_CLASS fatals on it and the whole request 500s.
     */
    #[Test]
    public function historyEventsKeepsTheTranslationWhenAnIssueDiffShadowsTheCurrentVersion(): void
    {
        $this->insertCurrentTranslation(self::SEGMENT_ID, 2, 'the text under review');
        // The reviewer raised an issue on version 2 while version 2 is still the current one.
        $this->insertVersion(self::SEGMENT_ID, 2, null);
        $this->insertEvent(self::SEGMENT_ID, 2, 2, 'APPROVED');

        $dao = new TranslationVersionDao(obtainTestDatabase());
        $rows = $dao->historyEvents([self::SEGMENT_ID], self::JOB_ID);

        $this->assertCount(1, $rows);
        $this->assertSame('the text under review', $rows[0]->translation);
    }

    /**
     * The same null-translation row at version 0, written before the translator ever saved. There
     * is no first version to show, and the first branch cannot fall back to segment_translations,
     * so the row is skipped rather than fetched into a non-nullable string property.
     */
    #[Test]
    public function historyEventsSkipsAFirstVersionThatHoldsOnlyAnIssueDiff(): void
    {
        $this->insertVersion(self::SEGMENT_ID, 0, null);
        $this->insertCurrentTranslation(self::SEGMENT_ID, 0, 'the untouched pre-translation');
        $this->insertEvent(self::SEGMENT_ID, 0, 1, 'TRANSLATED');

        $dao = new TranslationVersionDao(obtainTestDatabase());
        $rows = $dao->historyEvents([self::SEGMENT_ID], self::JOB_ID);

        $this->assertCount(1, $rows, 'only the event row survives, the empty first version is dropped');
        $this->assertSame('TRANSLATED', $rows[0]->status);
        $this->assertSame('the untouched pre-translation', $rows[0]->translation);
    }

    #[Test]
    public function historyEventsReturnsNothingWhenTheSegmentHasNoHistory(): void
    {
        $dao = new TranslationVersionDao(obtainTestDatabase());

        $this->assertSame([], $dao->historyEvents([self::SEGMENT_ID], self::JOB_ID));
    }
}
