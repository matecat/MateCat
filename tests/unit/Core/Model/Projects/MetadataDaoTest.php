<?php

namespace Matecat\Core\Model\Projects;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

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

        $conn = obtainTestDatabase()->getConnection();
        $conn->beginTransaction();

        $this->testProjectId = self::BASE_TEST_PROJECT_ID + random_int(1, 999);

        $conn->exec(
            "INSERT IGNORE INTO projects (id, password, id_customer, name, create_date)
             VALUES (" . $this->testProjectId . ", '" . self::TEST_PROJECT_PASSWORD . "', 1, 'MetadataDao test project', NOW())"
        );

        $conn->exec('DELETE FROM project_metadata WHERE id_project = ' . $this->testProjectId);

        $this->dao = new MetadataDao(obtainTestDatabase());
    }

    protected function tearDown(): void
    {
        $conn = obtainTestDatabase()->getConnection();
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

    /**
     * isIcuEnabled() reads through a 3600s cache, so each state gets its own id band: a band is
     * only ever asked about one value, which keeps a cached answer from a previous run equal to
     * the answer this run expects.
     */
    private function projectWithIcuFlag(int $band, ?string $value): int
    {
        $conn = obtainTestDatabase()->getConnection();
        $id = $band + random_int(1, 999);
        $conn->exec(
            "INSERT IGNORE INTO projects (id, password, id_customer, name, create_date)
             VALUES ($id, '" . self::TEST_PROJECT_PASSWORD . "', 1, 'MetadataDao icu test', NOW())"
        );
        $conn->exec('DELETE FROM project_metadata WHERE id_project = ' . $id);

        if ($value !== null) {
            $this->dao->set($id, ProjectsMetadataMarshaller::ICU_ENABLED->value, $value);
        }

        return $id;
    }

    #[Test]
    public function isIcuEnabledIsTrueWhenTheProjectEnabledIt(): void
    {
        $id = $this->projectWithIcuFlag(9_991_000, '1');

        $this->assertTrue($this->dao->isIcuEnabled($id));
    }

    #[Test]
    public function isIcuEnabledIsFalseWhenTheProjectDisabledIt(): void
    {
        $id = $this->projectWithIcuFlag(9_992_000, '0');

        $this->assertFalse($this->dao->isIcuEnabled($id));
    }

    #[Test]
    public function isIcuEnabledIsFalseWhenTheKeyWasNeverSet(): void
    {
        // Projects created before the flag existed carry no row at all.
        $id = $this->projectWithIcuFlag(9_993_000, null);

        $this->assertFalse($this->dao->isIcuEnabled($id));
    }

    #[Test]
    public function getValueReturnsNullWhenKeyDoesNotExist(): void
    {
        $result = $this->dao->getValue($this->testProjectId, 'missing_key');

        $this->assertNull($result);
    }

    #[Test]
    public function getValueReturnsUnmarshalledValueWhenKeyExists(): void
    {
        $this->dao->set($this->testProjectId, ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value, '85');

        $result = $this->dao->getValue($this->testProjectId, ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value);

        $this->assertSame(85, $result);
    }

    #[Test]
    public function setCreatesAndUpdatesMetadata(): void
    {
        $firstInsert = $this->dao->set($this->testProjectId, 'upsert_key', 'first_value');
        $update = $this->dao->set($this->testProjectId, 'upsert_key', 'second_value');

        $value = $this->dao->getValue($this->testProjectId, 'upsert_key');

        $this->assertTrue($firstInsert);
        $this->assertTrue($update);
        $this->assertSame('second_value', $value);
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

        $this->assertSame('new', $this->dao->getValue($this->testProjectId, 'existing_key'));
        $this->assertSame('fresh', $this->dao->getValue($this->testProjectId, 'new_key'));
    }

    #[Test]
    public function bulkSetWithEmptyArrayDoesNothing(): void
    {
        $this->dao->set($this->testProjectId, 'untouched_key', 'untouched_value');
        $countBefore = count($this->dao->allByProjectId($this->testProjectId));

        $this->dao->bulkSet($this->testProjectId, []);

        $countAfter = count($this->dao->allByProjectId($this->testProjectId));
        $this->assertSame($countBefore, $countAfter);
        $this->assertSame('untouched_value', $this->dao->getValue($this->testProjectId, 'untouched_key'));
    }

    #[Test]
    public function deleteRemovesMetadataKey(): void
    {
        $this->dao->set($this->testProjectId, 'delete_key', 'to_remove');
        $this->assertNotNull($this->dao->getValue($this->testProjectId, 'delete_key'));

        $this->dao->delete($this->testProjectId, 'delete_key');

        $this->assertNull($this->dao->getValue($this->testProjectId, 'delete_key'));
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

        $result = (new MetadataDao(obtainTestDatabase()))->buildChunkKey('base_key', $chunk);

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
