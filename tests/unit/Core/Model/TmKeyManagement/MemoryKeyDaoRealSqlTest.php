<?php

namespace Matecat\Core\Model\TmKeyManagement;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use Utils\TmKeyManagement\TmKeyStruct;

/**
 * Real-SQL coverage for MemoryKeyDao (plan dao-realsql-90.md, Wave 5 / T12).
 *
 * `memory_keys` is an assignable composite-PK table (uid, key_value) with NO AUTO_INCREMENT and
 * NO seed rows; fixtures and DAO-created rows use uid >= 1_900_000_000 (M-2), so they can never
 * collide with or delete a protected seed PK (M-1). MemoryKeyDao is NOT self-committing and
 * opens no transaction, so per C-1 the harness wraps NO transaction; cleanup is the seed-safe
 * id-list DELETE. Rows the DAO INSERTs itself (create / createList) are registered via
 * trackExisting() so the whole-table COUNT(*) residue gate over [memory_keys] returns to
 * baseline.
 *
 * Every public SQL method is called DIRECTLY and asserted on real returned data (DoD b):
 * create, getKeyringOwnerKeysByUid, read (incl. traverse=true through UserDao), atomicUpdate,
 * delete, disable, enable, createList. The non-SQL helpers sanitize/sanitizeArray are also
 * exercised. NO assertion on absolute generated id values (M-3) - identity is round-tripped.
 *
 * Uses the dedicated RealSqlDaoTestTrait (S-4), NOT bare AbstractTest behaviour, so the 666
 * AbstractTest subclasses are unperturbed.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class MemoryKeyDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['memory_keys', 'users'];

    private MemoryKeyDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new MemoryKeyDao($this->realSqlDb());
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

    /** An assignable uid above the seeded band (M-2). */
    private function newUid(): int
    {
        return $this->fixtures->nextAssignableId();
    }

    private function newStruct(int $uid, string $keyValue, array $tmOverrides = []): MemoryKeyStruct
    {
        $tmKey        = new TmKeyStruct();
        $tmKey->key   = $keyValue;
        $tmKey->name  = $tmOverrides['name'] ?? ('rsq_key_' . bin2hex(random_bytes(3)));
        $tmKey->tm    = $tmOverrides['tm'] ?? true;
        $tmKey->glos  = $tmOverrides['glos'] ?? true;

        $s          = new MemoryKeyStruct();
        $s->uid     = $uid;
        $s->tm_key  = $tmKey;

        return $s;
    }

    /** Create via the DAO and register the inserted row for residue-safe cleanup. */
    private function daoCreate(int $uid, string $keyValue, array $tmOverrides = []): MemoryKeyStruct
    {
        $struct = $this->newStruct($uid, $keyValue, $tmOverrides);
        $saved  = $this->dao->create($struct);
        $this->assertInstanceOf(MemoryKeyStruct::class, $saved);
        $this->fixtures->trackExisting('memory_keys', ['uid' => $uid, 'key_value' => $keyValue]);

        return $saved;
    }

    // -----------------------------------------------------------------------------------------
    // create + read
    // -----------------------------------------------------------------------------------------

    public function testCreatePersistsAndReadRoundTrips(): void
    {
        $uid      = $this->newUid();
        $keyValue = bin2hex(random_bytes(8));

        $this->daoCreate($uid, $keyValue, ['name' => 'My Key', 'tm' => true, 'glos' => false]);

        $rows = $this->dao->read(new MemoryKeyStruct(['uid' => $uid]));

        $this->assertCount(1, $rows);
        $this->assertInstanceOf(MemoryKeyStruct::class, $rows[0]);
        $this->assertSame($uid, (int)$rows[0]->uid);
        $this->assertInstanceOf(TmKeyStruct::class, $rows[0]->tm_key);
        $this->assertSame($keyValue, $rows[0]->tm_key->key);
        $this->assertSame('My Key', $rows[0]->tm_key->name);
        $this->assertTrue($rows[0]->tm_key->tm);
        $this->assertFalse($rows[0]->tm_key->glos);
        // owner is the only uid for this key => owner true, not shared.
        $this->assertTrue($rows[0]->tm_key->owner);
        $this->assertFalse($rows[0]->tm_key->is_shared);
        // non-traverse read populates in_users_id (raw owner uids) and leaves in_users empty.
        $this->assertSame([(string)$uid], $rows[0]->tm_key->getInUsersId());
        $this->assertSame([], $rows[0]->tm_key->getInUsers());
    }

    public function testReadSharedKeyMarksMultipleOwners(): void
    {
        $keyValue = bin2hex(random_bytes(8));
        $uidA     = $this->newUid();
        $uidB     = $this->newUid();

        $this->daoCreate($uidA, $keyValue);
        $this->daoCreate($uidB, $keyValue);

        $rowsA = $this->dao->read(new MemoryKeyStruct(['uid' => $uidA]));

        $this->assertCount(1, $rowsA, 'GROUP BY key_value => one row per uid filter');
        $this->assertTrue($rowsA[0]->tm_key->is_shared, 'two owners of same key_value => shared');
    }

    public function testReadFiltersByKeyValueNameTmGlos(): void
    {
        $uid = $this->newUid();
        $k1  = bin2hex(random_bytes(8));
        $k2  = bin2hex(random_bytes(8));
        $this->daoCreate($uid, $k1, ['name' => 'Alpha', 'tm' => true, 'glos' => true]);
        $this->daoCreate($uid, $k2, ['name' => 'Beta', 'tm' => false, 'glos' => true]);

        // filter by key_value only (name=null so the DAO does not add a key_name predicate;
        // tm/glos=true here match k1 which was created with tm=true,glos=true).
        $byKeyStruct               = new MemoryKeyStruct(['uid' => $uid]);
        $byKeyStruct->tm_key       = new TmKeyStruct();
        $byKeyStruct->tm_key->key  = $k1;
        $byKeyStruct->tm_key->name = null;
        $byKey = $this->dao->read($byKeyStruct);
        $this->assertCount(1, $byKey);
        $this->assertSame($k1, $byKey[0]->tm_key->key);

        // filter by name. tm/glos default to true on TmKeyStruct and the DAO adds them as
        // WHERE predicates when non-null, so null them out to constrain on name alone.
        $byName               = new MemoryKeyStruct(['uid' => $uid]);
        $byName->tm_key       = new TmKeyStruct();
        $byName->tm_key->name = 'Beta';
        $byName->tm_key->tm   = null;
        $byName->tm_key->glos = null;
        $resByName = $this->dao->read($byName);
        $this->assertCount(1, $resByName);
        $this->assertSame($k2, $resByName[0]->tm_key->key);
    }

    public function testReadDeletedKeyIsExcluded(): void
    {
        $uid      = $this->newUid();
        $keyValue = bin2hex(random_bytes(8));
        $this->daoCreate($uid, $keyValue);
        $this->dao->disable($this->newStruct($uid, $keyValue)); // sets deleted = 1

        $rows = $this->dao->read(new MemoryKeyStruct(['uid' => $uid]));
        $this->assertCount(0, $rows, 'disabled (deleted=1) rows are filtered by WHERE m1.deleted = 0');
    }

    // -----------------------------------------------------------------------------------------
    // getKeyringOwnerKeysByUid (delegates to read)
    // -----------------------------------------------------------------------------------------

    public function testGetKeyringOwnerKeysByUidReturnsOwnedKeys(): void
    {
        $uid = $this->newUid();
        $this->daoCreate($uid, bin2hex(random_bytes(8)));
        $this->daoCreate($uid, bin2hex(random_bytes(8)));

        $keys = $this->dao->getKeyringOwnerKeysByUid($uid);

        $this->assertCount(2, $keys);
        foreach ($keys as $k) {
            $this->assertInstanceOf(MemoryKeyStruct::class, $k);
            $this->assertSame($uid, (int)$k->uid);
        }
    }

    public function testGetKeyringOwnerKeysByUidEmptyForUnknownUid(): void
    {
        $keys = $this->dao->getKeyringOwnerKeysByUid(1_999_999_998);
        $this->assertSame([], $keys);
    }

    // -----------------------------------------------------------------------------------------
    // read with traverse=true (joins UserDao::getByUids on the real owner uid)
    // -----------------------------------------------------------------------------------------

    public function testReadWithTraverseResolvesOwnerUsers(): void
    {
        // Build a real user so UserDao::getByUids returns a row.
        $user     = $this->fixtures->makeUser();
        $uid      = (int)$user['uid'];
        $keyValue = bin2hex(random_bytes(8));
        $this->daoCreate($uid, $keyValue);

        // traverse=true takes the branch that resolves owner uids through UserDao::getByUids
        // (vs. the non-traverse branch that keeps the raw owner_uids string). The observable
        // contract is that the read still succeeds and round-trips the owned key without error.
        $rows = $this->dao->read(new MemoryKeyStruct(['uid' => $uid]), true);

        $this->assertCount(1, $rows);
        $this->assertSame($keyValue, $rows[0]->tm_key->key);
        $this->assertSame($uid, (int)$rows[0]->uid);
        $this->assertTrue($rows[0]->tm_key->owner, 'the requesting uid owns the key');

        // traverse=true resolves owner uids to UserStruct[] on in_users (keyed by uid via
        // UserDao::getByUids); in_users_id stays empty.
        $inUsers = $rows[0]->tm_key->getInUsers();
        $this->assertCount(1, $inUsers);
        $this->assertArrayHasKey($uid, $inUsers);
        $this->assertInstanceOf(UserStruct::class, $inUsers[$uid]);
        $this->assertSame($uid, (int)$inUsers[$uid]->uid);
        $this->assertEquals([$uid], $rows[0]->tm_key->getInUsersId());
    }

    // -----------------------------------------------------------------------------------------
    // atomicUpdate
    // -----------------------------------------------------------------------------------------

    public function testAtomicUpdateChangesKeyName(): void
    {
        $uid      = $this->newUid();
        $keyValue = bin2hex(random_bytes(8));
        $this->daoCreate($uid, $keyValue, ['name' => 'Original']);

        $update = $this->newStruct($uid, $keyValue, ['name' => 'Renamed']);
        $result = $this->dao->atomicUpdate($update);

        $this->assertInstanceOf(MemoryKeyStruct::class, $result);

        $rows = $this->dao->read(new MemoryKeyStruct(['uid' => $uid]));
        $this->assertSame('Renamed', $rows[0]->tm_key->name);
    }

    public function testAtomicUpdateReturnsNullWhenNoRowMatches(): void
    {
        $uid    = $this->newUid();
        $update = $this->newStruct($uid, bin2hex(random_bytes(8)), ['name' => 'Ghost']);

        $result = $this->dao->atomicUpdate($update);
        $this->assertNull($result, 'no matching (uid,key_value) => 0 rows => null');
    }

    // -----------------------------------------------------------------------------------------
    // disable / enable
    // -----------------------------------------------------------------------------------------

    public function testDisableThenEnableTogglesDeletedFlag(): void
    {
        $uid      = $this->newUid();
        $keyValue = bin2hex(random_bytes(8));
        $this->daoCreate($uid, $keyValue);

        $disabled = $this->dao->disable($this->newStruct($uid, $keyValue));
        $this->assertInstanceOf(MemoryKeyStruct::class, $disabled);
        $this->assertCount(0, $this->dao->read(new MemoryKeyStruct(['uid' => $uid])));

        $enabled = $this->dao->enable($this->newStruct($uid, $keyValue));
        $this->assertInstanceOf(MemoryKeyStruct::class, $enabled);
        $this->assertCount(1, $this->dao->read(new MemoryKeyStruct(['uid' => $uid])));
    }

    // -----------------------------------------------------------------------------------------
    // delete
    // -----------------------------------------------------------------------------------------

    public function testDeleteRemovesRow(): void
    {
        $uid      = $this->newUid();
        $keyValue = bin2hex(random_bytes(8));
        $this->daoCreate($uid, $keyValue);

        $deleted = $this->dao->delete($this->newStruct($uid, $keyValue));
        $this->assertInstanceOf(MemoryKeyStruct::class, $deleted);

        // Row is physically gone => read returns nothing.
        $this->assertCount(0, $this->dao->read(new MemoryKeyStruct(['uid' => $uid])));
    }

    public function testDeleteReturnsNullWhenRowAbsent(): void
    {
        $uid    = $this->newUid();
        $result = $this->dao->delete($this->newStruct($uid, bin2hex(random_bytes(8))));
        $this->assertNull($result, 'deleting a non-existent row => 0 rows => null');
    }

    // -----------------------------------------------------------------------------------------
    // createList (batched multi-row INSERT)
    // -----------------------------------------------------------------------------------------

    public function testCreateListInsertsMultipleRows(): void
    {
        $uid = $this->newUid();
        $k1  = bin2hex(random_bytes(8));
        $k2  = bin2hex(random_bytes(8));
        $k3  = bin2hex(random_bytes(8));

        $list = [
            $this->newStruct($uid, $k1, ['name' => 'L1']),
            $this->newStruct($uid, $k2, ['name' => 'L2']),
            $this->newStruct($uid, $k3, ['name' => 'L3']),
        ];

        $this->dao->createList($list);
        foreach ([$k1, $k2, $k3] as $kv) {
            $this->fixtures->trackExisting('memory_keys', ['uid' => $uid, 'key_value' => $kv]);
        }

        $rows = $this->dao->read(new MemoryKeyStruct(['uid' => $uid]));
        $this->assertCount(3, $rows, 'createList inserted three rows');

        $names = array_map(static fn(MemoryKeyStruct $r): string => (string)$r->tm_key->name, $rows);
        sort($names);
        $this->assertSame(['L1', 'L2', 'L3'], $names);
    }

    // -----------------------------------------------------------------------------------------
    // sanitize / sanitizeArray (non-SQL helpers, exercised for completeness)
    // -----------------------------------------------------------------------------------------

    public function testSanitizeArrayReturnsOnlyMemoryKeyStructs(): void
    {
        $uid  = $this->newUid();
        $list = [$this->newStruct($uid, bin2hex(random_bytes(8)))];

        $result = $this->dao->sanitizeArray($list);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(MemoryKeyStruct::class, $result[0]);
    }
}
