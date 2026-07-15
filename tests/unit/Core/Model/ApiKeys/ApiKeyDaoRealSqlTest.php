<?php

namespace Matecat\Core\Model\ApiKeys;

use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use PHPUnit\Framework\Attributes\Group;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;

/**
 * Real-SQL coverage for ApiKeyDao (plan dao-realsql-90.md, Wave 1 shallow pilot).
 * Every public SQL method is called DIRECTLY and asserted on real returned data (DoD b).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class ApiKeyDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    /** ApiKeyDao touches only api_keys; users referenced logically (no FK). */
    private const array TABLE_DEPS = ['api_keys'];

    private ApiKeyDao $dao;
    private int $uid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new ApiKeyDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);

        $this->uid = $this->fixtures->makeUser()['uid'];
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    public function testCreatePersistsAndRoundTrips(): void
    {
        $struct = new ApiKeyStruct();
        $struct->uid = $this->uid;
        $struct->api_key = 'rsk_create_' . bin2hex(random_bytes(6));
        $struct->api_secret = 'sec_' . bin2hex(random_bytes(6));
        $struct->enabled = true;

        $created = $this->dao->create($struct);
        // create() INSERTs through the DAO, not the builder: register it for cleanup so the
        // whole-table residue gate returns to baseline.
        $this->fixtures->trackExisting('api_keys', ['id' => (int)$created->id]);

        $this->assertNotNull($created->id);
        $this->assertSame($this->uid, $created->uid);
        $this->assertSame($struct->api_key, $created->api_key);
        $this->assertTrue((bool)$created->enabled);
    }

    public function testFindByKeyReturnsEnabledRow(): void
    {
        $made = $this->fixtures->makeApiKey($this->uid, null, true);

        $found = $this->dao->findByKey($made['api_key']);

        $this->assertInstanceOf(ApiKeyStruct::class, $found);
        $this->assertSame($made['id'], (int)$found->id);
        $this->assertSame($this->uid, (int)$found->uid);
    }

    public function testFindByKeyIgnoresDisabledRow(): void
    {
        $made = $this->fixtures->makeApiKey($this->uid, null, false);

        $this->assertNull($this->dao->findByKey($made['api_key']));
    }

    public function testGetByUidReturnsEnabledRow(): void
    {
        $made = $this->fixtures->makeApiKey($this->uid, null, true);

        $found = $this->dao->getByUid($this->uid);

        $this->assertInstanceOf(ApiKeyStruct::class, $found);
        $this->assertSame($made['api_key'], $found->api_key);
    }

    public function testGetByUidReturnsNullWhenAbsent(): void
    {
        $this->assertNull($this->dao->getByUid($this->uid)); // no key for this fresh user
    }

    public function testDeleteByUidRemovesRowAndReturnsCount(): void
    {
        $made = $this->fixtures->makeApiKey($this->uid, null, true);

        $deleted = $this->dao->deleteByUid($this->uid);

        $this->assertSame(1, $deleted);
        $this->assertNull($this->dao->findByKey($made['api_key']));
    }

    public function testDeleteByUidReturnsZeroWhenAbsent(): void
    {
        $this->assertSame(0, $this->dao->deleteByUid($this->uid));
    }
}
