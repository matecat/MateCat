<?php

namespace Matecat\Core\Model\Xliff;

use Model\Projects\ProjectTemplateDao;
use Model\Xliff\XliffConfigTemplateDao;
use Model\Xliff\XliffConfigTemplateStruct;
use PHPUnit\Framework\Attributes\Group;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;

/**
 * Real-SQL coverage for XliffConfigTemplateDao (plan dao-realsql-90.md, Wave 6 / T15).
 *
 * Every public SQL method is called DIRECTLY and asserted on real returned data (DoD b):
 *   getAllPaginated, createFromJSON, editFromJSON, getById, getByIdAndUser, getByUid,
 *   remove, save, update, getDefaultTemplate.
 *
 * The collaborating ProjectTemplateDao (used by remove()) is injected on the SAME per-test
 * connection so the whole DAO graph stays on one PDO handle (C-2). No wrapping transaction
 * (C-1). DAO-created rows are tracked for the whole-table residue gate (A-1). No assertion on
 * absolute generated id values (M-3).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class XliffConfigTemplateDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['xliff_config_templates', 'project_templates', 'users'];

    private XliffConfigTemplateDao $dao;
    private int $uid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        // inject the collaborating DAO on the SAME connection (C-2): remove() delegates to it.
        $projectTemplateDao = new ProjectTemplateDao($this->realSqlDb());
        $this->dao = new XliffConfigTemplateDao($this->realSqlDb(), $projectTemplateDao);
        $this->assertDaoUsesTestConnection($this->dao);

        $this->uid = $this->fixtures->makeUser()['uid'];
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    /** Create via the DAO and register the inserted row for residue-gate cleanup. */
    private function createTemplate(string $name, string $rules = '{"data_fuzzy_matches":true}'): XliffConfigTemplateStruct
    {
        $json = '{"name":"' . $name . ' ' . bin2hex(random_bytes(4)) . '","rules":' . $rules . '}';
        $struct = $this->dao->createFromJSON($json, $this->uid);
        $this->fixtures->trackExisting('xliff_config_templates', ['id' => $struct->id]);

        return $struct;
    }

    public function testGetDefaultTemplateBuildsInMemoryDefaults(): void
    {
        $default = $this->dao->getDefaultTemplate($this->uid);

        $this->assertSame(0, $default->id);
        $this->assertSame($this->uid, $default->uid);
        $this->assertSame('Matecat original settings', $default->name);
        $this->assertNotNull($default->created_at);
        $this->assertNotNull($default->modified_at);
    }

    public function testCreateFromJSONPersistsAndRoundTrips(): void
    {
        $struct = $this->createTemplate('Created', '{"data_fuzzy_matches":true,"sort_parts":false}');

        $this->assertGreaterThan(0, $struct->id);
        $this->assertSame($this->uid, $struct->uid);
        $this->assertStringContainsString('Created', $struct->name);
        $this->assertNotNull($struct->created_at);
        $this->assertNull($struct->deleted_at);

        $byId = $this->dao->getById($struct->id, 0);
        $this->assertInstanceOf(XliffConfigTemplateStruct::class, $byId);
        $this->assertSame($struct->id, $byId->id);
    }

    public function testGetByIdAndUserReturnsRowAndNullForWrongUid(): void
    {
        $created = $this->createTemplate('ByIdUser');

        $fetched = $this->dao->getByIdAndUser($created->id, $this->uid, 0);
        $this->assertInstanceOf(XliffConfigTemplateStruct::class, $fetched);
        $this->assertSame($created->id, $fetched->id);

        $this->assertNull($this->dao->getByIdAndUser($created->id, $this->uid + 12345, 0));
    }

    public function testGetByIdReturnsNullForAbsent(): void
    {
        $this->assertNull($this->dao->getById(2_000_111_222, 0));
    }

    public function testGetByUidReturnsAllUserTemplates(): void
    {
        $a = $this->createTemplate('UidA');
        $b = $this->createTemplate('UidB');

        $all = $this->dao->getByUid($this->uid, 0);

        $ids = array_map(static fn(XliffConfigTemplateStruct $s): int => $s->id, $all);
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
        $this->assertCount(2, $all);
    }

    public function testGetByUidReturnsEmptyForFreshUser(): void
    {
        $this->assertSame([], $this->dao->getByUid($this->uid, 0));
    }

    public function testEditFromJSONUpdatesNameAndPersists(): void
    {
        $created = $this->createTemplate('Editable');

        $edited = $this->dao->editFromJSON(
            $created,
            '{"name":"Edited Name","rules":{"data_fuzzy_matches":false}}',
            $this->uid
        );

        $this->assertSame('Edited Name', $edited->name);

        $reloaded = $this->dao->getById($created->id, 0);
        $this->assertInstanceOf(XliffConfigTemplateStruct::class, $reloaded);
        $this->assertSame('Edited Name', $reloaded->name);
    }

    public function testUpdatePersistsNameChange(): void
    {
        $created = $this->createTemplate('PreUpdate');
        $created->name = 'PostUpdate';

        $returned = $this->dao->update($created);
        $this->assertSame('PostUpdate', $returned->name);

        $reloaded = $this->dao->getById($created->id, 0);
        $this->assertInstanceOf(XliffConfigTemplateStruct::class, $reloaded);
        $this->assertSame('PostUpdate', $reloaded->name);
    }

    public function testGetAllPaginatedReturnsPagerShape(): void
    {
        $this->createTemplate('Page1');
        $this->createTemplate('Page2');

        $result = $this->dao->getAllPaginated($this->uid, '/base/route', 1, 20, 0);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);
        $this->assertContainsOnlyInstancesOf(XliffConfigTemplateStruct::class, $result['items']);
    }

    public function testRemoveSoftDeletesAndHidesFromReads(): void
    {
        $created = $this->createTemplate('ToRemove');

        $affected = $this->dao->remove($created->id, $this->uid);
        $this->assertSame(1, $affected);

        // soft-deleted rows are excluded by the deleted_at IS NULL predicate
        $this->assertNull($this->dao->getByIdAndUser($created->id, $this->uid, 0));
        $this->assertSame([], $this->dao->getByUid($this->uid, 0));
    }

    public function testSaveDirectlyInsertsRow(): void
    {
        $struct = new XliffConfigTemplateStruct();
        $struct->uid = $this->uid;
        $struct->name = 'DirectSave ' . bin2hex(random_bytes(3));
        $struct->hydrateRulesFromJson('{"data_fuzzy_matches":true}');

        $saved = $this->dao->save($struct);
        $this->fixtures->trackExisting('xliff_config_templates', ['id' => $saved->id]);

        $this->assertGreaterThan(0, $saved->id);
        $this->assertNotNull($saved->created_at);

        $reloaded = $this->dao->getById($saved->id, 0);
        $this->assertInstanceOf(XliffConfigTemplateStruct::class, $reloaded);
    }
}
