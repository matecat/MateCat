<?php

namespace unit\Model\Files;

use Model\DataAccess\Database;
use Model\Files\MetadataDao;
use Model\Files\MetadataStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class MetadataDaoTest extends AbstractTest
{
    private const int FIXTURE_PROJECT_ID = 999997;
    private const int FIXTURE_FILE_ID    = 999996;

    private Database    $database;
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

        $this->dao = new MetadataDao($this->database);

        $this->database->getConnection()->exec(
            'DELETE FROM file_metadata WHERE id_project = ' . self::FIXTURE_PROJECT_ID
        );
    }

    protected function tearDown(): void
    {
        $this->database->getConnection()->exec(
            'DELETE FROM file_metadata WHERE id_project = ' . self::FIXTURE_PROJECT_ID
        );

        $flusher = new \Predis\Client(AppConfig::$REDIS_SERVERS);
        $flusher->select(AppConfig::$INSTANCE_ID);
        $flusher->flushdb();

        parent::tearDown();
    }

    #[Test]
    public function testInsertReturnsMetadataStructWithCorrectValues(): void
    {
        $struct = $this->dao->insert(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions',
            'Translate carefully.'
        );

        self::assertInstanceOf(MetadataStruct::class, $struct);
        self::assertSame(self::FIXTURE_PROJECT_ID, $struct->id_project);
        self::assertSame(self::FIXTURE_FILE_ID, $struct->id_file);
        self::assertSame('instructions', $struct->key);
        self::assertSame('Translate carefully.', $struct->value);
        self::assertNull($struct->files_parts_id);
        self::assertIsInt($struct->id);
        self::assertGreaterThan(0, $struct->id);
    }

    #[Test]
    public function testInsertWithFilePartsIdStoresTheId(): void
    {
        $struct = $this->dao->insert(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'context-url',
            'https://example.com',
            42
        );

        self::assertInstanceOf(MetadataStruct::class, $struct);
        self::assertSame(42, $struct->files_parts_id);
        self::assertSame('https://example.com', $struct->value);
    }

    #[Test]
    public function testGetReturnsInsertedRecord(): void
    {
        $this->dao->insert(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions',
            'Hello world.'
        );

        $struct = $this->dao->get(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions'
        );

        self::assertInstanceOf(MetadataStruct::class, $struct);
        self::assertSame(self::FIXTURE_PROJECT_ID, $struct->id_project);
        self::assertSame(self::FIXTURE_FILE_ID, $struct->id_file);
        self::assertSame('instructions', $struct->key);
        self::assertSame('Hello world.', $struct->value);
    }

    #[Test]
    public function testGetReturnsNullForNonExistentKey(): void
    {
        $result = $this->dao->get(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'nonexistent-key-xyz'
        );

        self::assertNull($result);
    }

    #[Test]
    public function testGetWithFilePartsIdReturnsMatchingRecord(): void
    {
        $this->dao->insert(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions',
            'Part-specific note.',
            7
        );

        $struct = $this->dao->get(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions',
            7
        );

        self::assertInstanceOf(MetadataStruct::class, $struct);
        self::assertSame(7, $struct->files_parts_id);
        self::assertSame('Part-specific note.', $struct->value);
    }

    #[Test]
    public function testUpdateChangesValueAndReturnsFreshStruct(): void
    {
        $this->dao->insert(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions',
            'Old value.'
        );

        $updated = $this->dao->update(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions',
            'New value.'
        );

        self::assertInstanceOf(MetadataStruct::class, $updated);
        self::assertSame('New value.', $updated->value);
    }

    #[Test]
    public function testUpdateWithFilePartsIdChangesOnlyThatRecord(): void
    {
        $this->dao->insert(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions',
            'Global value.'
        );
        $this->dao->insert(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions',
            'Part value.',
            5
        );

        $updated = $this->dao->update(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions',
            'Updated part value.',
            5
        );

        self::assertSame('Updated part value.', $updated->value);

        $global = $this->dao->get(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions'
        );
        self::assertSame('Global value.', $global->value);
    }

    #[Test]
    public function testBulkInsertStoresAllProvidedMetadata(): void
    {
        $metadata = [
            'instructions' => 'Bulk note A.',
            'context-url'  => 'https://bulk.example.com',
        ];

        $result = $this->dao->bulkInsert(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            $metadata
        );

        self::assertTrue($result);

        self::assertSame(
            'Bulk note A.',
            $this->dao->get(self::FIXTURE_PROJECT_ID, self::FIXTURE_FILE_ID, 'instructions')->value
        );
        self::assertSame(
            'https://bulk.example.com',
            $this->dao->get(self::FIXTURE_PROJECT_ID, self::FIXTURE_FILE_ID, 'context-url')->value
        );
    }

    #[Test]
    public function testBulkInsertSkipsNullAndEmptyValues(): void
    {
        $metadata = [
            'context-url'  => '',
            'pdfAnalysis'  => null,
            'instructions' => 'Keep this.',
        ];

        $result = $this->dao->bulkInsert(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            $metadata
        );

        self::assertTrue($result);

        self::assertNull(
            $this->dao->get(self::FIXTURE_PROJECT_ID, self::FIXTURE_FILE_ID, 'context-url')
        );
        self::assertNull(
            $this->dao->get(self::FIXTURE_PROJECT_ID, self::FIXTURE_FILE_ID, 'pdfAnalysis')
        );
    }

    #[Test]
    public function testBulkInsertReturnsNullWhenAllValuesAreEmpty(): void
    {
        $metadata = [
            'instructions' => '',
            'context-url'  => null,
        ];

        $result = $this->dao->bulkInsert(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            $metadata
        );

        self::assertNull($result);
    }

    #[Test]
    public function testGetByJobIdProjectAndIdFileReturnsAllMatchingRecords(): void
    {
        $this->dao->insert(self::FIXTURE_PROJECT_ID, self::FIXTURE_FILE_ID, 'instructions', 'Note 1.');
        $this->dao->insert(self::FIXTURE_PROJECT_ID, self::FIXTURE_FILE_ID, 'context-url', 'https://example.com');

        $list = $this->dao->getByJobIdProjectAndIdFile(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID
        );

        self::assertIsArray($list);
        self::assertCount(2, $list);

        foreach ($list as $item) {
            self::assertInstanceOf(MetadataStruct::class, $item);
            self::assertSame(self::FIXTURE_PROJECT_ID, $item->id_project);
            self::assertSame(self::FIXTURE_FILE_ID, $item->id_file);
        }
    }

    #[Test]
    public function testGetByJobIdProjectAndIdFileReturnsNullWhenNoRows(): void
    {
        $list = $this->dao->getByJobIdProjectAndIdFile(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID
        );

        self::assertIsArray($list);
        self::assertEmpty($list);
    }

    #[Test]
    public function testDestroyCacheByJobIdProjectAndIdFileReturnsBool(): void
    {
        $this->dao->insert(self::FIXTURE_PROJECT_ID, self::FIXTURE_FILE_ID, 'instructions', 'Cache test.');

        $result = $this->dao->destroyCacheByJobIdProjectAndIdFile(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID
        );

        self::assertIsBool($result);
    }

    #[Test]
    public function testDestroyGetCacheReturnsBoolWithoutFilePartsId(): void
    {
        $this->dao->insert(self::FIXTURE_PROJECT_ID, self::FIXTURE_FILE_ID, 'instructions', 'Some note.');

        $result = $this->dao->destroyGetCache(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions'
        );

        self::assertIsBool($result);
    }

    #[Test]
    public function testDestroyGetCacheReturnsBoolWithFilePartsId(): void
    {
        $this->dao->insert(self::FIXTURE_PROJECT_ID, self::FIXTURE_FILE_ID, 'instructions', 'Parted note.', 3);

        $result = $this->dao->destroyGetCache(
            self::FIXTURE_PROJECT_ID,
            self::FIXTURE_FILE_ID,
            'instructions',
            3
        );

        self::assertIsBool($result);
    }
}
