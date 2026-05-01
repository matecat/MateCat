<?php


use Model\DataAccess\Database;
use Model\Projects\MetadataDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

#[Group('PersistenceNeeded')]
class ProjectsMetadataDaoTest extends AbstractTest
{

    /**
     * @throws ReflectionException
     */
    private function resetProjectMetadata(MetadataDao $dao, int $idProject): void
    {
        foreach ($dao->allByProjectId($idProject) as $record) {
            $dao->delete($idProject, $record->key);
        }
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    function testCreateNewKey()
    {
        $dao = new MetadataDao(Database::obtain());
        $this->resetProjectMetadata($dao, 1);
        $record = $dao->get(1, 'foo');
        $this->assertEquals($record, false);

        $dao->set(1, 'foo', 'bar');
        $record = $dao->get(1, 'foo');

        $this->assertEquals('bar', $record->value);
        $this->assertEquals('foo', $record->key);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    function testUpdate()
    {
        $dao = new MetadataDao(Database::obtain());
        $this->resetProjectMetadata($dao, 1);
        $dao->set(1, 'foo', 'bar');
        $dao->set(1, 'foo', 'bar2');
        $record = $dao->get(1, 'foo');

        $this->assertEquals('bar2', $record->value);
        $this->assertEquals('foo', $record->key);

        $count = $dao->allByProjectId(1);
        $this->assertCount(1, $count);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    function testDelete()
    {
        $dao = new MetadataDao(Database::obtain());
        $this->resetProjectMetadata($dao, 1);
        $dao->set(1, 'foo', 'bar2');
        $dao->delete(1, 'foo');

        $count = $dao->allByProjectId(1);
        $this->assertCount(0, $count);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBulkSetInsertsMultipleKeys(): void
    {
        $dao = new MetadataDao(Database::obtain());
        $this->resetProjectMetadata($dao, 1);

        $dao->bulkSet(1, [
            'key_a' => 'val_a',
            'key_b' => 'val_b',
            'key_c' => 'val_c',
        ]);

        $recordA = $dao->get(1, 'key_a');
        $recordB = $dao->get(1, 'key_b');
        $recordC = $dao->get(1, 'key_c');

        $this->assertEquals('val_a', $recordA?->value);
        $this->assertEquals('val_b', $recordB?->value);
        $this->assertEquals('val_c', $recordC?->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBulkSetUpsertsExistingKeys(): void
    {
        $dao = new MetadataDao(Database::obtain());
        $this->resetProjectMetadata($dao, 1);
        $dao->set(1, 'existing_key', 'old_value');

        $dao->bulkSet(1, [
            'existing_key' => 'new_value',
            'new_key' => 'fresh',
        ]);

        $existing = $dao->get(1, 'existing_key');
        $new = $dao->get(1, 'new_key');

        $this->assertEquals('new_value', $existing?->value);
        $this->assertEquals('fresh', $new?->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBulkSetWithEmptyArrayIsNoop(): void
    {
        $dao = new MetadataDao(Database::obtain());
        $this->resetProjectMetadata($dao, 1);
        $countBefore = count($dao->allByProjectId(1));

        $dao->bulkSet(1, []);

        $countAfter = count($dao->allByProjectId(1));
        $this->assertEquals($countBefore, $countAfter);
    }

}
