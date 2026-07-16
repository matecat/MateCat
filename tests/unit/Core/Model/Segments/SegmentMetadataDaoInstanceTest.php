<?php

declare(strict_types=1);

namespace Matecat\Core\Model\Segments;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Segments\SegmentMetadataCollection;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class SegmentMetadataDaoInstanceTest extends AbstractTest
{
    private const int SEGMENT_ID_1 = 999991;
    private const int SEGMENT_ID_2 = 999992;
    private const int SEGMENT_ID_3 = 999993;

    private Database $database;
    private SegmentMetadataDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = obtainTestDatabase(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );
        $this->dao = new SegmentMetadataDao(obtainTestDatabase());
        $this->deleteFixtureRows();
    }

    protected function tearDown(): void
    {
        $this->deleteFixtureRows();

        $flusher = (new RedisHandler())->getConnection();
        $flusher->flushdb();

        parent::tearDown();
    }

    private function deleteFixtureRows(): void
    {
        $ids = implode(',', [self::SEGMENT_ID_1, self::SEGMENT_ID_2, self::SEGMENT_ID_3]);
        $this->database->getConnection()->exec(
            "DELETE FROM segment_metadata WHERE id_segment IN ($ids)"
        );
    }

    private function makeStruct(int $idSegment, string $key, string $value): SegmentMetadataStruct
    {
        $struct             = new SegmentMetadataStruct();
        $struct->id_segment = $idSegment;
        $struct->meta_key   = $key;
        $struct->meta_value = $value;

        return $struct;
    }

    private function fetchRows(int $idSegment, string $key): array
    {
        return $this->database->getConnection()
            ->query("SELECT * FROM segment_metadata WHERE id_segment = $idSegment AND meta_key = '$key'")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── save ─────────────────────────────────────────────────────────────────

    #[Test]
    public function testSaveInsertsRow(): void
    {
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_1, 'save_key', 'save_value'));

        $rows = $this->fetchRows(self::SEGMENT_ID_1, 'save_key');
        $this->assertCount(1, $rows);
        $this->assertEquals(self::SEGMENT_ID_1, (int)$rows[0]['id_segment']);
        $this->assertEquals('save_key', $rows[0]['meta_key']);
        $this->assertEquals('save_value', $rows[0]['meta_value']);
    }

    // ── get ──────────────────────────────────────────────────────────────────

    #[Test]
    public function testGetReturnsCorrectStruct(): void
    {
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_1, 'get_key', 'get_value'));

        $result = $this->dao->get(self::SEGMENT_ID_1, 'get_key', 0);

        $this->assertInstanceOf(SegmentMetadataStruct::class, $result);
        $this->assertEquals(self::SEGMENT_ID_1, $result->id_segment);
        $this->assertEquals('get_key', $result->meta_key);
        $this->assertEquals('get_value', $result->meta_value);
    }

    #[Test]
    public function testGetNonexistentReturnsNull(): void
    {
        $result = $this->dao->get(self::SEGMENT_ID_1, 'no_such_key', 0);

        $this->assertNull($result);
    }

    // ── getAll ───────────────────────────────────────────────────────────────

    #[Test]
    public function testGetAllReturnsCollectionWithAllMetadataForSegment(): void
    {
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_1, 'key_a', 'val_a'));
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_1, 'key_b', 'val_b'));

        $collection = $this->dao->getAll(self::SEGMENT_ID_1, 0);

        $this->assertInstanceOf(SegmentMetadataCollection::class, $collection);
        $this->assertCount(2, $collection);
    }

    #[Test]
    public function testGetAllReturnsEmptyCollectionWhenNoMetadataExists(): void
    {
        $collection = $this->dao->getAll(self::SEGMENT_ID_2, 0);

        $this->assertInstanceOf(SegmentMetadataCollection::class, $collection);
        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    // ── getBySegmentIds ───────────────────────────────────────────────────────

    #[Test]
    public function testGetBySegmentIdsReturnsOnlyRowsMatchingKey(): void
    {
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_1, 'shared_key', 'v1'));
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_2, 'shared_key', 'v2'));
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_3, 'other_key', 'v3'));

        $results = $this->dao->getBySegmentIds(
            [self::SEGMENT_ID_1, self::SEGMENT_ID_2, self::SEGMENT_ID_3],
            'shared_key',
            0
        );

        $this->assertIsArray($results);
        $this->assertCount(2, $results);

        $segmentIds = array_map(fn(SegmentMetadataStruct $s) => (int)$s->id_segment, $results);
        $this->assertContains(self::SEGMENT_ID_1, $segmentIds);
        $this->assertContains(self::SEGMENT_ID_2, $segmentIds);
    }

    #[Test]
    public function testGetBySegmentIdsReturnsEmptyArrayWhenKeyNotFound(): void
    {
        $results = $this->dao->getBySegmentIds(
            [self::SEGMENT_ID_1, self::SEGMENT_ID_2],
            'nonexistent_key',
            0
        );

        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    // ── upsert ────────────────────────────────────────────────────────────────

    #[Test]
    public function testUpsertInsertsRowWhenNotPresent(): void
    {
        $this->dao->upsert(self::SEGMENT_ID_1, 'upsert_key', 'upsert_value');

        $rows = $this->fetchRows(self::SEGMENT_ID_1, 'upsert_key');
        $this->assertCount(1, $rows);
        $this->assertEquals('upsert_value', $rows[0]['meta_value']);
    }

    #[Test]
    public function testUpsertDoesNotThrowOnRepeatCallWithSameKey(): void
    {
        $this->dao->upsert(self::SEGMENT_ID_1, 'upsert_key2', 'first');
        $this->dao->upsert(self::SEGMENT_ID_1, 'upsert_key2', 'second');

        $rows = $this->fetchRows(self::SEGMENT_ID_1, 'upsert_key2');
        $this->assertGreaterThanOrEqual(1, count($rows));
    }

    // ── delete ────────────────────────────────────────────────────────────────

    #[Test]
    public function testDeleteRemovesRowFromDatabase(): void
    {
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_1, 'del_key', 'del_value'));
        $this->assertNotNull($this->dao->get(self::SEGMENT_ID_1, 'del_key', 0));

        $this->dao->delete(self::SEGMENT_ID_1, 'del_key');

        $this->assertNull($this->dao->get(self::SEGMENT_ID_1, 'del_key', 0));
    }

    #[Test]
    public function testDeleteNonexistentRowDoesNotThrow(): void
    {
        $this->dao->delete(self::SEGMENT_ID_1, 'key_that_never_existed');

        $this->assertTrue(true);
    }

    // ── destroyGetAllCache ────────────────────────────────────────────────────

    #[Test]
    public function testDestroyGetAllCacheReturnsBool(): void
    {
        $result = $this->dao->destroyGetAllCache(self::SEGMENT_ID_1);

        $this->assertIsBool($result);
    }

    // ── destroyGetCache ───────────────────────────────────────────────────────

    #[Test]
    public function testDestroyGetCacheReturnsBool(): void
    {
        $result = $this->dao->destroyGetCache(self::SEGMENT_ID_1, 'any_key');

        $this->assertIsBool($result);
    }

    // ── destroyGetBySegmentIdsCache ───────────────────────────────────────────

    #[Test]
    public function testDestroyGetBySegmentIdsCacheReturnsBool(): void
    {
        $result = $this->dao->destroyGetBySegmentIdsCache('any_key');

        $this->assertIsBool($result);
    }

    // ── destroyGetAllInRangeCache ───────────────────────────────────────────────

    #[Test]
    public function testDestroyGetAllInRangeCacheReturnsBool(): void
    {
        $result = $this->dao->destroyGetAllInRangeCache();

        $this->assertIsBool($result);
    }

    #[Test]
    public function testDestroyGetAllInRangeCacheBustsStaleCachedResult(): void
    {
        $ttl = 60;

        // Warm the cache for a range where SEGMENT_ID_1 has no metadata yet.
        $before = $this->dao->getAllInRange(self::SEGMENT_ID_1, self::SEGMENT_ID_1, $ttl);
        $this->assertArrayNotHasKey(self::SEGMENT_ID_1, $before);

        // Insert directly, bypassing save()/upsert() (which already bust this cache), to
        // simulate any writer unaware of getAllInRange's cache.
        $this->database->getConnection()->prepare(
            "INSERT INTO segment_metadata (id_segment, meta_key, meta_value) VALUES (?, ?, ?)"
        )->execute([self::SEGMENT_ID_1, 'direct_key', 'direct_value']);

        // Without busting, the stale (empty) result is still served from cache.
        $stillStale = $this->dao->getAllInRange(self::SEGMENT_ID_1, self::SEGMENT_ID_1, $ttl);
        $this->assertArrayNotHasKey(self::SEGMENT_ID_1, $stillStale);

        $this->dao->destroyGetAllInRangeCache();

        $fresh = $this->dao->getAllInRange(self::SEGMENT_ID_1, self::SEGMENT_ID_1, $ttl);
        $this->assertArrayHasKey(self::SEGMENT_ID_1, $fresh);
        $this->assertCount(1, $fresh[self::SEGMENT_ID_1]);
    }

    // ── getAllInRange (renamed from staticGetAllInRange) ───────────────────────

    #[Test]
    public function testGetAllInRangeReturnsGroupedCollections(): void
    {
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_1, 'key_a', 'val_a'));
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_1, 'key_b', 'val_b'));
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_2, 'key_a', 'val_c'));
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_3, 'key_a', 'val_d'));

        $result = $this->dao->getAllInRange(self::SEGMENT_ID_1, self::SEGMENT_ID_3, 0);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        $this->assertArrayHasKey(self::SEGMENT_ID_1, $result);
        $this->assertArrayHasKey(self::SEGMENT_ID_2, $result);
        $this->assertArrayHasKey(self::SEGMENT_ID_3, $result);

        $this->assertInstanceOf(SegmentMetadataCollection::class, $result[self::SEGMENT_ID_1]);
        $this->assertCount(2, $result[self::SEGMENT_ID_1]);
        $this->assertCount(1, $result[self::SEGMENT_ID_2]);
        $this->assertCount(1, $result[self::SEGMENT_ID_3]);
    }

    #[Test]
    public function testGetAllInRangeReturnsEmptyArrayWhenNoDataInRange(): void
    {
        $result = $this->dao->getAllInRange(self::SEGMENT_ID_1, self::SEGMENT_ID_3, 0);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function testGetAllInRangeExcludesSegmentsOutsideRange(): void
    {
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_1, 'key_a', 'val_a'));
        $this->dao->save($this->makeStruct(self::SEGMENT_ID_3, 'key_a', 'val_b'));

        $result = $this->dao->getAllInRange(self::SEGMENT_ID_1, self::SEGMENT_ID_2, 0);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(self::SEGMENT_ID_1, $result);
        $this->assertArrayNotHasKey(self::SEGMENT_ID_3, $result);
    }
}
