<?php

namespace Matecat\Core\Model\ConnectedServices\GDrive;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\ConnectedServices\GDrive\GDriveTokenVerifyModel;
use PHPUnit\Framework\Attributes\Test;

class GDriveTokenVerifyModelTest extends AbstractTest
{
    private function createDaoMock(): ConnectedServiceDao
    {
        return $this->getMockBuilder(ConnectedServiceDao::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    #[Test]
    public function constructorPreservesServiceReference(): void
    {
        $service = $this->createStub(ConnectedServiceStruct::class);
        $model = new GDriveTokenVerifyModel($service);

        $this->assertSame($service, $model->getService());
    }

    #[Test]
    public function constructorAcceptsOptionalDao(): void
    {
        $service = $this->createStub(ConnectedServiceStruct::class);
        $dao = $this->getStubBuilder(ConnectedServiceDao::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getStub();
        $model = new GDriveTokenVerifyModel($service, $dao);

        $this->assertSame($service, $model->getService());
    }

    #[Test]
    public function validOrRefreshedReturnsTrueWhenTokenNotExpired(): void
    {
        $service = $this->createConfiguredStub(ConnectedServiceStruct::class, [
            'getDecryptedOauthAccessToken' => '{"access_token":"valid","refresh_token":"ref"}',
        ]);

        $model = new GDriveTokenVerifyModel($service);

        $gClient = $this->createStub(\Google_Client::class);
        $gClient->method('isAccessTokenExpired')
            ->willReturn(false);

        $this->assertTrue($model->validOrRefreshed($gClient));
    }

    #[Test]
    public function validOrRefreshedReturnsFalseWhenTokenIsNull(): void
    {
        $service = $this->createConfiguredStub(ConnectedServiceStruct::class, [
            'getDecryptedOauthAccessToken' => null,
        ]);

        $dao = $this->createDaoMock();
        $dao->expects($this->once())
            ->method('setServiceExpired')
            ->with($this->anything(), $service);

        $model = new GDriveTokenVerifyModel($service, $dao);
        $gClient = $this->createStub(\Google_Client::class);

        $this->assertFalse($model->validOrRefreshed($gClient));
    }

    #[Test]
    public function validOrRefreshedReturnsFalseWhenGetNewTokenThrows(): void
    {
        $service = $this->createConfiguredStub(ConnectedServiceStruct::class, [
            'getDecryptedOauthAccessToken' => '{"access_token":"bad","refresh_token":"ref"}',
        ]);

        $dao = $this->createDaoMock();
        $dao->expects($this->once())
            ->method('setServiceExpired');

        $model = new GDriveTokenVerifyModel($service, $dao);

        $gClient = $this->createStub(\Google_Client::class);
        $gClient->method('isAccessTokenExpired')
            ->willReturn(true);
        $gClient->method('refreshToken')
            ->willThrowException(new \Exception('Token refresh failed'));

        $this->assertFalse($model->validOrRefreshed($gClient));
    }

    #[Test]
    public function validOrRefreshedUpdatesTokenWhenRefreshed(): void
    {
        $service = $this->createConfiguredStub(ConnectedServiceStruct::class, [
            'getDecryptedOauthAccessToken' => '{"access_token":"old","refresh_token":"ref"}',
        ]);

        $updatedService = $this->createStub(ConnectedServiceStruct::class);

        $dao = $this->createDaoMock();
        $dao->expects($this->once())
            ->method('updateOauthToken')
            ->with($this->anything(), $service)
            ->willReturn($updatedService);

        $model = new GDriveTokenVerifyModel($service, $dao);

        $gClient = $this->createStub(\Google_Client::class);
        $gClient->method('isAccessTokenExpired')
            ->willReturn(true);
        $gClient->method('refreshToken')
            ->willReturn(['access_token' => 'ok']);
        $gClient->method('getAccessToken')
            ->willReturn('{"access_token":"new_token"}');

        $this->assertTrue($model->validOrRefreshed($gClient));
        $this->assertSame($updatedService, $model->getService());
    }
}
