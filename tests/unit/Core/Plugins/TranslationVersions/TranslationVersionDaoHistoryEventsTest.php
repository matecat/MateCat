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

    private function insertEvent(
        int $segmentId,
        int $versionNumber,
        int $sourcePage,
        string $status,
        int $jobId = self::JOB_ID,
        ?string $createDate = null
    ): void {
        $createDate ??= "2026-03-0$sourcePage 10:00:00";
        $this->database->getConnection()->exec(
            "INSERT INTO segment_translation_events
                (id_job, id_segment, uid, version_number, source_page, status, create_date)
             VALUES
                ($jobId, $segmentId, 0, $versionNumber, $sourcePage, '$status', '$createDate')"
        );
    }

    private function insertVersion(
        int $segmentId,
        int $versionNumber,
        ?string $translation,
        int $jobId = self::JOB_ID,
        string $creationDate = '2026-02-01 08:00:00'
    ): void {
        $conn = $this->database->getConnection();
        $text = $translation === null ? 'NULL' : $conn->quote($translation);
        $conn->exec(
            "INSERT INTO segment_translation_versions
                (id_job, id_segment, translation, version_number, creation_date, raw_diff)
             VALUES
                ($jobId, $segmentId, $text, $versionNumber, '$creationDate', '[\"diff\"]')"
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
     * Sorts by version number so these assertions do not depend on the query's ordering, which
     * historyEventsReturnsEveryEventInOrderWithItsOwnDate() covers on its own.
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

    /**
     * The quality report case this was reported on: translated twice, approved in R1, approved in
     * R2 and approved in R2 again without a change. A versions row is written when its text is
     * replaced, so each one's creation_date is the time of the *next* save. The history used to
     * date every event with it, so each step showed the following step's time. It also grouped by
     * version and step, so the second R2 approve, which keeps version 4, merged into the first.
     */
    #[Test]
    public function historyEventsReturnsEveryEventInOrderWithItsOwnDate(): void
    {
        // Each version row is dated when the next save replaced it, an hour off like the
        // TIMESTAMP column reads back in UTC against the DATETIME event dates.
        $this->insertVersion(self::SEGMENT_ID, 0, 'pre-translation', self::JOB_ID, '2026-10-19 04:35:32');
        $this->insertVersion(self::SEGMENT_ID, 1, 'first translation', self::JOB_ID, '2026-10-19 04:35:37');
        $this->insertVersion(self::SEGMENT_ID, 2, 'second translation', self::JOB_ID, '2026-10-19 12:19:01');
        $this->insertVersion(self::SEGMENT_ID, 3, 'r1 revision', self::JOB_ID, '2026-10-20 10:14:12');
        $this->insertCurrentTranslation(self::SEGMENT_ID, 4, 'r2 revision');

        $this->insertEvent(self::SEGMENT_ID, 1, 1, 'TRANSLATED', self::JOB_ID, '2026-10-19 06:35:32');
        $this->insertEvent(self::SEGMENT_ID, 2, 1, 'TRANSLATED', self::JOB_ID, '2026-10-19 06:35:37');
        $this->insertEvent(self::SEGMENT_ID, 3, 2, 'APPROVED', self::JOB_ID, '2026-10-19 14:19:01');
        $this->insertEvent(self::SEGMENT_ID, 4, 3, 'APPROVED2', self::JOB_ID, '2026-10-20 12:14:12');
        $this->insertEvent(self::SEGMENT_ID, 4, 3, 'APPROVED2', self::JOB_ID, '2026-10-20 12:14:23');

        // Another segment of the page, interleaved by id, must not break this segment's order.
        $this->seedTwiceEditedSegment(self::OTHER_SEGMENT_ID, 'other');

        $dao = new TranslationVersionDao(obtainTestDatabase());
        $rows = array_values(array_filter(
            $dao->historyEvents([self::SEGMENT_ID, self::OTHER_SEGMENT_ID], self::JOB_ID),
            fn(HistoryElementStruct $row): bool => (int)$row->id_segment === self::SEGMENT_ID
        ));

        $this->assertSame(
            [
                [0, null, null, 'pre-translation'],
                [1, 'TRANSLATED', '2026-10-19 06:35:32', 'first translation'],
                [2, 'TRANSLATED', '2026-10-19 06:35:37', 'second translation'],
                [3, 'APPROVED', '2026-10-19 14:19:01', 'r1 revision'],
                [4, 'APPROVED2', '2026-10-20 12:14:12', 'r2 revision'],
                [4, 'APPROVED2', '2026-10-20 12:14:23', 'r2 revision'],
            ],
            array_map(
                fn(HistoryElementStruct $row): array => [(int)$row->version_number, $row->status, $row->create_date, $row->translation],
                $rows
            ),
            'one row per event, in the order they happened, each with its own date'
        );

        $this->assertSame('2026-10-19 04:35:32', $rows[0]->creation_date, 'version 0 keeps the only date it has');
        foreach (array_slice($rows, 1) as $eventRow) {
            $this->assertNull($eventRow->creation_date, 'an event row must not carry the next save\'s time');
            $this->assertNotNull($eventRow->id);
        }
    }

    #[Test]
    public function historyEventsReturnsNothingWhenTheSegmentHasNoHistory(): void
    {
        $dao = new TranslationVersionDao(obtainTestDatabase());

        $this->assertSame([], $dao->historyEvents([self::SEGMENT_ID], self::JOB_ID));
    }
}
