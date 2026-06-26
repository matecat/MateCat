<?php

namespace Matecat\Core\Model\LQA;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\LQA\EntryCommentDao;
use Model\LQA\EntryCommentStruct;
use PHPUnit\Framework\Attributes\Test;

/**
 * RED→GREEN guard test for EntryCommentStruct::getEntriesById singleton removal.
 *
 * Written BEFORE the implementation change (TDD strict RED step).
 * After T1 implementation the test must be GREEN.
 */
class EntryCommentStructGuardTest extends AbstractTest
{
    /**
     * getEntriesById must use the injected EntryCommentDao, never the singleton.
     *
     * Before T1: getEntriesById(int $id) builds `new EntryCommentDao(\Model\DataAccess\Database::obtain())` internally → hits singleton.
     * After T1: getEntriesById(EntryCommentDao $dao, int $id, ?int $ttl) — injected dao is used.
     */
    #[Test]
    public function getEntriesById_uses_injected_dao_not_singleton(): void
    {
        $struct = new EntryCommentStruct();

        $comment1 = new EntryCommentStruct();
        $comment1->id = 1;
        $comment1->id_qa_entry = 42;

        // Mock EntryCommentDao — findByIssueId must be called on the injected instance
        $dao = $this->createMock(EntryCommentDao::class);
        $dao->expects($this->once())
            ->method('findByIssueId')
            ->with(42)
            ->willReturn([$comment1]);

        // Poison singleton — must never be touched after T1
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        // After T1: getEntriesById(EntryCommentDao $dao, int $id, ?int $ttl = 86400)
        $result = $struct->getEntriesById($dao, 42);

        $this->assertSame([$comment1], $result);
    }
}
