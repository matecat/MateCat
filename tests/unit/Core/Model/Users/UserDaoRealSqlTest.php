<?php

namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Users\UserDao;
use Model\Users\UserStruct;
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

    public function testGetByConfirmationTokenReturnsRow(): void
    {
        $token = 'tok_' . bin2hex(random_bytes(10));
        $made = $this->fixtures->makeUser(['confirmation_token' => $token]);

        $found = $this->dao->getByConfirmationToken($token);

        $this->assertInstanceOf(UserStruct::class, $found);
        $this->assertSame($made['uid'], (int)$found->uid);
    }

    public function testGetByConfirmationTokenReturnsNullWhenAbsent(): void
    {
        $this->assertNull($this->dao->getByConfirmationToken('missing_' . bin2hex(random_bytes(8))));
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

    public function testDestroyCacheByUidEvictsWarmedEntry(): void
    {
        $made = $this->fixtures->makeUser();
        // Warm the cache with a non-zero TTL so a key actually exists to evict.
        $this->dao->setCacheTTL(60);
        $this->dao->getByUid($made['uid']);

        $this->assertTrue($this->dao->destroyCacheByUid($made['uid']));
        // Second eviction finds nothing -> false: proves the first call really removed the key.
        $this->assertFalse($this->dao->destroyCacheByUid($made['uid']));
        $this->dao->setCacheTTL(0);
    }

    public function testDestroyCacheByEmailEvictsWarmedEntry(): void
    {
        $made = $this->fixtures->makeUser();
        $this->dao->setCacheTTL(60);
        $this->dao->getByEmail($made['email']);

        $this->assertTrue($this->dao->destroyCacheByEmail($made['email']));
        $this->dao->setCacheTTL(0);
    }
}
