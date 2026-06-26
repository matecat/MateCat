<?php

namespace Matecat\Core\Model\Comments;

use Matecat\TestHelpers\AbstractTest;
use Model\Comments\BaseCommentStruct;
use Model\Comments\CommentDao;
use Model\Comments\CommentStruct;
use Model\Comments\OpenThreadsStruct;
use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class CommentDaoTest extends AbstractTest
{
    private PDOStatement $stmtStub;
    private \PDO $pdoStub;

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

    // ─── getOpenThreadsForProjects() ───

    #[Test]
    public function getOpenThreadsForProjectsReturnsStructs(): void
    {
        $struct = new OpenThreadsStruct();
        $struct->id_project = 1;
        $struct->password = 'abc';
        $struct->id_job = 10;
        $struct->count = 3;

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getOpenThreadsForProjects([1, 2]);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(OpenThreadsStruct::class, $results[0]);
    }

    #[Test]
    public function getOpenThreadsForProjectsReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getOpenThreadsForProjects([999]);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ─── deleteComment() ───

    #[Test]
    public function deleteCommentReturnsTrue(): void
    {
        $comment = new BaseCommentStruct();
        $comment->id = 1;
        $comment->id_segment = 100;

        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $result = $dao->deleteComment($comment);

        $this->assertTrue($result);
    }

    // ─── getBySegmentId() ───

    #[Test]
    public function getBySegmentIdReturnsComments(): void
    {
        $comment = new BaseCommentStruct();
        $comment->id = 1;
        $comment->id_segment = 50;

        $this->stmtStub->method('fetchAll')->willReturn([$comment]);

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getBySegmentId(50);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(BaseCommentStruct::class, $results[0]);
    }

    #[Test]
    public function getBySegmentIdReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getBySegmentId(999);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ─── saveComment() ───

    #[Test]
    public function saveCommentSetsDefaultMessageType(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('getConnection')->willReturn($this->pdoStub);
        $dbMock->method('insert')->willReturn('1');
        $dbMock->method('last_insert')->willReturn('42');

        $this->setDatabaseInstance($dbMock);

        $obj = new CommentStruct();
        $obj->id_job = 1;
        $obj->id_segment = 100;
        $obj->email = 'test@test.com';
        $obj->full_name = 'Test User';
        $obj->uid = 5;
        $obj->source_page = 1;
        $obj->message = 'Hello';
        $obj->message_type = null;

        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $result = $dao->saveComment($obj);

        $this->assertSame(CommentDao::TYPE_COMMENT, $result->message_type);
        $this->assertSame(42, $result->id);
    }

    #[Test]
    public function saveCommentThrowsOnEmptyMessage(): void
    {
        $obj = new CommentStruct();
        $obj->id_job = 1;
        $obj->id_segment = 100;
        $obj->full_name = 'Test User';
        $obj->message = '';
        $obj->message_type = CommentDao::TYPE_COMMENT;

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Comment message can't be blank.");
        $dao->saveComment($obj);
    }

    #[Test]
    public function saveCommentThrowsOnEmptyFullName(): void
    {
        $obj = new CommentStruct();
        $obj->id_job = 1;
        $obj->id_segment = 100;
        $obj->full_name = '';
        $obj->message = 'Hello';
        $obj->message_type = CommentDao::TYPE_COMMENT;

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Full name can't be blank.");
        $dao->saveComment($obj);
    }

    // ─── getThreadContributorUids() ───

    #[Test]
    public function getThreadContributorUidsReturnsUids(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([['uid' => 1], ['uid' => 2]]);

        $obj = new CommentStruct();
        $obj->id_job = 1;
        $obj->id_segment = 100;
        $obj->uid = 5;

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getThreadContributorUids($obj);

        $this->assertCount(2, $results);
    }

    #[Test]
    public function getThreadContributorUidsWithNullUid(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([['uid' => 1]]);

        $obj = new CommentStruct();
        $obj->id_job = 1;
        $obj->id_segment = 100;
        $obj->uid = null;

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getThreadContributorUids($obj);

        $this->assertCount(1, $results);
    }

    // ─── getThreadsBySegments() ───

    #[Test]
    public function getThreadsBySegmentsReturnsComments(): void
    {
        $comment = new BaseCommentStruct();
        $comment->id = 1;
        $comment->id_segment = 10;

        $this->stmtStub->method('fetchAll')->willReturn([$comment]);

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getThreadsBySegments([10, 20], 1);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function getThreadsBySegmentsReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getThreadsBySegments([10], 1);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ─── getCommentsForChunk() ───

    #[Test]
    public function getCommentsForChunkReturnsComments(): void
    {
        $comment = new BaseCommentStruct();
        $comment->id = 1;

        $this->stmtStub->method('fetchAll')->willReturn([$comment]);

        $chunk = new JobStruct();
        $chunk->id = 5;

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getCommentsForChunk($chunk);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function getCommentsForChunkWithFromId(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $chunk = new JobStruct();
        $chunk->id = 5;

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getCommentsForChunk($chunk, ['from_id' => 10]);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ─── resolveThread() ───

    #[Test]
    public function resolveThreadSetsTypeAndResolveDate(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('getConnection')->willReturn($this->pdoStub);
        $dbMock->method('insert')->willReturn('1');
        $dbMock->method('last_insert')->willReturn('99');

        $this->setDatabaseInstance($dbMock);

        $obj = new CommentStruct();
        $obj->id_job = 1;
        $obj->id_segment = 100;
        $obj->email = 'test@test.com';
        $obj->full_name = 'Test User';
        $obj->uid = 5;
        $obj->source_page = 1;
        $obj->message = 'Resolved';

        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $result = $dao->resolveThread($obj);

        $this->assertSame(CommentDao::TYPE_RESOLVE, $result->message_type);
        $this->assertNotNull($result->resolve_date);
    }

    // ─── placeholdContent() ───

    #[Test]
    public function placeholdContentReplacesTeamMention(): void
    {
        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $result = $dao->placeholdContent('Hello {@team@}');

        $this->assertSame('Hello @team', $result);
    }

    // ─── getUsersIdFromContent() ───

    #[Test]
    public function getUsersIdFromContentExtractsIds(): void
    {
        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $result = $dao->getUsersIdFromContent('Hello {@123@} and {@456@}');

        $this->assertSame(['123', '456'], $result);
    }

    #[Test]
    public function getUsersIdFromContentReturnsEmptyForNoMentions(): void
    {
        $dao = new CommentDao(\Model\DataAccess\Database::obtain());
        $result = $dao->getUsersIdFromContent('Hello world');

        $this->assertEmpty($result);
    }
}
