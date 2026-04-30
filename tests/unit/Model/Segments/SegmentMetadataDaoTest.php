<?php

namespace unit\Model\Segments;

use Model\DataAccess\Database;
use Model\Segments\SegmentMetadataCollection;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class SegmentMetadataDaoTest extends AbstractTest
{
    private const int SEGMENT_ID_1 = 999991;
    private const int SEGMENT_ID_2 = 999992;
    private const int SEGMENT_ID_3 = 999993;

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

        $flusher = new \Predis\Client(AppConfig::$REDIS_SERVERS);
        $flusher->select(AppConfig::$INSTANCE_ID);
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

    #[Test]
    public function testSaveInsertsRow(): void
    {
        SegmentMetadataDao::save($this->makeStruct(self::SEGMENT_ID_1, 'save_key', 'save_value'));

        $rows = $this->fetchRows(self::SEGMENT_ID_1, 'save_key');
        $this->assertCount(1, $rows);
        $this->assertEquals(self::SEGMENT_ID_1, (int)$rows[0]['id_segment']);
        $this->assertEquals('save_key', $rows[0]['meta_key']);
        $this->assertEquals('save_value', $rows[0]['meta_value']);
    }

    #[Test]
    public function testGetReturnsCorrectStruct(): void
    {
        SegmentMetadataDao::save($this->makeStruct(self::SEGMENT_ID_1, 'get_key', 'get_value'));

        $result = SegmentMetadataDao::get(self::SEGMENT_ID_1, 'get_key', 0);

        $this->assertInstanceOf(SegmentMetadataStruct::class, $result);
        $this->assertEquals(self::SEGMENT_ID_1, $result->id_segment);
        $this->assertEquals('get_key', $result->meta_key);
        $this->assertEquals('get_value', $result->meta_value);
    }

    #[Test]
    public function testGetNonexistentReturnsNull(): void
    {
        $result = SegmentMetadataDao::get(self::SEGMENT_ID_1, 'no_such_key', 0);

        $this->assertNull($result);
    }

    #[Test]
    public function testGetAllReturnsCollectionWithAllMetadataForSegment(): void
    {
        SegmentMetadataDao::save($this->makeStruct(self::SEGMENT_ID_1, 'key_a', 'val_a'));
        SegmentMetadataDao::save($this->makeStruct(self::SEGMENT_ID_1, 'key_b', 'val_b'));

        $collection = SegmentMetadataDao::getAll(self::SEGMENT_ID_1, 0);

        $this->assertInstanceOf(SegmentMetadataCollection::class, $collection);
        $this->assertCount(2, $collection);
    }

    #[Test]
    public function testGetAllReturnsEmptyCollectionWhenNoMetadataExists(): void
    {
        $collection = SegmentMetadataDao::getAll(self::SEGMENT_ID_2, 0);

        $this->assertInstanceOf(SegmentMetadataCollection::class, $collection);
        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    #[Test]
    public function testGetBySegmentIdsReturnsOnlyRowsMatchingKey(): void
    {
        SegmentMetadataDao::save($this->makeStruct(self::SEGMENT_ID_1, 'shared_key', 'v1'));
        SegmentMetadataDao::save($this->makeStruct(self::SEGMENT_ID_2, 'shared_key', 'v2'));
        SegmentMetadataDao::save($this->makeStruct(self::SEGMENT_ID_3, 'other_key', 'v3'));

        $results = SegmentMetadataDao::getBySegmentIds(
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
        $results = SegmentMetadataDao::getBySegmentIds(
            [self::SEGMENT_ID_1, self::SEGMENT_ID_2],
            'nonexistent_key',
            0
        );

        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    #[Test]
    public function testUpsertInsertsRowWhenNotPresent(): void
    {
        SegmentMetadataDao::upsert(self::SEGMENT_ID_1, 'upsert_key', 'upsert_value');

        $rows = $this->fetchRows(self::SEGMENT_ID_1, 'upsert_key');
        $this->assertCount(1, $rows);
        $this->assertEquals('upsert_value', $rows[0]['meta_value']);
    }

    #[Test]
    public function testUpsertDoesNotThrowOnRepeatCallWithSameKey(): void
    {
        SegmentMetadataDao::upsert(self::SEGMENT_ID_1, 'upsert_key2', 'first');
        SegmentMetadataDao::upsert(self::SEGMENT_ID_1, 'upsert_key2', 'second');

        $rows = $this->fetchRows(self::SEGMENT_ID_1, 'upsert_key2');
        $this->assertGreaterThanOrEqual(1, count($rows));
    }

    #[Test]
    public function testDeleteRemovesRowFromDatabase(): void
    {
        SegmentMetadataDao::save($this->makeStruct(self::SEGMENT_ID_1, 'del_key', 'del_value'));
        $this->assertNotNull(SegmentMetadataDao::get(self::SEGMENT_ID_1, 'del_key', 0));

        SegmentMetadataDao::delete(self::SEGMENT_ID_1, 'del_key');

        $this->assertNull(SegmentMetadataDao::get(self::SEGMENT_ID_1, 'del_key', 0));
    }

    #[Test]
    public function testDeleteNonexistentRowDoesNotThrow(): void
    {
        SegmentMetadataDao::delete(self::SEGMENT_ID_1, 'key_that_never_existed');

        $this->assertTrue(true);
    }

    #[Test]
    public function testSetTranslationDisabledCreatesRowWithKeyAndValueOne(): void
    {
        SegmentMetadataDao::setTranslationDisabled(self::SEGMENT_ID_1);

        $result = SegmentMetadataDao::get(self::SEGMENT_ID_1, 'translation_disabled', 0);

        $this->assertInstanceOf(SegmentMetadataStruct::class, $result);
        $this->assertEquals(self::SEGMENT_ID_1, $result->id_segment);
        $this->assertEquals('translation_disabled', $result->meta_key);
        $this->assertEquals('1', $result->meta_value);
    }

    #[Test]
    public function testDestroyGetAllCacheReturnsBool(): void
    {
        $result = SegmentMetadataDao::destroyGetAllCache(self::SEGMENT_ID_1);

        $this->assertIsBool($result);
    }

    #[Test]
    public function testDestroyGetCacheReturnsBool(): void
    {
        $result = SegmentMetadataDao::destroyGetCache(self::SEGMENT_ID_1, 'any_key');

        $this->assertIsBool($result);
    }

    #[Test]
    public function testDestroyGetBySegmentIdsCacheReturnsBool(): void
    {
        $result = SegmentMetadataDao::destroyGetBySegmentIdsCache('any_key');

        $this->assertIsBool($result);
    }
}
