<?php

namespace Matecat\Core\Model\ConnectedServices;

use Model\ConnectedServices\ConnectedServiceDao;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\Exceptions\ValidationError;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;

/**
 * Real-SQL coverage for ConnectedServiceDao (plan dao-realsql-90.md, Wave 6 / T15).
 *
 * Every public SQL method is called DIRECTLY and asserted on real returned data (DoD b):
 *   updateOauthToken, setServiceExpired, setDefaultService, findServiceByUserAndId,
 *   findServicesByUser, findDefaultServiceByUserAndName, findUserServicesByNameAndEmail.
 *
 * Single per-test connection for DAO + builder + cleanup (C-2); no wrapping transaction
 * (C-1); seed-safe id-list cleanup + whole-table residue gate (M-1/M-2/A-1). No assertion on
 * absolute generated id values (M-3).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class ConnectedServiceDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['connected_services', 'users'];

    private ConnectedServiceDao $dao;
    private int $uid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new ConnectedServiceDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);

        $this->uid = $this->fixtures->makeUser()['uid'];
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    private function userStruct(int $uid): UserStruct
    {
        $u = new UserStruct();
        $u->uid = $uid;

        return $u;
    }

    public function testFindServiceByUserAndIdReturnsRow(): void
    {
        $made = $this->fixtures->makeConnectedService($this->uid, 'gdrive');

        $found = $this->dao->findServiceByUserAndId($this->userStruct($this->uid), $made['id']);

        $this->assertInstanceOf(ConnectedServiceStruct::class, $found);
        $this->assertSame($made['id'], (int)$found->id);
        $this->assertSame($this->uid, (int)$found->uid);
        $this->assertSame('gdrive', $found->service);
    }

    public function testFindServiceByUserAndIdReturnsNullWhenAbsent(): void
    {
        $this->assertNull(
            $this->dao->findServiceByUserAndId($this->userStruct($this->uid), 1)
        );
    }

    public function testFindServicesByUserReturnsAllRows(): void
    {
        $this->fixtures->makeConnectedService($this->uid, 'gdrive');
        $this->fixtures->makeConnectedService($this->uid, 'dropbox');

        $services = $this->dao->findServicesByUser($this->userStruct($this->uid));

        $this->assertCount(2, $services);
        $names = array_map(static fn(ConnectedServiceStruct $s): string => $s->service, $services);
        sort($names);
        $this->assertSame(['dropbox', 'gdrive'], $names);
    }

    public function testFindServicesByUserReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], $this->dao->findServicesByUser($this->userStruct($this->uid)));
    }

    public function testFindUserServicesByNameAndEmailMatchesEmail(): void
    {
        $made = $this->fixtures->makeConnectedService($this->uid, 'gdrive');
        // resolve the email the builder assigned to this row
        $row = $this->dao->findServiceByUserAndId($this->userStruct($this->uid), $made['id']);
        $this->assertInstanceOf(ConnectedServiceStruct::class, $row);

        $found = $this->dao->findUserServicesByNameAndEmail(
            $this->userStruct($this->uid),
            'gdrive',
            $row->email
        );

        $this->assertInstanceOf(ConnectedServiceStruct::class, $found);
        $this->assertSame($made['id'], (int)$found->id);
    }

    public function testFindUserServicesByNameAndEmailReturnsNullForWrongEmail(): void
    {
        $this->fixtures->makeConnectedService($this->uid, 'gdrive');

        $this->assertNull(
            $this->dao->findUserServicesByNameAndEmail($this->userStruct($this->uid), 'gdrive', 'nobody@nowhere.test')
        );
    }

    public function testSetDefaultServiceFlipsTheFlag(): void
    {
        // two gdrive rows for the same user; only the second must end up default.
        $a = $this->fixtures->makeConnectedService($this->uid, 'gdrive');
        $b = $this->fixtures->makeConnectedService($this->uid, 'gdrive');

        $structB = $this->dao->findServiceByUserAndId($this->userStruct($this->uid), $b['id']);
        $this->assertInstanceOf(ConnectedServiceStruct::class, $structB);

        $this->dao->setDefaultService($structB);

        $default = $this->dao->findDefaultServiceByUserAndName($this->userStruct($this->uid), 'gdrive');
        $this->assertInstanceOf(ConnectedServiceStruct::class, $default);
        $this->assertSame($b['id'], (int)$default->id);

        // the other row must NOT be default
        $rowA = $this->dao->findServiceByUserAndId($this->userStruct($this->uid), $a['id']);
        $this->assertInstanceOf(ConnectedServiceStruct::class, $rowA);
        $this->assertSame(0, (int)$rowA->is_default);
    }

    public function testFindDefaultServiceByUserAndNameReturnsNullWhenNoDefault(): void
    {
        // builder inserts with is_default = 0 by default
        $this->fixtures->makeConnectedService($this->uid, 'gdrive');

        $this->assertNull(
            $this->dao->findDefaultServiceByUserAndName($this->userStruct($this->uid), 'gdrive')
        );
    }

    public function testUpdateOauthTokenPersistsEncryptedTokenAndTimestamp(): void
    {
        $made = $this->fixtures->makeConnectedService($this->uid, 'gdrive');
        $struct = $this->dao->findServiceByUserAndId($this->userStruct($this->uid), $made['id']);
        $this->assertInstanceOf(ConnectedServiceStruct::class, $struct);

        $returned = $this->dao->updateOauthToken('{"access_token":"new-token"}', $struct);

        $this->assertInstanceOf(ConnectedServiceStruct::class, $returned);
        $this->assertNotNull($returned->updated_at);

        // round-trip: re-read and decrypt, asserting on the round-tripped payload (M-3).
        $reloaded = $this->dao->findServiceByUserAndId($this->userStruct($this->uid), $made['id']);
        $this->assertInstanceOf(ConnectedServiceStruct::class, $reloaded);
        $this->assertSame('{"access_token":"new-token"}', $reloaded->getDecryptedOauthAccessToken());
    }

    public function testSetDefaultServiceRejectsInvalidStruct(): void
    {
        $invalid = new ConnectedServiceStruct();
        $invalid->uid = 0;       // empty uid -> validation error path
        $invalid->service = '';  // empty service

        $this->expectException(ValidationError::class);
        $this->dao->setDefaultService($invalid);
    }

    public function testSetServiceExpiredPersistsExpiry(): void
    {
        $made = $this->fixtures->makeConnectedService($this->uid, 'gdrive');
        $struct = $this->dao->findServiceByUserAndId($this->userStruct($this->uid), $made['id']);
        $this->assertInstanceOf(ConnectedServiceStruct::class, $struct);

        $affected = $this->dao->setServiceExpired(1_700_000_000, $struct);

        $this->assertSame(1, $affected);

        $reloaded = $this->dao->findServiceByUserAndId($this->userStruct($this->uid), $made['id']);
        $this->assertInstanceOf(ConnectedServiceStruct::class, $reloaded);
        $this->assertNotNull($reloaded->expired_at);
    }
}
