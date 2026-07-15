<?php

namespace Matecat\Core\DAO\TestChunkCompletionUpdateDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\ChunksCompletion\ChunkCompletionUpdateDao;
use Model\ChunksCompletion\ChunkCompletionUpdateStruct;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class ChunkCompletionUpdateDaoTest extends AbstractTest
{
    private PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;

        [,, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function makeStruct(): ChunkCompletionUpdateStruct
    {
        $struct = new ChunkCompletionUpdateStruct();
        $struct->id_project = 1;
        $struct->id_job = 10;
        $struct->password = 'pass1';
        $struct->job_first_segment = 100;
        $struct->job_last_segment = 200;
        $struct->source = 'web';
        $struct->uid = 42;
        $struct->is_review = false;
        $struct->last_translation_at = '2024-01-01 00:00:00';

        return $struct;
    }

    #[Test]
    public function createOrUpdateFromStructReturnsTrue(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new ChunkCompletionUpdateDao(obtainTestDatabase());
        $result = $dao->createOrUpdateFromStruct($this->makeStruct());

        $this->assertTrue($result);
    }

    #[Test]
    public function createOrUpdateFromStructReturnsFalse(): void
    {
        $this->stmtStub->method('execute')->willReturn(false);

        $dao = new ChunkCompletionUpdateDao(obtainTestDatabase());
        $result = $dao->createOrUpdateFromStruct($this->makeStruct());

        $this->assertFalse($result);
    }

    #[Test]
    public function createOrUpdateFromStructWithReview(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $struct = $this->makeStruct();
        $struct->is_review = true;

        $dao = new ChunkCompletionUpdateDao(obtainTestDatabase());
        $result = $dao->createOrUpdateFromStruct($struct);

        $this->assertTrue($result);
    }

    #[Test]
    public function updatePasswordReturnsRowCount(): void
    {
        $this->stmtStub->method('rowCount')->willReturn(1);

        $dao = new ChunkCompletionUpdateDao(obtainTestDatabase());
        $result = $dao->updatePassword(10, 'new_pass', 'old_pass');

        $this->assertSame(1, $result);
    }

    #[Test]
    public function updatePasswordReturnsZeroWhenNoMatch(): void
    {
        $this->stmtStub->method('rowCount')->willReturn(0);

        $dao = new ChunkCompletionUpdateDao(obtainTestDatabase());
        $result = $dao->updatePassword(10, 'new_pass', 'old_pass');

        $this->assertSame(0, $result);
    }
}
