<?php

namespace Matecat\Core\Model\Comments;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Comments\CommentDao;
use Model\Comments\CommentStruct;
use Model\Comments\OpenThreadsStruct;
use Model\Jobs\JobStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-SQL coverage for CommentDao (plan dao-realsql-90.md, Wave 5 / T10).
 *
 * CommentDao is SELF-COMMITTING with a raw beginTransaction()/commit() path in resolveThread()
 * (census: commitMode=self-commit, rawTx=true). Per C-1/S-3 the harness uses NO wrapping
 * transaction and seed-safe id-list DELETE cleanup only; rows the DAO INSERTs itself
 * (saveComment, resolveThread) are registered via trackExisting() so the whole-table COUNT(*)
 * residue gate over [comments, jobs, projects] returns to baseline.
 *
 * Every public SQL method is called DIRECTLY and asserted on real returned data (DoD b). The
 * two non-SQL helpers (getUsersIdFromContent regex; placeholdContent which round-trips through
 * UserDao) are also exercised directly. NO assertion on absolute generated id values (M-3):
 * identity is checked by round-tripping the row, not by a literal id.
 *
 * Uses the dedicated RealSqlDaoTestTrait (S-4), NOT bare AbstractTest behaviour, so the 666
 * AbstractTest subclasses are unperturbed.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class CommentDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    /** Census tableDeps for CommentDao: comments + the jobs/projects it JOINs. */
    private const array TABLE_DEPS = ['comments', 'jobs', 'projects'];

    private CommentDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new CommentDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------------------------

    /**
     * Build a project + a job whose [job_first_segment, job_last_segment] window contains a
     * real segment id, so getOpenThreadsForProjects() JOIN predicates resolve. Returns the
     * fixture ids needed by the comment-building tests.
     *
     * @return array{id_project:int,id_job:int,password:string,id_segment:int}
     */
    private function makeJobWithSegment(): array
    {
        $project = $this->fixtures->makeProject();
        $file = $this->fixtures->makeFile($project['id']);
        $segment = $this->fixtures->makeSegment($file['id']);
        $job = $this->fixtures->makeJob($project['id'], [
            'job_first_segment' => $segment['id'],
            'job_last_segment'  => $segment['id'],
        ]);

        return [
            'id_project' => $project['id'],
            'id_job'     => $job['id'],
            'password'   => $job['password'],
            'id_segment' => $segment['id'],
        ];
    }

    private function newCommentStruct(int $idJob, int $idSegment, array $overrides = []): CommentStruct
    {
        $s = new CommentStruct();
        $s->id_job = $idJob;
        $s->id_segment = $idSegment;
        $s->full_name = $overrides['full_name'] ?? 'Rsq Author';
        $s->email = $overrides['email'] ?? ('rsq_' . bin2hex(random_bytes(5)) . '@example.test');
        $s->uid = $overrides['uid'] ?? null;
        $s->source_page = $overrides['source_page'] ?? 1;
        $s->is_anonymous = $overrides['is_anonymous'] ?? 0;
        $s->message_type = $overrides['message_type'] ?? CommentDao::TYPE_COMMENT;
        $s->message = $overrides['message'] ?? ('rsq message ' . bin2hex(random_bytes(4)));

        return $s;
    }

    // -----------------------------------------------------------------------------------------
    // saveComment (INSERT through the DAO — self-commit)
    // -----------------------------------------------------------------------------------------

    public function testSaveCommentPersistsAndRoundTrips(): void
    {
        $ctx = $this->makeJobWithSegment();
        $struct = $this->newCommentStruct($ctx['id_job'], $ctx['id_segment'], ['message' => 'hello world']);

        $saved = $this->dao->saveComment($struct);
        // DAO INSERTs the row itself: register for cleanup so residue returns to baseline.
        $this->fixtures->trackExisting('comments', ['id' => (int)$saved->id]);

        $this->assertInstanceOf(CommentStruct::class, $saved);
        $this->assertGreaterThan(0, $saved->id);
        $this->assertSame(CommentDao::TYPE_COMMENT, $saved->message_type);
        $this->assertNotEmpty($saved->create_date);

        // Round-trip via getBySegmentId on a clean cache.
        $this->flushDaoCache();
        $rows = $this->dao->getBySegmentId($ctx['id_segment'], 0);
        $this->assertCount(1, $rows);
        $this->assertSame('hello world', $rows[0]->message);
        $this->assertSame($ctx['id_job'], (int)$rows[0]->id_job);
    }

    public function testSaveCommentDefaultsMessageTypeToComment(): void
    {
        $ctx = $this->makeJobWithSegment();
        $struct = $this->newCommentStruct($ctx['id_job'], $ctx['id_segment']);
        $struct->message_type = null;

        $saved = $this->dao->saveComment($struct);
        $this->fixtures->trackExisting('comments', ['id' => (int)$saved->id]);

        $this->assertSame(CommentDao::TYPE_COMMENT, $saved->message_type);
    }

    public function testSaveCommentBlankMessageThrows(): void
    {
        $ctx = $this->makeJobWithSegment();
        $struct = $this->newCommentStruct($ctx['id_job'], $ctx['id_segment'], ['message' => '']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Comment message can't be blank.");
        $this->dao->saveComment($struct);
    }

    public function testSaveCommentBlankFullNameThrows(): void
    {
        $ctx = $this->makeJobWithSegment();
        $struct = $this->newCommentStruct($ctx['id_job'], $ctx['id_segment']);
        $struct->full_name = '';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Full name can't be blank.");
        $this->dao->saveComment($struct);
    }

    // -----------------------------------------------------------------------------------------
    // getBySegmentId + destroySegmentIdSegmentCache
    // -----------------------------------------------------------------------------------------

    public function testGetBySegmentIdReturnsOrderedComments(): void
    {
        $ctx = $this->makeJobWithSegment();
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'first']);
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'second']);
        // A MENTION (type 3) must be excluded by the WHERE clause.
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], [
            'message'      => 'mention',
            'message_type' => CommentDao::TYPE_MENTION,
        ]);

        $rows = $this->dao->getBySegmentId($ctx['id_segment'], 0);

        $this->assertCount(2, $rows);
        $this->assertSame('first', $rows[0]->message);
        $this->assertSame('second', $rows[1]->message);
    }

    public function testGetBySegmentIdEmptyWhenNoComments(): void
    {
        $ctx = $this->makeJobWithSegment();
        $this->assertSame([], $this->dao->getBySegmentId($ctx['id_segment'], 0));
    }

    public function testDestroySegmentIdSegmentCacheEvictsWarmedEntry(): void
    {
        $ctx = $this->makeJobWithSegment();
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'cached']);

        // Warm the cache with a non-zero TTL so a key actually exists to evict.
        $warm = $this->dao->getBySegmentId($ctx['id_segment'], 60);
        $this->assertCount(1, $warm);

        $this->assertTrue($this->dao->destroySegmentIdSegmentCache($ctx['id_segment']));
        // Second eviction finds nothing -> false: proves the first call really removed the key.
        $this->assertFalse($this->dao->destroySegmentIdSegmentCache($ctx['id_segment']));
    }

    // -----------------------------------------------------------------------------------------
    // deleteComment (DELETE — self-commit)
    // -----------------------------------------------------------------------------------------

    public function testDeleteCommentRemovesRow(): void
    {
        $ctx = $this->makeJobWithSegment();
        $made = $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'to delete']);

        $toDelete = new CommentStruct();
        $toDelete->id = $made['id'];
        $toDelete->id_segment = $ctx['id_segment'];

        $this->assertTrue($this->dao->deleteComment($toDelete));

        $this->flushDaoCache();
        $this->assertSame([], $this->dao->getBySegmentId($ctx['id_segment'], 0));
    }

    // -----------------------------------------------------------------------------------------
    // resolveThread (raw beginTransaction/commit + updateFields — C-1 critical path)
    // -----------------------------------------------------------------------------------------

    public function testResolveThreadInsertsResolveRowAndClosesOpenComments(): void
    {
        $ctx = $this->makeJobWithSegment();
        // An open comment on the same job/segment that resolveThread should stamp resolved.
        $open = $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], [
            'message'      => 'open issue',
            'message_type' => CommentDao::TYPE_COMMENT,
        ]);

        $resolveStruct = $this->newCommentStruct($ctx['id_job'], $ctx['id_segment'], [
            'message'      => 'resolving',
            'message_type' => CommentDao::TYPE_COMMENT,
        ]);

        $result = $this->dao->resolveThread($resolveStruct);
        // The DAO INSERTed a resolve row itself: track it for cleanup.
        $this->fixtures->trackExisting('comments', ['id' => (int)$result->id]);

        $this->assertInstanceOf(CommentStruct::class, $result);
        $this->assertSame(CommentDao::TYPE_RESOLVE, $result->message_type);
        $this->assertNotNull($result->resolve_date);
        $this->assertNotEmpty($result->thread_id);

        // updateFields() must have stamped the previously-open comment with a resolve_date.
        $row = $this->fetchCommentRow($open['id']);
        $this->assertNotNull($row['resolve_date']);
    }

    /**
     * Direct DB read (independent of the DAO cache) used to assert resolveThread side effects.
     *
     * @return array<string,mixed>
     */
    private function fetchCommentRow(int $id): array
    {
        $stmt = $this->realSqlDb()->getConnection()->prepare('SELECT * FROM comments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    // -----------------------------------------------------------------------------------------
    // getThreadContributorUids
    // -----------------------------------------------------------------------------------------

    public function testGetThreadContributorUidsReturnsDistinctUids(): void
    {
        $ctx = $this->makeJobWithSegment();
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['uid' => 111]);
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['uid' => 111]);
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['uid' => 222]);
        // A NULL-uid row that must be excluded.
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['uid' => null]);

        $query = new CommentStruct();
        $query->id_job = $ctx['id_job'];
        $query->id_segment = $ctx['id_segment'];

        $rows = $this->dao->getThreadContributorUids($query);

        $uids = array_map(static fn(array $r): int => (int)$r['uid'], $rows);
        sort($uids);
        $this->assertSame([111, 222], $uids);
    }

    public function testGetThreadContributorUidsExcludesGivenUid(): void
    {
        $ctx = $this->makeJobWithSegment();
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['uid' => 111]);
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['uid' => 222]);

        $query = new CommentStruct();
        $query->id_job = $ctx['id_job'];
        $query->id_segment = $ctx['id_segment'];
        $query->uid = 111;

        $rows = $this->dao->getThreadContributorUids($query);

        $uids = array_map(static fn(array $r): int => (int)$r['uid'], $rows);
        $this->assertSame([222], $uids);
    }

    // -----------------------------------------------------------------------------------------
    // getThreadsBySegments (UNION SELECT JOIN)
    // -----------------------------------------------------------------------------------------

    public function testGetThreadsBySegmentsReturnsCommentsAcrossSegments(): void
    {
        $project = $this->fixtures->makeProject();
        $file = $this->fixtures->makeFile($project['id']);
        $segA = $this->fixtures->makeSegment($file['id']);
        $segB = $this->fixtures->makeSegment($file['id']);
        $job = $this->fixtures->makeJob($project['id'], [
            'job_first_segment' => $segA['id'],
            'job_last_segment'  => $segB['id'],
        ]);

        $this->fixtures->makeComment($job['id'], $segA['id'], ['message' => 'seg-a', 'message_type' => CommentDao::TYPE_COMMENT]);
        $this->fixtures->makeComment($job['id'], $segB['id'], ['message' => 'seg-b', 'message_type' => CommentDao::TYPE_RESOLVE]);
        // type 3 must be excluded by message_type IN (1,2).
        $this->fixtures->makeComment($job['id'], $segA['id'], ['message' => 'mention', 'message_type' => CommentDao::TYPE_MENTION]);

        $rows = $this->dao->getThreadsBySegments([$segA['id'], $segB['id']], $job['id']);

        $this->assertCount(2, $rows);
        $messages = array_map(static fn($r): string => (string)$r->message, $rows);
        sort($messages);
        $this->assertSame(['seg-a', 'seg-b'], $messages);
    }

    public function testGetThreadsBySegmentsSingleSegment(): void
    {
        $ctx = $this->makeJobWithSegment();
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'only', 'message_type' => CommentDao::TYPE_COMMENT]);

        $rows = $this->dao->getThreadsBySegments([$ctx['id_segment']], $ctx['id_job']);

        $this->assertCount(1, $rows);
        $this->assertSame('only', $rows[0]->message);
    }

    // -----------------------------------------------------------------------------------------
    // getCommentsForChunk
    // -----------------------------------------------------------------------------------------

    public function testGetCommentsForChunkReturnsChunkComments(): void
    {
        $ctx = $this->makeJobWithSegment();
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'c1', 'message_type' => CommentDao::TYPE_COMMENT, 'is_anonymous' => 0, 'full_name' => 'Named Person']);
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'c2', 'message_type' => CommentDao::TYPE_RESOLVE]);
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'mention', 'message_type' => CommentDao::TYPE_MENTION]);

        $chunk = new JobStruct();
        $chunk->id = $ctx['id_job'];
        $chunk->password = $ctx['password'];

        $rows = $this->dao->getCommentsForChunk($chunk);

        $this->assertCount(2, $rows);
        $messages = array_map(static fn($r): string => (string)$r->message, $rows);
        $this->assertContains('c1', $messages);
        $this->assertContains('c2', $messages);
    }

    public function testGetCommentsForChunkAnonymisesFullName(): void
    {
        $ctx = $this->makeJobWithSegment();
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], [
            'message'      => 'anon',
            'message_type' => CommentDao::TYPE_COMMENT,
            'is_anonymous' => 1,
            'full_name'    => 'Should Be Hidden',
        ]);

        $chunk = new JobStruct();
        $chunk->id = $ctx['id_job'];

        $rows = $this->dao->getCommentsForChunk($chunk);

        $this->assertCount(1, $rows);
        $this->assertSame('Anonymous', $rows[0]->full_name);
    }

    /**
     * CHARACTERIZATION of a real prod bug (logged in Findings, NOT fixed per the plan's
     * no-prod-changes rule). getCommentsForChunk() builds the "AND id >= :from_id" filter and
     * binds it on the FIRST $stmt->execute($params), but then calls $stmt->execute() a SECOND
     * time with NO arguments (CommentDao.php:300). The second execute drops the bound
     * from_id parameter, so the from_id filter has NO effect: BOTH comments come back even
     * though from_id == high->id and low->id < high->id. This test asserts the ACTUAL
     * (buggy) behaviour so it stays green and documents the defect; it must be updated to
     * assertCount(1) once the redundant second execute is removed in prod.
     */
    public function testGetCommentsForChunkFromIdOptionIsIgnoredDueToDoubleExecuteBug(): void
    {
        $ctx = $this->makeJobWithSegment();
        $low = $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'low', 'message_type' => CommentDao::TYPE_COMMENT]);
        $high = $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'high', 'message_type' => CommentDao::TYPE_COMMENT]);
        $this->assertLessThan($high['id'], $low['id'], 'precondition: low row has a lower id than high row');

        $chunk = new JobStruct();
        $chunk->id = $ctx['id_job'];

        $rows = $this->dao->getCommentsForChunk($chunk, ['from_id' => $high['id']]);

        // BUG: from_id is ignored, so both rows return instead of only 'high'.
        $this->assertCount(2, $rows);
        $messages = array_map(static fn($r): string => (string)$r->message, $rows);
        sort($messages);
        $this->assertSame(['high', 'low'], $messages);
    }

    public function testGetCommentsForChunkEmptyWhenNoComments(): void
    {
        $ctx = $this->makeJobWithSegment();
        $chunk = new JobStruct();
        $chunk->id = $ctx['id_job'];

        $this->assertSame([], $this->dao->getCommentsForChunk($chunk));
    }

    // -----------------------------------------------------------------------------------------
    // getOpenThreadsForProjects (3-table JOIN projects/jobs/comments)
    // -----------------------------------------------------------------------------------------

    public function testGetOpenThreadsForProjectsCountsOpenThreads(): void
    {
        $ctx = $this->makeJobWithSegment();
        // Two open comments on the same segment count as ONE distinct id_segment thread.
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'open1', 'message_type' => CommentDao::TYPE_COMMENT]);
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], ['message' => 'open2', 'message_type' => CommentDao::TYPE_COMMENT]);

        $rows = $this->dao->getOpenThreadsForProjects([$ctx['id_project']]);

        $this->assertCount(1, $rows);
        $this->assertInstanceOf(OpenThreadsStruct::class, $rows[0]);
        $this->assertSame($ctx['id_project'], (int)$rows[0]->id_project);
        $this->assertSame($ctx['id_job'], (int)$rows[0]->id_job);
        $this->assertSame($ctx['password'], $rows[0]->password);
        $this->assertSame(1, (int)$rows[0]->count);
    }

    public function testGetOpenThreadsForProjectsExcludesResolved(): void
    {
        $ctx = $this->makeJobWithSegment();
        // A resolved comment (resolve_date set) must NOT count as an open thread.
        $this->fixtures->makeComment($ctx['id_job'], $ctx['id_segment'], [
            'message'      => 'resolved',
            'message_type' => CommentDao::TYPE_COMMENT,
            'resolve_date' => date('Y-m-d H:i:s'),
        ]);

        $rows = $this->dao->getOpenThreadsForProjects([$ctx['id_project']]);

        $this->assertSame([], $rows);
    }

    public function testGetOpenThreadsForProjectsEmptyForUnknownProject(): void
    {
        // A project id far above the seeded band that no fixture uses.
        $this->assertSame([], $this->dao->getOpenThreadsForProjects([2_000_000_999]));
    }

    // -----------------------------------------------------------------------------------------
    // getUsersIdFromContent (pure regex helper — no SQL)
    // -----------------------------------------------------------------------------------------

    public function testGetUsersIdFromContentExtractsIds(): void
    {
        $content = 'Hello {@123@} and {@456@}, also {@team@} ignored.';
        $this->assertSame(['123', '456'], $this->dao->getUsersIdFromContent($content));
    }

    public function testGetUsersIdFromContentEmptyWhenNoMentions(): void
    {
        $this->assertSame([], $this->dao->getUsersIdFromContent('no mentions here'));
    }

    // -----------------------------------------------------------------------------------------
    // placeholdContent (round-trips through UserDao -> real SQL)
    // -----------------------------------------------------------------------------------------

    public function testPlaceholdContentReplacesMentionsWithUserNames(): void
    {
        $user = $this->fixtures->makeUser(['first_name' => 'Alice']);

        $content = 'ping {@' . $user['uid'] . '@} and {@team@}';
        $out = $this->dao->placeholdContent($content);

        $this->assertSame('ping @Alice and @team', $out);
    }

    public function testPlaceholdContentLeavesUnknownMentionsAndTeam(): void
    {
        // No matching user row -> the {@id@} token is left as-is; {@team@} always becomes @team.
        $out = $this->dao->placeholdContent('hi {@2000000998@} {@team@}');

        $this->assertStringContainsString('@team', $out);
        $this->assertStringContainsString('{@2000000998@}', $out);
    }
}
