<?php

declare(strict_types=1);

namespace Matecat\Core\DAO\TestModelDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\LQA\ModelDao;
use Model\LQA\ModelStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class ModelDaoTest extends AbstractTest
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

    #[Test]
    public function fetchByIdReturnsStructWhenFound(): void
    {
        $struct = new ModelStruct();
        $struct->id = 42;
        $struct->label = 'Test Model';
        $struct->pass_type = 'passfail';
        $struct->pass_options = '{"limit":[8,5]}';
        $struct->hash = 'abc123';
        $struct->create_date = '2026-01-01';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new ModelDao($this->dbStub);
        /** @var ?ModelStruct $result */
        $result = $dao->fetchById(42, ModelStruct::class);

        $this->assertInstanceOf(ModelStruct::class, $result);
        $this->assertSame(42, $result->id);
    }

    #[Test]
    public function fetchByIdReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new ModelDao($this->dbStub);
        $result = $dao->fetchById(999, ModelStruct::class);

        $this->assertNull($result);
    }

    #[Test]
    public function createRecordReturnsStructWithId(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->pdoStub->method('lastInsertId')->willReturn('55');

        $data = [
            'uid' => 1,
            'label' => 'Test QA Model',
            'passfail' => [
                'type' => 'passfail',
                'options' => ['limit' => [8, 5]],
            ],
        ];

        $dao = new ModelDao($this->dbStub);
        $result = $dao->createRecord($data);

        $this->assertInstanceOf(ModelStruct::class, $result);
        $this->assertSame(55, $result->id);
        $this->assertSame(1, $result->uid);
        $this->assertSame('Test QA Model', $result->label);
        $this->assertSame('passfail', $result->pass_type);
    }

    #[Test]
    public function createRecordHandlesTemplateId(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->pdoStub->method('lastInsertId')->willReturn('66');

        $data = [
            'uid' => 2,
            'label' => 'Template Model',
            'passfail' => [
                'type' => 'passfail',
                'options' => ['limit' => [15, 10]],
            ],
            'id_template' => 99,
        ];

        $dao = new ModelDao($this->dbStub);
        $result = $dao->createRecord($data);

        $this->assertSame(66, $result->id);
        $this->assertSame(99, $result->qa_model_template_id);
    }

    #[Test]
    public function createRecordNormalizesPassOptionsLimits(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->pdoStub->method('lastInsertId')->willReturn('77');

        $data = [
            'uid' => 1,
            'label' => 'Normalize Test',
            'passfail' => [
                'type' => 'passfail',
                'options' => ['limit' => ['8', '5']],
            ],
        ];

        $dao = new ModelDao($this->dbStub);
        $result = $dao->createRecord($data);

        $decoded = json_decode($result->pass_options, true);
        $this->assertSame([8, 5], $decoded['limit']);
    }

    #[Test]
    public function createModelFromJsonDefinitionCreatesModelAndCategories(): void
    {
        $callCount = 0;
        $this->stmtStub->method('execute')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            return true;
        });
        $this->pdoStub->method('lastInsertId')->willReturnOnConsecutiveCalls('10', '20');

        $json = [
            'model' => [
                'uid' => 1,
                'label' => 'Full Model',
                'version' => '1',
                'passfail' => [
                    'type' => 'passfail',
                    'options' => ['limit' => [8, 5]],
                ],
                'categories' => [
                    [
                        'label' => 'Accuracy',
                        'code' => 'ACC',
                        'severities' => [['label' => 'Minor', 'penalty' => 1, 'sort' => 1]],
                    ],
                ],
            ],
        ];

        $dao = new ModelDao($this->dbStub);
        $result = $dao->createModelFromJsonDefinition($json);

        $this->assertInstanceOf(ModelStruct::class, $result);
        $this->assertSame(10, $result->id);
    }

    #[Test]
    public function createModelFromJsonDefinitionHandlesSubcategories(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->pdoStub->method('lastInsertId')->willReturnOnConsecutiveCalls('10', '20', '30');

        $json = [
            'model' => [
                'uid' => 1,
                'label' => 'Subcategory Model',
                'version' => '1',
                'passfail' => [
                    'type' => 'passfail',
                    'options' => ['limit' => [8, 5]],
                ],
                'categories' => [
                    [
                        'label' => 'Fluency',
                        'code' => 'FLU',
                        'severities' => [['label' => 'Major', 'penalty' => 5, 'sort' => 2]],
                        'subcategories' => [
                            [
                                'label' => 'Grammar',
                                'severities' => [['label' => 'Minor', 'penalty' => 1, 'sort' => 1]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $dao = new ModelDao($this->dbStub);
        $result = $dao->createModelFromJsonDefinition($json);

        $this->assertInstanceOf(ModelStruct::class, $result);
        $this->assertSame(10, $result->id);
    }

    #[Test]
    public function createModelFromJsonUsesDefaultSeveritiesWhenCategoryLacksThem(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->pdoStub->method('lastInsertId')->willReturnOnConsecutiveCalls('10', '20');

        $json = [
            'model' => [
                'uid' => 1,
                'label' => 'Default Sev Model',
                'version' => '1',
                'passfail' => [
                    'type' => 'passfail',
                    'options' => ['limit' => [8, 5]],
                ],
                'severities' => [['label' => 'Default', 'penalty' => 3, 'sort' => 0]],
                'categories' => [
                    [
                        'label' => 'NoSeverity',
                        'code' => 'NSV',
                    ],
                ],
            ],
        ];

        $dao = new ModelDao($this->dbStub);
        $result = $dao->createModelFromJsonDefinition($json);

        $this->assertInstanceOf(ModelStruct::class, $result);
    }
}
