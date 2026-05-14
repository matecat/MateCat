<?php

declare(strict_types=1);

namespace unit\DAO\TestCategoryDAO;

use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\LQA\CategoryDao;
use Model\LQA\CategoryStruct;
use Model\LQA\ModelStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Utils\Registry\AppConfig;

class CategoryDaoTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\Stub&IDatabase $dbStub;
    private \PHPUnit\Framework\MockObject\Stub&PDO $pdoStub;
    private \PHPUnit\Framework\MockObject\Stub&PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        $this->stmtStub = $this->createStub(PDOStatement::class);
        $this->stmtStub->queryString = '';

        $this->pdoStub = $this->createStub(PDO::class);
        $this->pdoStub->method('prepare')->willReturn($this->stmtStub);

        $this->dbStub = $this->createStub(IDatabase::class);
        $this->dbStub->method('getConnection')->willReturn($this->pdoStub);

        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $this->dbStub);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        AppConfig::$SKIP_SQL_CACHE = false;

        parent::tearDown();
    }


    public function testFindByIdReturnsStructWhenFound(): void
    {
        $struct = new CategoryStruct();
        $struct->id = 42;
        $struct->id_model = 1;
        $struct->label = 'Accuracy';
        $struct->severities = '[]';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = CategoryDao::findById(42);

        $this->assertInstanceOf(CategoryStruct::class, $result);
        $this->assertSame(42, $result->id);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = CategoryDao::findById(999);

        $this->assertNull($result);
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


    public function testCreateRecordReturnsStructWithInsertedId(): void
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

        $result = CategoryDao::createRecord($data);

        $this->assertInstanceOf(CategoryStruct::class, $result);
        $this->assertSame(77, $result->id);
        $this->assertSame('Fluency', $result->label);
        $this->assertSame(1, $result->id_model);
    }

    public function testCreateRecordSetsOptionsWhenProvided(): void
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

        $result = CategoryDao::createRecord($data);

        $this->assertSame(88, $result->id);
        $this->assertSame(5, $result->id_parent);
        $this->assertSame('{"code":"STY","sort":2}', $result->options);
    }


    public function testGetCategoriesByModelReturnsArrayOfStructs(): void
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

        $result = CategoryDao::getCategoriesByModel($model);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(CategoryStruct::class, $result[0]);
        $this->assertSame(10, $result[0]->id);
    }

    public function testGetCategoriesByModelReturnsEmptyArray(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $model = new ModelStruct();
        $model->id = 999;

        $result = CategoryDao::getCategoriesByModel($model);

        $this->assertSame([], $result);
    }


    public function testGetCategoriesAndSeveritiesReturnsParentCategory(): void
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

        $result = CategoryDao::getCategoriesAndSeverities(5);

        $this->assertCount(1, $result);
        $this->assertSame('Accuracy', $result[0]['label']);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame([], $result[0]['subcategories']);
        $this->assertCount(1, $result[0]['severities']);
        $this->assertSame('Minor', $result[0]['severities'][0]['label']);
        $this->assertSame(1, $result[0]['severities'][0]['penalty']);
    }

    public function testGetCategoriesAndSeveritiesProcessesSubcategories(): void
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

        $result = CategoryDao::getCategoriesAndSeverities(5);

        $this->assertCount(1, $result);
        $this->assertSame('Fluency', $result[0]['label']);
        $this->assertCount(1, $result[0]['subcategories']);
        $this->assertSame('Grammar', $result[0]['subcategories'][0]['label']);
        $this->assertSame('20', $result[0]['subcategories'][0]['id']);
    }

    public function testGetCategoriesAndSeveritiesReturnsEmptyArrayWhenNoResults(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = CategoryDao::getCategoriesAndSeverities(999);

        $this->assertSame([], $result);
    }

    public function testGetCategoriesAndSeveritiesHandlesMultipleParentsAndSubcategories(): void
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

        $result = CategoryDao::getCategoriesAndSeverities(3);

        $this->assertCount(2, $result);
        $this->assertSame('Accuracy', $result[0]['label']);
        $this->assertCount(1, $result[0]['subcategories']);
        $this->assertSame('Mistranslation', $result[0]['subcategories'][0]['label']);
        $this->assertSame('Fluency', $result[1]['label']);
        $this->assertCount(1, $result[1]['subcategories']);
        $this->assertSame('Grammar', $result[1]['subcategories'][0]['label']);
    }


    public function testExtractSeveritiesIncludesCodeWhenPresent(): void
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

        $result = CategoryDao::getCategoriesAndSeverities(1);

        $this->assertSame('MIN', $result[0]['severities'][0]['code']);
    }

    public function testExtractSeveritiesOmitsCodeWhenAbsent(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '1',
                'id_parent'  => null,
                'label'      => 'Test',
                'severities' => '[{"label":"Major","penalty":5,"sort":2}]',
                'options'    => null,
            ],
        ]);

        $result = CategoryDao::getCategoriesAndSeverities(1);

        $this->assertArrayNotHasKey('code', $result[0]['severities'][0]);
    }

    public function testExtractSeveritiesHandlesMultipleSeverities(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '1',
                'id_parent'  => null,
                'label'      => 'Test',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1},{"label":"Major","penalty":5,"sort":2},{"label":"Critical","penalty":10,"sort":0}]',
                'options'    => null,
            ],
        ]);

        $result = CategoryDao::getCategoriesAndSeverities(1);

        $this->assertCount(3, $result[0]['severities']);
        $this->assertSame('Minor', $result[0]['severities'][0]['label']);
        $this->assertSame('Major', $result[0]['severities'][1]['label']);
        $this->assertSame('Critical', $result[0]['severities'][2]['label']);
    }


    public function testExtractOptionsReturnsAllowedKeys(): void
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

        $result = CategoryDao::getCategoriesAndSeverities(1);

        $this->assertCount(2, $result[0]['options']);
        $this->assertSame('code', $result[0]['options'][0]['key']);
        $this->assertSame('ACC', $result[0]['options'][0]['value']);
        $this->assertSame('sort', $result[0]['options'][1]['key']);
        $this->assertSame(1, $result[0]['options'][1]['value']);
    }

    public function testExtractOptionsFiltersNonAllowedKeys(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '1',
                'id_parent'  => null,
                'label'      => 'Test',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1}]',
                'options'    => '{"code":"ACC","ignored_key":"should_not_appear","sort":2}',
            ],
        ]);

        $result = CategoryDao::getCategoriesAndSeverities(1);

        $this->assertCount(2, $result[0]['options']);
        foreach ($result[0]['options'] as $opt) {
            $this->assertContains($opt['key'], ['code', 'sort']);
        }
    }

    public function testExtractOptionsReturnsEmptyArrayWhenOptionsNull(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '1',
                'id_parent'  => null,
                'label'      => 'Test',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1}]',
                'options'    => null,
            ],
        ]);

        $result = CategoryDao::getCategoriesAndSeverities(1);

        $this->assertSame([], $result[0]['options']);
    }

    public function testExtractOptionsReturnsEmptyArrayWhenOptionsIsEmptyObject(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '1',
                'id_parent'  => null,
                'label'      => 'Test',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1}]',
                'options'    => '{}',
            ],
        ]);

        $result = CategoryDao::getCategoriesAndSeverities(1);

        $this->assertSame([], $result[0]['options']);
    }


    public function testSubcategoryOptionsAndSeveritiesAreExtracted(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '10',
                'id_model'   => '1',
                'id_parent'  => null,
                'label'      => 'Parent',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1}]',
                'options'    => null,
            ],
            [
                'id'         => '20',
                'id_model'   => '1',
                'id_parent'  => '10',
                'label'      => 'Child',
                'severities' => '[{"label":"Critical","penalty":10,"sort":0,"code":"CRI"}]',
                'options'    => '{"code":"CHD","sort":5}',
            ],
        ]);

        $result = CategoryDao::getCategoriesAndSeverities(1);

        $sub = $result[0]['subcategories'][0];
        $this->assertSame('Child', $sub['label']);
        $this->assertCount(1, $sub['severities']);
        $this->assertSame('Critical', $sub['severities'][0]['label']);
        $this->assertSame('CRI', $sub['severities'][0]['code']);
        $this->assertCount(2, $sub['options']);
        $this->assertSame('code', $sub['options'][0]['key']);
        $this->assertSame('CHD', $sub['options'][0]['value']);
    }
}
