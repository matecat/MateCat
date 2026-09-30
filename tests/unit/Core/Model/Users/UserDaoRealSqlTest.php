<?php

namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Users\AuthTokenScope;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use RuntimeException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-SQL coverage for UserDao (plan dao-realsql-90.md, Wave 1 shallow pilot — single-table
 * cleanup proof, DoD). Every public SQL method is called DIRECTLY and asserted on real
 * returned data (DoD b). NO assertion on absolute generated id values (M-3): identity is
 * checked by round-tripping the row, not by a literal id.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class UserDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    /** UserDao reads users plus projects/jobs for the two JOIN accessors. */
    private const array TABLE_DEPS = ['users', 'projects', 'jobs'];

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
        $this->finishRealSql();
        parent::tearDown();
    }

    /**
     * The scoped lookup is what confines a token to the flow that minted it. A confirmation token
     * offered to the reset flow has to find nothing at all — that is what stopped a "confirm your
     * account" link doubling as a "set any password" link.
     */
    public function testScopedConfirmationTokenLookupRejectsAnotherFlowsToken(): void
    {
        $struct = new UserStruct();
        $struct->email = 'rsq_scope_' . bin2hex(random_bytes(6)) . '@example.test';
        $struct->first_name = 'Scoped';
        $struct->last_name = 'Token';
        $struct->create_date = date('Y-m-d H:i:s');
        $struct->initAuthToken(AuthTokenScope::SignupConfirmation);

        $uid = $this->dao->insertStruct($struct);
        $this->fixtures->trackExisting('users', ['uid' => (int)$uid]);

        $raw = $struct->authTokenForUrl();

        $this->assertNotNull(
            $this->dao->getByScopedConfirmationToken($raw, AuthTokenScope::SignupConfirmation),
            'the flow that minted the token must find it'
        );
        $this->assertNull(
            $this->dao->getByScopedConfirmationToken($raw, AuthTokenScope::PasswordReset),
            'the other flow must not find it at all'
        );
    }

    /**
     * The whole point of storing a digest is that reading the table gives no usable link. Someone
     * holding a stored value must not be able to spend it by presenting it as the token: whatever
     * arrives is hashed before the lookup, so the stored value hashes to something else and matches
     * nothing.
     */
    public function testScopedConfirmationTokenLookupRejectsTheStoredDigestItself(): void
    {
        $struct = new UserStruct();
        $struct->email = 'rsq_digest_' . bin2hex(random_bytes(6)) . '@example.test';
        $struct->first_name = 'Digest';
        $struct->last_name = 'Replay';
        $struct->create_date = date('Y-m-d H:i:s');
        $struct->initAuthToken(AuthTokenScope::PasswordReset);

        $uid = $this->dao->insertStruct($struct);
        $this->fixtures->trackExisting('users', ['uid' => (int)$uid]);

        $stored = (string)$struct->confirmation_token;
        $digest = substr($stored, strlen(AuthTokenScope::PasswordReset->marker()));

        $this->assertNull(
            $this->dao->getByScopedConfirmationToken($digest, AuthTokenScope::PasswordReset),
            'the digest read out of the column is not a token'
        );
        $this->assertNull(
            $this->dao->getByScopedConfirmationToken($stored, AuthTokenScope::PasswordReset),
            'and neither is the stored value with its marker'
        );
        $this->assertNotNull(
            $this->dao->getByScopedConfirmationToken($struct->authTokenForUrl(), AuthTokenScope::PasswordReset),
            'only the secret that was mailed finds the row'
        );
    }

    public function testCreateUserPersistsAndRoundTrips(): void
    {
        $struct = new UserStruct();
        $struct->email = 'rsq_create_' . bin2hex(random_bytes(6)) . '@example.test';
        $struct->salt = 'salt_' . bin2hex(random_bytes(4));
        $struct->pass = 'pass_' . bin2hex(random_bytes(4));
        $struct->first_name = 'Create';
        $struct->last_name = 'User';
        $struct->confirmation_token = 'tok_' . bin2hex(random_bytes(8));

        $created = $this->dao->createUser($struct);
        // createUser INSERTs through the DAO: register for cleanup so residue returns to baseline.
        $this->fixtures->trackExisting('users', ['uid' => (int)$created->uid]);

        $this->assertInstanceOf(UserStruct::class, $created);
        $this->assertNotNull($created->uid);
        $this->assertSame($struct->email, $created->email);
        $this->assertSame('Create', $created->first_name);
    }

    public function testGetByUidReturnsRow(): void
    {
        $made = $this->fixtures->makeUser();

        $found = $this->dao->getByUid($made['uid']);

        $this->assertInstanceOf(UserStruct::class, $found);
        $this->assertSame($made['uid'], (int)$found->uid);
        $this->assertSame($made['email'], $found->email);
    }

    public function testGetByUidReturnsNullWhenAbsent(): void
    {
        // An id far above the seeded band (max seeded uid 1_886_591_200) that no fixture uses.
        $this->assertNull($this->dao->getByUid(2_000_000_999));
    }

    public function testGetByEmailReturnsRow(): void
    {
        $made = $this->fixtures->makeUser();

        $found = $this->dao->getByEmail($made['email']);

        $this->assertInstanceOf(UserStruct::class, $found);
        $this->assertSame($made['uid'], (int)$found->uid);
    }

    public function testGetByEmailReturnsNullWhenAbsent(): void
    {
        $this->assertNull($this->dao->getByEmail('absent_' . bin2hex(random_bytes(6)) . '@example.test'));
    }

    public function testScopedConfirmationTokenLookupReturnsRow(): void
    {
        $struct = new UserStruct();
        $struct->email = 'rsq_lookup_' . bin2hex(random_bytes(6)) . '@example.test';
        $struct->first_name = 'Lookup';
        $struct->last_name = 'Token';
        $struct->create_date = date('Y-m-d H:i:s');
        $struct->initAuthToken(AuthTokenScope::PasswordReset);

        $uid = $this->dao->insertStruct($struct);
        $this->fixtures->trackExisting('users', ['uid' => (int)$uid]);

        $found = $this->dao->getByScopedConfirmationToken(
            $struct->authTokenForUrl(),
            AuthTokenScope::PasswordReset
        );

        $this->assertInstanceOf(UserStruct::class, $found);
        $this->assertSame((int)$uid, (int)$found->uid);
    }

    public function testScopedConfirmationTokenLookupReturnsNullWhenAbsent(): void
    {
        $this->assertNull(
            $this->dao->getByScopedConfirmationToken(
                'missing_' . bin2hex(random_bytes(8)),
                AuthTokenScope::PasswordReset
            )
        );
    }

    public function testGetByUidsReturnsMapKeyedByUid(): void
    {
        $a = $this->fixtures->makeUser();
        $b = $this->fixtures->makeUser();

        $map = $this->dao->getByUids([$a['uid'], ['uid' => $b['uid']], 'not-a-number']);

        $this->assertArrayHasKey($a['uid'], $map);
        $this->assertArrayHasKey($b['uid'], $map);
        $this->assertSame($a['email'], $map[$a['uid']]->email);
    }

    public function testGetByUidsReturnsEmptyForNoValidIds(): void
    {
        $this->assertSame([], $this->dao->getByUids(['nope', ['no' => 'uid']]));
    }

    public function testGetByEmailsReturnsMapKeyedByEmail(): void
    {
        $a = $this->fixtures->makeUser();
        $b = $this->fixtures->makeUser();

        $map = $this->dao->getByEmails([$a['email'], $b['email']]);

        $this->assertArrayHasKey($a['email'], $map);
        $this->assertArrayHasKey($b['email'], $map);
        $this->assertSame($b['uid'], (int)$map[$b['email']]->uid);
    }

    public function testReadByUidReturnsProjection(): void
    {
        $made = $this->fixtures->makeUser();

        $query = new UserStruct();
        $query->uid = $made['uid'];
        $rows = $this->dao->read($query);

        $this->assertCount(1, $rows);
        $this->assertSame($made['uid'], (int)$rows[0]->uid);
        $this->assertSame($made['email'], $rows[0]->email);
    }

    public function testReadByEmailReturnsProjection(): void
    {
        $made = $this->fixtures->makeUser();

        $query = new UserStruct();
        $query->email = $made['email'];
        $rows = $this->dao->read($query);

        $this->assertCount(1, $rows);
        $this->assertSame($made['uid'], (int)$rows[0]->uid);
    }

    public function testReadWithNoConditionsThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Where condition needed.');
        $this->dao->read(new UserStruct());
    }

    public function testUpdateUserPersistsChanges(): void
    {
        $made = $this->fixtures->makeUser();

        $obj = new UserStruct();
        $obj->uid = $made['uid'];
        $obj->email = $made['email'];
        $obj->salt = 'newsalt';
        $obj->pass = 'newpass';
        $obj->create_date = date('Y-m-d H:i:s');
        $obj->first_name = 'Renamed';
        $obj->last_name = 'Person';
        $obj->confirmation_token = null;
        $obj->oauth_access_token = null;

        $updated = $this->dao->updateUser($obj);

        $this->assertSame('Renamed', $updated->first_name);
        $this->assertSame('Person', $updated->last_name);

        // Confirm persisted by a fresh read on a clean cache.
        $this->flushDaoCache();
        $reloaded = $this->dao->getByUid($made['uid']);
        $this->assertSame('Renamed', $reloaded->first_name);
    }

    public function testDeleteRemovesRowAndReturnsCount(): void
    {
        $made = $this->fixtures->makeUser();

        $struct = new UserStruct();
        $struct->uid = $made['uid'];
        $count = $this->dao->delete($struct);

        $this->assertSame(1, $count);
        $this->flushDaoCache();
        $this->assertNull($this->dao->getByUid($made['uid']));
    }

    public function testSanitizeCoercesUid(): void
    {
        $input = new UserStruct();
        $input->uid = '123';
        $input->email = 'a@b.test';

        $out = $this->dao->sanitize($input);

        $this->assertSame(123, $out->uid);
    }

    public function testGetProjectOwnerJoinsByEmail(): void
    {
        $owner = $this->fixtures->makeUser();
        $project = $this->fixtures->makeProject();
        $job = $this->fixtures->makeJob($project['id'], ['owner' => $owner['email']]);

        $found = $this->dao->getProjectOwner($job['id']);

        $this->assertInstanceOf(UserStruct::class, $found);
        $this->assertSame($owner['uid'], (int)$found->uid);
    }

    public function testGetProjectOwnerReturnsNullWhenNoMatch(): void
    {
        $project = $this->fixtures->makeProject();
        $job = $this->fixtures->makeJob($project['id'], ['owner' => 'nobody_' . bin2hex(random_bytes(6)) . '@example.test']);

        $this->assertNull($this->dao->getProjectOwner($job['id']));
    }

    public function testGetProjectAssigneeJoinsByUid(): void
    {
        $assignee = $this->fixtures->makeUser();
        $project = $this->fixtures->makeProject(['id_assignee' => $assignee['uid']]);

        $found = $this->dao->getProjectAssignee($project['id']);

        $this->assertInstanceOf(UserStruct::class, $found);
        $this->assertSame($assignee['uid'], (int)$found->uid);
    }


    /**
     * The door is the whole invalidation for a user row: a caller names the entity it already holds
     * and both addresses that row is cached under go. Asserted by mutating the row underneath a warm
     * cache — a read that still returns the old name proves the entry was live, and the same read
     * returning the new one after the door proves it was evicted.
     */
    public function testDestroyCacheEvictsBothTheUidAndTheEmailKeys(): void
    {
        $made = $this->fixtures->makeUser();
        $user = $this->dao->getByUid($made['uid']);
        $this->assertInstanceOf(UserStruct::class, $user);

        $this->dao->setCacheTTL(60);
        $this->dao->getByUid($made['uid']);
        $this->dao->getByEmail($made['email']);

        $this->renameBehindTheCache((int)$made['uid'], 'Renamed');

        $this->assertNotSame(
            'Renamed',
            $this->dao->getByUid($made['uid'])?->first_name,
            'precondition: the uid entry must be warm and therefore stale'
        );
        $this->assertNotSame(
            'Renamed',
            $this->dao->getByEmail($made['email'])?->first_name,
            'precondition: the email entry must be warm and therefore stale'
        );

        $this->dao->destroyCache($user);

        $this->assertSame('Renamed', $this->dao->getByUid($made['uid'])?->first_name);
        $this->assertSame('Renamed', $this->dao->getByEmail($made['email'])?->first_name);
        $this->dao->setCacheTTL(0);
    }

    /**
     * A struct handed to the door after an email change carries only the new address, so the entry
     * keyed on the old one would survive its whole TTL and keep answering lookups for an address the
     * account no longer has. The parameter is the only way the door can learn that value.
     */
    public function testDestroyCacheEvictsTheRetiredEmailKey(): void
    {
        $made = $this->fixtures->makeUser();
        $retiredEmail = $made['email'];
        $newEmail = 'rsq_moved_' . bin2hex(random_bytes(6)) . '@example.test';

        $this->dao->setCacheTTL(60);
        $this->dao->getByEmail($retiredEmail);

        $this->realSqlDb()->getConnection()
            ->prepare('UPDATE users SET email = :email WHERE uid = :uid')
            ->execute(['email' => $newEmail, 'uid' => (int)$made['uid']]);

        $this->assertInstanceOf(
            UserStruct::class,
            $this->dao->getByEmail($retiredEmail),
            'precondition: the retired address still resolves out of the warm entry'
        );

        $moved = new UserStruct();
        $moved->uid = (int)$made['uid'];
        $moved->email = $newEmail;

        $this->dao->destroyCache($moved, $retiredEmail);

        $this->assertNull(
            $this->dao->getByEmail($retiredEmail),
            'the retired address must resolve to nothing once its entry is evicted'
        );
        $this->dao->setCacheTTL(0);
    }

    /**
     * Callers bust the same user from more than one path — a password change and the logout that
     * follows it. The second call must find nothing and say nothing, not fail.
     */
    public function testDestroyCacheIsIdempotent(): void
    {
        $made = $this->fixtures->makeUser();
        $user = $this->dao->getByUid($made['uid']);
        $this->assertInstanceOf(UserStruct::class, $user);

        $this->dao->setCacheTTL(60);
        $this->dao->getByUid($made['uid']);
        $this->dao->getByEmail($made['email']);

        $this->dao->destroyCache($user);
        $this->dao->destroyCache($user);

        $this->renameBehindTheCache((int)$made['uid'], 'Twice');
        $this->assertSame('Twice', $this->dao->getByUid($made['uid'])?->first_name);
        $this->dao->setCacheTTL(0);
    }

    /**
     * The uid is the identity the door is addressed by. Without it there is nothing to evict, and
     * silently doing nothing would leave a caller believing it had invalidated a row.
     */
    public function testDestroyCacheRefusesAStructWithNoUid(): void
    {
        $anonymous = new UserStruct();
        $anonymous->email = 'rsq_nouid_' . bin2hex(random_bytes(6)) . '@example.test';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User uid must be set before cache invalidation');

        $this->dao->destroyCache($anonymous);
    }

    /**
     * users.email is NOT NULL, so no stored row is reachable through the email query with a null
     * bind — there is provably no entry under that address to evict. The uid key still has to go.
     */
    public function testDestroyCacheEvictsTheUidKeyWhenTheStructCarriesNoEmail(): void
    {
        $made = $this->fixtures->makeUser();

        $this->dao->setCacheTTL(60);
        $this->dao->getByUid($made['uid']);

        $emailless = new UserStruct();
        $emailless->uid = (int)$made['uid'];

        $this->dao->destroyCache($emailless);

        $this->renameBehindTheCache((int)$made['uid'], 'NoEmail');
        $this->assertSame('NoEmail', $this->dao->getByUid($made['uid'])?->first_name);
        $this->dao->setCacheTTL(0);
    }

    /**
     * Writes the row on the connection directly, so the cached copy cannot follow it. A read that
     * still returns the previous value is then proof the entry is live rather than proof of nothing.
     */
    private function renameBehindTheCache(int $uid, string $firstName): void
    {
        $this->realSqlDb()->getConnection()
            ->prepare('UPDATE users SET first_name = :first_name WHERE uid = :uid')
            ->execute(['first_name' => $firstName, 'uid' => $uid]);
    }
}
