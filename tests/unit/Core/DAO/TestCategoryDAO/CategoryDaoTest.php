<?php

declare(strict_types=1);

namespace Matecat\Core\DAO\TestCategoryDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\LQA\CategoryDao;
use Model\LQA\CategoryStruct;
use Model\LQA\ModelStruct;
use PDO;
use PDOStatement;
use Utils\Registry\AppConfig;

class CategoryDaoTest extends AbstractTest
{
    private \PHPUnit\Framework\MockObject\Stub&IDatabase $dbStub;
    private \PHPUnit\Framework\MockObject\Stub&PDO $pdoStub;
    private \PHPUnit\Framework\MockObject\Stub&PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();

        AppConfig::$SKIP_SQL_CACHE = false;

        parent::tearDown();
    }


    public function testFindByIdModelAndIdParentReturnsArrayOfStructs(): void
    {
        $s1 = new CategoryStruct();
        $s1->id = 1;
        $s1->id_model = 5;
        $s1->id_parent = null;
        $s1->label = 'Cat1';
        $s1->severities = '[]';

        $s2 = new CategoryStruct();
        $s2->id = 2;
        $s2->id_model = 5;
        $s2->id_parent = null;
        $s2->label = 'Cat2';
        $s2->severities = '[]';

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$s1, $s2]);

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->findByIdModelAndIdParent(5, null);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(CategoryStruct::class, $result[0]);
    }

    public function testFindByIdModelAndIdParentReturnsEmptyArrayWhenNoResults(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->findByIdModelAndIdParent(5, 10);

        $this->assertSame([], $result);
    }


    // ── Instance method tests ──

    public function testInstanceFetchByIdReturnsStructWhenFound(): void
    {
        $struct = new CategoryStruct();
        $struct->id = 42;
        $struct->id_model = 1;
        $struct->label = 'Accuracy';
        $struct->severities = '[]';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new CategoryDao($this->dbStub);
        /** @var ?CategoryStruct $result */
        $result = $dao->fetchById(42, CategoryStruct::class);

        $this->assertInstanceOf(CategoryStruct::class, $result);
        $this->assertSame(42, $result->id);
    }

    public function testInstanceFetchByIdReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->fetchById(999, CategoryStruct::class);

        $this->assertNull($result);
    }

    public function testInstanceCreateRecordReturnsStructWithInsertedId(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->pdoStub->method('lastInsertId')->willReturn('77');

        $data = [
            'id_model'   => 1,
            'label'      => 'Fluency',
            'id_parent'  => null,
            'severities' => '[{"label":"Minor","penalty":1,"sort":1}]',
            'options'    => null,
        ];

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->createRecord($data);

        $this->assertInstanceOf(CategoryStruct::class, $result);
        $this->assertSame(77, $result->id);
        $this->assertSame('Fluency', $result->label);
        $this->assertSame(1, $result->id_model);
    }

    public function testInstanceCreateRecordSetsOptionsWhenProvided(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->pdoStub->method('lastInsertId')->willReturn('88');

        $data = [
            'id_model'   => 2,
            'label'      => 'Style',
            'id_parent'  => 5,
            'severities' => '[]',
            'options'    => '{"code":"STY","sort":2}',
        ];

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->createRecord($data);

        $this->assertSame(88, $result->id);
        $this->assertSame(5, $result->id_parent);
        $this->assertSame('{"code":"STY","sort":2}', $result->options);
    }

    public function testInstanceGetCategoriesByModelReturnsArrayOfStructs(): void
    {
        $s1 = new CategoryStruct();
        $s1->id = 10;
        $s1->id_model = 3;
        $s1->label = 'Terminology';
        $s1->severities = '[]';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$s1]);

        $model = new ModelStruct();
        $model->id = 3;

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->getCategoriesByModel($model);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(CategoryStruct::class, $result[0]);
        $this->assertSame(10, $result[0]->id);
    }

    public function testInstanceGetCategoriesByModelReturnsEmptyArray(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $model = new ModelStruct();
        $model->id = 999;

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->getCategoriesByModel($model);

        $this->assertSame([], $result);
    }

    public function testInstanceGetCategoriesAndSeveritiesReturnsParentCategory(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '5',
                'id_parent'  => null,
                'label'      => 'Accuracy',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1}]',
                'options'    => null,
            ],
        ]);

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->getCategoriesAndSeverities(5);

        $this->assertCount(1, $result);
        $this->assertSame('Accuracy', $result[0]['label']);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame([], $result[0]['subcategories']);
        $this->assertCount(1, $result[0]['severities']);
        $this->assertSame('Minor', $result[0]['severities'][0]['label']);
        $this->assertSame(1, $result[0]['severities'][0]['penalty']);
    }

    public function testInstanceGetCategoriesAndSeveritiesProcessesSubcategories(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '10',
                'id_model'   => '5',
                'id_parent'  => null,
                'label'      => 'Fluency',
                'severities' => '[{"label":"Critical","penalty":10,"sort":0}]',
                'options'    => null,
            ],
            [
                'id'         => '20',
                'id_model'   => '5',
                'id_parent'  => '10',
                'label'      => 'Grammar',
                'severities' => '[{"label":"Major","penalty":5,"sort":1}]',
                'options'    => '{"code":"GRM","sort":1}',
            ],
        ]);

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->getCategoriesAndSeverities(5);

        $this->assertCount(1, $result);
        $this->assertSame('Fluency', $result[0]['label']);
        $this->assertCount(1, $result[0]['subcategories']);
        $this->assertSame('Grammar', $result[0]['subcategories'][0]['label']);
        $this->assertSame('20', $result[0]['subcategories'][0]['id']);
    }

    public function testInstanceGetCategoriesAndSeveritiesReturnsEmptyArrayWhenNoResults(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->getCategoriesAndSeverities(999);

        $this->assertSame([], $result);
    }

    public function testInstanceGetCategoriesAndSeveritiesHandlesMultipleParentsAndSubcategories(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '3',
                'id_parent'  => null,
                'label'      => 'Accuracy',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1}]',
                'options'    => null,
            ],
            [
                'id'         => '2',
                'id_model'   => '3',
                'id_parent'  => null,
                'label'      => 'Fluency',
                'severities' => '[{"label":"Major","penalty":5,"sort":2}]',
                'options'    => null,
            ],
            [
                'id'         => '3',
                'id_model'   => '3',
                'id_parent'  => '1',
                'label'      => 'Mistranslation',
                'severities' => '[{"label":"Critical","penalty":10,"sort":0}]',
                'options'    => null,
            ],
            [
                'id'         => '4',
                'id_model'   => '3',
                'id_parent'  => '2',
                'label'      => 'Grammar',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1}]',
                'options'    => null,
            ],
        ]);

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->getCategoriesAndSeverities(3);

        $this->assertCount(2, $result);
        $this->assertSame('Accuracy', $result[0]['label']);
        $this->assertCount(1, $result[0]['subcategories']);
        $this->assertSame('Mistranslation', $result[0]['subcategories'][0]['label']);
        $this->assertSame('Fluency', $result[1]['label']);
        $this->assertCount(1, $result[1]['subcategories']);
        $this->assertSame('Grammar', $result[1]['subcategories'][0]['label']);
    }

    public function testInstanceExtractSeveritiesIncludesCodeWhenPresent(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '1',
                'id_parent'  => null,
                'label'      => 'Test',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1,"code":"MIN"}]',
                'options'    => null,
            ],
        ]);

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->getCategoriesAndSeverities(1);

        $this->assertSame('MIN', $result[0]['severities'][0]['code']);
    }

    public function testInstanceExtractOptionsReturnsAllowedKeys(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '1',
                'id_parent'  => null,
                'label'      => 'Test',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1}]',
                'options'    => '{"code":"ACC","sort":1}',
            ],
        ]);

        $dao = new CategoryDao($this->dbStub);
        $result = $dao->getCategoriesAndSeverities(1);

        $this->assertCount(2, $result[0]['options']);
        $this->assertSame('code', $result[0]['options'][0]['key']);
        $this->assertSame('ACC', $result[0]['options'][0]['value']);
    }
}
