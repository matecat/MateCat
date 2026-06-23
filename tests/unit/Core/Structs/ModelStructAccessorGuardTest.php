<?php

declare(strict_types=1);

namespace Matecat\Core\Structs;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\LQA\CategoryDao;
use Model\LQA\CategoryStruct;
use Model\LQA\ModelStruct;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * RED→GREEN guard tests for ModelStruct category accessor singleton removal (T3).
 *
 * Written BEFORE the implementation change (TDD strict RED step).
 * After T3:
 *   - getSerializedCategories(CategoryDao $dao)
 *   - getCategoriesAndSeverities(CategoryDao $dao)
 *   - getCategories(CategoryDao $dao)
 *   - getDecodedModel(CategoryDao $dao)
 * All use the injected DAO; singleton never touched.
 */
class ModelStructAccessorGuardTest extends AbstractTest
{
    private ModelStruct $struct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->struct              = new ModelStruct();
        $this->struct->id          = 1;
        $this->struct->label       = 'Test Model';
        $this->struct->create_date = '2026-01-01 00:00:00';
        $this->struct->pass_type   = 'passfail';
        $this->struct->pass_options = '{"limit":["8","5"]}';
        $this->struct->hash        = 'abc123';
    }

    private function makeCategoryStruct(): CategoryStruct
    {
        $s           = new CategoryStruct();
        $s->id       = 10;
        $s->id_model = 1;
        $s->label    = 'Accuracy';
        $s->severities = '[{"label":"Minor","penalty":1,"sort":1}]';
        $s->options  = '{"code":"ACC"}';

        return $s;
    }

    // -------------------------------------------------------------------------
    // getSerializedCategories
    // -------------------------------------------------------------------------

    /**
     * getSerializedCategories must use the injected CategoryDao, never the singleton.
     *
     * Before T3: getSerializedCategories() calls `new CategoryDao()` → hits
     * Database::obtain() → poison fails.
     * After T3: getSerializedCategories(CategoryDao $dao) uses $dao directly
     * → singleton never touched → GREEN.
     */
    #[Test]
    public function getSerializedCategories_uses_injected_dao_not_singleton(): void
    {
        $mockDao = $this->createMock(CategoryDao::class);
        $mockDao->expects($this->once())
            ->method('getCategoriesAndSeverities')
            ->with(1)
            ->willReturn([['label' => 'Accuracy', 'severities' => []]]);

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $result = $this->struct->getSerializedCategories($mockDao);

        $this->assertArrayHasKey('categories', $result);
        $this->assertCount(1, $result['categories']);
        $this->assertSame('Accuracy', $result['categories'][0]['label']);
    }

    /**
     * getSerializedCategories throws RuntimeException when struct id is null.
     */
    #[Test]
    public function getSerializedCategories_throws_when_id_null(): void
    {
        $this->struct->id = null;

        $mockDao = $this->createMock(CategoryDao::class);
        $mockDao->expects($this->never())->method('getCategoriesAndSeverities');

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing model id');

        $this->struct->getSerializedCategories($mockDao);
    }

    // -------------------------------------------------------------------------
    // getCategoriesAndSeverities
    // -------------------------------------------------------------------------

    /**
     * getCategoriesAndSeverities must use the injected CategoryDao, never the singleton.
     */
    #[Test]
    public function getCategoriesAndSeverities_uses_injected_dao_not_singleton(): void
    {
        $mockDao = $this->createMock(CategoryDao::class);
        $mockDao->expects($this->once())
            ->method('getCategoriesAndSeverities')
            ->with(1)
            ->willReturn([['label' => 'Fluency', 'severities' => []]]);

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $result = $this->struct->getCategoriesAndSeverities($mockDao);

        $this->assertCount(1, $result);
        $this->assertSame('Fluency', $result[0]['label']);
    }

    // -------------------------------------------------------------------------
    // getCategories
    // -------------------------------------------------------------------------

    /**
     * getCategories must use the injected CategoryDao, never the singleton.
     */
    #[Test]
    public function getCategories_uses_injected_dao_not_singleton(): void
    {
        $cat     = $this->makeCategoryStruct();
        $mockDao = $this->createMock(CategoryDao::class);
        $mockDao->expects($this->once())
            ->method('getCategoriesByModel')
            ->with($this->struct)
            ->willReturn([$cat]);

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $result = $this->struct->getCategories($mockDao);

        $this->assertCount(1, $result);
        $this->assertSame($cat, $result[0]);
    }

    // -------------------------------------------------------------------------
    // getDecodedModel
    // -------------------------------------------------------------------------

    /**
     * getDecodedModel must forward the injected DAO to getCategories, never
     * touching the singleton.
     */
    #[Test]
    public function getDecodedModel_uses_injected_dao_not_singleton(): void
    {
        $cat     = $this->makeCategoryStruct();
        $mockDao = $this->createMock(CategoryDao::class);
        $mockDao->expects($this->once())
            ->method('getCategoriesByModel')
            ->with($this->struct)
            ->willReturn([$cat]);

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $this->struct->uid = 42;
        $result = $this->struct->getDecodedModel($mockDao);

        $this->assertArrayHasKey('model', $result);
        $this->assertSame(1, $result['model']['id']);
        $this->assertSame(42, $result['model']['uid']);
        $this->assertCount(1, $result['model']['categories']);
        $this->assertSame('Accuracy', $result['model']['categories'][0]['label']);
        $this->assertSame('ACC', $result['model']['categories'][0]['code']);
    }
}
