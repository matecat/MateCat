<?php

namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Users\MetadataDao;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-SQL coverage for the batched read `Users\MetadataDao::getAllByUidList()`.
 *
 * It had the same unevictable set-valued key as `UserDao::getByUids()`: one entry addressed by the
 * whole uid list, which no per-uid door could name and which a change to the list replaced
 * outright. It was worse in one respect — there was no per-uid door at all, because `getAllByUid()`
 * was raw uncached PDO, so a metadata write left `MembershipDao::getMemberListByTeamId()` serving
 * the old value for the full 24h TTL it sets.
 *
 * These tests pin the fix: one entry per uid, shared with `getAllByUid()`, evicted by the writes
 * that change it.
 *
 * Each test mutates the row behind the DAO's back with a direct UPDATE. That value is observable
 * only through a genuine cache miss, which is what makes "was this served from cache?" assertable
 * without a query counter.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class MetadataDaoBatchCacheRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['users', 'user_metadata'];

    private const string KEY = 'rsq_batch_key';

    private MetadataDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new MetadataDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);
    }

    protected function tearDown(): void
    {
        $this->dao->setCacheTTL(0);
        $this->finishRealSql();
        parent::tearDown();
    }

    /**
     * A user with one metadata row under self::KEY.
     */
    private function makeUserWithMetadata(string $value): int
    {
        $uid = (int)$this->fixtures->makeUser()['uid'];
        $this->fixtures->makeUserMetadata($uid, self::KEY, $value);

        return $uid;
    }

    private function editBehindTheDao(int $uid, string $value): void
    {
        $stmt = $this->realSqlDb()->getConnection()->prepare(
            'UPDATE user_metadata SET `value` = :value WHERE uid = :uid AND `key` = :key'
        );
        $stmt->execute(['value' => $value, 'uid' => $uid, 'key' => self::KEY]);
    }

    private function valueFromBatch(array $uids, int $of): ?string
    {
        $set = $this->dao->getAllByUidList($uids);

        return isset($set[$of][0]) ? (string)$set[$of][0]->value : null;
    }

    /**
     * The defect: writing a user's metadata has to be visible in the member lists that cached it.
     */
    public function testSetEvictsWhatTheBatchedReadCached(): void
    {
        $a = $this->makeUserWithMetadata('before');
        $b = $this->makeUserWithMetadata('before');

        $this->dao->setCacheTTL(60);
        $this->assertSame('before', $this->valueFromBatch([$a, $b], $a));

        $this->dao->set($a, self::KEY, 'after');

        $this->assertSame(
            'after',
            $this->valueFromBatch([$a, $b], $a),
            'set() must evict the entry the batched read serves.'
        );
    }

    /**
     * Deleting one is the same obligation as writing one.
     */
    public function testDeleteEvictsWhatTheBatchedReadCached(): void
    {
        $a = $this->makeUserWithMetadata('before');

        $this->dao->setCacheTTL(60);
        $this->assertSame('before', $this->valueFromBatch([$a], $a));

        $this->dao->delete($a, self::KEY);

        $this->assertNull(
            $this->valueFromBatch([$a], $a),
            'delete() must evict the entry the batched read serves.'
        );
    }

    /**
     * Evicting one uid must leave the others cached: the entries are per-uid, not per-set.
     */
    public function testEvictingOneUidLeavesTheOthersCached(): void
    {
        $a = $this->makeUserWithMetadata('before');
        $b = $this->makeUserWithMetadata('before');

        $this->dao->setCacheTTL(60);
        $this->dao->getAllByUidList([$a, $b]);

        $this->editBehindTheDao($b, 'untouched');
        $this->dao->set($a, self::KEY, 'after');

        $set = $this->dao->getAllByUidList([$a, $b]);

        $this->assertSame('after', (string)$set[$a][0]->value);
        $this->assertNotSame(
            'untouched',
            (string)$set[$b][0]->value,
            'B was not written to, so it must still be served from its own cache entry.'
        );
    }

    /**
     * The batched read has to reuse the entry the single read warmed, and the other way round.
     */
    public function testBatchedAndSingleReadShareTheSameEntry(): void
    {
        $a = $this->makeUserWithMetadata('shared');

        $this->dao->setCacheTTL(60);
        $this->dao->getAllByUid($a);

        $this->editBehindTheDao($a, 'changed');

        $this->assertNotSame(
            'changed',
            $this->valueFromBatch([$a], $a),
            'getAllByUidList() must hit the entry getAllByUid() wrote.'
        );
    }

    public function testBatchedReadBackfillsTheEntryReadBySingleRead(): void
    {
        $a = $this->makeUserWithMetadata('backfill');

        $this->dao->setCacheTTL(60);
        $this->dao->getAllByUidList([$a]);

        $this->editBehindTheDao($a, 'changed');

        $rows = $this->dao->getAllByUid($a);

        $this->assertCount(1, $rows);
        $this->assertNotSame(
            'changed',
            (string)$rows[0]->value,
            'getAllByUidList() must write the per-uid entry, not only read it.'
        );
    }

    /**
     * Membership churn immunity: a longer list must not invalidate the uids already in it.
     */
    public function testAddingAUidDoesNotInvalidateTheExistingOnes(): void
    {
        $a = $this->makeUserWithMetadata('before');
        $b = $this->makeUserWithMetadata('before');

        $this->dao->setCacheTTL(60);
        $this->dao->getAllByUidList([$a, $b]);

        $this->editBehindTheDao($a, 'churned');
        $this->editBehindTheDao($b, 'churned');

        $c = $this->makeUserWithMetadata('fresh');
        $grown = $this->dao->getAllByUidList([$a, $b, $c]);

        $this->assertSame('fresh', (string)$grown[$c][0]->value, 'the newly added uid still has to be loaded');
        $this->assertNotSame('churned', (string)$grown[$a][0]->value);
        $this->assertNotSame('churned', (string)$grown[$b][0]->value);
    }

    public function testTtlZeroKeepsTheBatchedReadLive(): void
    {
        $a = $this->makeUserWithMetadata('before');

        $this->dao->setCacheTTL(0);
        $this->dao->getAllByUidList([$a]);

        $this->editBehindTheDao($a, 'live');

        $this->assertSame('live', $this->valueFromBatch([$a], $a));
    }

    /**
     * Contract preserved from the set-shaped implementation: keyed by uid, one list per uid, an
     * empty input short-circuits, and uids with no metadata are absent.
     */
    public function testResultShapeIsUnchanged(): void
    {
        $a = $this->makeUserWithMetadata('one');
        $this->fixtures->makeUserMetadata($a, self::KEY . '_2', 'two');
        $withoutMetadata = (int)$this->fixtures->makeUser()['uid'];

        $this->dao->setCacheTTL(60);

        $this->assertSame([], $this->dao->getAllByUidList([]));

        $set = $this->dao->getAllByUidList([$a, $withoutMetadata]);

        $this->assertSame([$a], array_keys($set), 'uids with no metadata are absent, not empty lists');
        $this->assertCount(2, $set[$a]);
    }
}
