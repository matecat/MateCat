<?php

namespace unit\DAO\TestConnectedServiceDAO;

use Model\ConnectedServices\ConnectedServiceDao;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\DataAccess\Database;
use Model\Exceptions\ValidationError;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Utils\Registry\AppConfig;

class ConnectedServiceDaoTest extends TestCase
{
    private \PDO $pdoStub;
    private PDOStatement $stmtStub;
    private ConnectedServiceDao $dao;

    protected function setUp(): void
    {
        AppConfig::$SKIP_SQL_CACHE = true;

        $this->stmtStub = $this->createStub(PDOStatement::class);
        $this->stmtStub->queryString = '';

        $this->pdoStub = $this->createStub(PDO::class);
        $this->pdoStub->method('prepare')->willReturn($this->stmtStub);

        $dbStub = $this->createStub(Database::class);
        $dbStub->method('getConnection')->willReturn($this->pdoStub);

        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $dbStub);

        $this->dao = new ConnectedServiceDao();
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        AppConfig::$SKIP_SQL_CACHE = false;
    }

    #[Test]
    public function updateOauthTokenUpdatesFieldsAndReturnsStruct(): void
    {
        $service = $this->createMock(ConnectedServiceStruct::class);
        $service->expects($this->once())
            ->method('setEncryptedAccessToken')
            ->with('new-token');

        $result = $this->dao->updateOauthToken('new-token', $service);

        $this->assertSame($service, $result);
        $this->assertNotNull($service->updated_at);
    }

    #[Test]
    public function setServiceExpiredSetsTimestampAndReturnsAffectedRows(): void
    {
        $service = new ConnectedServiceStruct();
        $service->id = 1;
        $service->uid = 10;
        $service->service = 'gdrive';
        $service->email = 'test@example.com';
        $service->name = 'Test';
        $service->created_at = '2026-01-01 00:00:00';

        $result = $this->dao->setServiceExpired(time(), $service);

        $this->assertIsInt($result);
        $this->assertNotNull($service->expired_at);
    }

    #[Test]
    public function setDefaultServiceThrowsOnEmptyUid(): void
    {
        $service = new ConnectedServiceStruct();
        $service->uid = 0;
        $service->service = 'gdrive';

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Service is not valid for update');

        $this->dao->setDefaultService($service);
    }

    #[Test]
    public function setDefaultServiceThrowsOnEmptyService(): void
    {
        $service = new ConnectedServiceStruct();
        $service->uid = 10;
        $service->service = '';

        $this->expectException(ValidationError::class);

        $this->dao->setDefaultService($service);
    }

    #[Test]
    public function setDefaultServiceExecutesTwoUpdates(): void
    {
        $service = new ConnectedServiceStruct();
        $service->id = 5;
        $service->uid = 10;
        $service->service = 'gdrive';
        $service->email = 'x@x.com';
        $service->name = 'X';
        $service->created_at = '2026-01-01 00:00:00';

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->exactly(2))
            ->method('execute');

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($stmtMock);

        $dbStub = $this->createStub(Database::class);
        $dbStub->method('getConnection')->willReturn($pdoMock);

        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $dbStub);

        $dao = new ConnectedServiceDao();
        $dao->setDefaultService($service);
    }

    #[Test]
    public function findServiceByUserAndIdReturnsStructWhenFound(): void
    {
        $user = new UserStruct();
        $user->uid = 10;

        $struct = new ConnectedServiceStruct();
        $struct->id = 5;

        $this->stmtStub->method('fetch')->willReturn($struct);

        $result = $this->dao->findServiceByUserAndId($user, 5);

        $this->assertInstanceOf(ConnectedServiceStruct::class, $result);
        $this->assertSame(5, $result->id);
    }

    #[Test]
    public function findServiceByUserAndIdReturnsNullWhenNotFound(): void
    {
        $user = new UserStruct();
        $user->uid = 10;

        $this->stmtStub->method('fetch')->willReturn(false);

        $result = $this->dao->findServiceByUserAndId($user, 999);

        $this->assertNull($result);
    }

    #[Test]
    public function findServicesByUserReturnsArray(): void
    {
        $user = new UserStruct();
        $user->uid = 10;

        $struct1 = new ConnectedServiceStruct();
        $struct1->id = 1;
        $struct2 = new ConnectedServiceStruct();
        $struct2->id = 2;

        $this->stmtStub->method('fetchAll')->willReturn([$struct1, $struct2]);

        $results = $this->dao->findServicesByUser($user);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    #[Test]
    public function findServicesByUserReturnsEmptyArray(): void
    {
        $user = new UserStruct();
        $user->uid = 10;

        $this->stmtStub->method('fetchAll')->willReturn([]);

        $results = $this->dao->findServicesByUser($user);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    #[Test]
    public function findDefaultServiceByUserAndNameReturnsStructWhenFound(): void
    {
        $user = new UserStruct();
        $user->uid = 10;

        $struct = new ConnectedServiceStruct();
        $struct->id = 3;
        $struct->is_default = 1;

        $this->stmtStub->method('fetch')->willReturn($struct);

        $result = $this->dao->findDefaultServiceByUserAndName($user, 'gdrive');

        $this->assertInstanceOf(ConnectedServiceStruct::class, $result);
    }

    #[Test]
    public function findDefaultServiceByUserAndNameReturnsNullWhenNotFound(): void
    {
        $user = new UserStruct();
        $user->uid = 10;

        $this->stmtStub->method('fetch')->willReturn(false);

        $result = $this->dao->findDefaultServiceByUserAndName($user, 'gdrive');

        $this->assertNull($result);
    }

    #[Test]
    public function findUserServicesByNameAndEmailReturnsStructWhenFound(): void
    {
        $user = new UserStruct();
        $user->uid = 10;

        $struct = new ConnectedServiceStruct();
        $struct->id = 7;
        $struct->email = 'test@example.com';

        $this->stmtStub->method('fetch')->willReturn($struct);

        $result = $this->dao->findUserServicesByNameAndEmail($user, 'gdrive', 'test@example.com');

        $this->assertInstanceOf(ConnectedServiceStruct::class, $result);
        $this->assertSame('test@example.com', $result->email);
    }

    #[Test]
    public function findUserServicesByNameAndEmailReturnsNullWhenNotFound(): void
    {
        $user = new UserStruct();
        $user->uid = 10;

        $this->stmtStub->method('fetch')->willReturn(false);

        $result = $this->dao->findUserServicesByNameAndEmail($user, 'gdrive', 'noone@example.com');

        $this->assertNull($result);
    }
}
