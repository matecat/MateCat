<?php

namespace unit\Model\Segments;

use Model\DataAccess\Database;
use Model\Segments\SegmentDisabledService;
use Model\Segments\SegmentMetadataDao;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

/**
 * Integration test verifying the segment disable/enable flow
 * as consumed by both the segment-analysis API and the get-segments API.
 *
 * segment-analysis uses: SegmentDisabledService::isDisabled() → boolean "disabled" field
 * get-segments uses:     SegmentMetadataDao::getAll()          → metadata array with "translation_disabled" entry
 *
 * This test ensures both paths stay consistent across disable/enable cycles.
 */
#[CoversClass(SegmentDisabledService::class)]
#[CoversClass(SegmentMetadataDao::class)]
#[Group('PersistenceNeeded')]
class SegmentDisableIntegrationTest extends AbstractTest
{
    private const int TEST_SEGMENT_ID   = 998001;
    private const int TEST_SEGMENT_ID_2 = 998002;

    private Database $database;
    private SegmentDisabledService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );
        $this->service = new SegmentDisabledService();
        $this->cleanFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanFixtures();

        $flusher = new \Predis\Client(AppConfig::$REDIS_SERVERS);
        $flusher->select(AppConfig::$INSTANCE_ID);
        $flusher->flushdb();

        parent::tearDown();
    }

    private function cleanFixtures(): void
    {
        $ids = implode(',', [self::TEST_SEGMENT_ID, self::TEST_SEGMENT_ID_2]);
        $this->database->getConnection()->exec(
            "DELETE FROM segment_metadata WHERE id_segment IN ($ids)"
        );
    }

    // ─── Full disable/enable lifecycle ────────────────────────────────

    #[Test]
    public function initialStateShowsNotDisabledAndEmptyMetadata(): void
    {
        // segment-analysis path: SegmentDisabledService::isDisabled()
        $this->assertFalse(
            $this->service->isDisabled(self::TEST_SEGMENT_ID),
            'Segment should not be disabled initially (segment-analysis API path)'
        );

        // get-segments path: SegmentMetadataDao::getAll()
        $metadata = SegmentMetadataDao::getAll(self::TEST_SEGMENT_ID);
        $disabledEntries = $this->filterDisabledMetadata($metadata);

        $this->assertEmpty(
            $disabledEntries,
            'Metadata should not contain translation_disabled entry initially (get-segments API path)'
        );
    }

    #[Test]
    public function afterDisableSegmentAnalysisReportsDisabledTrue(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);

        // segment-analysis path
        $this->assertTrue(
            $this->service->isDisabled(self::TEST_SEGMENT_ID),
            'Segment should be disabled after disable() call (segment-analysis API path)'
        );
    }

    #[Test]
    public function afterDisableGetSegmentsMetadataContainsTranslationDisabled(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);

        // get-segments path
        $metadata = SegmentMetadataDao::getAll(self::TEST_SEGMENT_ID);
        $disabledEntries = $this->filterDisabledMetadata($metadata);

        $this->assertCount(1, $disabledEntries, 'Expected exactly one translation_disabled metadata entry');
        $this->assertSame('1', $disabledEntries[0]->meta_value, 'translation_disabled meta_value should be "1"');
    }

    #[Test]
    public function afterEnableSegmentAnalysisReportsDisabledFalse(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);
        $this->service->enable(self::TEST_SEGMENT_ID);

        // segment-analysis path
        $this->assertFalse(
            $this->service->isDisabled(self::TEST_SEGMENT_ID),
            'Segment should not be disabled after enable() call (segment-analysis API path)'
        );
    }

    #[Test]
    public function afterEnableGetSegmentsMetadataNoLongerContainsTranslationDisabled(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);
        $this->service->enable(self::TEST_SEGMENT_ID);

        // get-segments path
        $metadata = SegmentMetadataDao::getAll(self::TEST_SEGMENT_ID);
        $disabledEntries = $this->filterDisabledMetadata($metadata);

        $this->assertEmpty(
            $disabledEntries,
            'Metadata should not contain translation_disabled entry after enable() (get-segments API path)'
        );
    }

    // ─── Full cycle in single test (regression guard) ─────────────────

    #[Test]
    public function fullDisableEnableCycleKeepsBothApiPathsConsistent(): void
    {
        // 1. Initial state: both paths agree segment is enabled
        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
        $this->assertEmpty($this->filterDisabledMetadata(SegmentMetadataDao::getAll(self::TEST_SEGMENT_ID)));

        // 2. After disable: both paths agree segment is disabled
        $this->service->disable(self::TEST_SEGMENT_ID);

        $this->assertTrue($this->service->isDisabled(self::TEST_SEGMENT_ID));
        $disabledEntries = $this->filterDisabledMetadata(SegmentMetadataDao::getAll(self::TEST_SEGMENT_ID));
        $this->assertCount(1, $disabledEntries);
        $this->assertSame('1', $disabledEntries[0]->meta_value);

        // 3. After re-enable: both paths agree segment is enabled again
        $this->service->enable(self::TEST_SEGMENT_ID);

        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
        $this->assertEmpty($this->filterDisabledMetadata(SegmentMetadataDao::getAll(self::TEST_SEGMENT_ID)));
    }

    // ─── Cross-segment isolation ──────────────────────────────────────

    #[Test]
    public function disablingOneSegmentDoesNotAffectOtherSegmentInEitherApiPath(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);

        // segment-analysis path: other segment unaffected
        $this->assertFalse(
            $this->service->isDisabled(self::TEST_SEGMENT_ID_2),
            'Disabling segment A should not affect segment B (segment-analysis path)'
        );

        // get-segments path: other segment unaffected
        $metadata = SegmentMetadataDao::getAll(self::TEST_SEGMENT_ID_2);
        $disabledEntries = $this->filterDisabledMetadata($metadata);

        $this->assertEmpty(
            $disabledEntries,
            'Disabling segment A should not create metadata on segment B (get-segments path)'
        );
    }

    // ─── Idempotency ──────────────────────────────────────────────────

    #[Test]
    public function disableIsIdempotentAndDoesNotThrowOnDoubleCall(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);
        $this->service->disable(self::TEST_SEGMENT_ID);

        $this->assertTrue($this->service->isDisabled(self::TEST_SEGMENT_ID));

        $metadata = SegmentMetadataDao::getAll(self::TEST_SEGMENT_ID);
        $disabledEntries = $this->filterDisabledMetadata($metadata);
        $this->assertCount(1, $disabledEntries);
    }

    #[Test]
    public function enableOnNonDisabledSegmentLeavesMetadataClean(): void
    {
        $this->service->enable(self::TEST_SEGMENT_ID);

        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
        $this->assertEmpty($this->filterDisabledMetadata(SegmentMetadataDao::getAll(self::TEST_SEGMENT_ID)));
    }

    // ─── Helper ───────────────────────────────────────────────────────

    /**
     * Filters metadata array to only translation_disabled entries.
     * Mirrors what the get-segments API consumer would look for.
     *
     * @param array $metadata Array of SegmentMetadataStruct
     * @return array Filtered entries with meta_key = 'translation_disabled'
     */
    private function filterDisabledMetadata(iterable $metadata): array
    {
        $items = is_array($metadata) ? $metadata : iterator_to_array($metadata);

        return array_values(array_filter($items, static function ($entry) {
            return $entry->meta_key === 'translation_disabled';
        }));
    }
}
