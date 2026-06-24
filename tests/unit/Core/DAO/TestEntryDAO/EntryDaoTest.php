<?php

declare(strict_types=1);

namespace Matecat\Core\DAO\TestEntryDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\LQA\EntryDao;
use Model\LQA\EntryStruct;
use Model\LQA\EntryWithCategoryStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class EntryDaoTest extends AbstractTest
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

    private function makeEntryStruct(array $overrides = []): EntryStruct
    {
        return new EntryStruct(array_merge([
            'id' => 1,
            'id_segment' => 100,
            'id_job' => 10,
            'id_category' => 5,
            'severity' => 'Minor',
            'translation_version' => 1,
            'start_node' => 0,
            'start_offset' => 0,
            'end_node' => 0,
            'end_offset' => 10,
            'is_full_segment' => 0,
            'penalty_points' => 1.0,
            'comment' => 'test comment',
            'target_text' => 'test text',
            'uid' => 42,
            'source_page' => 2,
        ], $overrides));
    }

    #[Test]
    public function getBySegmentIdsReturnsStructs(): void
    {
        $s = new ShapelessConcreteStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$s]);

        $dao = new EntryDao($this->dbStub);
        $result = $dao->getBySegmentIds([1, 2]);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $result[0]);
    }

    #[Test]
    public function getBySegmentIdsReturnsEmptyForEmptyInput(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new EntryDao($this->dbStub);
        $result = $dao->getBySegmentIds([999]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function updateRepliesCountReturnsTrue(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new EntryDao($this->dbStub);
        $result = $dao->updateRepliesCount(1);

        $this->assertTrue($result);
    }

    #[Test]
    public function deleteEntryReturnsTrue(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new EntryDao($this->dbStub);
        $entry = $this->makeEntryStruct();
        $result = $dao->deleteEntry($entry);

        $this->assertTrue($result);
    }

    #[Test]
    public function findByIdReturnsStructWhenFound(): void
    {
        $entry = $this->makeEntryStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn($entry);

        $dao = new EntryDao($this->dbStub);
        $result = $dao->findById(1);

        $this->assertInstanceOf(EntryStruct::class, $result);
        $this->assertSame(1, $result->id);
    }

    #[Test]
    public function findByIdReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new EntryDao($this->dbStub);
        $result = $dao->findById(999);

        $this->assertNull($result);
    }

    #[Test]
    public function findAllByChunkReturnsStructs(): void
    {
        $s = new ShapelessConcreteStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$s]);

        $chunk = new JobStruct();
        $chunk->id = 10;
        $chunk->password = 'abc123';

        $dao = new EntryDao($this->dbStub);
        $result = $dao->findAllByChunk($chunk);

        $this->assertCount(1, $result);
    }

    #[Test]
    public function findByIdSegmentAndSourcePageReturnsArray(): void
    {
        $entry = new EntryWithCategoryStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$entry]);

        $dao = new EntryDao($this->dbStub);
        $result = $dao->findByIdSegmentAndSourcePage(100, 10, 2);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(EntryWithCategoryStruct::class, $result[0]);
    }

    #[Test]
    public function findAllByTranslationVersionReturnsArray(): void
    {
        $entry = $this->makeEntryStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$entry]);

        $dao = new EntryDao($this->dbStub);
        $result = $dao->findAllByTranslationVersion(100, 10, 1);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(EntryStruct::class, $result[0]);
    }

    #[Test]
    public function modifyEntryExecutesUpdate(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new EntryDao($this->dbStub);
        $entry = $this->makeEntryStruct();
        $result = $dao->modifyEntry($entry);

        $this->assertInstanceOf(EntryStruct::class, $result);
        $this->assertSame(1, $result->id);
    }

    #[Test]
    public function createEntryReturnsStructWithId(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->pdoStub->method('lastInsertId')->willReturn('99');

        $dao = new EntryDao($this->dbStub);
        $entry = $this->makeEntryStruct(['id' => null]);
        $result = $dao->createEntry($entry);

        $this->assertInstanceOf(EntryStruct::class, $result);
        $this->assertSame(99, $result->id);
    }

    #[Test]
    public function structEnsureOrderedSwapsOffsetsOnSameNodeWhenBackward(): void
    {
        $entry = $this->makeEntryStruct([
            'start_node' => 0,
            'start_offset' => 20,
            'end_node' => 0,
            'end_offset' => 5,
        ]);

        $entry->ensureStartAndStopPositionAreOrdered();

        $this->assertSame(5, $entry->start_offset);
        $this->assertSame(20, $entry->end_offset);
    }

    #[Test]
    public function structEnsureOrderedLeavesCorrectOrderUntouched(): void
    {
        $entry = $this->makeEntryStruct([
            'start_node' => 0,
            'start_offset' => 3,
            'end_node' => 0,
            'end_offset' => 10,
        ]);

        $entry->ensureStartAndStopPositionAreOrdered();

        $this->assertSame(3, $entry->start_offset);
        $this->assertSame(10, $entry->end_offset);
    }

    #[Test]
    public function structEnsureOrderedSwapsNodesWhenBackward(): void
    {
        $entry = $this->makeEntryStruct([
            'start_node' => 5,
            'start_offset' => 10,
            'end_node' => 2,
            'end_offset' => 3,
        ]);

        $entry->ensureStartAndStopPositionAreOrdered();

        $this->assertSame(2, $entry->start_node);
        $this->assertSame(5, $entry->end_node);
        $this->assertSame(3, $entry->start_offset);
        $this->assertSame(10, $entry->end_offset);
    }

    #[Test]
    public function structEnsureOrderedLeavesForwardDifferentNodesUntouched(): void
    {
        $entry = $this->makeEntryStruct([
            'start_node' => 1,
            'start_offset' => 5,
            'end_node' => 3,
            'end_offset' => 8,
        ]);

        $entry->ensureStartAndStopPositionAreOrdered();

        $this->assertSame(1, $entry->start_node);
        $this->assertSame(3, $entry->end_node);
        $this->assertSame(5, $entry->start_offset);
        $this->assertSame(8, $entry->end_offset);
    }
}
