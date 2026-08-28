<?php

namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-SQL coverage for the batched read `UserDao::getByUids()`.
 *
 * The set-valued cache key this method used to build — one entry addressed by the whole uid
 * list — was unreachable from any eviction door. `destroyCache()` addresses a single uid,
 * so it could never name the set entry, and a renamed user stayed visible inside other members'
 * cached team lists for the full 24h TTL that `MembershipDao::getMemberListByTeamId()` sets.
 * The same property made the entry die on every membership change: adding or removing one member
 * produces a different key, guaranteeing a miss and orphaning the previous payload for its TTL.
 *
 * These tests pin the fix: `getByUids()` reads and writes the SAME per-uid entries that
 * `getByUid()` uses, so the existing single-uid door evicts them and a changing member list
 * cannot invalidate the members that did not change.
 *
 * Each test mutates the row behind the DAO's back with a direct UPDATE. That value is observable
 * only through a genuine cache miss, which is what makes "was this served from cache?" assertable
 * without a query counter.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class UserDaoBatchCacheRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['users'];

    private UserDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new UserDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);
    }

    protected function tearDown(): void
    {
        $this->dao->setCacheTTL(0);
        $this->finishRealSql();
        parent::tearDown();
    }

    /**
     * Rename a user without going through the DAO, so the new value can only be read by a query.
     */
    private function renameBehindTheDao(int $uid, string $name): void
    {
        $stmt = $this->realSqlDb()->getConnection()->prepare(
            'UPDATE users SET first_name = :name WHERE uid = :uid'
        );
        $stmt->execute(['name' => $name, 'uid' => $uid]);
    }

    private function firstNameFromBatch(array $uids, int $of): ?string
    {
        $set = $this->dao->getByUids($uids);

        return isset($set[$of]) ? (string)$set[$of]->first_name : null;
    }

    /**
     * The defect this change exists to fix: the single-uid eviction door has to reach whatever
     * `getByUids()` cached, or a renamed user stays stale in every cached member list.
     */
    public function testDestroyCacheByUidEvictsWhatGetByUidsCached(): void
    {
        $a = (int)$this->fixtures->makeUser()['uid'];
        $b = (int)$this->fixtures->makeUser()['uid'];

        $this->dao->setCacheTTL(60);
        $this->assertCount(2, $this->dao->getByUids([$a, $b]));

        $this->renameBehindTheDao($a, 'evicted');
        $this->dao->destroyCache($this->identityOf($a));

        $this->assertSame(
            'evicted',
            $this->firstNameFromBatch([$a, $b], $a),
            'destroyCache() must evict the entry getByUids() reads.'
        );
    }

    /**
     * Evicting one member must not evict the others: the entries are per-uid, not per-set.
     */
    public function testDestroyCacheLeavesTheOtherMembersCached(): void
    {
        $a = (int)$this->fixtures->makeUser()['uid'];
        $b = (int)$this->fixtures->makeUser()['uid'];

        $this->dao->setCacheTTL(60);
        $this->dao->getByUids([$a, $b]);

        $this->renameBehindTheDao($a, 'evicted');
        $this->renameBehindTheDao($b, 'untouched');
        $this->dao->destroyCache($this->identityOf($a));

        $set = $this->dao->getByUids([$a, $b]);

        $this->assertSame('evicted', (string)$set[$a]->first_name);
        $this->assertNotSame(
            'untouched',
            (string)$set[$b]->first_name,
            'B was not evicted, so it must still be served from its own cache entry.'
        );
    }

    /**
     * A batched read has to reuse an entry a single read already warmed — that shared address is
     * what makes the single-uid door sufficient.
     */
    public function testGetByUidsReusesTheEntryWarmedByGetByUid(): void
    {
        $a = (int)$this->fixtures->makeUser()['uid'];

        $this->dao->setCacheTTL(60);
        $this->dao->getByUid($a);

        $this->renameBehindTheDao($a, 'changed');

        $this->assertNotSame(
            'changed',
            $this->firstNameFromBatch([$a], $a),
            'getByUids() must hit the entry getByUid() wrote, not a separate set-shaped one.'
        );
    }

    /**
     * The other direction: a member first seen through a batched read is backfilled into its own
     * entry, so a later single read is served from cache too.
     */
    public function testGetByUidsBackfillsEntriesReadableByGetByUid(): void
    {
        $a = (int)$this->fixtures->makeUser()['uid'];

        $this->dao->setCacheTTL(60);
        $this->dao->getByUids([$a]);

        $this->renameBehindTheDao($a, 'backfilled');

        $found = $this->dao->getByUid($a);

        $this->assertNotNull($found);
        $this->assertNotSame(
            'backfilled',
            (string)$found->first_name,
            'getByUids() must write the per-uid entry, not only read it.'
        );
    }

    /**
     * Membership churn immunity. Adding a member changes the list but not the members already in
     * it, so the unchanged ones must still come from cache. Under a set-valued key the new list
     * addressed a different entry, so every member was re-read and the previous payload was
     * orphaned for its whole TTL.
     */
    public function testAddingAMemberDoesNotInvalidateTheExistingOnes(): void
    {
        $a = (int)$this->fixtures->makeUser()['uid'];
        $b = (int)$this->fixtures->makeUser()['uid'];

        $this->dao->setCacheTTL(60);
        $this->dao->getByUids([$a, $b]);

        $this->renameBehindTheDao($a, 'churned');
        $this->renameBehindTheDao($b, 'churned');

        $c = (int)$this->fixtures->makeUser()['uid'];
        $grown = $this->dao->getByUids([$a, $b, $c]);

        $this->assertCount(3, $grown, 'the newly added member still has to be loaded');
        $this->assertNotSame('churned', (string)$grown[$a]->first_name);
        $this->assertNotSame('churned', (string)$grown[$b]->first_name);
    }

    /**
     * A partially warm list must load only what is missing and return the whole set.
     */
    public function testPartiallyWarmListLoadsOnlyTheMissingMembers(): void
    {
        $a = (int)$this->fixtures->makeUser()['uid'];
        $b = (int)$this->fixtures->makeUser()['uid'];

        $this->dao->setCacheTTL(60);
        $this->dao->getByUid($a);

        $this->renameBehindTheDao($a, 'warm');
        $this->renameBehindTheDao($b, 'cold');

        $set = $this->dao->getByUids([$a, $b]);

        $this->assertCount(2, $set);
        $this->assertNotSame('warm', (string)$set[$a]->first_name, 'A was warm and must come from cache');
        $this->assertSame('cold', (string)$set[$b]->first_name, 'B was cold and must be read from the database');
    }

    /**
     * TTL 0 means "no cache" for the batched read exactly as it does for the single one: every
     * call is a live query.
     */
    public function testTtlZeroKeepsTheBatchedReadLive(): void
    {
        $a = (int)$this->fixtures->makeUser()['uid'];

        $this->dao->setCacheTTL(0);
        $this->dao->getByUids([$a]);

        $this->renameBehindTheDao($a, 'live');

        $this->assertSame('live', $this->firstNameFromBatch([$a], $a));
    }

    /**
     * Contract preserved from the set-shaped implementation: the result is keyed by uid, an empty
     * or unusable input short-circuits, and rows that do not exist are simply absent.
     */
    public function testResultShapeIsUnchanged(): void
    {
        $a = (int)$this->fixtures->makeUser()['uid'];
        $missing = $a + 10_000_000;

        $this->dao->setCacheTTL(60);

        $this->assertSame([], $this->dao->getByUids([]));
        $this->assertSame([], $this->dao->getByUids(['not-a-uid']));

        $set = $this->dao->getByUids([$a, $missing, ['uid' => $a]]);

        $this->assertSame([$a], array_keys($set), 'keyed by uid, deduplicated, absent rows omitted');
        $this->assertSame($a, (int)$set[$a]->uid);
    }

    /**
     * The door is addressed by the entity, and these tests hold only a uid. The email is left unset
     * on purpose: the door skips that key, so the eviction stays confined to the uid entry.
     */
    private function identityOf(int $uid): UserStruct
    {
        $user = new UserStruct();
        $user->uid = $uid;

        return $user;
    }
}
