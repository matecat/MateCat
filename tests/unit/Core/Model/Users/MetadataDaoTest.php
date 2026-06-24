<?php

declare(strict_types=1);

namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Users\MetadataDao;
use Model\Users\MetadataStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class MetadataDaoTest extends AbstractTest
{
    private const int UID_1 = 999991;
    private const int UID_2 = 999992;

    private Database $database;
    private MetadataDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );
        $this->dao = new MetadataDao();
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
        $uids = implode(',', [self::UID_1, self::UID_2]);
        $this->database->getConnection()->exec(
            "DELETE FROM user_metadata WHERE uid IN ($uids)"
        );
    }

    // ── set ──────────────────────────────────────────────────────────────────

    #[Test]
    public function testSetInsertsAndReturnsStruct(): void
    {
        $result = $this->dao->set(self::UID_1, 'test_key', 'test_value');

        $this->assertInstanceOf(MetadataStruct::class, $result);
        $this->assertEquals(self::UID_1, (int)$result->uid);
        $this->assertEquals('test_key', $result->key);
        $this->assertEquals('test_value', $result->value);
    }

    #[Test]
    public function testSetUpsertsOnDuplicateKey(): void
    {
        $this->dao->set(self::UID_1, 'upsert_key', 'first');
        $result = $this->dao->set(self::UID_1, 'upsert_key', 'second');

        $this->assertInstanceOf(MetadataStruct::class, $result);

        $fetched = $this->dao->get(self::UID_1, 'upsert_key');
        $this->assertNotNull($fetched);
        $this->assertEquals('second', $fetched->value);
    }

    #[Test]
    public function testSetSerializesArrayValue(): void
    {
        $arrayValue = ['engine' => 'deepl', 'enabled' => true];
        $result = $this->dao->set(self::UID_1, 'array_key', $arrayValue);

        $this->assertInstanceOf(MetadataStruct::class, $result);

        $fetched = $this->dao->get(self::UID_1, 'array_key');
        $this->assertNotNull($fetched);
        $this->assertIsString($fetched->value);
    }

    // ── get ──────────────────────────────────────────────────────────────────

    #[Test]
    public function testGetReturnsCorrectStruct(): void
    {
        $this->dao->set(self::UID_1, 'get_key', 'get_value');

        $result = $this->dao->get(self::UID_1, 'get_key');

        $this->assertInstanceOf(MetadataStruct::class, $result);
        $this->assertEquals(self::UID_1, (int)$result->uid);
        $this->assertEquals('get_key', $result->key);
        $this->assertEquals('get_value', $result->value);
    }

    #[Test]
    public function testGetReturnsNullWhenNotFound(): void
    {
        $result = $this->dao->get(self::UID_1, 'nonexistent_key');

        $this->assertNull($result);
    }

    // ── getAllByUid ──────────────────────────────────────────────────────────

    #[Test]
    public function testGetAllByUidReturnsAllMetadataForUser(): void
    {
        $this->dao->set(self::UID_1, 'key_a', 'val_a');
        $this->dao->set(self::UID_1, 'key_b', 'val_b');
        $this->dao->set(self::UID_2, 'key_c', 'val_c');

        $results = $this->dao->getAllByUid(self::UID_1);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        foreach ($results as $row) {
            $this->assertInstanceOf(MetadataStruct::class, $row);
            $this->assertEquals(self::UID_1, (int)$row->uid);
        }
    }

    #[Test]
    public function testGetAllByUidReturnsEmptyArrayWhenNoMetadata(): void
    {
        $results = $this->dao->getAllByUid(self::UID_1);

        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    // ── getAllByUidList ──────────────────────────────────────────────────────

    #[Test]
    public function testGetAllByUidListReturnsGroupedByUid(): void
    {
        $this->dao->set(self::UID_1, 'key_a', 'val_a');
        $this->dao->set(self::UID_1, 'key_b', 'val_b');
        $this->dao->set(self::UID_2, 'key_c', 'val_c');

        $results = $this->dao->getAllByUidList([self::UID_1, self::UID_2]);

        $this->assertIsArray($results);
        $this->assertArrayHasKey(self::UID_1, $results);
        $this->assertArrayHasKey(self::UID_2, $results);
        $this->assertCount(2, $results[self::UID_1]);
        $this->assertCount(1, $results[self::UID_2]);
    }

    #[Test]
    public function testGetAllByUidListReturnsEmptyArrayForEmptyInput(): void
    {
        $results = $this->dao->getAllByUidList([]);

        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    // ── delete ──────────────────────────────────────────────────────────────

    #[Test]
    public function testDeleteRemovesRow(): void
    {
        $this->dao->set(self::UID_1, 'del_key', 'del_value');
        $this->assertNotNull($this->dao->get(self::UID_1, 'del_key'));

        $this->dao->delete(self::UID_1, 'del_key');

        $fetched = $this->dao->getAllByUid(self::UID_1);
        $matching = array_filter($fetched, fn(MetadataStruct $s) => $s->key === 'del_key');
        $this->assertCount(0, $matching);
    }

    #[Test]
    public function testDeleteNonexistentRowDoesNotThrow(): void
    {
        $this->dao->delete(self::UID_1, 'no_such_key');

        $this->assertTrue(true);
    }

    // ── destroyCacheKey ─────────────────────────────────────────────────────

    #[Test]
    public function testDestroyCacheKeyReturnsBool(): void
    {
        $result = $this->dao->destroyCacheKey(self::UID_1, 'any_key');

        $this->assertIsBool($result);
    }

    // ── DI testability ──────────────────────────────────────────────────────

    #[Test]
    public function testConstructorAcceptsInjectedDatabase(): void
    {
        $dao = new MetadataDao($this->database);

        $dao->set(self::UID_1, 'di_key', 'di_value');
        $result = $dao->get(self::UID_1, 'di_key');

        $this->assertInstanceOf(MetadataStruct::class, $result);
        $this->assertEquals('di_value', $result->value);
    }
}
