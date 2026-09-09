<?php

namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Users\MetadataDao;
use Model\Users\MetadataStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TypeError;

/**
 * Cache-eviction coverage for Model\Users\MetadataDao against the live user_metadata table.
 *
 * A row answers two reads — one addressed by uid alone, one by uid and key — and `delete()` removes
 * rows by a key SUFFIX, so the key it is given is not the key the row is stored and cached under.
 * These tests pin that a delete clears the address of every row it actually removed.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class MetadataDaoEvictionRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['users', 'user_metadata'];

    /** The key an adaptive MT engine is stored under: its class_load. */
    private const string CLASS_LOAD = 'Utils\Engines\MMT';

    /** What EngineStruct::getEngineType() returns for that class_load, and what delete() is given. */
    private const string ENGINE_TYPE = 'MMT';

    private MetadataDao $dao;
    private int $uid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new MetadataDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);
        $this->uid = (int)$this->fixtures->makeUser()['uid'];
    }

    protected function tearDown(): void
    {
        $this->dao->setCacheTTL(0);
        $this->finishRealSql();
        parent::tearDown();
    }

    /**
     * EngineController::delete() hands the engine TYPE to a DELETE matching on `key` LIKE '%MMT',
     * which removes the row stored under the full class_load. TMSService:212,
     * TmKeyManagementController:183 and UserKeysController:282 all read that row by class_load at a
     * 30-day TTL, so the address the delete has to clear is the one it never names.
     */
    #[Test]
    public function testDeleteEvictsTheAddressOfTheRowItRemoved(): void
    {
        $this->fixtures->makeUserMetadata($this->uid, self::CLASS_LOAD, '42');

        $this->dao->setCacheTTL(60);
        self::assertSame('42', $this->dao->get($this->uid, self::CLASS_LOAD)?->value);

        $this->dao->delete($this->uid, self::ENGINE_TYPE);

        self::assertNull(
            $this->dao->get($this->uid, self::CLASS_LOAD),
            'delete() must evict the address of the row it removed, not of the suffix it was given.'
        );
    }

    #[Test]
    public function testDestroyCacheEvictsBothAddressesOfAMetadataRow(): void
    {
        $this->fixtures->makeUserMetadata($this->uid, self::CLASS_LOAD, 'before');

        $this->dao->setCacheTTL(60);
        self::assertSame('before', $this->dao->get($this->uid, self::CLASS_LOAD)?->value);
        self::assertSame('before', $this->firstValueByUid());

        $this->editBehindTheDao(self::CLASS_LOAD, 'after');

        $this->dao->destroyCache(new MetadataStruct([
            'uid' => $this->uid,
            'key' => self::CLASS_LOAD,
        ]));

        self::assertSame('after', $this->dao->get($this->uid, self::CLASS_LOAD)?->value);
        self::assertSame('after', $this->firstValueByUid());
    }

    /** Without this the test above would also pass on a door that cleared the whole DAO. */
    #[Test]
    public function testDestroyCacheLeavesAKeyItWasNotGiven(): void
    {
        $this->fixtures->makeUserMetadata($this->uid, self::CLASS_LOAD, 'before');

        $this->dao->setCacheTTL(60);
        self::assertSame('before', $this->dao->get($this->uid, self::CLASS_LOAD)?->value);

        $this->editBehindTheDao(self::CLASS_LOAD, 'after');

        $this->dao->destroyCache(new MetadataStruct([
            'uid' => $this->uid,
            'key' => 'unrelated',
        ]));

        self::assertSame('before', $this->dao->get($this->uid, self::CLASS_LOAD)?->value);
    }

    #[Test]
    public function testDestroyCacheRefusesAStructThatNamesNoKey(): void
    {
        $this->expectException(TypeError::class);

        $this->dao->destroyCache(new MetadataStruct(['uid' => $this->uid]));
    }

    #[Test]
    public function testDestroyCacheRefusesAStructThatNamesNoUid(): void
    {
        $this->expectException(TypeError::class);

        $this->dao->destroyCache(new MetadataStruct(['key' => self::CLASS_LOAD]));
    }

    /** The value the uid-addressed read serves, which is the entry a member list shares. */
    private function firstValueByUid(): ?string
    {
        $rows = $this->dao->getAllByUidList([$this->uid]);

        return isset($rows[$this->uid][0]) ? (string)$rows[$this->uid][0]->value : null;
    }

    /** Change the stored value without going through the DAO, so any entry left behind shows up. */
    private function editBehindTheDao(string $key, string $value): void
    {
        $this->realSqlDb()->getConnection()
            ->prepare('UPDATE user_metadata SET `value` = :v WHERE uid = :u AND `key` = :k')
            ->execute(['v' => $value, 'u' => $this->uid, 'k' => $key]);
    }
}
