<?php

namespace Matecat\Core\Model\ConnectedServices\GDrive;

use Exception;
use Google\Service\Oauth2\Userinfo;
use Google_Client;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\ConnectedServices\GDrive\GDriveUserAuthorizationModel;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

class GDriveUserAuthorizationModelTest extends AbstractTest
{
    private function createUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';
        return $user;
    }

    private function createDaoMock(): ConnectedServiceDao
    {
        return $this->createStub(ConnectedServiceDao::class);
    }

    private function createGoogleClientStub(): Google_Client
    {
        return $this->createStub(Google_Client::class);
    }

    #[Test]
    public function constructorStoresUser(): void
    {
        $user = $this->createUser();
        $model = new GDriveUserAuthorizationModel($user);

        $ref = new ReflectionProperty($model, 'user');
        $this->assertSame($user, $ref->getValue($model));
    }

    #[Test]
    public function constructorAcceptsOptionalDao(): void
    {
        $dao = $this->createDaoMock();
        $model = new GDriveUserAuthorizationModel($this->createUser(), $dao);

        $ref = new ReflectionProperty($model, 'dao');
        $this->assertSame($dao, $ref->getValue($model));
    }

    #[Test]
    public function constructorAcceptsOptionalGoogleClient(): void
    {
        $client = $this->createGoogleClientStub();
        $model = new GDriveUserAuthorizationModel($this->createUser(), null, $client);

        $ref = new ReflectionProperty($model, 'googleClient');
        $this->assertSame($client, $ref->getValue($model));
    }

    #[Test]
    public function constructorAcceptsBothOptionalDeps(): void
    {
        $dao = $this->createDaoMock();
        $client = $this->createGoogleClientStub();
        $model = new GDriveUserAuthorizationModel($this->createUser(), $dao, $client);

        $refDao = new ReflectionProperty($model, 'dao');
        $refClient = new ReflectionProperty($model, 'googleClient');
        $this->assertSame($dao, $refDao->getValue($model));
        $this->assertSame($client, $refClient->getValue($model));
    }

    #[Test]
    public function updateOrCreateRecordByCode_updatesExistingService(): void
    {
        $user = $this->createUser();
        $existingService = new ConnectedServiceStruct([
            'id' => 99,
            'uid' => 42,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'service' => ConnectedServiceDao::GDRIVE_SERVICE,
            'is_default' => 0,
        ]);

        $dao = $this->getMockBuilder(ConnectedServiceDao::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dao->expects($this->once())
            ->method('findUserServicesByNameAndEmail')
            ->with($user, ConnectedServiceDao::GDRIVE_SERVICE, 'test@example.com')
            ->willReturn($existingService);
        $dao->expects($this->once())
            ->method('updateOauthToken')
            ->with('fake-token', $existingService);
        $dao->expects($this->once())
            ->method('updateStruct')
            ->with($this->callback(function (ConnectedServiceStruct $s) {
                return $s->id === 99 && $s->expired_at === null && $s->disabled_at === null;
            }));
        $dao->expects($this->once())
            ->method('setDefaultService')
            ->with($existingService);

        $model = new TestableGDriveUserAuthorizationModel($user, $dao, 'fake-token', 'test@example.com', 'user-1', 'Test User');
        $model->updateOrCreateRecordByCode('auth-code');
    }

    #[Test]
    public function updateOrCreateRecordByCode_insertsNewService(): void
    {
        $user = $this->createUser();
        $insertedService = new ConnectedServiceStruct([
            'id' => 100,
            'uid' => 42,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'service' => ConnectedServiceDao::GDRIVE_SERVICE,
            'is_default' => 1,
        ]);

        $dao = $this->getMockBuilder(ConnectedServiceDao::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dao->expects($this->once())->method('findUserServicesByNameAndEmail')->willReturn(null);
        $dao->expects($this->once())->method('insertStruct')->willReturn(100);
        $dao->expects($this->once())->method('fetchById')->with(100, ConnectedServiceStruct::class)->willReturn($insertedService);
        $dao->expects($this->once())->method('setDefaultService');

        $model = new TestableGDriveUserAuthorizationModel($user, $dao, 'fake-token', 'test@example.com', 'user-1', 'Test User');
        $model->updateOrCreateRecordByCode('auth-code');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function updateOrCreateRecordByCode_throwsWhenInsertReturnsFalse(): void
    {
        $user = $this->createUser();
        $dao = $this->getMockBuilder(ConnectedServiceDao::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dao->expects($this->once())->method('findUserServicesByNameAndEmail')->willReturn(null);
        $dao->expects($this->once())->method('insertStruct')->willReturn(false);

        $model = new TestableGDriveUserAuthorizationModel($user, $dao, 'fake-token', 'test@example.com', 'user-1', 'Test User');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to insert connected service');
        $model->updateOrCreateRecordByCode('auth-code');
    }

    #[Test]
    public function updateOrCreateRecordByCode_throwsWhenFetchByIdReturnsNull(): void
    {
        $user = $this->createUser();
        $dao = $this->getMockBuilder(ConnectedServiceDao::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dao->expects($this->once())->method('findUserServicesByNameAndEmail')->willReturn(null);
        $dao->expects($this->once())->method('insertStruct')->willReturn(200);
        $dao->expects($this->once())->method('fetchById')->with(200, ConnectedServiceStruct::class)->willReturn(null);

        $model = new TestableGDriveUserAuthorizationModel($user, $dao, 'fake-token', 'test@example.com', 'user-1', 'Test User');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to retrieve inserted connected service');
        $model->updateOrCreateRecordByCode('auth-code');
    }

    #[Test]
    public function collectProperties_setsTokensAndUserInfoFromGoogleApi(): void
    {
        $user = $this->createUser();

        $userinfoModel = new Userinfo();
        $userinfoModel->email = 'test@example.com';
        $userinfoModel->id = 'remote-123';
        $userinfoModel->name = 'Test User';

        $loggerStub = $this->createStub(LoggerInterface::class);

        $googleClientMock = $this->getMockBuilder(Google_Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetchAccessTokenWithAuthCode', 'getAccessToken', 'execute', 'getLogger', 'getUniverseDomain', 'shouldDefer'])
            ->getMock();
        $googleClientMock->expects($this->once())
            ->method('fetchAccessTokenWithAuthCode')
            ->with('auth-code');
        $googleClientMock->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('access-token-string');
        $googleClientMock->expects($this->once())
            ->method('execute')
            ->willReturn($userinfoModel);
        $googleClientMock->method('getLogger')
            ->willReturn($loggerStub);
        $googleClientMock->method('getUniverseDomain')
            ->willReturn('googleapis.com');
        $googleClientMock->method('shouldDefer')
            ->willReturn(false);

        $model = new TestableGDriveUserAuthorizationModel(
            $user, null, '', '', '', ''
        );

        $ref = new ReflectionProperty(GDriveUserAuthorizationModel::class, 'googleClient');
        $ref->setValue($model, $googleClientMock);

        $model->exposedCollectProperties('auth-code');

        $refToken = new ReflectionProperty(GDriveUserAuthorizationModel::class, 'token');
        $refEmail = new ReflectionProperty(GDriveUserAuthorizationModel::class, 'user_email');
        $refRemoteId = new ReflectionProperty(GDriveUserAuthorizationModel::class, 'user_remote_id');
        $refName = new ReflectionProperty(GDriveUserAuthorizationModel::class, 'user_name');

        $this->assertSame('access-token-string', $refToken->getValue($model));
        $this->assertSame('test@example.com', $refEmail->getValue($model));
        $this->assertSame('remote-123', $refRemoteId->getValue($model));
        $this->assertSame('Test User', $refName->getValue($model));
    }

}

/**
 * Testable subclass that bypasses the Google OAuth API calls
 * in __collectProperties by overriding it with pre-set values.
 */
class TestableGDriveUserAuthorizationModel extends GDriveUserAuthorizationModel
{
    public function __construct(
        UserStruct $user,
        ?ConnectedServiceDao $dao,
        string $token,
        string $email,
        string $remoteId,
        string $name,
    ) {
        parent::__construct($user, $dao, null);

        $refToken = new ReflectionProperty($this, 'token');
        $refToken->setValue($this, $token);

        $refEmail = new ReflectionProperty($this, 'user_email');
        $refEmail->setValue($this, $email);

        $refRemoteId = new ReflectionProperty($this, 'user_remote_id');
        $refRemoteId->setValue($this, $remoteId);

        $refName = new ReflectionProperty($this, 'user_name');
        $refName->setValue($this, $name);
    }

    /** Override to skip the Google API calls during tests of updateOrCreateRecordByCode. */
    protected function __collectProperties(string $code): void
    {
        // No-op: properties already set in constructor
    }

    /** Expose the real __collectProperties for isolated testing. */
    public function exposedCollectProperties(string $code): void
    {
        parent::__collectProperties($code);
    }

}
