<?php

namespace Matecat\Core\Model\ConnectedServices\GDrive;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\GDrive\GDriveTokenHandler;
use PHPUnit\Framework\Attributes\Test;

class GDriveTokenHandlerTest extends AbstractTest
{
    #[Test]
    public function accessTokenToJsonStringHandlesEncodeFailure(): void
    {
        $obj = new \stdClass();
        $obj->self = $obj;

        $result = GDriveTokenHandler::accessTokenToJsonString(['bad' => $obj]);
        $this->assertSame('', $result);
    }

    #[Test]
    public function accessTokenToJsonStringEncodesArrayToken(): void
    {
        $token = ['access_token' => 'abc123', 'refresh_token' => 'xyz789'];
        $result = GDriveTokenHandler::accessTokenToJsonString($token);

        $this->assertIsString($result);
        $decoded = json_decode($result, true);
        $this->assertSame('abc123', $decoded['access_token']);
    }

    #[Test]
    public function accessTokenToJsonStringPreservesStringToken(): void
    {
        $result = GDriveTokenHandler::accessTokenToJsonString('{"access_token":"abc"}');
        $this->assertIsString($result);
        $decoded = json_decode($result, true);
        $this->assertSame('abc', $decoded['access_token']);
    }

    #[Test]
    public function getNewTokenSkipsJsonDecodeForArrayToken(): void
    {
        $client = $this->createMock(\Google_Client::class);
        $token = ['access_token' => 'abc', 'refresh_token' => 'ref456'];

        $client->method('setAccessToken')
            ->with($this->equalTo($token));
        $client->method('isAccessTokenExpired')
            ->willReturn(false);

        $result = GDriveTokenHandler::getNewToken($client, $token);
        $this->assertFalse($result);
    }

    #[Test]
    public function getNewTokenJsonDecodesStringToken(): void
    {
        $client = $this->createMock(\Google_Client::class);
        $tokenStr = '{"access_token":"abc","refresh_token":"ref456"}';

        $client->method('setAccessToken')
            ->with($this->equalTo($tokenStr));
        $client->method('isAccessTokenExpired')
            ->willReturn(false);

        $result = GDriveTokenHandler::getNewToken($client, $tokenStr);
        $this->assertFalse($result);
    }

    #[Test]
    public function getNewTokenReturnsNewTokenWhenExpired(): void
    {
        $client = $this->createMock(\Google_Client::class);
        $token = ['access_token' => 'old', 'refresh_token' => 'ref456'];

        $client->method('setAccessToken');
        $client->method('isAccessTokenExpired')
            ->willReturn(true);
        $client->method('refreshToken')
            ->with('ref456')
            ->willReturn(['access_token' => 'new_token', 'expires_in' => 3600]);
        $client->method('getAccessToken')
            ->willReturn('{"access_token":"new_token"}');

        $result = GDriveTokenHandler::getNewToken($client, $token);
        $this->assertSame('{"access_token":"new_token"}', $result);
    }

    #[Test]
    public function getNewTokenEncodesArrayAccessToken(): void
    {
        $client = $this->createStub(\Google_Client::class);
        $token = ['access_token' => 'old', 'refresh_token' => 'ref456'];
        $arrayToken = ['access_token' => 'new_token', 'expires_in' => 3600];

        $client->method('isAccessTokenExpired')
            ->willReturn(true);
        $client->method('refreshToken')
            ->willReturn(['access_token' => 'ok']);
        $client->method('getAccessToken')
            ->willReturn($arrayToken);

        $result = GDriveTokenHandler::getNewToken($client, $token);
        $this->assertIsString($result);
        $decoded = json_decode($result, true);
        $this->assertSame('new_token', $decoded['access_token']);
    }

    #[Test]
    public function getNewTokenThrowsOnTokenError(): void
    {
        $client = $this->createStub(\Google_Client::class);
        $token = ['access_token' => 'old', 'refresh_token' => 'ref456'];

        $client->method('isAccessTokenExpired')
            ->willReturn(true);
        $client->method('refreshToken')
            ->willReturn(['error' => 'invalid_grant', 'error_description' => 'Token expired']);

        $this->expectException(\Exception::class);
        GDriveTokenHandler::getNewToken($client, $token);
    }

    #[Test]
    public function getNewTokenHandlesArrayTokenWithExpiredRefresh(): void
    {
        $client = $this->createStub(\Google_Client::class);
        $token = ['access_token' => 'abc', 'refresh_token' => 'ref'];

        $client->method('isAccessTokenExpired')
            ->willReturn(true);
        $client->method('refreshToken')
            ->willReturn(['access_token' => 'refreshed']);
        $client->method('getAccessToken')
            ->willReturn('refreshed_token_string');

        $result = GDriveTokenHandler::getNewToken($client, $token);
        $this->assertSame('refreshed_token_string', $result);
    }
}
