<?php

namespace Matecat\Core\Model\TranslationsSplit;

use Matecat\TestHelpers\AbstractTest;
use Model\TranslationsSplit\SegmentSplitStruct;
use Model\TranslationsSplit\SplitDAO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class SplitDAOTest extends AbstractTest
{
    private \PDO $pdoStub;
    private PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;

        [, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    // ─── read() ───

    #[Test]
    public function readReturnsStructs(): void
    {
        $struct = new SegmentSplitStruct();
        $struct->id_segment = 1;
        $struct->id_job = 10;
        $struct->source_chunk_lengths = '[100,200]';
        $struct->target_chunk_lengths = '[150,250]';

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $query = new SegmentSplitStruct();
        $query->id_segment = 1;
        $query->id_job = 10;

        $dao = new SplitDAO(obtainTestDatabase());
        $results = $dao->read($query);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(SegmentSplitStruct::class, $results[0]);
        $this->assertSame([100, 200], $results[0]->source_chunk_lengths);
        $this->assertSame([150, 250], $results[0]->target_chunk_lengths);
    }

    #[Test]
    public function readReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $query = new SegmentSplitStruct();
        $query->id_segment = 999;
        $query->id_job = 999;

        $dao = new SplitDAO(obtainTestDatabase());
        $results = $dao->read($query);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ─── sanitize() ───

    #[Test]
    public function sanitizeEncodesArraysToJson(): void
    {
        $struct = new SegmentSplitStruct();
        $struct->id_segment = 1;
        $struct->id_job = 10;
        $struct->source_chunk_lengths = [100, 200];
        $struct->target_chunk_lengths = [150, 250];

        $dao = new SplitDAO(obtainTestDatabase());
        $result = $dao->sanitize($struct);

        $this->assertSame('[100,200]', $result->source_chunk_lengths);
        $this->assertSame('[150,250]', $result->target_chunk_lengths);
    }

    #[Test]
    public function sanitizeKeepsNullValues(): void
    {
        $struct = new SegmentSplitStruct();
        $struct->id_segment = 1;
        $struct->id_job = 10;
        $struct->source_chunk_lengths = null;
        $struct->target_chunk_lengths = null;

        $dao = new SplitDAO(obtainTestDatabase());
        $result = $dao->sanitize($struct);

        $this->assertNull($result->source_chunk_lengths);
        $this->assertNull($result->target_chunk_lengths);
    }


}
