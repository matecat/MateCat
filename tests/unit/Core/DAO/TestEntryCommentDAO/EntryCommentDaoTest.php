<?php

namespace Matecat\Core\DAO\TestEntryCommentDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\LQA\EntryCommentDao;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class EntryCommentDaoTest extends AbstractTest
{
    #[Test]
    public function move_returns_affected_row_count(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(3);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $dao = new EntryCommentDao($db);
        $this->assertSame(3, $dao->move(10, 20));
    }

    #[Test]
    public function move_returns_zero_when_no_rows_affected(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $dao = new EntryCommentDao($db);
        $this->assertSame(0, $dao->move(999, 888));
    }

    #[Test]
    public function fetchCommentsGroupedByIssueIds_returns_grouped_array(): void
    {
        $flatRows = [
            ['id_qa_entry' => 1, 'id' => 10, 'comment' => 'First comment'],
            ['id_qa_entry' => 1, 'id' => 11, 'comment' => 'Second comment'],
            ['id_qa_entry' => 2, 'id' => 20, 'comment' => 'Other comment'],
        ];

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($flatRows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $dao = new EntryCommentDao($db);
        $result = $dao->fetchCommentsGroupedByIssueIds([1, 2]);

        $this->assertCount(2, $result);
        $this->assertCount(2, $result[1]);
        $this->assertCount(1, $result[2]);
        $this->assertSame('First comment', $result[1][0]['comment']);
        $this->assertSame('Other comment', $result[2][0]['comment']);
    }

    #[Test]
    public function fetchCommentsGroupedByIssueIds_returns_empty_array_when_no_results(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $dao = new EntryCommentDao($db);
        $result = $dao->fetchCommentsGroupedByIssueIds([99, 100]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function findByIssueId_returns_empty_list_when_no_rows(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $dao = new EntryCommentDao($db);
        $result = $dao->findByIssueId(42);

        $this->assertSame([], $result);
    }
}
