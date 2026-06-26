<?php

namespace Matecat\Core\DAO\TestCommentDAO;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\Comments\BaseCommentStruct;
use Model\Comments\CommentDao;
use Model\Comments\CommentStruct;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class TestCommentDao extends CommentDao
{
    public function destroySegmentIdSegmentCache(int $idSegment): bool
    {
        return true;
    }
}

class CommentDaoTest extends AbstractTest
{
    #[Test]
    public function saveCommentThrowsWhenMessageIsEmptyAndTypeIsComment(): void
    {
        $dao = new CommentDao($this->createStub(IDatabase::class));

        $struct               = new CommentStruct();
        $struct->id_job       = 1;
        $struct->id_segment   = 100;
        $struct->source_page  = 1;
        $struct->full_name    = 'Test User';
        $struct->message      = '';
        $struct->message_type = CommentDao::TYPE_COMMENT;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Comment message can't be blank.");

        $dao->saveComment($struct);
    }

    #[Test]
    public function saveCommentThrowsWhenMessageIsNullAndTypeIsComment(): void
    {
        $dao = new CommentDao($this->createStub(IDatabase::class));

        $struct               = new CommentStruct();
        $struct->id_job       = 1;
        $struct->id_segment   = 100;
        $struct->source_page  = 1;
        $struct->full_name    = 'Test User';
        $struct->message      = null;
        $struct->message_type = CommentDao::TYPE_COMMENT;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Comment message can't be blank.");

        $dao->saveComment($struct);
    }

    #[Test]
    public function saveCommentDoesNotThrowBlankMessageWhenTypeIsResolve(): void
    {
        $dao = new CommentDao($this->createStub(IDatabase::class));

        $struct               = new CommentStruct();
        $struct->id_job       = 1;
        $struct->id_segment   = 100;
        $struct->source_page  = 1;
        $struct->full_name    = '';
        $struct->message      = '';
        $struct->message_type = CommentDao::TYPE_RESOLVE;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Full name can't be blank.");

        $dao->saveComment($struct);
    }

    #[Test]
    public function saveCommentThrowsWhenFullNameIsEmpty(): void
    {
        $dao = new CommentDao($this->createStub(IDatabase::class));

        $struct               = new CommentStruct();
        $struct->id_job       = 1;
        $struct->id_segment   = 100;
        $struct->source_page  = 1;
        $struct->full_name    = '';
        $struct->message      = 'Hello world';
        $struct->message_type = CommentDao::TYPE_COMMENT;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Full name can't be blank.");

        $dao->saveComment($struct);
    }

    #[Test]
    public function deleteCommentReturnsTrueOnSuccess(): void
    {
        $dbMock   = $this->createMock(IDatabase::class);
        $pdoMock  = $this->createMock(PDO::class);
        $stmtMock = $this->createMock(PDOStatement::class);

        $dbMock
            ->expects($this->once())
            ->method('getConnection')
            ->willReturn($pdoMock);

        $pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($stmtMock);

        $stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with(['id' => 42])
            ->willReturn(true);

        $dao = new TestCommentDao($dbMock);

        $comment             = new BaseCommentStruct();
        $comment->id         = 42;
        $comment->id_segment = 100;

        $result = $dao->deleteComment($comment);

        $this->assertTrue($result);
    }

    #[Test]
    public function deleteCommentReturnsFalseWhenExecuteFails(): void
    {
        $dbMock   = $this->createStub(IDatabase::class);
        $pdoMock  = $this->createStub(PDO::class);
        $stmtMock = $this->createStub(PDOStatement::class);

        $dbMock->method('getConnection')->willReturn($pdoMock);
        $pdoMock->method('prepare')->willReturn($stmtMock);
        $stmtMock->method('execute')->willReturn(false);

        $dao = new TestCommentDao($dbMock);

        $comment             = new BaseCommentStruct();
        $comment->id         = 99;
        $comment->id_segment = 200;

        $result = $dao->deleteComment($comment);

        $this->assertFalse($result);
    }

    #[Test]
    public function getThreadContributorUidsReturnsEmptyArrayWhenNoResults(): void
    {
        $dbMock   = $this->createMock(IDatabase::class);
        $pdoMock  = $this->createMock(PDO::class);
        $stmtMock = $this->createMock(PDOStatement::class);

        $dbMock->expects($this->once())->method('getConnection')->willReturn($pdoMock);
        $pdoMock->expects($this->once())->method('prepare')->willReturn($stmtMock);
        $stmtMock->expects($this->once())->method('setFetchMode');
        $stmtMock->expects($this->once())->method('execute');
        $stmtMock->expects($this->once())->method('fetchAll')->willReturn([]);

        $dao = new CommentDao($dbMock);

        $struct             = new CommentStruct();
        $struct->id_job     = 1;
        $struct->id_segment = 100;
        $struct->uid        = null;

        $result = $dao->getThreadContributorUids($struct);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getThreadContributorUidsExcludesCurrentUserWhenUidIsSet(): void
    {
        $dbMock   = $this->createMock(IDatabase::class);
        $pdoMock  = $this->createMock(PDO::class);
        $stmtMock = $this->createMock(PDOStatement::class);

        $dbMock->expects($this->once())->method('getConnection')->willReturn($pdoMock);

        $pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('AND uid <> :uid'))
            ->willReturn($stmtMock);

        $stmtMock->expects($this->once())->method('setFetchMode');
        $stmtMock->expects($this->once())->method('execute');
        $stmtMock->expects($this->once())->method('fetchAll')
            ->willReturn([['uid' => 7]]);

        $dao = new CommentDao($dbMock);

        $struct             = new CommentStruct();
        $struct->id_job     = 1;
        $struct->id_segment = 100;
        $struct->uid        = 42;

        $result = $dao->getThreadContributorUids($struct);

        $this->assertSame([['uid' => 7]], $result);
    }

    // ── Instance method tests (specular) ──

    private function makeChunk(int $id = 10, string $password = 'pass1'): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = $id;
        $chunk->password = $password;

        return $chunk;
    }

    #[Test]
    public function instanceGetCommentsForChunkReturnsArray(): void
    {
        [,, $stmtStub] = $this->createDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = true;

        $comment = new BaseCommentStruct();
        $comment->id = 1;
        $stmtStub->method('fetchAll')->willReturn([$comment]);

        $dao = new CommentDao(obtainTestDatabase());
        $results = $dao->getCommentsForChunk($this->makeChunk());

        $this->assertIsArray($results);
        $this->assertCount(1, $results);

        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
    }

    #[Test]
    public function instanceGetCommentsForChunkReturnsEmptyArray(): void
    {
        [,, $stmtStub] = $this->createDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = true;

        $stmtStub->method('fetchAll')->willReturn([]);

        $dao = new CommentDao(obtainTestDatabase());
        $results = $dao->getCommentsForChunk($this->makeChunk());

        $this->assertIsArray($results);
        $this->assertEmpty($results);

        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
    }

    #[Test]
    public function instanceGetCommentsForChunkWithFromIdOption(): void
    {
        [,, $stmtStub] = $this->createDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = true;

        $stmtStub->method('fetchAll')->willReturn([]);

        $dao = new CommentDao(obtainTestDatabase());
        $results = $dao->getCommentsForChunk($this->makeChunk(), ['from_id' => 5]);

        $this->assertIsArray($results);

        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
    }

    #[Test]
    public function instanceGetUsersIdFromContentReturnsEmptyArray(): void
    {
        $dao = new CommentDao(obtainTestDatabase());
        $result = $dao->getUsersIdFromContent('Hello world, no mentions here.');

        $this->assertSame([], $result);
    }

    #[Test]
    public function instanceGetUsersIdFromContentReturnsSingleId(): void
    {
        $dao = new CommentDao(obtainTestDatabase());
        $result = $dao->getUsersIdFromContent('Hey {@999@}');

        $this->assertSame(['999'], $result);
    }

    #[Test]
    public function instanceGetUsersIdFromContentReturnsMultipleIds(): void
    {
        $dao = new CommentDao(obtainTestDatabase());
        $result = $dao->getUsersIdFromContent('Hello {@123@} and {@456@}');

        $this->assertSame(['123', '456'], $result);
    }

    #[Test]
    public function instancePlaceholdContentReplacesTeamMention(): void
    {
        [,, $stmtStub] = $this->createDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = true;

        $stmtStub->method('fetchAll')->willReturn([]);

        $dao = new CommentDao(obtainTestDatabase());
        $result = $dao->placeholdContent('Hello {@team@}');

        $this->assertSame('Hello @team', $result);

        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
    }
}
