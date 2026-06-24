<?php

declare(strict_types=1);

namespace Matecat\Core\LQA;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\LQA\CategoryStruct;
use Model\LQA\ModelStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Utils\Registry\AppConfig;

class ModelStructTest extends AbstractTest
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

    private function makeModel(int $id = 1): ModelStruct
    {
        $model = new ModelStruct();
        $model->id = $id;
        $model->label = 'Test Model';
        $model->create_date = '2026-01-01 00:00:00';
        $model->pass_type = 'passfail';
        $model->pass_options = '{"limit":["8","5"]}';
        $model->hash = 'abc123';

        return $model;
    }

    #[Test]
    public function getPassOptionsReturnsDecodedJson(): void
    {
        $model = $this->makeModel();
        $result = $model->getPassOptions();

        $this->assertIsObject($result);
        $this->assertEquals([8, 5], $result->limit);
    }

    #[Test]
    public function getPassOptionsReturnsNullForInvalidJson(): void
    {
        $model = $this->makeModel();
        $model->pass_options = '';
        $result = $model->getPassOptions();

        $this->assertNull($result);
    }

    #[Test]
    public function getLimitReturnsNormalizedIntArray(): void
    {
        $model = $this->makeModel();
        $result = $model->getLimit();

        $this->assertSame([8, 5], $result);
    }

    #[Test]
    public function getLimitThrowsWhenLimitKeyMissing(): void
    {
        $model = $this->makeModel();
        $model->pass_options = '{"type":"passfail"}';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('limit is not defined in JSON options');

        $model->getLimit();
    }

    #[Test]
    public function getSerializedCategoriesReturnsStructure(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '1',
                'id_parent'  => null,
                'label'      => 'Accuracy',
                'severities' => '[{"label":"Minor","penalty":1,"sort":1}]',
                'options'    => null,
            ],
        ]);

        $model = $this->makeModel();
        $result = $model->getSerializedCategories();

        $this->assertArrayHasKey('categories', $result);
        $this->assertCount(1, $result['categories']);
        $this->assertSame('Accuracy', $result['categories'][0]['label']);
    }

    #[Test]
    public function getSerializedCategoriesThrowsWhenIdNull(): void
    {
        $model = $this->makeModel();
        $model->id = null;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing model id');

        $model->getSerializedCategories();
    }

    #[Test]
    public function getCategoriesAndSeveritiesReturnsList(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            [
                'id'         => '1',
                'id_model'   => '1',
                'id_parent'  => null,
                'label'      => 'Fluency',
                'severities' => '[{"label":"Major","penalty":5,"sort":2}]',
                'options'    => null,
            ],
        ]);

        $model = $this->makeModel();
        $result = $model->getCategoriesAndSeverities();

        $this->assertCount(1, $result);
        $this->assertSame('Fluency', $result[0]['label']);
    }

    #[Test]
    public function getCategoriesReturnsArrayOfStructs(): void
    {
        $s1 = new CategoryStruct();
        $s1->id = 10;
        $s1->id_model = 1;
        $s1->label = 'Terminology';
        $s1->severities = '[]';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$s1]);

        $model = $this->makeModel();
        $result = $model->getCategories();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(CategoryStruct::class, $result[0]);
    }

    #[Test]
    public function getDecodedModelReturnsFullStructure(): void
    {
        $s1 = new CategoryStruct();
        $s1->id = 10;
        $s1->id_model = 1;
        $s1->id_parent = null;
        $s1->label = 'Accuracy';
        $s1->severities = '[{"label":"Minor","penalty":1,"sort":1}]';
        $s1->options = '{"code":"ACC"}';

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$s1]);

        $model = $this->makeModel();
        $model->uid = 42;
        $result = $model->getDecodedModel();

        $this->assertArrayHasKey('model', $result);
        $this->assertSame(1, $result['model']['id']);
        $this->assertSame(42, $result['model']['uid']);
        $this->assertSame('Test Model', $result['model']['label']);
        $this->assertCount(1, $result['model']['categories']);
        $this->assertSame('Accuracy', $result['model']['categories'][0]['label']);
        $this->assertSame('ACC', $result['model']['categories'][0]['code']);
    }
}
