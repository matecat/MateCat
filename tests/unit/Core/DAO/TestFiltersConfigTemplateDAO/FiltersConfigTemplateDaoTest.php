<?php

namespace Matecat\Core\DAO\TestFiltersConfigTemplateDAO;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Filters\FiltersConfigTemplateDao;
use Model\Filters\FiltersConfigTemplateStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

class FiltersConfigTemplateDaoTest extends AbstractTest
{
    private FiltersConfigTemplateDao $dao;
    private int $uid = 999999;
    /** @var array<int> */
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new FiltersConfigTemplateDao();
        $this->createdIds = [];
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdIds as $id) {
            try {
                $this->dao->remove($id, $this->uid);
            } catch (Exception) {
            }
        }
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM filters_config_templates WHERE uid = {$this->uid}");
    }

    private function makeValidJson(string $name = 'Test Filter'): string
    {
        return json_encode([
            'name' => $name . ' ' . uniqid(),
            'json' => ['data_fuzzy_matches' => true],
        ]);
    }

    private function create(string $name = 'Test Filter'): FiltersConfigTemplateStruct
    {
        $struct = $this->dao->createFromJSON($this->makeValidJson($name), $this->uid);
        $this->createdIds[] = $struct->id;

        return $struct;
    }

    #[Test]
    public function saveThrowsWhenUidIsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->uid = null;
        $struct->name = 'test';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        $this->dao->save($struct);
    }

    #[Test]
    public function saveThrowsWhenNameIsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->uid = 1;
        $struct->name = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('name');

        $this->dao->save($struct);
    }

    #[Test]
    public function updateThrowsWhenIdIsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->id = null;
        $struct->uid = 1;
        $struct->name = 'test';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('id');

        $this->dao->update($struct);
    }

    #[Test]
    public function updateThrowsWhenUidIsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->id = 1;
        $struct->uid = null;
        $struct->name = 'test';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        $this->dao->update($struct);
    }

    #[Test]
    public function updateThrowsWhenNameIsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->id = 1;
        $struct->uid = 1;
        $struct->name = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('name');

        $this->dao->update($struct);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function createFromJsonPersistsAndReturns(): void
    {
        $struct = $this->create('CRUD Create');

        $this->assertGreaterThan(0, $struct->id);
        $this->assertSame($this->uid, $struct->uid);
        $this->assertStringContainsString('CRUD Create', $struct->name);
        $this->assertNotNull($struct->created_at);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getByIdReturnsCreatedTemplate(): void
    {
        $created = $this->create('GetById Test');

        $fetched = $this->dao->getById($created->id);

        $this->assertNotNull($fetched);
        $this->assertSame($created->id, $fetched->id);
        $this->assertStringContainsString('GetById Test', $fetched->name);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getByIdAndUserReturnsCreatedTemplate(): void
    {
        $created = $this->create('ByIdAndUser Test');

        $fetched = $this->dao->getByIdAndUser($created->id, $this->uid);

        $this->assertNotNull($fetched);
        $this->assertSame($created->id, $fetched->id);
        $this->assertStringContainsString('ByIdAndUser Test', $fetched->name);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getByIdAndUserReturnsNullForWrongUid(): void
    {
        $created = $this->create('Wrong Uid');

        $this->assertNull($this->dao->getByIdAndUser($created->id, 888888));
    }

    #[Test]
    public function getByIdReturnsNullForNonExistentId(): void
    {
        $this->assertNull($this->dao->getById(999999999));
    }

    #[Test]
    public function getByIdAndUserReturnsNullForNonExistentId(): void
    {
        $this->assertNull($this->dao->getByIdAndUser(999999999, $this->uid));
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function editFromJsonUpdatesTemplate(): void
    {
        $created = $this->create('Before Edit');

        $editJson = $this->makeValidJson('After Edit');
        $updated = $this->dao->editFromJSON($created, $editJson, $this->uid);

        $this->assertSame($created->id, $updated->id);
        $this->assertStringContainsString('After Edit', $updated->name);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function removeSoftDeletesTemplate(): void
    {
        $created = $this->create('Delete Test');

        $count = $this->dao->remove($created->id, $this->uid);

        $this->assertSame(1, $count);
        $this->assertNull($this->dao->getByIdAndUser($created->id, $this->uid));
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function removeReturnsZeroForNonExistent(): void
    {
        $count = $this->dao->remove(999999999, $this->uid);
        $this->assertSame(0, $count);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getAllPaginatedReturnsStructure(): void
    {
        $this->create('Paginated Test');

        $result = $this->dao->getAllPaginated($this->uid, '/api/v3/filters-config-template?page=', 1, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertNotEmpty($result['items']);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function saveDirectlyPersists(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->uid = $this->uid;
        $struct->name = 'Direct Save ' . uniqid();

        $saved = $this->dao->save($struct);
        $this->createdIds[] = $saved->id;

        $this->assertGreaterThan(0, $saved->id);
        $this->assertStringContainsString('Direct Save', $saved->name);
    }
}
