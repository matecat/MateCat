<?php

namespace Matecat\Core\Structs;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Files\FileDao;
use Model\Files\FileStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

/**
 * RED→GREEN guard tests for getFiles, isSplitted, and getSegments singleton removal.
 *
 * These tests are written BEFORE the implementation changes (TDD strict RED step).
 * After T1 implementation, all three must be GREEN.
 */
class JobStructNewAccessorGuardTest extends AbstractTest
{
    private JobStruct $struct;

    public function setUp(): void
    {
        parent::setUp();

        $this->struct = new JobStruct([
            'id' => 42,
            'password' => 'secret',
            'id_project' => 99,
            'job_first_segment' => '1',
            'job_last_segment' => '100',
            'source' => 'en-US',
            'target' => 'it-IT',
            'tm_keys' => '[]',
            'id_translator' => '',
            'job_type' => null,
            'total_time_to_edit' => '0',
            'avg_post_editing_effort' => '0',
            'last_opened_segment' => null,
            'id_tms' => '1',
            'id_mt_engine' => '1',
            'create_date' => '2024-01-01 00:00:00',
            'last_update' => '2024-01-01 00:00:00',
            'disabled' => '0',
            'owner' => 'test@example.com',
            'status_owner' => 'active',
            'status' => 'active',
            'status_translator' => null,
            'completed' => false,
            'new_words' => '0',
            'draft_words' => '0',
            'translated_words' => '0',
            'approved_words' => '0',
            'rejected_words' => '0',
            'subject' => 'test',
            'payable_rates' => '{}',
            'total_raw_wc' => 1,
        ]);
    }

    /**
     * getFiles must require a FileDao argument (mandatory param after T1).
     * Before T1: getFiles() accepts no arg (has its own `new FileDao()`).
     * After T1: getFiles(FileDao $dao) — calling with no arg is an ArgumentCountError.
     *
     * Guard: pass an injected FileDao mock; assert the injected DAO is used
     * (verifies the mandatory-param contract rather than testing no-arg error,
     * which phpstan catches statically).
     */
    #[Test]
    public function getFiles_uses_injected_file_dao(): void
    {
        $file = new FileStruct(['id' => 1, 'id_job' => 42, 'filename' => 'test.txt']);

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->queryString = '';
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetchAll')->willReturn([['id' => 1, 'id_job' => 42, 'filename' => 'test.txt']]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $mockDb = $this->createMock(IDatabase::class);
        $mockDb->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($pdoStub);

        // Poison singleton — must never be touched
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $dao = new FileDao($mockDb);
        // After T1 this calls dao->getByJobId using $mockDb, not Database::obtain()
        $result = $this->struct->getFiles($dao);

        $this->assertIsArray($result);
    }

    /**
     * isSplitted must require a JobDao argument (mandatory param after T1).
     * Before T1: isSplitted() accepts no arg (calls $this->getChunks() which has `?? new JobDao()`).
     * After T1: isSplitted(JobDao $dao) — threading the dao into getChunks($dao).
     *
     * Guard: inject a mock JobDao returning 2 chunks → isSplitted returns true.
     */
    #[Test]
    public function isSplitted_uses_injected_job_dao(): void
    {
        $chunk1 = new JobStruct(['id' => 42, 'password' => 'a', 'id_project' => 99]);
        $chunk2 = new JobStruct(['id' => 42, 'password' => 'b', 'id_project' => 99]);

        $dao = $this->createStub(JobDao::class);
        $dao->method('getNotDeletedById')->willReturn([$chunk1, $chunk2]);

        // Poison singleton — must never be touched
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        // After T1: isSplitted(JobDao $dao)
        $result = $this->struct->isSplit($dao);

        $this->assertTrue($result);
    }

    /**
     * isSplitted returns false for a single-chunk job.
     */
    #[Test]
    public function isSplitted_returns_false_for_single_chunk(): void
    {
        $chunk1 = new JobStruct(['id' => 42, 'password' => 'a', 'id_project' => 99]);

        $dao = $this->createStub(JobDao::class);
        $dao->method('getNotDeletedById')->willReturn([$chunk1]);

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $result = $this->struct->isSplit($dao);

        $this->assertFalse($result);
    }

    /**
     * getSegments singleton-removal guard.
     * Before T1: getSegments() without arg falls back to `new SegmentDao(Database::obtain())` — hits singleton.
     * After T1: getSegments(SegmentDao $dao) — mandatory; the injected DAO is used, singleton never touched.
     *
     * This test poisons the singleton and passes a mock SegmentDao; after T1 it must be GREEN.
     * Before T1 implementation (with ?SegmentDao = null default), passing $dao already works
     * so this test will be GREEN even before T1 — but after removing the `?? new SegmentDao(Database::obtain())`
     * fallback, calling without arg would fail statically. The poison guard specifically catches
     * any remaining Database::obtain() call inside the method.
     */
    #[Test]
    public function getSegments_uses_injected_dao_not_singleton(): void
    {
        $segment = new SegmentStruct(['id' => 1]);

        $dao = $this->createMock(SegmentDao::class);
        $dao->expects($this->once())
            ->method('getByChunkId')
            ->with(42, 'secret')
            ->willReturn([$segment]);

        // Poison singleton — must never be touched after T1
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $result = $this->struct->getSegments($dao);

        $this->assertSame([$segment], $result);
    }
}
