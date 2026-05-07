<?php

namespace unit\Model\Projects;

use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

#[Group('PersistenceNeeded')]
class MetadataDaoTest extends AbstractTest
{
    private const int BASE_TEST_PROJECT_ID = 9990000;
    private const string TEST_PROJECT_PASSWORD = 'metadata_test_pwd';

    private int $testProjectId;
    private MetadataDao $dao;

    protected function setUp(): void
    {
        parent::setUp();

        $conn = Database::obtain()->getConnection();
        $conn->beginTransaction();

        $this->testProjectId = self::BASE_TEST_PROJECT_ID + random_int(1, 999);

        $conn->exec(
            "INSERT IGNORE INTO projects (id, password, id_customer, name, create_date)
             VALUES (" . $this->testProjectId . ", '" . self::TEST_PROJECT_PASSWORD . "', 1, 'MetadataDao test project', NOW())"
        );

        $conn->exec('DELETE FROM project_metadata WHERE id_project = ' . $this->testProjectId);

        $this->dao = new MetadataDao(Database::obtain());
    }

    protected function tearDown(): void
    {
        $conn = Database::obtain()->getConnection();
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        parent::tearDown();
    }

    #[Test]
    public function allByProjectIdReturnsUnmarshalledMetadata(): void
    {
        $this->dao->set($this->testProjectId, ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value, '1');
        $this->dao->set($this->testProjectId, 'plain_key', 'plain_value');

        $rows = $this->dao->allByProjectId($this->testProjectId);

        $this->assertCount(2, $rows);

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->key] = $row->value;
        }

        $this->assertArrayHasKey(ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value, $indexed);
        $this->assertArrayHasKey('plain_key', $indexed);
        $this->assertTrue($indexed[ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value]);
        $this->assertSame('plain_value', $indexed['plain_key']);
    }

    #[Test]
    public function getReturnsNullWhenKeyDoesNotExist(): void
    {
        $result = $this->dao->get($this->testProjectId, 'missing_key');

        $this->assertNull($result);
    }

    #[Test]
    public function getReturnsUnmarshalledMetadataWhenKeyExists(): void
    {
        $this->dao->set($this->testProjectId, ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value, '85');

        $result = $this->dao->get($this->testProjectId, ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value);

        $this->assertNotNull($result);
        $this->assertSame(ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value, $result->key);
        $this->assertSame(85, $result->value);
    }

    #[Test]
    public function setCreatesAndUpdatesMetadata(): void
    {
        $firstInsert = $this->dao->set($this->testProjectId, 'upsert_key', 'first_value');
        $update = $this->dao->set($this->testProjectId, 'upsert_key', 'second_value');

        $row = $this->dao->get($this->testProjectId, 'upsert_key');

        $this->assertTrue($firstInsert);
        $this->assertTrue($update);
        $this->assertNotNull($row);
        $this->assertSame('second_value', $row->value);
        $this->assertCount(1, $this->dao->allByProjectId($this->testProjectId));
    }

    #[Test]
    public function bulkSetInsertsAndUpdatesMultipleKeys(): void
    {
        $this->dao->set($this->testProjectId, 'existing_key', 'old');

        $this->dao->bulkSet($this->testProjectId, [
            'existing_key' => 'new',
            'new_key' => 'fresh',
        ]);

        $this->assertSame('new', $this->dao->get($this->testProjectId, 'existing_key')?->value);
        $this->assertSame('fresh', $this->dao->get($this->testProjectId, 'new_key')?->value);
    }

    #[Test]
    public function bulkSetWithEmptyArrayDoesNothing(): void
    {
        $this->dao->set($this->testProjectId, 'untouched_key', 'untouched_value');
        $countBefore = count($this->dao->allByProjectId($this->testProjectId));

        $this->dao->bulkSet($this->testProjectId, []);

        $countAfter = count($this->dao->allByProjectId($this->testProjectId));
        $this->assertSame($countBefore, $countAfter);
        $this->assertSame('untouched_value', $this->dao->get($this->testProjectId, 'untouched_key')?->value);
    }

    #[Test]
    public function deleteRemovesMetadataKey(): void
    {
        $this->dao->set($this->testProjectId, 'delete_key', 'to_remove');
        $this->assertNotNull($this->dao->get($this->testProjectId, 'delete_key'));

        $this->dao->delete($this->testProjectId, 'delete_key');

        $this->assertNull($this->dao->get($this->testProjectId, 'delete_key'));
    }

    #[Test]
    public function destroyMetadataCacheReturnsBooleanForProjectAndSpecificKey(): void
    {
        $this->dao->set($this->testProjectId, 'cache_key', 'cache_value');

        $projectCacheDestroyed = $this->dao->destroyMetadataCache($this->testProjectId);
        $keyCacheDestroyed = $this->dao->destroyMetadataCache($this->testProjectId, 'cache_key');

        $this->assertIsBool($projectCacheDestroyed);
        $this->assertIsBool($keyCacheDestroyed);
    }

    #[Test]
    public function buildChunkKeyReturnsExpectedFormat(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 321;
        $chunk->password = 'chunk_pwd';

        $result = MetadataDao::buildChunkKey('base_key', $chunk);

        $this->assertSame('base_key_chunk_321_chunk_pwd', $result);
    }

    #[Test]
    public function getProjectStaticSubfilteringCustomHandlersReturnsEmptyArrayWhenMetadataDoesNotExist(): void
    {
        $handlers = $this->dao->getProjectStaticSubfilteringCustomHandlers($this->testProjectId);

        $this->assertSame([], $handlers);
    }

    #[Test]
    public function getProjectStaticSubfilteringCustomHandlersReturnsStoredHandlers(): void
    {
        $expectedHandlers = [
            'html' => 'My\\Handler\\Html',
            'md' => 'My\\Handler\\Markdown',
        ];

        $this->dao->set(
            $this->testProjectId,
            ProjectsMetadataMarshaller::SUBFILTERING_HANDLERS->value,
            json_encode($expectedHandlers, JSON_THROW_ON_ERROR)
        );

        $handlers = $this->dao->getProjectStaticSubfilteringCustomHandlers($this->testProjectId);

        $this->assertSame($expectedHandlers, $handlers);
    }
}
