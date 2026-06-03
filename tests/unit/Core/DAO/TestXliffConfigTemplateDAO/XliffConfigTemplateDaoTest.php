<?php

namespace Matecat\Core\DAO\TestXliffConfigTemplateDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\Xliff\XliffConfigTemplateDao;
use Model\Xliff\XliffConfigTemplateStruct;
use PHPUnit\Framework\Attributes\Test;

class XliffConfigTemplateDaoTest extends AbstractTest
{
    private XliffConfigTemplateDao $dao;
    private int $uid = 999999;
    /** @var array<int, array{int, int}> */
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new XliffConfigTemplateDao();
        $this->createdIds = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->createdIds as [$id, $uid]) {
            $this->dao->remove($id, $uid);
        }
        parent::tearDown();
    }

    private function create(string $name, string $rules = '{"data_fuzzy_matches":true}'): XliffConfigTemplateStruct
    {
        $json = '{"name":"' . $name . ' ' . uniqid() . '","rules":' . $rules . '}';
        $struct = $this->dao->createFromJSON($json, $this->uid);
        $this->createdIds[] = [$struct->id, $this->uid];

        return $struct;
    }

    #[Test]
    public function getDefaultTemplateReturnsExpectedDefaults(): void
    {
        $default = $this->dao->getDefaultTemplate($this->uid);

        $this->assertSame(0, $default->id);
        $this->assertSame($this->uid, $default->uid);
        $this->assertSame('Matecat original settings', $default->name);
        $this->assertNotNull($default->created_at);
        $this->assertNotNull($default->modified_at);
    }

    #[Test]
    public function createFromJSONPersistsAndReturnsStruct(): void
    {
        $struct = $this->create('Test Template', '{"data_fuzzy_matches":true,"sort_parts":false}');

        $this->assertGreaterThan(0, $struct->id);
        $this->assertSame($this->uid, $struct->uid);
        $this->assertStringContainsString('Test Template', $struct->name);
        $this->assertNotNull($struct->created_at);
        $this->assertNotNull($struct->modified_at);
        $this->assertNull($struct->deleted_at);
    }

    #[Test]
    public function getByIdAndUserReturnsCreatedTemplate(): void
    {
        $created = $this->create('Readback Test');

        $fetched = $this->dao->getByIdAndUser($created->id, $this->uid);

        $this->assertNotNull($fetched);
        $this->assertSame($created->id, $fetched->id);
        $this->assertStringContainsString('Readback Test', $fetched->name);
    }

    #[Test]
    public function getByIdAndUserReturnsNullForWrongUid(): void
    {
        $created = $this->create('Wrong Uid Test');

        $this->assertNull($this->dao->getByIdAndUser($created->id, 999998));
    }

    #[Test]
    public function getByIdAndUserReturnsNullForNonExistentId(): void
    {
        $this->assertNull($this->dao->getByIdAndUser(999999999, $this->uid));
    }

    #[Test]
    public function getByIdReturnsTemplateWithoutUidCheck(): void
    {
        $created = $this->create('GetById Test');

        $fetched = $this->dao->getById($created->id);

        $this->assertNotNull($fetched);
        $this->assertStringContainsString('GetById Test', $fetched->name);
    }

    #[Test]
    public function getByIdReturnsNullForNonExistentId(): void
    {
        $this->assertNull($this->dao->getById(999999999));
    }

    #[Test]
    public function getByUidReturnsCreatedTemplates(): void
    {
        $c1 = $this->create('List Test 1');
        $c2 = $this->create('List Test 2', '{"sort_parts":false}');

        $list = $this->dao->getByUid($this->uid);

        $this->assertNotEmpty($list);
        $ids = array_map(fn(XliffConfigTemplateStruct $s) => $s->id, $list);
        $this->assertContains($c1->id, $ids);
        $this->assertContains($c2->id, $ids);
    }

    #[Test]
    public function getByUidReturnsEmptyForNoTemplates(): void
    {
        $this->assertSame([], $this->dao->getByUid(999999888));
    }

    #[Test]
    public function getAllPaginatedReturnsExpectedShape(): void
    {
        $this->create('Pagination Test');

        $result = $this->dao->getAllPaginated($this->uid, '/api/v3/xliff-config-template?page=', 1, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertNotEmpty($result['items']);
    }

    #[Test]
    public function editFromJSONUpdatesExistingTemplate(): void
    {
        $created = $this->create('Before Edit');

        $editJson = '{"name":"After Edit ' . uniqid() . '","rules":{"data_fuzzy_matches":false,"sort_parts":true}}';
        $updated = $this->dao->editFromJSON($created, $editJson, $this->uid);

        $this->assertSame($created->id, $updated->id);
        $this->assertStringContainsString('After Edit', $updated->name);

        $fetched = $this->dao->getByIdAndUser($created->id, $this->uid);
        $this->assertSame($updated->name, $fetched->name);
    }

    #[Test]
    public function saveInsertsNewTemplate(): void
    {
        $struct = new XliffConfigTemplateStruct();
        $struct->uid = $this->uid;
        $struct->name = 'Direct Save ' . uniqid();
        $struct->hydrateRulesFromJson('{"data_fuzzy_matches":true}');

        $saved = $this->dao->save($struct);
        $this->createdIds[] = [$saved->id, $this->uid];

        $this->assertGreaterThan(0, $saved->id);
        $this->assertStringContainsString('Direct Save', $saved->name);
    }

    #[Test]
    public function updateModifiesExistingTemplate(): void
    {
        $created = $this->create('Before Update');
        $created->name = 'After Update ' . uniqid();

        $updated = $this->dao->update($created);

        $this->assertStringContainsString('After Update', $updated->name);
    }

    #[Test]
    public function removeSoftDeletesTemplate(): void
    {
        $created = $this->create('Delete Test');

        $count = $this->dao->remove($created->id, $this->uid);

        $this->assertSame(1, $count);
        $this->assertNull($this->dao->getByIdAndUser($created->id, $this->uid));
    }
}
