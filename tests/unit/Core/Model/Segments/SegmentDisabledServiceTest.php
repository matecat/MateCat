<?php

namespace Matecat\Core\Model\Segments;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Segments\SegmentDisabledService;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataMarshaller;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

#[CoversClass(SegmentDisabledService::class)]
#[Group('PersistenceNeeded')]
class SegmentDisabledServiceTest extends AbstractTest
{
    private const int TEST_SEGMENT_ID   = 999888;
    private const int TEST_SEGMENT_ID_2 = 999887;

    private Database $database;
    private SegmentDisabledService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = obtainTestDatabase(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );
        $this->service = new SegmentDisabledService(new SegmentMetadataDao($this->database));
        $this->cleanFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanFixtures();

        $flusher = (new RedisHandler())->getConnection();
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

    private function insertDisabledFlag(int $idSegment, string $value = '1'): void
    {
        $this->database->getConnection()->prepare(
            "INSERT INTO segment_metadata (id_segment, meta_key, meta_value) VALUES (?, ?, ?)"
        )->execute([$idSegment, 'translation_disabled', $value]);
    }

    private function fetchDisabledRows(int $idSegment): array
    {
        $key = 'translation_disabled';

        return $this->database->getConnection()
            ->query("SELECT * FROM segment_metadata WHERE id_segment = $idSegment AND meta_key = '$key'")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- constructor contract ---

    #[Test]
    public function constructorRequiresInjectedDao(): void
    {
        $param = (new \ReflectionMethod(SegmentDisabledService::class, '__construct'))->getParameters()[0];

        $this->assertFalse(
            $param->isOptional(),
            'SegmentMetadataDao must be a mandatory ctor dependency (no obtainTestDatabase() fallback)'
        );
        $this->assertFalse($param->allowsNull(), 'SegmentMetadataDao ctor param must not be nullable');
    }

    // --- isDisabled() ---

    #[Test]
    public function isDisabledReturnsFalseWhenNoMetadataExists(): void
    {
        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function isDisabledReturnsTrueWhenTranslationDisabledFlagIsSet(): void
    {
        $this->insertDisabledFlag(self::TEST_SEGMENT_ID);

        $this->assertTrue($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function isDisabledReturnsFalseWhenMetaValueIsNotOne(): void
    {
        $this->insertDisabledFlag(self::TEST_SEGMENT_ID, '0');

        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function isDisabledReturnsFalseForDifferentMetaKey(): void
    {
        $this->database->getConnection()->prepare(
            "INSERT INTO segment_metadata (id_segment, meta_key, meta_value) VALUES (?, ?, ?)"
        )->execute([self::TEST_SEGMENT_ID, 'some_other_key', '1']);

        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    // --- isDisabled() cache-race regression ---

    #[Test]
    public function isDisabledWithTtlZeroBypassesAStaleCachedFalse(): void
    {
        // Warm a "not disabled" cache entry at the default TTL, simulating an earlier read.
        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));

        // Insert the disabled row directly, bypassing disable() — simulates a concurrent write
        // whose eviction already ran before this cache entry was (re-)populated, i.e. the race.
        $this->insertDisabledFlag(self::TEST_SEGMENT_ID);

        // The default-TTL call still sees the stale cached "false" — this is the bug, demonstrated.
        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));

        // ttl=0 must bypass the cache and read the fresh, correct state.
        $this->assertTrue($this->service->isDisabled(self::TEST_SEGMENT_ID, 0));
    }

    // --- disable() ---

    #[Test]
    public function disableCreatesTranslationDisabledRow(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);

        $rows = $this->fetchDisabledRows(self::TEST_SEGMENT_ID);
        $this->assertCount(1, $rows);
        $this->assertSame('1', $rows[0]['meta_value']);
    }

    #[Test]
    public function disableDoesNotCrashOnDuplicateKeyWhenItsOwnIdempotencyCheckWasStaleCached(): void
    {
        // Warm disable()'s internal isDisabled() check to "not disabled" at the default TTL, as a
        // concurrent isDisabled() read racing another disable() of the same segment would have.
        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));

        // The row already exists (the concurrent disable() already committed) — segment_metadata
        // has a UNIQUE KEY on (id_segment, meta_key) and save() is a plain INSERT, so calling
        // disable() again while the stale cache still says "not disabled" would previously crash
        // on a duplicate-key error instead of returning early as the docblock promises.
        $this->insertDisabledFlag(self::TEST_SEGMENT_ID);

        $this->service->disable(self::TEST_SEGMENT_ID);

        $this->assertCount(1, $this->fetchDisabledRows(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function disableDoesNotAffectOtherSegments(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);

        $this->assertCount(0, $this->fetchDisabledRows(self::TEST_SEGMENT_ID_2));
    }

    // --- enable() ---

    #[Test]
    public function enableRemovesTranslationDisabledRow(): void
    {
        $this->insertDisabledFlag(self::TEST_SEGMENT_ID);

        $this->service->enable(self::TEST_SEGMENT_ID);

        $this->assertCount(0, $this->fetchDisabledRows(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function enableOnNonDisabledSegmentDoesNotThrow(): void
    {
        $this->service->enable(self::TEST_SEGMENT_ID);
        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    // --- Composition tests ---

    #[Test]
    public function disableThenIsDisabledReturnsTrue(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);

        $this->assertTrue($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function disableThenEnableThenIsDisabledReturnsFalse(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);
        $this->service->enable(self::TEST_SEGMENT_ID);

        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function enableDoesNotAffectOtherDisabledSegments(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);
        $this->service->disable(self::TEST_SEGMENT_ID_2);

        $this->service->enable(self::TEST_SEGMENT_ID);

        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
        $this->assertTrue($this->service->isDisabled(self::TEST_SEGMENT_ID_2));
    }

    // --- getAllInRange cache invalidation (regression) ---

    #[Test]
    public function disableBustsStaleGetAllInRangeCacheSoFreshCallReflectsDisabledState(): void
    {
        $dao = new SegmentMetadataDao($this->database);
        $ttl = 60;

        // Warm getAllInRange's cache for the range while the segment is not yet disabled,
        // simulating an earlier get-segments page load / editor scroll.
        $before = $dao->getAllInRange(self::TEST_SEGMENT_ID, self::TEST_SEGMENT_ID, $ttl);
        $this->assertArrayNotHasKey(self::TEST_SEGMENT_ID, $before);

        $this->service->disable(self::TEST_SEGMENT_ID);

        // A fresh getAllInRange call, as get-segments would make on refresh, must see the
        // disabled flag instead of the stale pre-disable cached (empty) result.
        $after = $dao->getAllInRange(self::TEST_SEGMENT_ID, self::TEST_SEGMENT_ID, $ttl);

        $this->assertArrayHasKey(self::TEST_SEGMENT_ID, $after);
        $this->assertSame(
            '1',
            $after[self::TEST_SEGMENT_ID]->find(SegmentMetadataMarshaller::TRANSLATION_DISABLED)
        );
    }

    #[Test]
    public function enableBustsStaleGetAllInRangeCacheSoFreshCallNoLongerShowsDisabled(): void
    {
        $dao = new SegmentMetadataDao($this->database);
        $ttl = 60;

        $this->insertDisabledFlag(self::TEST_SEGMENT_ID);

        // Warm getAllInRange's cache while the segment IS disabled.
        $before = $dao->getAllInRange(self::TEST_SEGMENT_ID, self::TEST_SEGMENT_ID, $ttl);
        $this->assertSame(
            '1',
            $before[self::TEST_SEGMENT_ID]->find(SegmentMetadataMarshaller::TRANSLATION_DISABLED)
        );

        $this->service->enable(self::TEST_SEGMENT_ID);

        $after = $dao->getAllInRange(self::TEST_SEGMENT_ID, self::TEST_SEGMENT_ID, $ttl);
        $this->assertArrayNotHasKey(self::TEST_SEGMENT_ID, $after);
    }
}
