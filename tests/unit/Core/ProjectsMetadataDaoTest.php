<?php


namespace Matecat\Core;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Projects\MetadataDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

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
        $value = $dao->getValue(1, 'foo');
        $this->assertNull($value);

        $dao->set(1, 'foo', 'bar');
        $value = $dao->getValue(1, 'foo');

        $this->assertEquals('bar', $value);
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
        $value = $dao->getValue(1, 'foo');

        $this->assertEquals('bar2', $value);

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

        $this->assertEquals('val_a', $dao->getValue(1, 'key_a'));
        $this->assertEquals('val_b', $dao->getValue(1, 'key_b'));
        $this->assertEquals('val_c', $dao->getValue(1, 'key_c'));
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

        $this->assertEquals('new_value', $dao->getValue(1, 'existing_key'));
        $this->assertEquals('fresh', $dao->getValue(1, 'new_key'));
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
